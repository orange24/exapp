// Package autostart ทำให้โปรแกรมเปิดเองเมื่อเปิดเครื่อง
//
// พนักงานที่สาขาเปิดคอมมาแล้วควรพร้อมใช้ ไม่ต้องจำว่าต้องกดอะไรก่อน
// และไม่ต้องให้ IT remote เข้าไปเปิดให้ทุกเช้า
package autostart

import "os"

// Name ที่ใช้ลงทะเบียนในระบบปฏิบัติการ
const Name = "exapp-card-agent"

// Enabled บอกว่าตอนนี้ตั้งให้เปิดเองอยู่ไหม
func Enabled() bool { return enabled() }

// Set เปิดหรือปิดการเปิดเองตอนบูต
func Set(on bool) error {
	exe, err := os.Executable()
	if err != nil {
		return err
	}

	if on {
		return enable(exe)
	}

	return disable()
}
