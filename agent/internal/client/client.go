// Package client คุยกับ exapp
//
// ทิศทางเดียวที่ใช้ได้คือ agent ยิงออกไปหาเซิร์ฟเวอร์ ส่วนทิศกลับกัน
// (หน้าเว็บเรียกโปรแกรมในเครื่อง) ใช้ไม่ได้ เพราะหน้า HTTPS เรียก
// http://127.0.0.1 ไม่ได้ — Chrome บล็อกตั้งแต่ต้นทาง คำขอไม่เคยไปถึง
package client

import (
	"bytes"
	"context"
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"net"
	"net/http"
	"strings"
	"syscall"
	"time"

	"github.com/softernity/exapp-card-agent/internal/card"
)

// ErrRevoked แปลว่าเครื่องนี้ถูกเพิกถอนแล้ว ต้องหยุดถาวร ไม่ใช่ลองใหม่
var ErrRevoked = fmt.Errorf("เครื่องนี้ถูกเพิกถอนแล้ว")

type Client struct {
	baseURL string
	token   string
	version string
	http    *http.Client
}

func New(baseURL, token, version string) *Client {
	transport := http.DefaultTransport.(*http.Transport).Clone()

	// ทิ้ง connection ที่ว่างเร็วกว่าที่ปลายทางจะทิ้ง
	//
	// Cloud Run ปิด connection ที่ไม่มีอะไรวิ่งหลังไม่กี่นาที ถ้าเราเก็บไว้นานกว่านั้น
	// จะหยิบตัวที่ตายแล้วมาใช้แล้วเจอ connection reset by peer
	// เจอจริงบนเครื่องสาขา: สัญญาณชีพล้มสองครั้งใน 3 ชั่วโมง
	transport.IdleConnTimeout = 30 * time.Second

	return &Client{
		baseURL: strings.TrimRight(baseURL, "/"),
		token:   token,
		version: version,
		http:    &http.Client{Timeout: 20 * time.Second, Transport: transport},
	}
}

/*
staleConnection บอกว่าคำขอล้มเพราะ connection ที่ตายแล้ว ไม่ใช่เพราะเซิร์ฟเวอร์ปฏิเสธ

Go ลองใหม่ให้เองเฉพาะคำขอที่ซ้ำได้ปลอดภัย POST ไม่เข้าข่ายเพราะมันไม่รู้ว่า
เซิร์ฟเวอร์ประมวลผลไปแล้วหรือยัง เราจึงต้องแยกเองว่าเคสไหนลองใหม่ได้

เคสนี้ปลอดภัยที่จะลองใหม่ เพราะคำขอไม่เคยไปถึงเซิร์ฟเวอร์เลย
*/
func staleConnection(err error) bool {
	if err == nil {
		return false
	}

	if errors.Is(err, syscall.ECONNRESET) || errors.Is(err, syscall.EPIPE) || errors.Is(err, io.EOF) {
		return true
	}

	var netErr *net.OpError

	return errors.As(err, &netErr)
}

func (c *Client) post(ctx context.Context, path string, body any) error {
	raw, err := json.Marshal(body)
	if err != nil {
		return err
	}

	req, err := http.NewRequestWithContext(ctx, http.MethodPost, c.baseURL+path, bytes.NewReader(raw))
	if err != nil {
		return err
	}

	req.Header.Set("Content-Type", "application/json")
	req.Header.Set("Accept", "application/json")
	req.Header.Set("Authorization", "Bearer "+c.token)

	resp, err := c.http.Do(req)

	if staleConnection(err) {
		// สร้างคำขอใหม่ทั้งใบ — ตัวเดิมอ่าน body ไปแล้วใช้ซ้ำไม่ได้
		retry, buildErr := http.NewRequestWithContext(ctx, http.MethodPost, c.baseURL+path, bytes.NewReader(raw))
		if buildErr != nil {
			return err
		}

		retry.Header = req.Header.Clone()
		resp, err = c.http.Do(retry)
	}

	if err != nil {
		return err
	}
	defer resp.Body.Close()

	// 401 คือคำตอบถาวร ไม่ใช่ปัญหาชั่วคราว — ลองใหม่ไปก็เท่านั้น
	if resp.StatusCode == http.StatusUnauthorized {
		return ErrRevoked
	}

	if resp.StatusCode >= 300 {
		return fmt.Errorf("%s ตอบ %d", path, resp.StatusCode)
	}

	return nil
}

// Heartbeat บอกเซิร์ฟเวอร์ว่ายังอยู่ — ขับไฟสถานะที่หน้าเคาน์เตอร์
func (c *Client) Heartbeat(ctx context.Context, status, errMessage string) error {
	return c.post(ctx, "/api/card-reader/heartbeat", map[string]string{
		"status":  status,
		"error":   errMessage,
		"version": c.version,
	})
}

// SendCard ส่งข้อมูลบัตรที่เพิ่งอ่านได้
func (c *Client) SendCard(ctx context.Context, d card.Data) error {
	return c.post(ctx, "/api/card-reader/read", d)
}
