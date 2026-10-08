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
	/*
	 * สองวินาที ไม่ใช่สามสิบ
	 *
	 * สัญญาณชีพเป็นทางเดียวที่กุญแจเปิดชิปเดินทางมาถึง agent ได้ ที่สามสิบวินาที
	 * พนักงานถ่ายรูปเสร็จแล้วแตะทันที แต่ agent ยังไม่รู้เรื่อง การแตะจึงเงียบสนิท
	 * แล้วพนักงานจะคิดว่าเครื่องเสีย
	 *
	 * ยี่สิบสาขา = สิบคำขอต่อวินาที ซึ่ง Cloud Run รับสบาย และไม่ได้ทำให้ instance
	 * ตื่นมากกว่าเดิมเพราะสามสิบวินาทีก็ตื่นอยู่แล้ว แลกมาด้วยไฟสถานะที่ไวขึ้นด้วย
	 */
	heartbeatEvery = 2 * time.Second

	// กรอบเวลาที่ยอมให้การรอบัตรค้างได้ก่อนวนมาเช็กสัญญาณปิดโปรแกรม
	// ไม่ใช่จังหวะการถาม — การรอนี้บล็อกจริงจนกว่าจะมีบัตรเสียบ
	cardWaitWindow = 5 * time.Second

	// นานพอให้พนักงานหยิบพาสปอร์ตมาแตะ แต่ไม่นานจนกุญแจที่เซิร์ฟเวอร์หมดอายุก่อน
	passportTapWindow = 100 * time.Second
)

func main() {
	setup := flag.Bool("setup", false, "ตั้งค่าเซิร์ฟเวอร์และ token ครั้งแรก")
	server := flag.String("server", "", "ที่อยู่ exapp เช่น https://exapp.softernity.com")
	token := flag.String("token", "", "token ประจำเครื่องจากหน้าผู้ดูแลระบบ")
	showVersion := flag.Bool("version", false, "แสดงรุ่น")
	listReaders := flag.Bool("list-readers", false, "แสดงเครื่องอ่านที่เสียบอยู่ แล้วออก")
	flag.Parse()

	if *showVersion {
		fmt.Println(version)
		return
	}

	if *listReaders {
		runListReaders()
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

// runListReaders ช่วยวินิจฉัยตอนติดตั้งครั้งแรก
//
// ถ้าไม่ขึ้นชื่อเครื่องอ่าน ปัญหาอยู่ที่สาย ไดรเวอร์ หรือ PC/SC ไม่ใช่ที่ token
// หรือเน็ต — แยกสองเรื่องนี้ออกจากกันได้ก่อนจะไปไล่ผิดทาง
func runListReaders() {
	r, err := card.NewReader()
	if err != nil {
		fmt.Printf("ต่อกับบริการ PC/SC ไม่ได้: %v\n", err)
		os.Exit(1)
	}
	defer r.Close()

	all, err := r.AllReaders()
	if err != nil {
		fmt.Printf("อ่านรายชื่อเครื่องไม่ได้: %v\n", err)
		os.Exit(1)
	}

	if len(all) == 0 {
		fmt.Println("ไม่พบเครื่องอ่านบัตร — ตรวจสายและไดรเวอร์")
		os.Exit(1)
	}

	usable := map[string]bool{}
	for _, n := range card.SelectReaders(all) {
		usable[n] = true
	}

	fmt.Printf("ระบบเห็นช่องทั้งหมด %d ช่อง:\n", len(all))
	for _, n := range all {
		mark := "ข้าม"
		if usable[n] {
			mark = "ใช้ช่องนี้"
		}

		fmt.Printf("  [%-10s] %s\n", mark, n)
	}

	if len(usable) == 0 {
		fmt.Println("\nไม่มีช่องที่ใช้ได้ — เห็นแต่ช่อง SAM ซึ่งไม่ใช่ช่องเสียบบัตรประชาชน")
		os.Exit(1)
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

	keys := make(chan *client.PassportRequest, 1)

	go heartbeatLoop(ctx, api, reader, keys)
	go passportLoop(ctx, api, reader, keys)

	watcher, err := reader.Watch()
	if err != nil {
		reportFatal(api, err)

		return err
	}

	log.Printf("exapp-card-agent %s — เฝ้าช่องเสียบบัตรอยู่", version)

	for {
		select {
		case <-ctx.Done():
			log.Println("ปิดโปรแกรม")

			return nil
		default:
		}

		// อ่านเฉพาะจังหวะที่บัตรถูกเสียบเข้าไป บัตรที่ค้างอยู่จะเงียบ
		// จนกว่าจะถูกดึงออกแล้วเสียบใหม่
		name, err := watcher.WaitForInsertion(cardWaitWindow)
		if err != nil {
			log.Printf("เฝ้าช่องเสียบบัตรไม่สำเร็จ: %v", err)

			continue
		}

		if name == "" {
			continue
		}

		data, err := reader.Read(name)
		if err != nil {
			log.Printf("อ่านบัตรไม่สำเร็จ: %v", err)
			_, _ = api.Heartbeat(ctx, "error", err.Error())

			time.Sleep(time.Second)

			continue
		}

		if err := api.SendCard(ctx, data); err != nil {
			if errors.Is(err, client.ErrRevoked) {
				return fmt.Errorf("เครื่องนี้ถูกเพิกถอนจากระบบแล้ว — ติดต่อผู้ดูแลเพื่อขอ token ใหม่")
			}

			log.Printf("ส่งข้อมูลไม่สำเร็จ: %v", err)

			continue
		}

		log.Printf("ส่งข้อมูลบัตรขึ้นระบบแล้ว")
	}
}

func heartbeatLoop(ctx context.Context, api *client.Client, reader *card.Reader, keys chan<- *client.PassportRequest) {
	ticker := time.NewTicker(heartbeatEvery)
	defer ticker.Stop()

	for {
		status, message := "ready", ""

		if names, err := reader.Readers(); err != nil || len(names) == 0 {
			status, message = "no_reader", "ไม่พบเครื่องอ่านบัตรที่เสียบอยู่"
		}

		req, err := api.Heartbeat(ctx, status, message)
		if err != nil {
			log.Printf("ส่งสัญญาณชีพไม่สำเร็จ: %v", err)
		}

		if req != nil {
			// ไม่บล็อกถ้าตัวอ่านพาสปอร์ตยังทำงานอยู่ — กุญแจใบถัดไปจะมากับ
			// สัญญาณชีพรอบหน้าอยู่แล้ว
			select {
			case keys <- req:
			default:
			}
		}

		select {
		case <-ctx.Done():
			return
		case <-ticker.C:
		}
	}
}

/*
passportLoop เฝ้าช่องไร้สัมผัส เฉพาะตอนมีกุญแจรออยู่

ไม่เฝ้าตลอดเวลาโดยตั้งใจ — บัตรรถไฟฟ้าหรือบัตรเครดิตที่วางใกล้แท่นจะทำให้
เกิดการพยายามอ่านที่ล้มเหลวไม่หยุด และไฟสถานะจะกะพริบแดงทั้งวัน
*/
func passportLoop(ctx context.Context, api *client.Client, reader *card.Reader, keys <-chan *client.PassportRequest) {
	for {
		var req *client.PassportRequest

		select {
		case <-ctx.Done():
			return
		case req = <-keys:
		}

		contactless := reader.Contactless()
		if contactless == nil {
			_ = api.FailPassport(ctx, req.ID, "เครื่องอ่านนี้ไม่มีช่องไร้สัมผัส")

			continue
		}

		readPassport(ctx, api, contactless, req)
	}
}

func readPassport(ctx context.Context, api *client.Client, reader *card.ContactlessReader, req *client.PassportRequest) {
	log.Printf("ได้กุญแจแล้ว — วางพาสปอร์ตบนแท่นได้เลย (คำขอ #%d)", req.ID)

	deadline := time.Now().Add(passportTapWindow)

	for time.Now().Before(deadline) {
		if ctx.Err() != nil {
			return
		}

		tapped, err := reader.WaitForTap(2 * time.Second)
		if err != nil {
			log.Printf("เฝ้าช่องไร้สัมผัสไม่สำเร็จ: %v", err)

			continue
		}

		if !tapped {
			continue
		}

		/*
		 * บอกทันทีที่สัมผัสได้ ก่อนเริ่มคุยกับชิปด้วยซ้ำ
		 *
		 * การอ่านใช้เวลาสองสามวินาที ถ้าหน้าจอยังเงียบอยู่ พนักงานจะคิดว่า
		 * แตะไม่ติดแล้วยกเล่มออกไปลองใหม่ ซึ่งทำให้การอ่านที่กำลังไปได้ดีล้มจริง ๆ
		 */
		_ = api.PassportProgress(ctx, req.ID, "กำลังอ่านบัตร อย่าเพิ่งยกพาสปอร์ตออก")

		data, err := reader.Read(
			card.Key{DocumentNo: req.DocumentNo, DateOfBirth: req.DateOfBirth, ExpiryDate: req.ExpiryDate},
			func(step string) { _ = api.PassportProgress(ctx, req.ID, step) },
		)
		if err != nil {
			// log เก็บต้นฉบับไว้ให้ไล่ปัญหา ส่วนที่ส่งขึ้นไปต้องสั้นและบอกว่าทำอะไรต่อ
			log.Printf("อ่านชิปไม่สำเร็จ: %v", err)
			_ = api.FailPassport(ctx, req.ID, card.DescribeFailure(err))

			return
		}

		if err := api.SendPassport(ctx, req.ID, data); err != nil {
			log.Printf("ส่งข้อมูลจากชิปไม่สำเร็จ: %v", err)

			return
		}

		log.Printf("ส่งข้อมูลจากชิปขึ้นระบบแล้ว")

		return
	}

	// หมดเวลารอ — ลูกค้าอาจไม่ยอมให้แตะ หรือเล่มไม่มีชิป ทำรายการต่อด้วย OCR ได้
	_ = api.FailPassport(ctx, req.ID, "หมดเวลารอ — ไม่มีการแตะพาสปอร์ต")
}

func reportFatal(api *client.Client, cause error) {
	ctx, cancel := context.WithTimeout(context.Background(), 10*time.Second)
	defer cancel()

	_, _ = api.Heartbeat(ctx, "no_reader", cause.Error())
}
