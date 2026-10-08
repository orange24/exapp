package card

import (
	"errors"
	"strings"
	"testing"
)

func TestAWrongKeyTellsStaffWhichFieldsToCheck(t *testing.T) {
	// ของจริงจากเครื่องที่เคาน์เตอร์ ยาว 281 ตัวอักษร
	raw := errors.New("[ReadDocument] runSteps error: [runSteps] error: [performPace] " +
		"PACE failed: [DoPACE] doGenericMappingGmCam error: [doGenericMappingGmCam] " +
		"keyAgreementGmEcDh error: [keyAgreementGmEcDh] GeneralAuthenticate error: " +
		"[GeneralAuthenticate] DoAPDU bad rApdu status: 6a80")

	got := DescribeFailure(raw)

	// ต้องเสนอทางที่น่าจะเป็นก่อน แล้วค่อยถึงเรื่องตัวเลข
	//
	// ของจริงที่เจอ: ข้อมูลถูกต้องทุกช่อง กดอ่านใหม่ก็ผ่าน การชี้ไปที่ตัวเลข
	// อย่างเดียวทำให้พนักงานไล่ตรวจสิ่งที่ไม่ได้ผิด
	if !strings.Contains(got, "วางเล่มให้นิ่ง") {
		t.Fatalf("ควรเสนอให้วางนิ่งแล้วลองใหม่ก่อน ได้ %q", got)
	}

	if !strings.Contains(got, "เลขพาสปอร์ต") {
		t.Fatalf("ควรบอกด้วยว่าอาจเป็นเรื่องตัวเลข ได้ %q", got)
	}

	// ยาวเกิน 255 แล้ว API จะปฏิเสธ ความล้มเหลวจะหายไปเงียบ ๆ
	if len([]rune(got)) > 255 {
		t.Fatalf("ยาว %d ตัวอักษร เกินที่ API รับได้", len([]rune(got)))
	}
}

func TestAnUnknownErrorIsStillReportedWithinTheLimit(t *testing.T) {
	long := errors.New(strings.Repeat("ข", 400))

	got := DescribeFailure(long)

	if len([]rune(got)) > 255 {
		t.Fatalf("ยาว %d ตัวอักษร เกินที่ API รับได้", len([]rune(got)))
	}

	if got == "" {
		t.Fatal("ต้องไม่เงียบ — ความล้มเหลวที่ไม่รู้จักก็ต้องรายงาน")
	}
}

func TestEachKnownFailureSaysWhatToDoNext(t *testing.T) {
	for raw, want := range map[string]string{
		"SelectAid 6a82 not found":         "ไม่มีชิป",
		"card was removed during Transmit": "วางเล่มนิ่ง",
		"โหลดใบรับรองประเทศผู้ออกไม่ได้: x": "อินเทอร์เน็ต",
	} {
		got := DescribeFailure(errors.New(raw))

		if !strings.Contains(got, want) {
			t.Fatalf("%q -> %q ไม่มีคำว่า %q", raw, got, want)
		}
	}
}

func TestTruncateCountsCharactersNotBytes(t *testing.T) {
	// ภาษาไทยหนึ่งตัวใช้สามไบต์ การตัดตามไบต์จะได้ตัวอักษรพังกลางตัว
	got := Truncate(strings.Repeat("ก", 10), 5)

	if len([]rune(got)) != 8 { // 5 ตัว + "..."
		t.Fatalf("ตัดผิด ได้ %q (%d ตัว)", got, len([]rune(got)))
	}
}
