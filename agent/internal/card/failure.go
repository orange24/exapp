package card

import "strings"

/*
DescribeFailure แปลความผิดพลาดจากไลบรารีเป็นสิ่งที่พนักงานทำต่อได้

ของจริงที่ไลบรารีคืนมาหน้าตาแบบนี้:

	[ReadDocument] runSteps error: [runSteps] error: [performPace] PACE failed:
	[DoPACE] doGenericMappingGmCam error: ... DoAPDU bad rApdu status: 6a80

ยาว 281 ตัวอักษร เกินขีดจำกัดของ API จนความล้มเหลวถูกปฏิเสธทิ้งและหน้าจอค้าง
อยู่ที่ "กำลังเปิดชิป..." ตลอดกาล และต่อให้ส่งผ่าน พนักงานก็อ่านไม่รู้เรื่องอยู่ดี
*/
func DescribeFailure(err error) string {
	if err == nil {
		return ""
	}

	raw := err.Error()

	switch {
	// 6a80 ระหว่าง PACE/BAC แปลว่ากุญแจที่สร้างจาก MRZ ไม่ตรงกับเล่มนี้
	case strings.Contains(raw, "6a80"),
		strings.Contains(raw, "PACE failed"),
		strings.Contains(raw, "BAC failed"),
		strings.Contains(raw, "MutualAuthenticate"):
		return "ข้อมูลที่ใช้เปิดชิปไม่ตรงกับเล่มนี้ — ตรวจเลขพาสปอร์ต วันเกิด และวันหมดอายุ แล้วกดอ่านใหม่"

	case strings.Contains(raw, "6a82"), strings.Contains(raw, "no MRTD"), strings.Contains(raw, "SelectAid"):
		return "เล่มนี้ไม่มีชิป หรือชิปไม่ตอบสนอง — ใช้ข้อมูลจากการถ่ายรูปต่อได้"

	case strings.Contains(raw, "เชื่อมกับพาสปอร์ตไม่ได้"),
		strings.Contains(raw, "Transmit"),
		strings.Contains(raw, "removed"),
		strings.Contains(raw, "reset"):
		return "สัญญาณขาดระหว่างอ่าน — วางเล่มนิ่ง ๆ บนแท่นแล้วลองใหม่"

	case strings.Contains(raw, "ข้อมูลจากหน้าพาสปอร์ตไม่ครบ"):
		return "ข้อมูลจากหน้าพาสปอร์ตไม่ครบ — กรอกเลขพาสปอร์ต วันเกิด และวันหมดอายุ แล้วกดอ่านใหม่"

	case strings.Contains(raw, "โหลดใบรับรอง"):
		return "โหลดใบรับรองประเทศผู้ออกไม่ได้ — ตรวจการเชื่อมต่ออินเทอร์เน็ต"
	}

	// ไม่รู้จัก — ส่งต้นฉบับไปแต่ตัดให้พอดี ดีกว่าให้ความล้มเหลวหายไปเงียบ ๆ
	return Truncate(raw, 200)
}

// Truncate ตัดข้อความตามจำนวนตัวอักษร ไม่ใช่จำนวนไบต์ — ภาษาไทยหนึ่งตัวใช้สามไบต์
func Truncate(s string, max int) string {
	r := []rune(s)

	if len(r) <= max {
		return s
	}

	return string(r[:max]) + "..."
}
