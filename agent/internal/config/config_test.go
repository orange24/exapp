package config

import (
	"os"
	"runtime"
	"testing"
)

func TestSaveWritesAPrivateFile(t *testing.T) {
	t.Setenv("XDG_CONFIG_HOME", t.TempDir())
	if runtime.GOOS == "darwin" {
		t.Setenv("HOME", t.TempDir())
	}

	c := &Config{ServerURL: "https://exapp.example.com", Token: "crd_secret"}
	if err := c.Save(); err != nil {
		t.Fatalf("บันทึกไม่ได้: %v", err)
	}

	path, _ := Path()
	info, err := os.Stat(path)
	if err != nil {
		t.Fatalf("ไม่พบไฟล์: %v", err)
	}

	// ไฟล์นี้มี token ประจำเครื่อง คนอื่นบนเครื่องเดียวกันต้องอ่านไม่ได้
	if runtime.GOOS != "windows" && info.Mode().Perm() != 0o600 {
		t.Fatalf("สิทธิ์ไฟล์ควรเป็น 0600 ได้ %v", info.Mode().Perm())
	}
}

func TestPlainHttpIsRefused(t *testing.T) {
	// token เดินทางไปกับทุกคำขอ ส่งผ่าน http ธรรมดาคือแจกให้คนดักฟัง
	c := &Config{ServerURL: "http://exapp.example.com", Token: "crd_secret"}

	if err := c.Validate(); err == nil {
		t.Fatal("http ธรรมดาควรถูกปฏิเสธ")
	}
}

func TestLocalhostIsAllowedForDevelopment(t *testing.T) {
	c := &Config{ServerURL: "http://127.0.0.1:8000", Token: "crd_secret"}

	if err := c.Validate(); err != nil {
		t.Fatalf("127.0.0.1 ควรใช้ได้ตอน dev: %v", err)
	}
}

func TestMissingFieldsAreRefused(t *testing.T) {
	for _, c := range []*Config{
		{ServerURL: "", Token: "t"},
		{ServerURL: "https://x.test", Token: ""},
	} {
		if err := c.Validate(); err == nil {
			t.Fatalf("ควรถูกปฏิเสธ: %+v", c)
		}
	}
}
