//go:build darwin

package autostart

import (
	"fmt"
	"os"
	"os/exec"
	"path/filepath"
)

// macOS ใช้ LaunchAgent ซึ่งเป็นไฟล์ plist ในโฟลเดอร์ของผู้ใช้
// ไม่ต้องขอสิทธิ์ผู้ดูแลระบบ และผูกกับผู้ใช้คนนั้นคนเดียว
func plistPath() string {
	home, _ := os.UserHomeDir()

	return filepath.Join(home, "Library", "LaunchAgents", Name+".plist")
}

func enabled() bool {
	_, err := os.Stat(plistPath())

	return err == nil
}

func enable(exe string) error {
	path := plistPath()

	if err := os.MkdirAll(filepath.Dir(path), 0o755); err != nil {
		return err
	}

	body := fmt.Sprintf(`<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0"><dict>
  <key>Label</key><string>%s</string>
  <key>ProgramArguments</key><array><string>%s</string><string>-background</string></array>
  <key>RunAtLoad</key><true/>
  <key>KeepAlive</key><false/>
</dict></plist>
`, Name, exe)

	if err := os.WriteFile(path, []byte(body), 0o644); err != nil {
		return err
	}

	// โหลดทันทีโดยไม่ต้องรีสตาร์ตเครื่อง ถ้าล้มเหลวก็ไม่เป็นไร
	// ไฟล์อยู่แล้ว รอบบูตหน้าได้แน่นอน
	_ = exec.Command("launchctl", "load", "-w", path).Run()

	return nil
}

func disable() error {
	path := plistPath()

	_ = exec.Command("launchctl", "unload", "-w", path).Run()

	if err := os.Remove(path); err != nil && !os.IsNotExist(err) {
		return err
	}

	return nil
}
