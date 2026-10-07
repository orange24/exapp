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

	err := New(srv.URL, "crd_revoked", "1.0.0").Heartbeat(context.Background(), "ready", "")

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

	if err := New(srv.URL, "t", "1.0.0").Heartbeat(context.Background(), "ready", ""); err == nil {
		t.Fatal("ควร error แต่เงียบ")
	}
}
