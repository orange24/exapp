// exapp-card-agent — อ่านบัตรประชาชนที่เคาน์เตอร์แล้วส่งขึ้น exapp
//
// ทำงานทางเดียว: เฝ้าช่องเสียบบัตร เสียบเมื่อไหร่ก็อ่านแล้วยิงขึ้นเซิร์ฟเวอร์
// ไม่เปิดพอร์ต ไม่รับคำสั่งจากใคร เพราะหน้าเว็บ HTTPS เรียกโปรแกรมในเครื่อง
// ไม่ได้อยู่แล้ว — Chrome บล็อก http://127.0.0.1 ตั้งแต่ต้นทาง
package main

import (
	"context"
	"errors"
	"flag"
	"fmt"
	"log"
	"os"
	"os/signal"
	"syscall"
	"time"

	"github.com/softernity/exapp-card-agent/internal/card"
	"github.com/softernity/exapp-card-agent/internal/client"
	"github.com/softernity/exapp-card-agent/internal/config"
)

var version = "dev"

const (
	heartbeatEvery = 30 * time.Second
	cardWaitWindow = 5 * time.Second

	// กันการอ่านซ้ำตอนบัตรยังเสียบค้างอยู่ หรือหน้าสัมผัสขยับจนหลุดแล้วติดใหม่
	sameCardCooldown = 10 * time.Second
)

func main() {
	setup := flag.Bool("setup", false, "ตั้งค่าเซิร์ฟเวอร์และ token ครั้งแรก")
	server := flag.String("server", "", "ที่อยู่ exapp เช่น https://exapp.softernity.com")
	token := flag.String("token", "", "token ประจำเครื่องจากหน้าผู้ดูแลระบบ")
	showVersion := flag.Bool("version", false, "แสดงรุ่น")
	flag.Parse()

	if *showVersion {
		fmt.Println(version)
		return
	}

	if *setup {
		runSetup(*server, *token)
		return
	}

	cfg, err := config.Load()
	if err != nil {
		path, _ := config.Path()
		log.Fatalf("อ่านไฟล์ตั้งค่าไม่ได้ (%s): %v\n\nตั้งค่าครั้งแรกด้วย:\n  exapp-card-agent -setup -server https://... -token crd_...", path, err)
	}

	if err := run(cfg); err != nil {
		log.Fatal(err)
	}
}

func runSetup(server, token string) {
	cfg := &config.Config{ServerURL: server, Token: token}

	if err := cfg.Save(); err != nil {
		log.Fatalf("บันทึกไม่สำเร็จ: %v", err)
	}

	path, _ := config.Path()
	fmt.Printf("บันทึกแล้วที่ %s\nรันต่อด้วย: exapp-card-agent\n", path)
}

func run(cfg *config.Config) error {
	api := client.New(cfg.ServerURL, cfg.Token, version)

	reader, err := card.NewReader()
	if err != nil {
		// ไม่มี PC/SC ก็ยังต้องรายงานตัวให้หน้าเคาน์เตอร์ขึ้นไฟแดง
		// ไม่งั้นพนักงานจะเห็นแค่ "ไม่มีอะไรเกิดขึ้น" แล้วเดาไม่ถูกว่าทำไม
		reportFatal(api, err)

		return err
	}
	defer reader.Close()

	ctx, stop := signal.NotifyContext(context.Background(), os.Interrupt, syscall.SIGTERM)
	defer stop()

	go heartbeatLoop(ctx, api, reader)

	log.Printf("exapp-card-agent %s — เฝ้าช่องเสียบบัตรอยู่", version)

	var lastID string
	var lastAt time.Time

	for {
		select {
		case <-ctx.Done():
			log.Println("ปิดโปรแกรม")

			return nil
		default:
		}

		name, err := reader.WaitForCard(cardWaitWindow)
		if err != nil || name == "" {
			continue
		}

		data, err := reader.Read(name)
		if err != nil {
			log.Printf("อ่านบัตรไม่สำเร็จ: %v", err)
			_ = api.Heartbeat(ctx, "error", err.Error())

			time.Sleep(time.Second)

			continue
		}

		// บัตรใบเดิมที่ยังเสียบค้างอยู่ไม่ต้องส่งซ้ำทุกห้าวินาที
		if data.CitizenID == lastID && time.Since(lastAt) < sameCardCooldown {
			continue
		}

		if err := api.SendCard(ctx, data); err != nil {
			if errors.Is(err, client.ErrRevoked) {
				return fmt.Errorf("เครื่องนี้ถูกเพิกถอนจากระบบแล้ว — ติดต่อผู้ดูแลเพื่อขอ token ใหม่")
			}

			log.Printf("ส่งข้อมูลไม่สำเร็จ: %v", err)

			continue
		}

		lastID, lastAt = data.CitizenID, time.Now()
		log.Printf("ส่งข้อมูลบัตรขึ้นระบบแล้ว")
	}
}

func heartbeatLoop(ctx context.Context, api *client.Client, reader *card.Reader) {
	ticker := time.NewTicker(heartbeatEvery)
	defer ticker.Stop()

	for {
		status, message := "ready", ""

		if names, err := reader.Readers(); err != nil || len(names) == 0 {
			status, message = "no_reader", "ไม่พบเครื่องอ่านบัตรที่เสียบอยู่"
		}

		if err := api.Heartbeat(ctx, status, message); err != nil {
			log.Printf("ส่งสัญญาณชีพไม่สำเร็จ: %v", err)
		}

		select {
		case <-ctx.Done():
			return
		case <-ticker.C:
		}
	}
}

func reportFatal(api *client.Client, cause error) {
	ctx, cancel := context.WithTimeout(context.Background(), 10*time.Second)
	defer cancel()

	_ = api.Heartbeat(ctx, "no_reader", cause.Error())
}
