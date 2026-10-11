package card

import "testing"

/*
บน macOS บริการ PC/SC หยุดทำงานเมื่อไม่มีเครื่องอ่านเสียบอยู่ และเริ่มใหม่
เมื่อมีคนเสียบ context ที่สร้างไว้ตอนยังไม่เสียบจึงตายไปพร้อมบริการเดิม แล้ว
ListReaders คืนรายการว่างตลอดกาลแม้เสียบเครื่องอ่านแล้ว

เจอจริงที่เคาน์เตอร์: เปิดโปรแกรมก่อนเสียบสาย พอเสียบแล้วหน้าจอยังบอกว่า
ไม่พบเครื่องอ่าน และไม่หายแม้เปิดโปรแกรมใหม่
*/
func TestTheReaderRecoversFromADeadContext(t *testing.T) {
	r, err := NewReader()
	if err != nil {
		t.Skipf("ไม่มีบริการ PC/SC ในเครื่องนี้: %v", err)
	}
	defer r.Close()

	before, err := r.AllReaders()
	if err != nil {
		t.Fatalf("อ่านรายชื่อครั้งแรกไม่ได้: %v", err)
	}

	// จำลอง context ที่ตายไปแล้ว แบบเดียวกับตอนบริการ PC/SC รีสตาร์ต
	r.invalidate()

	after, err := r.AllReaders()
	if err != nil {
		t.Fatalf("ควรสร้าง context ใหม่แล้วอ่านได้: %v", err)
	}

	if len(before) != len(after) {
		t.Fatalf("ควรได้รายการเดิม ก่อน %v หลัง %v", before, after)
	}
}

func TestNoReaderPluggedInIsNotAnError(t *testing.T) {
	r, err := NewReader()
	if err != nil {
		t.Skipf("ไม่มีบริการ PC/SC ในเครื่องนี้: %v", err)
	}
	defer r.Close()

	// ยังไม่เสียบสายไม่ใช่ความผิดพลาด แค่ยังไม่มีอะไรให้ใช้
	// ถ้าถือเป็น error หน้าจอจะขึ้นข้อความเทคนิคแทนที่จะบอกว่าให้เสียบสาย
	if _, err := r.AllReaders(); err != nil {
		t.Fatalf("ไม่ควร error: %v", err)
	}
}

func TestTheReaderSurvivesBeingClosedTwice(t *testing.T) {
	r, err := NewReader()
	if err != nil {
		t.Skipf("ไม่มีบริการ PC/SC ในเครื่องนี้: %v", err)
	}

	r.Close()
	r.Close()
}
