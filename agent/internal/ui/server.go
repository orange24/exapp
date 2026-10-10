// Package ui เปิดหน้าตั้งค่าและหน้าสถานะที่ 127.0.0.1
//
// เลือกหน้าเว็บแทนหน้าต่าง native เพราะ Fyne ทำให้ไฟล์โตจาก 11 MB เป็น 30 MB
// ลาก dependency จาก 13 เป็น 54 โมดูล และที่สำคัญคือทำให้ cross-compile
// ไป Windows จากเครื่อง Mac ไม่ได้อีก (OpenGL ต้องใช้ cgo)
package ui

import (
	"context"
	"crypto/rand"
	"encoding/hex"
	"fmt"
	"net"
	"net/http"
	"strings"
	"sync"
	"time"
)

// Server คือหน้าเว็บในเครื่องที่พนักงานใช้ตั้งค่าและดูสถานะ
type Server struct {
	secret string
	addr   string
	http   *http.Server

	mu    sync.RWMutex
	state State
	deps  Deps
}

// State คือสิ่งที่หน้าสถานะแสดง
type State struct {
	Configured  bool
	ServerURL   string
	ReaderFound bool
	ReaderNames []string
	LastBeat    time.Time
	LastError   string
	Version     string
	CounterName string
	AutoStart   bool
}

// Deps คือสิ่งที่ UI สั่งให้ส่วนอื่นทำ — ประกาศเป็น interface เพื่อให้ทดสอบได้
// โดยไม่ต้องมีเครื่องอ่านบัตรและไม่ต้องมีเซิร์ฟเวอร์จริง
type Deps struct {
	Save           func(serverURL, token string) error
	TestConnection func(serverURL, token string) error
	SetAutoStart   func(on bool) error
	CheckUpdate    func() (version string, available bool, err error)
	ApplyUpdate    func() error
}

func New(deps Deps) (*Server, error) {
	raw := make([]byte, 16)
	if _, err := rand.Read(raw); err != nil {
		return nil, err
	}

	/*
	 * ผูกกับ 127.0.0.1 เท่านั้น ไม่ใช่ทุก interface
	 *
	 * พอร์ตนี้ตั้งค่า token ประจำเครื่องได้ ถ้าเปิดออกเครือข่าย ใครก็ตาม
	 * ที่อยู่วงเดียวกันจะเปลี่ยนปลายทางของเครื่องอ่านบัตรได้
	 *
	 * พอร์ต 0 = ให้ระบบสุ่มให้ จะได้ไม่ชนกับโปรแกรมอื่นที่สาขา
	 */
	ln, err := net.Listen("tcp", "127.0.0.1:0")
	if err != nil {
		return nil, fmt.Errorf("เปิดหน้าตั้งค่าไม่ได้: %w", err)
	}

	s := &Server{
		secret: hex.EncodeToString(raw),
		addr:   ln.Addr().String(),
		deps:   deps,
	}

	mux := http.NewServeMux()
	mux.HandleFunc("/", s.guard(s.handlePage))
	mux.HandleFunc("/save", s.guard(s.handleSave))
	mux.HandleFunc("/test", s.guard(s.handleTest))
	mux.HandleFunc("/autostart", s.guard(s.handleAutoStart))
	mux.HandleFunc("/update", s.guard(s.handleUpdate))

	s.http = &http.Server{Handler: mux, ReadHeaderTimeout: 5 * time.Second}

	go func() { _ = s.http.Serve(ln) }()

	return s, nil
}

// URL คือที่อยู่ที่ต้องเปิดในเบราว์เซอร์ — มีรหัสลับติดไปด้วย
func (s *Server) URL() string {
	return fmt.Sprintf("http://%s/?k=%s", s.addr, s.secret)
}

func (s *Server) Close() error {
	ctx, cancel := context.WithTimeout(context.Background(), 2*time.Second)
	defer cancel()

	return s.http.Shutdown(ctx)
}

func (s *Server) SetState(f func(*State)) {
	s.mu.Lock()
	defer s.mu.Unlock()
	f(&s.state)
}

func (s *Server) snapshot() State {
	s.mu.RLock()
	defer s.mu.RUnlock()

	return s.state
}

/*
guard กันสองอย่างที่พอร์ตใน 127.0.0.1 ยังโดนได้

รหัสลับในที่อยู่ — โปรแกรมอื่นบนเครื่องเดียวกันเดาพอร์ตได้ไม่ยาก แต่เดารหัส
32 ตัวอักษรไม่ได้

ตรวจ Host — หน้าเว็บจากอินเทอร์เน็ตสามารถชี้ชื่อโดเมนของตัวเองมาที่ 127.0.0.1
แล้วยิงคำขอมาได้ (DNS rebinding) การบังคับว่า Host ต้องเป็น 127.0.0.1 ปิดทางนั้น
*/
func (s *Server) guard(next http.HandlerFunc) http.HandlerFunc {
	return func(w http.ResponseWriter, r *http.Request) {
		host := r.Host
		if h, _, err := net.SplitHostPort(host); err == nil {
			host = h
		}

		if host != "127.0.0.1" && host != "localhost" {
			http.Error(w, "ปฏิเสธคำขอจากที่อยู่ภายนอก", http.StatusForbidden)

			return
		}

		key := r.URL.Query().Get("k")
		if key == "" {
			key = r.FormValue("k")
		}

		if !secureEqual(key, s.secret) {
			http.Error(w, "รหัสไม่ถูกต้อง", http.StatusForbidden)

			return
		}

		w.Header().Set("Referrer-Policy", "no-referrer")
		w.Header().Set("X-Content-Type-Options", "nosniff")

		next(w, r)
	}
}

// เทียบแบบใช้เวลาเท่ากันเสมอ ไม่ให้เดาทีละตัวอักษรจากเวลาที่ตอบ
func secureEqual(a, b string) bool {
	if len(a) != len(b) {
		return false
	}

	var diff byte
	for i := 0; i < len(a); i++ {
		diff |= a[i] ^ b[i]
	}

	return diff == 0
}

func trimmed(v string) string { return strings.TrimSpace(v) }
