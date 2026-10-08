package client

import (
	"context"
	"encoding/json"
	"errors"
	"net/http"
	"net/http/httptest"
	"testing"

	"github.com/softernity/exapp-card-agent/internal/card"
)

func TestSendCardCarriesTheDeviceToken(t *testing.T) {
	var gotAuth, gotID string

	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		gotAuth = r.Header.Get("Authorization")

		var body map[string]any
		_ = json.NewDecoder(r.Body).Decode(&body)
		gotID, _ = body["citizen_id"].(string)

		w.WriteHeader(http.StatusOK)
	}))
	defer srv.Close()

	err := New(srv.URL, "crd_secret", "1.0.0").
		SendCard(context.Background(), card.Data{CitizenID: "5960500028101"})
	if err != nil {
		t.Fatalf("ไม่ควร error: %v", err)
	}

	if gotAuth != "Bearer crd_secret" {
		t.Fatalf("token ไม่ถูกส่งไป ได้ %q", gotAuth)
	}

	if gotID != "5960500028101" {
		t.Fatalf("เลขบัตรไม่ถูกส่งไป ได้ %q", gotID)
	}
}

func TestA401MeansStopForeverNotRetry(t *testing.T) {
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.WriteHeader(http.StatusUnauthorized)
	}))
	defer srv.Close()

	_, err := New(srv.URL, "crd_revoked", "1.0.0").Heartbeat(context.Background(), "ready", "")

	// เครื่องที่ถูกเพิกถอนต้องหยุด ไม่ใช่วนยิงเซิร์ฟเวอร์ต่อไปเรื่อย ๆ
	if !errors.Is(err, ErrRevoked) {
		t.Fatalf("อยากได้ ErrRevoked ได้ %v", err)
	}
}

func TestAServerErrorIsReportedNotSwallowed(t *testing.T) {
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.WriteHeader(http.StatusInternalServerError)
	}))
	defer srv.Close()

	if _, err := New(srv.URL, "t", "1.0.0").Heartbeat(context.Background(), "ready", ""); err == nil {
		t.Fatal("ควร error แต่เงียบ")
	}
}

// ปิด connection ก่อนตอบ เลียนแบบ Cloud Run ที่ทิ้ง connection ว่างทิ้ง
type killFirstConnection struct {
	hits int
}

func (k *killFirstConnection) ServeHTTP(w http.ResponseWriter, r *http.Request) {
	k.hits++

	if k.hits == 1 {
		hijacker, ok := w.(http.Hijacker)
		if !ok {
			w.WriteHeader(http.StatusInternalServerError)

			return
		}

		conn, _, err := hijacker.Hijack()
		if err == nil {
			_ = conn.Close()
		}

		return
	}

	w.WriteHeader(http.StatusOK)
}

func TestACardIsResentWhenTheConnectionDiedBeforeReachingTheServer(t *testing.T) {
	h := &killFirstConnection{}
	srv := httptest.NewServer(h)
	defer srv.Close()

	// ข้อมูลบัตรหายไปเงียบ ๆ เพราะ connection ที่ค้างอยู่ในพูลตายแล้ว
	// เป็นสิ่งที่ยอมไม่ได้ — ลูกค้ายืนรออยู่หน้าเคาน์เตอร์
	err := New(srv.URL, "crd_secret", "1.0.0").
		SendCard(context.Background(), card.Data{CitizenID: "5960500028101"})
	if err != nil {
		t.Fatalf("ควรลองใหม่แล้วสำเร็จ แต่ได้ error: %v", err)
	}

	if h.hits != 2 {
		t.Fatalf("ควรยิงสองครั้ง (ตาย 1 สำเร็จ 1) ได้ %d", h.hits)
	}
}

func TestAServerRejectionIsNotRetried(t *testing.T) {
	h := &countingHandler{status: http.StatusUnauthorized}
	srv := httptest.NewServer(h)
	defer srv.Close()

	// 401 คือคำตอบจริงจากเซิร์ฟเวอร์ ไม่ใช่ connection ตาย ยิงซ้ำไปก็เท่านั้น
	_, _ = New(srv.URL, "crd_revoked", "1.0.0").Heartbeat(context.Background(), "ready", "")

	if h.hits != 1 {
		t.Fatalf("ไม่ควรลองใหม่ ได้ %d ครั้ง", h.hits)
	}
}

type countingHandler struct {
	status int
	hits   int
}

func (c *countingHandler) ServeHTTP(w http.ResponseWriter, r *http.Request) {
	c.hits++
	w.WriteHeader(c.status)
}

func TestTheHeartbeatBringsBackAKeyWaitingAtTheCounter(t *testing.T) {
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Type", "application/json")
		_, _ = w.Write([]byte(`{"ok":true,"passport_request":{"id":7,"document_no":"AC2784283","date_of_birth":"830625","expiry_date":"311031"}}`))
	}))
	defer srv.Close()

	// กุญแจเดินทางกลับมากับสัญญาณชีพ ไม่ใช่ช่องทางใหม่ — เบราว์เซอร์ส่งตรงมาหา
	// agent ไม่ได้ ทิศเดียวที่ใช้ได้คือเรายิงออกไปถาม
	req, err := New(srv.URL, "t", "1.0.0").Heartbeat(context.Background(), "ready", "")
	if err != nil {
		t.Fatalf("ไม่ควร error: %v", err)
	}

	if req == nil {
		t.Fatal("ควรได้กุญแจกลับมา")
	}

	if req.ID != 7 || req.DocumentNo != "AC2784283" || req.DateOfBirth != "830625" {
		t.Fatalf("กุญแจไม่ครบ: %+v", req)
	}
}

func TestAHeartbeatWithNoKeyWaitingReturnsNothing(t *testing.T) {
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Type", "application/json")
		_, _ = w.Write([]byte(`{"ok":true,"passport_request":null}`))
	}))
	defer srv.Close()

	req, err := New(srv.URL, "t", "1.0.0").Heartbeat(context.Background(), "ready", "")
	if err != nil {
		t.Fatalf("ไม่ควร error: %v", err)
	}

	if req != nil {
		t.Fatalf("ไม่ควรได้กุญแจ ได้ %+v", req)
	}
}

func TestChipDataIsSentAsFlatFields(t *testing.T) {
	var got map[string]any

	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		_ = json.NewDecoder(r.Body).Decode(&got)
		w.WriteHeader(http.StatusOK)
	}))
	defer srv.Close()

	err := New(srv.URL, "t", "1.0.0").SendPassport(context.Background(), 7, card.Passport{
		DocumentNo:   "AC2784283",
		Surname:      "KITTIKUM",
		NationalID:   "3500900234628",
		Authenticity: "verified",
	})
	if err != nil {
		t.Fatalf("ไม่ควร error: %v", err)
	}

	// เซิร์ฟเวอร์ validate ทีละช่อง ถ้าส่งเป็นก้อนซ้อนจะตกทั้งใบ
	for k, want := range map[string]any{
		"request_id":   float64(7),
		"ok":           true,
		"document_no":  "AC2784283",
		"surname":      "KITTIKUM",
		"national_id":  "3500900234628",
		"authenticity": "verified",
	} {
		if got[k] != want {
			t.Fatalf("ช่อง %s: อยากได้ %v ได้ %v", k, want, got[k])
		}
	}
}
