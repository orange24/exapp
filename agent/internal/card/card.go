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
