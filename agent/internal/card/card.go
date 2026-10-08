// Package card แปลงข้อมูลดิบจากชิปบัตรประชาชนไทยให้เป็นโครงสร้างที่ใช้งานได้
//
// แยกจากส่วนที่คุยกับเครื่องอ่านโดยตั้งใจ ส่วนนี้ไม่แตะฮาร์ดแวร์เลย
// จึงทดสอบได้ครบโดยไม่ต้องมีเครื่องอ่านและไม่ต้องมีบัตรจริง
package card

import (
	"fmt"
	"strings"
	"unicode"

	"golang.org/x/text/encoding/charmap"
)

// Data คือสิ่งที่ส่งขึ้น exapp
type Data struct {
	CitizenID   string `json:"citizen_id"`
	NameTH      string `json:"name_th,omitempty"`
	NameEN      string `json:"name_en,omitempty"`
	FirstNameEN string `json:"first_name_en,omitempty"`
	LastNameEN  string `json:"last_name_en,omitempty"`
	DateOfBirth string `json:"date_of_birth,omitempty"`
	Address     string `json:"address,omitempty"`
	IssueDate   string `json:"issue_date,omitempty"`
	ExpireDate  string `json:"expire_date,omitempty"`
	PhotoBase64 string `json:"photo_jpeg_base64,omitempty"`
}

// InsertedReader คืนชื่อช่องที่ "เพิ่งมีบัตรเสียบเข้าไป" เทียบจากสถานะสองรอบ
//
// ต้องดูที่จังหวะเปลี่ยน ไม่ใช่ดูว่าตอนนี้มีบัตรอยู่ไหม — บัตรที่เสียบค้างไว้
// มีสถานะ "มีบัตร" ตลอดเวลา ถ้าอ่านจากสถานะปัจจุบันจะอ่านซ้ำไม่หยุด
// แล้วแต่ละรอบจะไปสร้างรายการตรวจรายชื่อใหม่ของลูกค้าคนเดิมทับกันเป็นสิบ
func InsertedReader(names []string, before, after []bool) string {
	for i, name := range names {
		if i >= len(before) || i >= len(after) {
			break
		}

		if after[i] && !before[i] {
			return name
		}
	}

	return ""
}

// SelectReaders เลือกช่องที่อาจมีบัตรประชาชนอยู่จริง
//
// เครื่องอ่านรุ่นสองหน้าอย่าง ACR1581 โผล่มาเป็นสามช่องแยกกัน:
//
//	ACS ACR1581 1S Dual Reader SAM   โมดูลความปลอดภัยในตัวเครื่อง
//	ACS ACR1581 1S Dual Reader ICC   ช่องเสียบสัมผัส  <- บัตรประชาชนไทยอยู่ตรงนี้
//	ACS ACR1581 1S Dual Reader PICC  ช่องไร้สัมผัส
//
// ช่อง SAM รายงานว่า "มีการ์ดอยู่" ตลอดเวลาเพราะโมดูลติดมากับเครื่อง
// ถ้าไม่คัดออก ตัวเฝ้าจะคว้าช่องนั้นทุกครั้งแล้วอ่านไม่ออก ไฟขึ้นแดงค้าง
// ส่วนบัตรจริงในช่องสัมผัสจะไม่มีวันถูกอ่านเลย
//
// บัตรประชาชนไทยเป็นชิปสัมผัส จึงเลือกช่องสัมผัสก่อนถ้ามี
// เครื่องที่มีช่องเดียวจะไม่เข้าเงื่อนไขนี้ และใช้ช่องที่เหลือตามปกติ
func SelectReaders(all []string) []string {
	var usable, contact []string

	for _, name := range all {
		upper := strings.ToUpper(name)

		if strings.Contains(upper, "SAM") {
			continue
		}

		usable = append(usable, name)

		// ต้องตัด PICC ออกก่อน ไม่งั้นมันจะเข้าเงื่อนไข ICC ด้วยเพราะเป็นคำซ้อนกัน
		if !strings.Contains(upper, "PICC") && strings.Contains(upper, "ICC") {
			contact = append(contact, name)
		}
	}

	if len(contact) > 0 {
		return contact
	}

	return usable
}

// DecodeTIS620 แปลงข้อความจากชิปเป็น UTF-8
//
// ชิปเก็บภาษาไทยเป็น TIS-620 ไม่ใช่ UTF-8 ถ้าอ่านดิบ ๆ จะได้ตัวอักษรขยะ
// ซึ่งเป็นกับดักที่เจอบ่อยที่สุดของงานอ่านบัตรไทย
func DecodeTIS620(raw []byte) string {
	decoded, err := charmap.Windows874.NewDecoder().Bytes(raw)
	if err != nil {
		return strings.TrimSpace(string(raw))
	}

	return strings.TrimSpace(string(decoded))
}

// SplitNameField แยกข้อความชื่อของบัตรที่คั่นด้วย #
//
// รูปแบบในชิปคือ "คำนำหน้า#ชื่อ##สกุล" ช่องว่างระหว่าง # ที่ติดกัน
// คือช่องชื่อกลางที่คนไทยส่วนใหญ่ไม่มี
func SplitNameField(field string) (title, first, last string) {
	parts := strings.Split(field, "#")

	get := func(i int) string {
		if i < len(parts) {
			return strings.TrimSpace(parts[i])
		}

		return ""
	}

	return get(0), get(1), get(3)
}

// ParseBuddhistDate แปลง "25241218" (พ.ศ.) เป็น "1981-12-18" (ค.ศ.)
//
// ชิปเก็บวันที่เป็นพุทธศักราชล้วน ถ้าส่งขึ้นระบบตรง ๆ วันเกิดจะเพี้ยนไป 543 ปี
// แล้วการเทียบกับรายชื่อ ปปง. จะไม่มีวันตรงเลยสักราย
func ParseBuddhistDate(raw string) (string, error) {
	raw = strings.TrimSpace(raw)

	if len(raw) != 8 {
		return "", fmt.Errorf("วันที่ต้องมี 8 หลัก ได้มา %q", raw)
	}

	for _, r := range raw {
		if !unicode.IsDigit(r) {
			return "", fmt.Errorf("วันที่มีอักขระที่ไม่ใช่ตัวเลข: %q", raw)
		}
	}

	var buddhistYear int
	if _, err := fmt.Sscanf(raw[:4], "%d", &buddhistYear); err != nil {
		return "", err
	}

	// บัตรที่ยังไม่ได้ระบุวันจะเก็บเป็น 0000 หรือเดือน/วันเป็น 00
	if buddhistYear < 2400 {
		return "", fmt.Errorf("ปีพุทธศักราชไม่สมเหตุผล: %q", raw)
	}

	month, day := raw[4:6], raw[6:8]
	if month == "00" || day == "00" {
		return "", fmt.Errorf("วันหรือเดือนว่าง: %q", raw)
	}

	return fmt.Sprintf("%04d-%s-%s", buddhistYear-543, month, day), nil
}

// ValidCitizenID ตรวจเลข 13 หลักด้วยหลักตรวจสอบของกรมการปกครอง
//
// ป้องกันไม่ให้ข้อมูลที่อ่านมาผิดพลาดกลายเป็นเลขบัตรของคนอื่น
// ซึ่งจะไปชนกับรายชื่อบุคคลต้องห้ามของคนที่ไม่เกี่ยวข้อง
func ValidCitizenID(id string) bool {
	if len(id) != 13 {
		return false
	}

	sum := 0
	for i := 0; i < 12; i++ {
		if !unicode.IsDigit(rune(id[i])) {
			return false
		}

		sum += int(id[i]-'0') * (13 - i)
	}

	if !unicode.IsDigit(rune(id[12])) {
		return false
	}

	return (11-(sum%11))%10 == int(id[12]-'0')
}

// ContactlessSlot คืนชื่อช่องไร้สัมผัส — พาสปอร์ตใช้ NFC ไม่ใช่ชิปสัมผัส
//
// แยกจาก SelectReaders เพราะสองช่องทำงานคนละหน้าที่และคนละเงื่อนไข:
// ช่องสัมผัสอ่านทันทีที่เสียบบัตร ส่วนช่องนี้อ่านเฉพาะตอนมีกุญแจรออยู่
func ContactlessSlot(all []string) string {
	for _, name := range all {
		if strings.Contains(strings.ToUpper(name), "PICC") {
			return name
		}
	}

	return ""
}
