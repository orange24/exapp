#!/usr/bin/env bash
#
# ตั้ง Cloud Scheduler ให้ปลุก Cloud Run Jobs — รันครั้งเดียวตอน setup
# ต้องรัน ./deploy-gcp.sh ให้ job ถูกสร้างก่อน
#
set -euo pipefail

gcloud config configurations activate exapp >/dev/null 2>&1 || {
  echo "ERROR: gcloud configuration 'exapp' not found — ดูหัวข้อ setup ใน deploy-gcp.sh"
  exit 1
}

PROJECT_ID=$(gcloud config get-value project 2>/dev/null)
REGION="asia-southeast1"
SA_NAME="exapp-scheduler"
SA_EMAIL="${SA_NAME}@${PROJECT_ID}.iam.gserviceaccount.com"

if [ -z "$PROJECT_ID" ]; then
  echo "ERROR: No GCP project set."
  exit 1
fi

echo "========================================"
echo "  Setting up Cloud Scheduler"
echo "  Project: $PROJECT_ID"
echo "  Region:  $REGION"
echo "========================================"

# --- 1. เปิด API ที่ต้องใช้ ---
gcloud services enable cloudscheduler.googleapis.com run.googleapis.com

# --- 2. Service account ที่มีสิทธิ์สั่งรัน job ---
if ! gcloud iam service-accounts describe "$SA_EMAIL" >/dev/null 2>&1; then
  echo "Creating service account $SA_EMAIL ..."
  gcloud iam service-accounts create "$SA_NAME" \
    --display-name "ExApp Cloud Scheduler"
fi

gcloud projects add-iam-policy-binding "$PROJECT_ID" \
  --member "serviceAccount:${SA_EMAIL}" \
  --role "roles/run.invoker" \
  --condition=None >/dev/null

# --- 3. สร้าง/อัปเดต scheduler job ---
#
# Cloud Scheduler เรียก Cloud Run Admin API endpoint :run ของแต่ละ job
# ด้วย OAuth token ของ service account ข้างบน
create_schedule() {
  NAME="$1"
  CRON="$2"
  TARGET_JOB="$3"
  DESC="$4"

  URI="https://run.googleapis.com/v2/projects/${PROJECT_ID}/locations/${REGION}/jobs/${TARGET_JOB}:run"

  if gcloud scheduler jobs describe "$NAME" --location "$REGION" >/dev/null 2>&1; then
    ACTION="update"
  else
    ACTION="create"
  fi

  echo ""
  echo "${ACTION} scheduler job: $NAME  ($CRON)"

  gcloud scheduler jobs "$ACTION" http "$NAME" \
    --location "$REGION" \
    --schedule "$CRON" \
    --time-zone "Asia/Bangkok" \
    --uri "$URI" \
    --http-method POST \
    --oauth-service-account-email "$SA_EMAIL" \
    --description "$DESC" \
    --attempt-deadline "1800s"
}

# 02:00 ทุกวัน นอกเวลาราชการ — มารยาทกับ server ของหน่วยงานราชการ
create_schedule "exapp-sanctions-sync" "0 2 * * *" "exapp-sanctions-sync" \
  "ดึงรายชื่อบุคคลที่ถูกกำหนดจากเว็บสาธารณะ ปปง."

# ทุก 5 นาที — ทำให้ bookings:expire กลับมาทำงาน
#
# ไม่ตั้งทุกนาทีเพราะแต่ละครั้งคือการ start container ใหม่ (1,440 ครั้ง/วัน)
# ส่วนการคืน hold ช้าไป 5 นาทีไม่กระทบอะไร — แค่ต้องมีอะไรมาคืนให้เท่านั้น
# `everyMinute()` ใน routes/console.php ไม่ต้องแก้ schedule:run จะรันงานที่ถึงกำหนด
# ทุกครั้งที่ถูกปลุกอยู่แล้ว
create_schedule "exapp-scheduler" "*/5 * * * *" "exapp-scheduler" \
  "Laravel scheduler (bookings:expire ฯลฯ)"

# สัปดาห์ละครั้ง — เตือนล่วงหน้าเมื่อ ปปง. เปลี่ยน layout หน้าเว็บ
create_schedule "exapp-parser-canary" "0 3 * * 1" "exapp-parser-canary" \
  "ตรวจว่า parser ยังอ่านหน้าเว็บ ปปง. ได้"

echo ""
echo "========================================"
echo "  เสร็จแล้ว"
echo "========================================"
echo ""
echo "ตรวจสอบ:"
echo "  gcloud scheduler jobs list --location $REGION"
echo "  gcloud run jobs list --region $REGION"
echo ""
echo "สั่งรันทันทีเพื่อทดสอบ:"
echo "  gcloud scheduler jobs run exapp-sanctions-sync --location $REGION"
echo ""
echo "ดู log:"
echo "  gcloud run jobs executions list --job exapp-sanctions-sync --region $REGION"
