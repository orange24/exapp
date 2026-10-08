// อ่านชิปพาสปอร์ตผ่านช่องไร้สัมผัส
//
// ชิปล็อกอยู่ ต่างจากบัตรประชาชนไทยที่เสียบแล้วอ่านได้เลย กุญแจสร้างจาก
// เลขพาสปอร์ต + วันเกิด + วันหมดอายุ ตาม ICAO 9303 กุญแจมาจากเซิร์ฟเวอร์
// เพราะเบราว์เซอร์ส่งตรงมาหา agent ไม่ได้
package card

import (
	"encoding/base64"
	"fmt"
	"strings"
	"time"

	"github.com/ebfe/scard"
	"github.com/gmrtd/gmrtd/cms"
	"github.com/gmrtd/gmrtd/document"
	"github.com/gmrtd/gmrtd/iso7816"
	"github.com/gmrtd/gmrtd/password"
	gmrtdreader "github.com/gmrtd/gmrtd/reader"
)

// Key คือสิ่งที่ปลดล็อกชิปได้ ทั้งสามค่ามาจากบรรทัดที่สองของ MRZ
type Key struct {
	DocumentNo  string
	DateOfBirth string // YYMMDD
	ExpiryDate  string // YYMMDD
}

// Passport คือสิ่งที่ส่งขึ้น exapp หลังอ่านชิปสำเร็จ
type Passport struct {
	DocumentNo   string `json:"document_no"`
	Surname      string `json:"surname,omitempty"`
	GivenNames   string `json:"given_names,omitempty"`
	Nationality  string `json:"nationality,omitempty"`
	IssuingState string `json:"issuing_state,omitempty"`

	// พาสปอร์ตไทยใส่เลขบัตรประชาชน 13 หลักไว้ในช่องนี้ ซึ่งเป็นตัวที่
	// ระบบตรวจรายชื่อใช้บล็อกแข็งที่ 100 คะแนน
	NationalID string `json:"national_id,omitempty"`

	DateOfBirth string `json:"date_of_birth,omitempty"`
	ExpiryDate  string `json:"expiry_date,omitempty"`
	Sex         string `json:"sex,omitempty"`

	// verified | unverifiable | failed
	Authenticity       string `json:"authenticity"`
	ChipAuthentication bool   `json:"chip_authentication"`

	PhotoBase64 string `json:"photo_base64,omitempty"`
	PhotoMime   string `json:"photo_mime,omitempty"`
}

type passportTransceiver struct{ card *scard.Card }

var _ iso7816.Transceiver = (*passportTransceiver)(nil)

func (t *passportTransceiver) Transceive(_, _, _, _ int, _ []byte, _ int, cApdu []byte) []byte {
	resp, err := t.card.Transmit(cApdu)
	if err != nil {
		return nil
	}

	return resp
}

type progressReporter struct {
	report func(string)
}

var _ gmrtdreader.ReaderStatus = (*progressReporter)(nil)

func (p *progressReporter) Status(s gmrtdreader.Status) {
	if p.report != nil {
		p.report(describeStep(s.String()))
	}
}

// describeStep แปลขั้นตอนของไลบรารีเป็นข้อความที่พนักงานเข้าใจ
func describeStep(step string) string {
	// ทุกข้อความย้ำว่าอย่ายกเล่มออก เพราะทุกขั้นยังต้องการให้เล่มอยู่บนแท่น
	const hold = " อย่าเพิ่งยกพาสปอร์ตออก"

	switch {
	case strings.Contains(step, "PACE"), strings.Contains(step, "BAC"):
		return "กำลังเปิดชิป" + hold
	case strings.Contains(step, "DG02"), strings.Contains(step, "DG07"):
		return "กำลังอ่านรูปถ่าย" + hold
	case strings.Contains(step, "DG"), strings.Contains(step, "EF."):
		return "กำลังอ่านข้อมูล" + hold
	case strings.Contains(step, "Passive"), strings.Contains(step, "Verif"):
		return "กำลังตรวจลายเซ็นของประเทศผู้ออก" + hold
	case strings.Contains(step, "Finished"):
		return "อ่านชิปเสร็จแล้ว"
	default:
		return "กำลังอ่านบัตร" + hold
	}
}

// ContactlessReader เฝ้าช่องไร้สัมผัสของเครื่องอ่าน
type ContactlessReader struct {
	ctx  *scard.Context
	name string
}

// Contactless คืนตัวเฝ้าช่องไร้สัมผัส หรือ nil ถ้าเครื่องนี้ไม่มีช่องนั้น
func (r *Reader) Contactless() *ContactlessReader {
	all, err := r.ctx.ListReaders()
	if err != nil {
		return nil
	}

	name := ContactlessSlot(all)
	if name == "" {
		return nil
	}

	return &ContactlessReader{ctx: r.ctx, name: name}
}

// WaitForTap รอจนมีอะไรมาแตะที่แท่น
//
// ใช้เฉพาะตอนมีกุญแจรออยู่เท่านั้น ถ้าเฝ้าตลอดเวลา บัตรรถไฟฟ้าหรือบัตรเครดิต
// ที่วางใกล้แท่นจะทำให้เกิดการพยายามอ่านที่ล้มเหลวไม่หยุด
func (c *ContactlessReader) WaitForTap(timeout time.Duration) (bool, error) {
	states := []scard.ReaderState{{Reader: c.name, CurrentState: scard.StateUnaware}}

	err := c.ctx.GetStatusChange(states, timeout)
	if err == scard.ErrTimeout {
		return false, nil
	}

	if err != nil {
		return false, err
	}

	return states[0].EventState&scard.StatePresent != 0, nil
}

// Read เปิดชิปด้วยกุญแจแล้วอ่านข้อมูลออกมา
func (c *ContactlessReader) Read(key Key, progress func(string)) (Passport, error) {
	pass, err := password.NewPasswordMrzi(key.DocumentNo, key.DateOfBirth, key.ExpiryDate)
	if err != nil {
		return Passport{}, fmt.Errorf("ข้อมูลจากหน้าพาสปอร์ตไม่ครบหรือไม่ถูกต้อง: %w", err)
	}

	sc, err := c.ctx.Connect(c.name, scard.ShareShared, scard.ProtocolT0|scard.ProtocolT1)
	if err != nil {
		return Passport{}, fmt.Errorf("เชื่อมกับพาสปอร์ตไม่ได้ วางเล่มนิ่ง ๆ แล้วลองใหม่: %w", err)
	}
	defer sc.Disconnect(scard.LeaveCard)

	st, err := sc.Status()
	if err != nil {
		return Passport{}, fmt.Errorf("อ่านสถานะการ์ดไม่ได้: %w", err)
	}

	pool, err := cms.GermanMasterList()
	if err != nil {
		return Passport{}, fmt.Errorf("โหลดใบรับรองประเทศผู้ออกไม่ได้: %w", err)
	}

	nfc := iso7816.NewNfcSession(&passportTransceiver{card: sc})
	rd := gmrtdreader.NewReader(&progressReporter{report: progress}, nfc, pool)

	// ไม่ถอยไป BAC เมื่อ PACE ล้มเหลว — การถอยไปใช้วิธีที่อ่อนกว่าเองเงียบ ๆ
	// เป็นรูปแบบที่ถูกโจมตีได้ เล่มเก่าที่รองรับแต่ BAC จะเจรจา BAC ตั้งแต่ต้นอยู่แล้ว
	rd.SkipImages()

	docEx, _, err := rd.ReadDocument(pass, st.Atr, nil)
	if err != nil {
		return Passport{}, fmt.Errorf("อ่านชิปไม่สำเร็จ: %w", err)
	}

	return toPassport(docEx), nil
}

func toPassport(docEx *document.DocumentEx) Passport {
	sum := docEx.Summary()

	out := Passport{
		Authenticity:       authenticityOf(sum),
		ChipAuthentication: sum.ChipAuthenticity != 0,
	}

	a := sum.IdentityAttributes
	if a == nil {
		return out
	}

	out.DocumentNo = a.DocumentNumber
	out.Sex = a.Sex
	out.NationalID = a.PersonalNumber

	if a.Name != nil {
		out.Surname = a.Name.Primary
		out.GivenNames = a.Name.Secondary
	}

	if a.Nationality != nil {
		out.Nationality = a.Nationality.Alpha3
	}

	if a.IssuingState != nil {
		out.IssuingState = a.IssuingState.Alpha3
	}

	out.DateOfBirth = isoDate(a.DateOfBirth)
	out.ExpiryDate = isoDate(a.DateOfExpiry)

	if len(a.FaceImages) > 0 {
		img := a.FaceImages[0]
		out.PhotoBase64 = base64.StdEncoding.EncodeToString(img.Data)
		out.PhotoMime = string(img.Format)
	}

	return out
}

/*
authenticityOf แยก "ยืนยันไม่ได้" ออกจาก "ยืนยันแล้วไม่ผ่าน"

ถ้ารวมสองอย่างนี้เข้าด้วยกัน ทุกประเทศที่ไม่อยู่ในคลังใบรับรองจะถูกกล่าวหาว่า
ถือพาสปอร์ตปลอม พนักงานจะเจอแถบแดงทุกวันแล้วเลิกสนใจ ซึ่งเป็นปัญหาเดียวกับ
ที่เพิ่งแก้ไปในระบบตรวจรายชื่อบุคคลต้องห้าม
*/
func authenticityOf(sum *document.DocumentSummary) string {
	if sum.DataTrusted {
		return "verified"
	}

	// ไม่มีข้อมูลตัวตนเลยแปลว่าอ่านไม่ได้จริง ๆ ไม่ใช่เรื่องใบรับรอง
	if sum.IdentityAttributes == nil {
		return "failed"
	}

	return "unverifiable"
}

// isoDate แปลง YYYYMMDD เป็น YYYY-MM-DD ชิปคืนมาแบบไม่มีขีด
func isoDate(v string) string {
	if len(v) != 8 {
		return ""
	}

	return v[0:4] + "-" + v[4:6] + "-" + v[6:8]
}
