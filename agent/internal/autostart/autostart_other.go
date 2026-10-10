//go:build !darwin && !windows

package autostart

import "fmt"

// สาขาใช้ Mac กับ Windows เท่านั้น ระบบอื่นไม่รองรับแต่ต้องคอมไพล์ผ่าน
func enabled() bool { return false }

func enable(string) error {
	return fmt.Errorf("ระบบปฏิบัติการนี้ยังไม่รองรับการเปิดเองตอนบูต")
}

func disable() error { return nil }
