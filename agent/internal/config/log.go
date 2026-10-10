package config

import (
	"io"
	"log"
	"os"
	"path/filepath"
)

const logFile = "agent.log"

// maxLogBytes ตัดไฟล์เมื่อโตเกินนี้ — เครื่องสาขาเปิดทิ้งไว้ทั้งวันทุกวัน
// ปล่อยไว้ log จะกินดิสก์จนเต็มในที่สุด
const maxLogBytes = 2 * 1024 * 1024

// LogPath คือที่อยู่ไฟล์ log ให้บอก IT ได้เวลาต้องไล่ปัญหา
func LogPath() string {
	dir, err := Dir()
	if err != nil {
		return ""
	}

	return filepath.Join(dir, logFile)
}

/*
StartLogging ส่ง log ลงไฟล์ด้วย

ตัวที่แจกให้สาขาเปิดแบบไม่มีหน้าจอ — .app บน macOS และ -H windowsgui บน
Windows — ข้อความที่เคยพิมพ์ออก terminal จึงหายไปหมด ถ้าไม่เขียนลงไฟล์
เวลาเครื่องไหนมีปัญหาจะไม่มีอะไรให้ดูเลยนอกจากเดา

คืน io.Writer ให้ caller ปิดเมื่อจบ
*/
func StartLogging() io.Closer {
	path := LogPath()
	if path == "" {
		return noopCloser{}
	}

	if err := os.MkdirAll(filepath.Dir(path), 0o700); err != nil {
		return noopCloser{}
	}

	// ตัดไฟล์เก่าทิ้งถ้าโตเกิน แทนที่จะหมุนหลายไฟล์ให้ซับซ้อน
	// สิ่งที่ต้องการคือ "เมื่อกี้เกิดอะไรขึ้น" ไม่ใช่ประวัติทั้งเดือน
	if st, err := os.Stat(path); err == nil && st.Size() > maxLogBytes {
		_ = os.Remove(path)
	}

	f, err := os.OpenFile(path, os.O_CREATE|os.O_APPEND|os.O_WRONLY, 0o600)
	if err != nil {
		return noopCloser{}
	}

	// เขียนทั้งสองทาง — ตอน dev ยังเห็นบน terminal ตอนแจกจริงมีแต่ไฟล์
	log.SetOutput(io.MultiWriter(os.Stderr, f))
	log.SetFlags(log.LstdFlags)

	return f
}

type noopCloser struct{}

func (noopCloser) Close() error { return nil }
