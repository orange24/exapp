package selfupdate

import (
	"context"
	"encoding/json"
	"io"
	"net/http"
	"net/http/httptest"
	"sync"
	"sync/atomic"
	"testing"
	"time"
)

// fakeReleases เลียน GitHub Releases API พอให้ตัวอัปเดตทำงานได้
//
// ส่ง tag ที่ขอมา พร้อมไฟล์แนบชื่อตรงกับที่เครื่องนี้มองหา ไม่งั้นตัวอัปเดต
// จะบอกว่า release ไม่มีไฟล์สำหรับเครื่องนี้
func fakeReleases(t *testing.T, tag string) *httptest.Server {
	t.Helper()

	var srv *httptest.Server

	srv = httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.URL.Path == "/asset" {
			_, _ = w.Write([]byte("ไบนารีรุ่นใหม่"))

			return
		}

		_ = json.NewEncoder(w).Encode([]map[string]any{{
			"tag_name": tag,
			"assets": []map[string]string{{
				"name":                 assetName(),
				"browser_download_url": srv.URL + "/asset",
			}},
		}})
	}))

	t.Cleanup(srv.Close)

	return srv
}

// newTestUpdater ชี้ตัวอัปเดตไปที่เซิร์ฟเวอร์ปลอม และดักขั้นเขียนทับไฟล์ไว้
//
// ห้ามให้มันเรียก selfupdate.Apply ของจริงเด็ดขาด — ฟังก์ชันนั้นเขียนทับ
// ไฟล์ที่กำลังรันอยู่ ซึ่งตอนเทสคือไบนารีของ go test เอง
func newTestUpdater(t *testing.T, current string, srv *httptest.Server) (*Updater, *int32) {
	t.Helper()

	var applied int32

	u := New(current)
	u.api = srv.URL + "/releases"
	u.apply = func(io.Reader) error {
		atomic.AddInt32(&applied, 1)

		return nil
	}

	return u, &applied
}

func TestItUpdatesItselfWithoutAnyoneClicking(t *testing.T) {
	srv := fakeReleases(t, tagPrefix+"9.9.9")
	u, applied := newTestUpdater(t, "1.0.0", srv)

	ctx, cancel := context.WithCancel(context.Background())
	defer cancel()

	var (
		mu       sync.Mutex
		gotVer   string
		applied2 = make(chan struct{}, 1)
	)

	go u.Auto(ctx, time.Hour, func(v string) {
		mu.Lock()
		gotVer = v
		mu.Unlock()

		select {
		case applied2 <- struct{}{}:
		default:
		}
	})

	select {
	case <-applied2:
	case <-time.After(3 * time.Second):
		t.Fatal("ไม่ได้อัปเดตเองเลย — สาขาต้องไปกดปุ่มที่เครื่องอยู่ดี")
	}

	if n := atomic.LoadInt32(applied); n != 1 {
		t.Fatalf("ควรเขียนทับไฟล์ครั้งเดียว ได้ %d", n)
	}

	mu.Lock()
	defer mu.Unlock()

	if gotVer != "9.9.9" {
		t.Fatalf("ควรบอกรุ่นที่อัปเดตไป ได้ %q", gotVer)
	}
}

func TestItLeavesTheLatestVersionAlone(t *testing.T) {
	srv := fakeReleases(t, tagPrefix+"1.0.0")
	u, applied := newTestUpdater(t, "1.0.0", srv)

	ctx, cancel := context.WithCancel(context.Background())
	defer cancel()

	go u.Auto(ctx, time.Hour, nil)

	time.Sleep(500 * time.Millisecond)

	if n := atomic.LoadInt32(applied); n != 0 {
		t.Fatalf("ใช้รุ่นล่าสุดอยู่แล้ว ไม่ควรเขียนทับอะไร แต่เขียนไป %d ครั้ง", n)
	}
}

func TestOneFailedCheckDoesNotKillTheWatcher(t *testing.T) {
	var hits int32

	var srv *httptest.Server

	srv = httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.URL.Path == "/asset" {
			_, _ = w.Write([]byte("ไบนารีรุ่นใหม่"))

			return
		}

		// รอบแรกเน็ตสาขาล่ม รอบต่อไปกลับมาปกติ
		if atomic.AddInt32(&hits, 1) == 1 {
			w.WriteHeader(http.StatusInternalServerError)

			return
		}

		_ = json.NewEncoder(w).Encode([]map[string]any{{
			"tag_name": tagPrefix + "9.9.9",
			"assets": []map[string]string{{
				"name":                 assetName(),
				"browser_download_url": srv.URL + "/asset",
			}},
		}})
	}))
	t.Cleanup(srv.Close)

	u, applied := newTestUpdater(t, "1.0.0", srv)

	ctx, cancel := context.WithCancel(context.Background())
	defer cancel()

	done := make(chan struct{}, 1)

	go u.Auto(ctx, 200*time.Millisecond, func(string) {
		select {
		case done <- struct{}{}:
		default:
		}
	})

	select {
	case <-done:
	case <-time.After(3 * time.Second):
		t.Fatal("ตรวจพลาดครั้งเดียวแล้วเลิกตรวจตลอดกาล — รุ่นใหม่จะไม่ถึงสาขาอีกเลย")
	}

	if n := atomic.LoadInt32(applied); n != 1 {
		t.Fatalf("ควรอัปเดตได้ในรอบที่เน็ตกลับมา ได้ %d", n)
	}
}

func TestItStopsWhenTheProgramIsClosing(t *testing.T) {
	srv := fakeReleases(t, tagPrefix+"1.0.0")
	u, _ := newTestUpdater(t, "1.0.0", srv)

	ctx, cancel := context.WithCancel(context.Background())

	stopped := make(chan struct{})

	go func() {
		u.Auto(ctx, 10*time.Millisecond, nil)
		close(stopped)
	}()

	cancel()

	select {
	case <-stopped:
	case <-time.After(2 * time.Second):
		t.Fatal("สั่งปิดแล้วยังวนตรวจอยู่")
	}
}
