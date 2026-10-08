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

// Readers คืนเฉพาะช่องที่อาจมีบัตรประชาชนอยู่จริง (ดู SelectReaders)
func (r *Reader) Readers() ([]string, error) {
	all, err := r.ctx.ListReaders()
	if err != nil {
		return nil, err
	}

	return SelectReaders(all), nil
}

// AllReaders คืนทุกช่องตามที่ระบบรายงาน ใช้ตอนวินิจฉัยเท่านั้น
func (r *Reader) AllReaders() ([]string, error) {
	return r.ctx.ListReaders()
}

// Watcher เฝ้าจังหวะที่บัตรถูกเสียบเข้าไป
//
// เก็บสถานะรอบก่อนไว้แล้วส่งกลับเข้า GetStatusChange เป็น CurrentState
// ซึ่งทำให้มันบล็อกรอจนกว่าจะมีอะไรเปลี่ยนจริง
//
// เดิมส่ง StateUnaware ทุกรอบ ซึ่ง PC/SC จะตอบกลับทันทีเสมอ วนลูปเปล่า
// กินซีพียูทั้งวันบนเครื่องสาขายี่สิบเครื่อง
type Watcher struct {
	ctx    *scard.Context
	names  []string
	states []scard.ReaderState
}

func (r *Reader) Watch() (*Watcher, error) {
	names, err := r.Readers()
	if err != nil {
		return nil, err
	}

	if len(names) == 0 {
		return nil, fmt.Errorf("ไม่พบเครื่องอ่านบัตร")
	}

	states := make([]scard.ReaderState, len(names))
	for i, n := range names {
		states[i].Reader = n
		states[i].CurrentState = scard.StateUnaware
	}

	return &Watcher{ctx: r.ctx, names: names, states: states}, nil
}

// WaitForInsertion บล็อกจนมีบัตรถูกเสียบ คืนชื่อช่องนั้น
// คืนค่าว่างเมื่อหมดเวลาหรือมีการเปลี่ยนแปลงที่ไม่ใช่การเสียบบัตร
func (w *Watcher) WaitForInsertion(timeout time.Duration) (string, error) {
	before := make([]bool, len(w.states))
	for i := range w.states {
		before[i] = w.states[i].CurrentState&scard.StatePresent != 0
	}

	err := w.ctx.GetStatusChange(w.states, timeout)
	if err == scard.ErrTimeout {
		return "", nil
	}

	if err != nil {
		return "", err
	}

	after := make([]bool, len(w.states))
	for i := range w.states {
		after[i] = w.states[i].EventState&scard.StatePresent != 0

		// ต้องตัดบิต StateChanged ออกก่อนส่งกลับ ไม่งั้นรอบถัดไปจะถูกมองว่า
		// "เปลี่ยนแล้ว" ตลอดเวลา แล้วกลับไปวนเปล่าเหมือนเดิม
		w.states[i].CurrentState = w.states[i].EventState &^ scard.StateChanged
	}

	return InsertedReader(w.names, before, after), nil
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
