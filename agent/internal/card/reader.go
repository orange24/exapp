// ส่วนที่คุยกับเครื่องอ่านจริงผ่าน PC/SC
//
// แยกไฟล์จาก card.go เพราะไฟล์นี้ต้องมีฮาร์ดแวร์ถึงจะทดสอบได้
// ส่วนตรรกะการแปลงข้อมูลใน card.go ทดสอบได้ครบโดยไม่ต้องมีบัตร
package card

import (
	"fmt"
	"time"

	"github.com/ebfe/scard"
)

// AID ของแอปพลิเคชันบัตรประชาชนไทยบนชิป
var thaiIDApplet = []byte{0xA0, 0x00, 0x00, 0x00, 0x54, 0x48, 0x00, 0x01}

// คำสั่งอ่านแต่ละช่อง — ตำแหน่งและความยาวกำหนดโดยกรมการปกครอง
var (
	cmdCitizenID = []byte{0x80, 0xB0, 0x00, 0x04, 0x02, 0x00, 0x0D}
	cmdNameTH    = []byte{0x80, 0xB0, 0x00, 0x11, 0x02, 0x00, 0x64}
	cmdNameEN    = []byte{0x80, 0xB0, 0x00, 0x75, 0x02, 0x00, 0x64}
	cmdBirth     = []byte{0x80, 0xB0, 0x00, 0xD9, 0x02, 0x00, 0x08}
	cmdAddress   = []byte{0x80, 0xB0, 0x15, 0x79, 0x02, 0x00, 0x64}
	cmdIssue     = []byte{0x80, 0xB0, 0x01, 0x67, 0x02, 0x00, 0x08}
	cmdExpire    = []byte{0x80, 0xB0, 0x01, 0x6F, 0x02, 0x00, 0x08}
)

type Reader struct {
	ctx *scard.Context
}

func NewReader() (*Reader, error) {
	ctx, err := scard.EstablishContext()
	if err != nil {
		return nil, fmt.Errorf("ต่อกับบริการ PC/SC ไม่ได้: %w", err)
	}

	return &Reader{ctx: ctx}, nil
}

func (r *Reader) Close() {
	if r.ctx != nil {
		_ = r.ctx.Release()
	}
}

// Readers คืนรายชื่อเครื่องอ่านที่เสียบอยู่
func (r *Reader) Readers() ([]string, error) {
	return r.ctx.ListReaders()
}

// WaitForCard รอจนมีบัตรถูกเสียบเข้าไป แล้วคืนชื่อเครื่องอ่านที่มีบัตร
//
// ใช้ GetStatusChange ของ PC/SC ซึ่งเป็นการรอแบบที่ระบบปลุกให้
// ไม่ใช่การวนถามทุกเสี้ยววินาที — ไม่งั้น CPU เครื่องสาขาจะร้อนทั้งวัน
func (r *Reader) WaitForCard(timeout time.Duration) (string, error) {
	names, err := r.Readers()
	if err != nil {
		return "", err
	}

	if len(names) == 0 {
		return "", fmt.Errorf("ไม่พบเครื่องอ่านบัตร")
	}

	states := make([]scard.ReaderState, len(names))
	for i, n := range names {
		states[i].Reader = n
		states[i].CurrentState = scard.StateUnaware
	}

	if err := r.ctx.GetStatusChange(states, timeout); err != nil {
		return "", err
	}

	for _, s := range states {
		if s.EventState&scard.StatePresent != 0 {
			return s.Reader, nil
		}
	}

	return "", nil
}

func transmit(c *scard.Card, cmd []byte) ([]byte, error) {
	resp, err := c.Transmit(cmd)
	if err != nil {
		return nil, err
	}

	if len(resp) < 2 {
		return nil, fmt.Errorf("คำตอบจากบัตรสั้นผิดปกติ")
	}

	// บัตรตอบ 61 xx แปลว่า "ข้อมูลพร้อมแล้ว xx ไบต์ มาเอาได้"
	if resp[len(resp)-2] == 0x61 {
		getResponse := []byte{0x00, 0xC0, 0x00, 0x00, resp[len(resp)-1]}

		data, err := c.Transmit(getResponse)
		if err != nil {
			return nil, err
		}

		if len(data) < 2 {
			return nil, fmt.Errorf("คำตอบรอบสองสั้นผิดปกติ")
		}

		return data[:len(data)-2], nil
	}

	return resp[:len(resp)-2], nil
}

// Read อ่านบัตรที่อยู่ในเครื่องอ่านชื่อ reader
func (r *Reader) Read(reader string) (Data, error) {
	c, err := r.ctx.Connect(reader, scard.ShareShared, scard.ProtocolT0|scard.ProtocolT1)
	if err != nil {
		return Data{}, fmt.Errorf("เชื่อมกับบัตรไม่ได้: %w", err)
	}
	defer c.Disconnect(scard.LeaveCard)

	if _, err := c.Transmit(append([]byte{0x00, 0xA4, 0x04, 0x00, byte(len(thaiIDApplet))}, thaiIDApplet...)); err != nil {
		return Data{}, fmt.Errorf("บัตรนี้ไม่ใช่บัตรประชาชนไทย หรืออ่านไม่ได้: %w", err)
	}

	field := func(cmd []byte) string {
		raw, err := transmit(c, cmd)
		if err != nil {
			return ""
		}

		return DecodeTIS620(raw)
	}

	id := field(cmdCitizenID)

	if !ValidCitizenID(id) {
		// ปล่อยผ่านไม่ได้ — เลขที่อ่านพลาดจะไปชนรายชื่อบุคคลต้องห้ามของคนอื่น
		return Data{}, fmt.Errorf("เลขบัตรที่อ่านได้ไม่ผ่านหลักตรวจสอบ")
	}

	d := Data{CitizenID: id}

	_, firstTH, lastTH := SplitNameField(field(cmdNameTH))
	d.NameTH = trimJoin(firstTH, lastTH)

	_, firstEN, lastEN := SplitNameField(field(cmdNameEN))
	d.FirstNameEN, d.LastNameEN = firstEN, lastEN
	d.NameEN = trimJoin(firstEN, lastEN)

	d.Address = field(cmdAddress)

	if v, err := ParseBuddhistDate(field(cmdBirth)); err == nil {
		d.DateOfBirth = v
	}

	if v, err := ParseBuddhistDate(field(cmdIssue)); err == nil {
		d.IssueDate = v
	}

	if v, err := ParseBuddhistDate(field(cmdExpire)); err == nil {
		d.ExpireDate = v
	}

	return d, nil
}

func trimJoin(a, b string) string {
	switch {
	case a == "":
		return b
	case b == "":
		return a
	default:
		return a + " " + b
	}
}
