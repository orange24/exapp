// Package config อ่านและเขียนไฟล์ตั้งค่าของ agent
//
// ไฟล์นี้เก็บ token ประจำเครื่อง จึงตั้งสิทธิ์ 0600 ให้เจ้าของอ่านได้คนเดียว
// ถ้าเครื่องถูกขโมย token ยังอยู่ในไฟล์ แต่ผู้ดูแลเพิกถอนจากฝั่งเซิร์ฟเวอร์ได้ทันที
package config

import (
	"encoding/json"
	"fmt"
	"os"
	"path/filepath"
	"runtime"
	"strings"
)

type Config struct {
	ServerURL string `json:"server_url"`
	Token     string `json:"token"`
}

const fileName = "config.json"

// Dir คืนโฟลเดอร์ตั้งค่าตามแบบของแต่ละระบบปฏิบัติการ
func Dir() (string, error) {
	base, err := os.UserConfigDir()
	if err != nil {
		return "", fmt.Errorf("หาโฟลเดอร์ตั้งค่าไม่เจอ: %w", err)
	}

	return filepath.Join(base, "exapp-card-agent"), nil
}

func Path() (string, error) {
	dir, err := Dir()
	if err != nil {
		return "", err
	}

	return filepath.Join(dir, fileName), nil
}

func Load() (*Config, error) {
	path, err := Path()
	if err != nil {
		return nil, err
	}

	raw, err := os.ReadFile(path)
	if err != nil {
		return nil, err
	}

	var c Config
	if err := json.Unmarshal(raw, &c); err != nil {
		return nil, fmt.Errorf("ไฟล์ตั้งค่าเสียหาย: %w", err)
	}

	if err := c.Validate(); err != nil {
		return nil, err
	}

	return &c, nil
}

func (c *Config) Validate() error {
	if strings.TrimSpace(c.ServerURL) == "" {
		return fmt.Errorf("ยังไม่ได้ตั้ง server_url")
	}

	if !strings.HasPrefix(c.ServerURL, "https://") && !strings.HasPrefix(c.ServerURL, "http://127.0.0.1") {
		// token ของเครื่องเดินทางไปกับทุกคำขอ ส่งผ่าน http ธรรมดาคือแจกให้คนดักฟัง
		return fmt.Errorf("server_url ต้องเป็น https (ยกเว้น 127.0.0.1 ตอน dev)")
	}

	if strings.TrimSpace(c.Token) == "" {
		return fmt.Errorf("ยังไม่ได้ตั้ง token")
	}

	return nil
}

func (c *Config) Save() error {
	if err := c.Validate(); err != nil {
		return err
	}

	dir, err := Dir()
	if err != nil {
		return err
	}

	if err := os.MkdirAll(dir, 0o700); err != nil {
		return err
	}

	path := filepath.Join(dir, fileName)

	raw, err := json.MarshalIndent(c, "", "  ")
	if err != nil {
		return err
	}

	// เขียนไฟล์ชั่วคราวแล้วสลับ เพื่อไม่ให้ไฟล์เดิมพังถ้าเขียนไม่จบ
	tmp := path + ".tmp"
	if err := os.WriteFile(tmp, raw, 0o600); err != nil {
		return err
	}

	if err := os.Rename(tmp, path); err != nil {
		return err
	}

	// Windows ไม่สนใจ mode ตอน WriteFile — ตั้งซ้ำให้แน่ใจบนระบบที่สนใจ
	if runtime.GOOS != "windows" {
		return os.Chmod(path, 0o600)
	}

	return nil
}
