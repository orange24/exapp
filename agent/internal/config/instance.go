package config

import (
	"os"
	"path/filepath"
	"strings"
)

// uiFile เก็บที่อยู่หน้าตั้งค่าของอินสแตนซ์ที่กำลังรันอยู่
//
// ดับเบิลคลิกซ้ำต้องเปิดหน้าของตัวที่รันอยู่ ไม่ใช่เปิดโปรแกรมตัวที่สอง
// สองตัวแย่งกันอ่านเครื่องอ่านเดียวกันจะพังทั้งคู่
const uiFile = "ui-url.txt"

func uiPath() (string, error) {
	dir, err := Dir()
	if err != nil {
		return "", err
	}

	return filepath.Join(dir, uiFile), nil
}

// PublishUI บันทึกที่อยู่หน้าตั้งค่าไว้ให้อินสแตนซ์ถัดไปเจอ
func PublishUI(url string) error {
	path, err := uiPath()
	if err != nil {
		return err
	}

	if err := os.MkdirAll(filepath.Dir(path), 0o700); err != nil {
		return err
	}

	// ที่อยู่มีรหัสลับอยู่ด้วย คนอื่นบนเครื่องเดียวกันต้องอ่านไม่ได้
	return os.WriteFile(path, []byte(url), 0o600)
}

// RunningUI คืนที่อยู่ของอินสแตนซ์ที่รันอยู่ ว่างเมื่อไม่มี
func RunningUI() string {
	path, err := uiPath()
	if err != nil {
		return ""
	}

	raw, err := os.ReadFile(path)
	if err != nil {
		return ""
	}

	return strings.TrimSpace(string(raw))
}

// ClearUI ลบที่อยู่ทิ้งตอนปิดโปรแกรม
func ClearUI() {
	if path, err := uiPath(); err == nil {
		_ = os.Remove(path)
	}
}
