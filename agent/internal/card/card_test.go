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
