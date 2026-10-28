# ย้ายเครื่อง / ตั้งเครื่องใหม่

บันทึกว่าอะไรอยู่ใน git อะไรไม่อยู่ และต้องหอบอะไรไปบ้างเวลาย้ายเครื่อง

> **สรุปสั้นที่สุด:** copy โฟลเดอร์โปรเจกต์อย่างเดียว **ไม่พอ** — ความจำของ
> Claude อยู่นอกโฟลเดอร์ ต้อง copy อีกที่หนึ่งด้วย (ดูหัวข้อ 3)

---

## 1. อะไรอยู่ใน git

ทุกอย่างที่เป็นซอร์สโค้ดของระบบ:

```
app/  bootstrap/  config/  database/  resources/  routes/  tests/
public/  agent/ (ซอร์ส Go, ไม่รวมไบนารี)  scripts/  docker/
composer.json  composer.lock  package.json  phpunit.xml
Dockerfile  docker-compose.yml  deploy-gcp.sh  deploy-gcp-uat.sh
MOVING-MACHINES.md (ไฟล์นี้)
```

remote: `Exapp` → `git@github.com:orange24/exapp.git` (ใช้ SSH ไม่ใช่ token)
branch หลักคือ **`master`** — push ตรง ไม่ใช้ PR

```bash
git push Exapp master
```

---

## 2. อะไร **ไม่** อยู่ใน git (แต่อยู่ในโฟลเดอร์)

ดูรายการเต็มได้ที่ `.gitignore` — ที่สำคัญคือ:

| ไฟล์/โฟลเดอร์ | คืออะไร | ย้ายเครื่องแล้วทำยังไง |
|---|---|---|
| **`CLAUDE.md`** | คำสั่งโปรเจกต์ที่ Claude อ่านทุกเซสชัน | copy ไปด้วย ไม่งั้น Claude ไม่รู้ stack/DB/กฎของโปรเจกต์ |
| **`.claude/`** | skills (`deploy`, `feature-map`), agents 6 ตัว, permission ที่อนุญาตไว้ | copy ไปด้วย |
| **`docs/`** | wireframes 6 โมดูล, แผน multi-company, inventory-flow, cloud-jobs | copy ไปด้วย |
| **`.env`** | **รหัสผ่าน DB + APP_KEY** | copy ไปด้วย — **ห้าม commit ขึ้น git เด็ดขาด** |
| `reference/` | ระบบเดิม Drupal 7 ไว้เทียบสเปก | copy ถ้ายังต้องใช้ |
| `vendor/`, `node_modules/` | dependency | ไม่ต้อง copy ก็ได้ — รัน `composer install` เอาใหม่เร็วกว่า |
| `agent/dist/`, ไบนารี agent | ไฟล์ build | ไม่ต้อง copy — โหลดจาก GitHub Release ได้ |

ถ้า **copy ทั้งโฟลเดอร์** ของพวกนี้ติดไปเองหมด ไม่ต้องทำอะไรเพิ่ม

---

## 3. อะไรอยู่ **นอก** โฟลเดอร์ — จุดที่พลาดกันบ่อย

ความจำอัตโนมัติของ Claude ไม่ได้อยู่ในโฟลเดอร์โปรเจกต์:

```
~/.claude/projects/-Volumes-Work-PHP-src-exapp-src-exapp/memory/
```

20 ไฟล์ ~100K เก็บสิ่งที่ไม่มีใน repo เช่น

- คุณเลือก "เตือนแต่ทำต่อได้" เสมอ อย่าใส่ hard block ที่หยุดงานหน้าเคาน์เตอร์
- migration ต้องรันได้ทั้งบน MySQL เก่าและ sqlite — ห้ามใช้ `Schema::hasColumn()`
- `.env` สลับไปมาระหว่าง DB prod กับ UAT ต้องเช็คทุกเซสชัน
- prod ถูกยุบเหลือสาขาเดียวเมื่อ 2026-09-09
- กฎธุรกิจเงินบาทเป็นสต็อก, สเปกระบบเดิม, รายละเอียด Cloud Run

**คำสั่ง copy ไปเครื่องใหม่** (ชื่อโฟลเดอร์มาจาก path ของโปรเจกต์ ถ้าเครื่องใหม่
วางโปรเจกต์ไว้ path เดิม ชื่อนี้จะตรงกันพอดี):

```bash
# เครื่องเก่า
cd ~/.claude/projects
tar czf ~/Desktop/claude-memory-exapp.tgz -Volumes-Work-PHP-src-exapp-src-exapp

# เครื่องใหม่ — วางโปรเจกต์ไว้ path เดิมก่อน แล้วค่อยแตก
mkdir -p ~/.claude/projects
tar xzf ~/Desktop/claude-memory-exapp.tgz -C ~/.claude/projects
```

ถ้าเครื่องใหม่วางโปรเจกต์ไว้ **path อื่น** ต้องเปลี่ยนชื่อโฟลเดอร์ให้ตรง path
ใหม่ (เอา `/` เป็น `-`) เช่น `/Users/me/code/exapp` → `-Users-me-code-exapp`

---

## 4. ตั้งเครื่องใหม่ให้รันได้

```bash
git clone git@github.com:orange24/exapp.git
cd exapp

# เอาของที่ไม่อยู่ใน git มาวาง: CLAUDE.md, .claude/, docs/, .env
composer install

php artisan migrate        # ชี้ DB ตาม .env — เช็ค DB_DATABASE ก่อนว่า prod หรือ uat
php artisan test           # ต้องผ่านทั้งหมด

/opt/homebrew/bin/php artisan serve --port=8001
```

**ต้องใช้ PHP 8.2+ จาก Homebrew** (`/opt/homebrew/bin/php`) ห้ามใช้ PHP ของ XAMPP
ซึ่งเป็น 7.2

gcloud สำหรับ deploy ใช้ configuration ชื่อ `exapp` แยกจากโปรเจกต์อื่นบนเครื่อง:

```bash
gcloud config configurations create exapp
gcloud config set account watcharaster@gmail.com
gcloud config set project exchange-app-496416
```

---

## 5. seeder ที่ต้องรันตอน deploy (migration อย่างเดียวไม่พอ)

เมนูเก็บเป็นแถวใน DB ไม่ใช่ในโค้ด — เพิ่มเมนูใหม่แล้วต้อง seed ด้วย ไม่งั้น
ไม่มีใครเห็นเมนูนั้น:

```bash
php artisan db:seed --class=CardReaderMenuSeeder --force   # เครื่องอ่านบัตร ปชช.
php artisan db:seed --class=CompanyMenuSeeder --force      # จัดการบริษัท
```

ทั้งคู่รันซ้ำได้ ไม่ลบข้อมูล

---

## 6. ข้อควรระวัง

- **`.env` ห้ามขึ้น git** — มีรหัสผ่าน DB อยู่ `.gitignore` กันไว้แล้ว อย่าไป `git add -f`
- **`deploy-gcp.sh` พ่นรหัสผ่าน DB เป็น plaintext** ในส่วน "Next steps" ตอนจบ
  ระวังตอนแชร์ log
- **อย่ารัน `./deploy-gcp.sh` (prod) โดยไม่ได้ไฟเขียวเป็นรายครั้ง** — ทดสอบที่
  local เองก่อนเสมอ
- prod DB คือ `cp338215_exapp` ส่วน UAT คือ `cp338215_exapp_uat` ทั้งคู่อยู่
  เซิร์ฟเวอร์เดียวกัน (`163.44.198.71`) — **เช็ค `DB_DATABASE` ใน `.env` ก่อนรัน
  migration หรือ seeder ทุกครั้ง**
