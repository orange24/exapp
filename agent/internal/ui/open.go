package ui

import (
	"os/exec"
	"runtime"
)

// OpenBrowser เปิดหน้าตั้งค่าในเบราว์เซอร์ของเครื่อง
//
// พนักงานดับเบิลคลิกที่ตัวโปรแกรมแล้วควรเห็นอะไรสักอย่างทันที ไม่ใช่ความเงียบ
// ที่แยกไม่ออกว่าโปรแกรมเปิดแล้วหรือกดไม่ติด
func OpenBrowser(url string) error {
	switch runtime.GOOS {
	case "darwin":
		return exec.Command("open", url).Start()
	case "windows":
		// rundll32 เลี่ยงปัญหาการ escape ของ cmd /c start ซึ่งตีความ & ในที่อยู่
		return exec.Command("rundll32", "url.dll,FileProtocolHandler", url).Start()
	default:
		return exec.Command("xdg-open", url).Start()
	}
}
