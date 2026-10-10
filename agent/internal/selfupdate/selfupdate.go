// Package selfupdate ดึงรุ่นใหม่จาก GitHub Releases มาแทนที่ตัวเอง
//
// สาขาลงโปรแกรมเองและ IT remote เข้าไปดูเมื่อมีปัญหา การให้ทุกสาขาดาวน์โหลด
// ไฟล์ใหม่เองทุกครั้งที่แก้บั๊กเป็นภาระที่หลีกเลี่ยงได้
package selfupdate

import (
	"encoding/json"
	"fmt"
	"io"
	"net/http"
	"runtime"
	"strings"
	"time"

	"github.com/minio/selfupdate"
)

const releasesAPI = "https://api.github.com/repos/orange24/exapp/releases"

// tagPrefix แยก release ของ agent ออกจาก release อื่นของ repo เดียวกัน
const tagPrefix = "card-agent-v"

type release struct {
	TagName string `json:"tag_name"`
	Assets  []struct {
		Name string `json:"name"`
		URL  string `json:"browser_download_url"`
	} `json:"assets"`
}

type Updater struct {
	current string
	http    *http.Client
}

func New(currentVersion string) *Updater {
	return &Updater{
		current: strings.TrimSuffix(strings.TrimPrefix(currentVersion, "v"), "-uat"),
		http:    &http.Client{Timeout: 60 * time.Second},
	}
}

// Check บอกว่ามีรุ่นใหม่กว่าที่ใช้อยู่ไหม
func (u *Updater) Check() (version string, available bool, err error) {
	rel, err := u.latest()
	if err != nil {
		return "", false, err
	}

	latest := strings.TrimPrefix(rel.TagName, tagPrefix)

	return latest, latest != u.current && latest != "", nil
}

// Apply ดาวน์โหลดรุ่นใหม่แล้วแทนที่ไฟล์ของตัวเอง
//
// บน Windows แทนที่ไฟล์ที่กำลังรันอยู่ตรง ๆ ไม่ได้ ไลบรารีจึงเปลี่ยนชื่อไฟล์เดิม
// ไปก่อนแล้ววางไฟล์ใหม่แทน ไฟล์เก่าจะถูกลบตอนเปิดครั้งถัดไป
func (u *Updater) Apply() error {
	rel, err := u.latest()
	if err != nil {
		return err
	}

	want := assetName()

	var link string
	for _, a := range rel.Assets {
		if a.Name == want {
			link = a.URL

			break
		}
	}

	if link == "" {
		return fmt.Errorf("release %s ไม่มีไฟล์สำหรับเครื่องนี้ (%s)", rel.TagName, want)
	}

	resp, err := u.http.Get(link)
	if err != nil {
		return fmt.Errorf("ดาวน์โหลดไม่สำเร็จ: %w", err)
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusOK {
		return fmt.Errorf("ดาวน์โหลดไม่สำเร็จ (HTTP %d)", resp.StatusCode)
	}

	if err := selfupdate.Apply(resp.Body, selfupdate.Options{}); err != nil {
		// ไลบรารีเก็บไฟล์เดิมไว้ให้กู้คืนเมื่อการเขียนทับล้มเหลวกลางคัน
		if rollbackErr := selfupdate.RollbackError(err); rollbackErr != nil {
			return fmt.Errorf("อัปเดตล้มเหลวและกู้คืนไฟล์เดิมไม่ได้: %w", rollbackErr)
		}

		return fmt.Errorf("อัปเดตไม่สำเร็จ แต่โปรแกรมเดิมยังใช้ได้: %w", err)
	}

	return nil
}

func (u *Updater) latest() (*release, error) {
	resp, err := u.http.Get(releasesAPI + "?per_page=20")
	if err != nil {
		return nil, fmt.Errorf("ติดต่อ GitHub ไม่ได้: %w", err)
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusOK {
		return nil, fmt.Errorf("GitHub ตอบ %d", resp.StatusCode)
	}

	body, err := io.ReadAll(io.LimitReader(resp.Body, 1<<20))
	if err != nil {
		return nil, err
	}

	var all []release
	if err := json.Unmarshal(body, &all); err != nil {
		return nil, fmt.Errorf("อ่านคำตอบจาก GitHub ไม่ได้: %w", err)
	}

	// repo เดียวกันมี release ของอย่างอื่นด้วย เอาเฉพาะของ agent
	for _, r := range all {
		if strings.HasPrefix(r.TagName, tagPrefix) {
			return &r, nil
		}
	}

	return nil, fmt.Errorf("ยังไม่มี release ของโปรแกรมอ่านบัตร")
}

// assetName คือชื่อไฟล์ที่ workflow แนบไว้ใน release สำหรับเครื่องแบบนี้
func assetName() string {
	if runtime.GOOS == "windows" {
		return "exapp-card-agent.exe"
	}

	return fmt.Sprintf("exapp-card-agent-%s-%s", runtime.GOOS, runtime.GOARCH)
}
