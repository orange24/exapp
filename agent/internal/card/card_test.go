package card

import "testing"

func TestParseBuddhistDateConvertsToGregorian(t *testing.T) {
	got, err := ParseBuddhistDate("25241218")
	if err != nil {
		t.Fatalf("ไม่ควร error: %v", err)
	}

	// ชิปเก็บเป็นพุทธศักราช ถ้าไม่แปลง วันเกิดจะเพี้ยน 543 ปี
	// แล้วการเทียบกับรายชื่อ ปปง. จะไม่มีวันตรงเลยสักราย
	if got != "1981-12-18" {
		t.Fatalf("อยากได้ 1981-12-18 ได้ %q", got)
	}
}

func TestParseBuddhistDateRejectsEmptyDayOrMonth(t *testing.T) {
	// บัตรบางใบไม่ระบุวัน ส่ง 00 มา ถ้าปล่อยผ่านจะได้วันที่ที่ไม่มีอยู่จริง
	for _, raw := range []string{"25240000", "25241200", "25240018", "", "2524"} {
		if _, err := ParseBuddhistDate(raw); err == nil {
			t.Fatalf("ควร error แต่ผ่าน: %q", raw)
		}
	}
}

func TestSplitNameField(t *testing.T) {
	title, first, last := SplitNameField("นาย#สมชาย##ใจดี")

	if title != "นาย" || first != "สมชาย" || last != "ใจดี" {
		t.Fatalf("แยกชื่อผิด: %q %q %q", title, first, last)
	}
}

func TestSplitNameFieldSurvivesShortInput(t *testing.T) {
	// บัตรที่อ่านมาไม่ครบต้องไม่ทำให้โปรแกรมพัง
	if _, _, last := SplitNameField("นาย"); last != "" {
		t.Fatalf("ควรได้สกุลว่าง ได้ %q", last)
	}
}

func TestValidCitizenIDAcceptsAWellFormedNumber(t *testing.T) {
	if !ValidCitizenID("5960500028101") {
		t.Fatal("เลขที่ถูกหลักตรวจสอบกลับถูกปฏิเสธ")
	}
}

func TestValidCitizenIDRejectsBadInput(t *testing.T) {
	// อ่านพลาดหนึ่งหลักแล้วส่งขึ้นไป = ไปชนรายชื่อบุคคลต้องห้ามของคนอื่น
	for _, id := range []string{"5960500028102", "123", "", "596050002810x", "1234567890123456"} {
		if ValidCitizenID(id) {
			t.Fatalf("ควรถูกปฏิเสธ: %q", id)
		}
	}
}

func TestDecodeTIS620(t *testing.T) {
	// ชิปเก็บภาษาไทยเป็น TIS-620 อ่านดิบ ๆ จะได้ตัวอักษรขยะ
	raw := []byte{0xB9, 0xD2, 0xC2} // "นาย"

	if got := DecodeTIS620(raw); got != "นาย" {
		t.Fatalf("อยากได้ \"นาย\" ได้ %q", got)
	}
}

func TestSelectReadersSkipsTheSamSlot(t *testing.T) {
	// ช่อง SAM รายงานว่ามีการ์ดอยู่ตลอด ถ้าไม่คัดออก ตัวเฝ้าจะคว้าช่องนั้น
	// ทุกครั้งแล้วอ่านไม่ออก ส่วนบัตรจริงจะไม่มีวันถูกอ่าน
	got := SelectReaders([]string{
		"ACS ACR1581 1S Dual Reader SAM",
		"ACS ACR1581 1S Dual Reader ICC",
		"ACS ACR1581 1S Dual Reader PICC",
	})

	if len(got) != 1 || got[0] != "ACS ACR1581 1S Dual Reader ICC" {
		t.Fatalf("ควรเหลือช่องสัมผัสช่องเดียว ได้ %v", got)
	}
}

func TestSelectReadersDoesNotMistakePiccForIcc(t *testing.T) {
	// PICC มีคำว่า ICC ซ้อนอยู่ ถ้าเทียบไม่ระวังจะเลือกช่องไร้สัมผัสมาด้วย
	got := SelectReaders([]string{"Some Reader PICC"})

	if len(got) != 1 || got[0] != "Some Reader PICC" {
		t.Fatalf("เครื่องที่มีแต่ช่องไร้สัมผัสควรยังใช้ได้ ได้ %v", got)
	}
}

func TestSelectReadersKeepsASingleSlotReader(t *testing.T) {
	got := SelectReaders([]string{"Generic Smart Card Reader 0"})

	if len(got) != 1 {
		t.Fatalf("เครื่องช่องเดียวต้องไม่ถูกคัดทิ้ง ได้ %v", got)
	}
}

func TestSelectReadersReturnsNothingWhenOnlySamIsPresent(t *testing.T) {
	if got := SelectReaders([]string{"Reader SAM"}); len(got) != 0 {
		t.Fatalf("มีแต่ช่อง SAM ต้องถือว่าไม่มีช่องให้ใช้ ได้ %v", got)
	}
}

func TestInsertedReaderOnlyFiresOnTheMomentOfInsertion(t *testing.T) {
	names := []string{"ICC"}

	// เสียบเข้าไป -> อ่าน
	if got := InsertedReader(names, []bool{false}, []bool{true}); got != "ICC" {
		t.Fatalf("ตอนเสียบต้องคืนชื่อช่อง ได้ %q", got)
	}

	// เสียบค้างไว้ -> ต้องเงียบ ไม่งั้นจะอ่านซ้ำไม่หยุด
	if got := InsertedReader(names, []bool{true}, []bool{true}); got != "" {
		t.Fatalf("บัตรที่ค้างอยู่ต้องไม่ถูกอ่านซ้ำ ได้ %q", got)
	}

	// ดึงออก -> เงียบ
	if got := InsertedReader(names, []bool{true}, []bool{false}); got != "" {
		t.Fatalf("ตอนดึงออกต้องไม่อ่าน ได้ %q", got)
	}

	// ดึงออกแล้วเสียบใหม่ -> อ่านอีกครั้ง
	if got := InsertedReader(names, []bool{false}, []bool{true}); got != "ICC" {
		t.Fatalf("เสียบใหม่ต้องอ่านอีกครั้ง ได้ %q", got)
	}
}

func TestInsertedReaderPicksTheSlotThatChanged(t *testing.T) {
	names := []string{"SlotA", "SlotB"}

	got := InsertedReader(names, []bool{true, false}, []bool{true, true})

	if got != "SlotB" {
		t.Fatalf("ต้องเลือกช่องที่เพิ่งเปลี่ยน ได้ %q", got)
	}
}

func TestInsertedReaderSurvivesMismatchedLengths(t *testing.T) {
	// เครื่องอ่านถูกถอดออกกลางคัน รายชื่อช่องกับสถานะอาจยาวไม่เท่ากัน
	if got := InsertedReader([]string{"A", "B"}, []bool{false}, []bool{true}); got != "A" {
		t.Fatalf("ไม่ควรพังและควรคืนช่องที่เทียบได้ ได้ %q", got)
	}
}
