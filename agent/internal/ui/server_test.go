package ui

import (
	"errors"
	"io"
	"net/http"
	"net/url"
	"strings"
	"testing"
)

func newTestServer(t *testing.T, deps Deps) *Server {
	t.Helper()

	s, err := New(deps)
	if err != nil {
		t.Fatalf("เปิดหน้าตั้งค่าไม่ได้: %v", err)
	}

	t.Cleanup(func() { _ = s.Close() })

	return s
}

func TestThePageIsRefusedWithoutTheSecret(t *testing.T) {
	s := newTestServer(t, Deps{})

	// พอร์ตเดาได้ไม่ยาก โปรแกรมอื่นบนเครื่องเดียวกันต้องเข้าไม่ได้
	resp, err := http.Get("http://" + s.addr + "/")
	if err != nil {
		t.Fatalf("ยิงไม่ได้: %v", err)
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusForbidden {
		t.Fatalf("ควรถูกปฏิเสธ ได้ %d", resp.StatusCode)
	}
}

func TestAWrongSecretIsRefused(t *testing.T) {
	s := newTestServer(t, Deps{})

	resp, err := http.Get("http://" + s.addr + "/?k=" + strings.Repeat("0", 32))
	if err != nil {
		t.Fatalf("ยิงไม่ได้: %v", err)
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusForbidden {
		t.Fatalf("ควรถูกปฏิเสธ ได้ %d", resp.StatusCode)
	}
}

func TestARequestClaimingAnotherHostIsRefused(t *testing.T) {
	s := newTestServer(t, Deps{})

	// หน้าเว็บจากอินเทอร์เน็ตชี้โดเมนตัวเองมาที่ 127.0.0.1 แล้วยิงคำขอได้
	// (DNS rebinding) — Host ที่ไม่ใช่ 127.0.0.1 จึงต้องถูกปฏิเสธ
	req, _ := http.NewRequest(http.MethodGet, s.URL(), nil)
	req.Host = "evil.example.com"

	resp, err := http.DefaultClient.Do(req)
	if err != nil {
		t.Fatalf("ยิงไม่ได้: %v", err)
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusForbidden {
		t.Fatalf("ควรถูกปฏิเสธ ได้ %d", resp.StatusCode)
	}
}

func TestTheServerOnlyListensOnLoopback(t *testing.T) {
	s := newTestServer(t, Deps{})

	// พอร์ตนี้ตั้ง token ประจำเครื่องได้ ถ้าเปิดออกเครือข่าย ใครในวงเดียวกัน
	// ก็เปลี่ยนปลายทางของเครื่องอ่านบัตรได้
	if !strings.HasPrefix(s.addr, "127.0.0.1:") {
		t.Fatalf("ต้องผูกกับ 127.0.0.1 เท่านั้น ได้ %q", s.addr)
	}
}

func TestEachRunGetsItsOwnPortAndSecret(t *testing.T) {
	a := newTestServer(t, Deps{})
	b := newTestServer(t, Deps{})

	if a.addr == b.addr {
		t.Fatal("พอร์ตต้องไม่ซ้ำกัน ไม่งั้นจะชนกับโปรแกรมอื่นที่สาขา")
	}

	if a.secret == b.secret {
		t.Fatal("รหัสลับต้องสุ่มใหม่ทุกครั้ง")
	}
}

func TestSettingsAreNotSavedWhenTheServerCannotBeReached(t *testing.T) {
	saved := false

	s := newTestServer(t, Deps{
		TestConnection: func(string, string) error { return errors.New("token ไม่ถูกต้อง") },
		Save:           func(string, string) error { saved = true; return nil },
	})

	resp, err := http.PostForm(s.saveURL(), url.Values{
		"k":          {s.secret},
		"server_url": {"https://exapp.test"},
		"token":      {"crd_wrong"},
	})
	if err != nil {
		t.Fatalf("ยิงไม่ได้: %v", err)
	}
	defer resp.Body.Close()

	/*
	 * ค่าที่ผิดต้องไม่ถูกบันทึก ไม่งั้นโปรแกรมจะรันอยู่เงียบ ๆ โดยไม่มีใครรู้ว่า
	 * ตั้งค่าผิด จนกว่าจะมีลูกค้ายืนรอหน้าเคาน์เตอร์แล้วเสียบบัตรไม่ขึ้น
	 */
	if saved {
		t.Fatal("ไม่ควรบันทึกค่าที่ต่อไม่ได้")
	}

	body, _ := io.ReadAll(resp.Body)
	if !strings.Contains(string(body), "token ไม่ถูกต้อง") {
		t.Fatal("ควรบอกเหตุผลที่ต่อไม่ได้")
	}
}

func TestGoodSettingsAreSavedAndTheStatusPageTakesOver(t *testing.T) {
	var gotURL, gotToken string

	s := newTestServer(t, Deps{
		TestConnection: func(string, string) error { return nil },
		Save:           func(u, tk string) error { gotURL, gotToken = u, tk; return nil },
	})

	resp, err := http.PostForm(s.saveURL(), url.Values{
		"k":          {s.secret},
		"server_url": {"  https://exapp.test  "},
		"token":      {" crd_good "},
	})
	if err != nil {
		t.Fatalf("ยิงไม่ได้: %v", err)
	}
	defer resp.Body.Close()

	// ช่องว่างหัวท้ายมาจากการคัดลอกวาง ต้องตัดออกก่อนใช้
	if gotURL != "https://exapp.test" || gotToken != "crd_good" {
		t.Fatalf("ค่าที่บันทึกไม่ถูกตัดช่องว่าง: %q %q", gotURL, gotToken)
	}

	if !s.snapshot().Configured {
		t.Fatal("หลังบันทึกควรถือว่าตั้งค่าแล้ว")
	}
}

func TestTheFirstRunShowsTheSetupFormAndLaterRunsShowStatus(t *testing.T) {
	s := newTestServer(t, Deps{})

	body := get(t, s.URL())
	if !strings.Contains(body, "ตั้งค่าเครื่องอ่านบัตรครั้งแรก") {
		t.Fatal("ครั้งแรกต้องเป็นหน้ากรอกข้อมูล")
	}

	s.SetState(func(st *State) { st.Configured = true; st.ServerURL = "https://exapp.test" })

	body = get(t, s.URL())
	if strings.Contains(body, "ตั้งค่าเครื่องอ่านบัตรครั้งแรก") {
		t.Fatal("ตั้งค่าแล้วต้องเป็นหน้าสถานะ")
	}

	if !strings.Contains(body, "แก้ไขการตั้งค่า") {
		t.Fatal("หน้าสถานะต้องมีปุ่มแก้ไข")
	}
}

func TestTheTokenIsNeverShownBackOnTheStatusPage(t *testing.T) {
	s := newTestServer(t, Deps{})
	s.SetState(func(st *State) { st.Configured = true; st.ServerURL = "https://exapp.test" })

	// token อยู่ในไฟล์ตั้งค่าแล้ว ไม่มีเหตุผลให้เอากลับมาแสดงบนหน้าจอ
	// ที่อาจมีคนเดินผ่านหรือถูกถ่ายภาพหน้าจอ
	if strings.Contains(get(t, s.URL()), "crd_") {
		t.Fatal("หน้าสถานะต้องไม่แสดง token")
	}
}

func (s *Server) saveURL() string {
	return "http://" + s.addr + "/save?k=" + s.secret
}

func get(t *testing.T, u string) string {
	t.Helper()

	resp, err := http.Get(u)
	if err != nil {
		t.Fatalf("ยิงไม่ได้: %v", err)
	}
	defer resp.Body.Close()

	b, _ := io.ReadAll(resp.Body)

	return string(b)
}
