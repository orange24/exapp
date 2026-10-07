#!/bin/bash
# ============================================
# Deploy ExApp to GCP Cloud Run
# ============================================
#
# Prerequisites:
#   1. gcloud CLI installed: https://cloud.google.com/sdk/docs/install
#   2. Login: gcloud auth login watcharaster@gmail.com
#   3. One-time named-config setup (keeps this project isolated from any
#      other gcloud project/account you use on this machine):
#      gcloud config configurations create exapp
#      gcloud config configurations activate exapp
#      gcloud config set account watcharaster@gmail.com
#      gcloud config set project exchange-app-496416
#   4. Enable APIs:
#      gcloud services enable run.googleapis.com artifactregistry.googleapis.com cloudbuild.googleapis.com
#   5. Create Artifact Registry repo (ครั้งแรกเท่านั้น):
#      gcloud artifacts repositories create exapp --repository-format=docker --location=asia-southeast1
#
# Usage:
#   chmod +x deploy-gcp.sh
#   ./deploy-gcp.sh
#
# ============================================

set -e

# Always deploy under the dedicated "exapp" gcloud configuration — isolates
# this project's account/project from any other gcloud project on this
# machine, so you never need to manually gcloud config set anything.
# ล็อก configuration ไว้กับ process นี้เท่านั้น
#
# เดิมใช้ `gcloud config configurations activate exapp` ซึ่งเปลี่ยนสถานะระดับเครื่อง
# มีผลสองทาง และผิดทั้งคู่:
#   - deploy ใช้เวลาหลายนาที ถ้าระหว่างนั้นมีใครสลับ config คำสั่งที่เหลือในสคริปต์
#     จะวิ่งไปโปรเจกต์อื่นกลางคัน (เกิดขึ้นจริง: prod deploy ไปโผล่ prj-ecommerce-prod)
#   - สคริปต์ไปกระชาก config ของ terminal อื่นที่กำลังทำงานอยู่ด้วย
#
# ตัวแปรนี้มีผลเฉพาะ process ลูกของสคริปต์ ใครสลับ config ข้างนอกก็ไม่กระทบ
export CLOUDSDK_ACTIVE_CONFIG_NAME=exapp

if ! gcloud config configurations describe exapp >/dev/null 2>&1; then
  echo "ERROR: ไม่พบ gcloud configuration ชื่อ exapp — สร้างก่อนด้วย:"
  echo "  gcloud config configurations create exapp"
  echo "  gcloud config set account watcharaster@gmail.com"
  echo "  gcloud config set project exchange-app-496416"
  exit 1
fi

# --- Configuration ---
PROJECT_ID=$(gcloud config get-value project 2>/dev/null)
REGION="asia-southeast1"          # Singapore (ใกล้ไทย)
SERVICE_NAME="exapp"
REPO_NAME="php-exapp-repo"
IMAGE="$REGION-docker.pkg.dev/$PROJECT_ID/$REPO_NAME/$SERVICE_NAME"

if [ -z "$PROJECT_ID" ]; then
  echo "ERROR: No GCP project set. Run: gcloud config set project YOUR_PROJECT_ID"
  exit 1
fi

# --- ยามกันปลายทางผิด ---
# ตรวจทั้งโปรเจกต์และบัญชี ยอมหยุดดีกว่า deploy ผิดที่
EXPECTED_PROJECT="exchange-app-496416"
EXPECTED_ACCOUNT="watcharaster@gmail.com"
ACTIVE_ACCOUNT=$(gcloud config get-value account 2>/dev/null)
if [ "$PROJECT_ID" != "$EXPECTED_PROJECT" ] || [ "$ACTIVE_ACCOUNT" != "$EXPECTED_ACCOUNT" ]; then
  echo ""
  echo "ERROR: ปลายทางไม่ถูกต้อง — ยกเลิก deploy"
  echo "  โปรเจกต์ คาดหวัง: $EXPECTED_PROJECT  ได้จริง: $PROJECT_ID"
  echo "  บัญชี    คาดหวัง: $EXPECTED_ACCOUNT  ได้จริง: $ACTIVE_ACCOUNT"
  exit 1
fi

echo "========================================"
echo "  Deploying ExApp to Cloud Run"
echo "  Project: $PROJECT_ID"
echo "  Region:  $REGION"
echo "  Image:   $IMAGE"
echo "========================================"

# --- Step 1: Build & Push using Cloud Build ---
echo ""
echo "Building image with Cloud Build..."
gcloud builds submit --tag "$IMAGE" .

# --- Step 2: Deploy to Cloud Run ---
echo ""
echo "Deploying to Cloud Run..."
gcloud run deploy "$SERVICE_NAME" \
  --image "$IMAGE" \
  --region "$REGION" \
  --platform managed \
  --allow-unauthenticated \
  --port 8080 \
  --memory 512Mi \
  --cpu 1 \
  --min-instances 0 \
  --max-instances 5 \
  --timeout 300 \
  --cpu-boost \
  --set-env-vars "APP_ENV=production" \
  --set-env-vars "APP_DEBUG=false" \
  --set-env-vars "APP_URL=https://exapp.softernity.com" \
  --set-env-vars "DB_CONNECTION=mysql" \
  --set-env-vars "GOOGLE_CLOUD_PROJECT=$PROJECT_ID" \
  --set-env-vars "SANCTION_SYNC_JOB=exapp-sanctions-sync" \
  --set-env-vars "SANCTION_SYNC_JOB_REGION=$REGION" \
  --set-env-vars "DB_HOST=163.44.198.71" \
  --set-env-vars "DB_PORT=3306" \
  --set-env-vars "DB_DATABASE=cp338215_exapp" \
  --set-env-vars "DB_USERNAME=cp338215_exapp" \
  --set-env-vars "SESSION_DRIVER=database" \
  --set-env-vars "CACHE_STORE=database" \
  --set-env-vars "QUEUE_CONNECTION=database" \
  --set-env-vars "LOG_CHANNEL=stderr" \
  --set-env-vars "HIDDEN_MENUS=" \
  --update-secrets "APP_KEY=exapp-app-key:latest" \
  --update-secrets "DB_PASSWORD=exapp-db-password:latest"

# --- Step 2.5: Deploy Cloud Run Jobs ---
#
# ใช้ Job ไม่ใช่ HTTP endpoint เพราะ:
#   1. Cloud Run service มีเพดาน request timeout 60 นาที
#      ส่วน sanctions:sync ครั้งแรกใช้ ~17 นาที/ลิสต์ และอาจนานกว่านั้น
#   2. การยิง HTTP เข้า service เดียวกับที่ลูกค้าใช้ จะไปแย่ง instance
#      กับหน้าเคาน์เตอร์ตอนกลางคืนที่สาขาสนามบินยังเปิด
#
# Job ใช้ image เดียวกับ service — ไม่ต้อง build ใหม่
deploy_job() {
  JOB_NAME="$1"
  TASK_TIMEOUT="$2"
  shift 2

  echo ""
  echo "Deploying Cloud Run Job: $JOB_NAME"

  # jobs deploy = create ถ้ายังไม่มี, update ถ้ามีแล้ว
  gcloud run jobs deploy "$JOB_NAME" \
    --image "$IMAGE" \
    --region "$REGION" \
    --command "/job-entrypoint.sh" \
    --args "$(IFS=, ; echo "$*")" \
    --task-timeout "$TASK_TIMEOUT" \
    --max-retries 1 \
    --memory 512Mi \
    --cpu 1 \
    --set-env-vars "APP_ENV=production" \
    --set-env-vars "APP_DEBUG=false" \
    --set-env-vars "APP_URL=https://exapp.softernity.com" \
    --set-env-vars "DB_CONNECTION=mysql" \
    --set-env-vars "DB_HOST=163.44.198.71" \
    --set-env-vars "DB_PORT=3306" \
    --set-env-vars "DB_DATABASE=cp338215_exapp" \
    --set-env-vars "DB_USERNAME=cp338215_exapp" \
    --set-env-vars "SESSION_DRIVER=database" \
    --set-env-vars "CACHE_STORE=database" \
    --set-env-vars "QUEUE_CONNECTION=database" \
    --set-env-vars "LOG_CHANNEL=stderr" \
    --set-env-vars "SANCTION_SYNC_CONTACT_EMAIL=${SANCTION_SYNC_CONTACT_EMAIL:-}" \
    --update-secrets "APP_KEY=exapp-app-key:latest" \
    --update-secrets "DB_PASSWORD=exapp-db-password:latest"
}

# sync รายชื่อ ปปง. — ครั้งแรก ~17 นาที/ลิสต์ ให้เวลาเหลือเฟือ
deploy_job "exapp-sanctions-sync" "3600s" php artisan sanctions:sync --list=all

# Laravel scheduler — ทำให้ bookings:expire กลับมาทำงาน
deploy_job "exapp-scheduler" "600s" php artisan schedule:run

# canary ตรวจว่า parser ยังอ่านหน้าเว็บ ปปง. ได้
deploy_job "exapp-parser-canary" "600s" php artisan sanctions:verify-parser

# --- Step 3: Get URL ---
echo ""
URL=$(gcloud run services describe "$SERVICE_NAME" --region "$REGION" --format='value(status.url)')
echo "========================================"
echo "  Deployed successfully!"
echo "  URL: $URL"
echo "========================================"
echo ""
echo "Next steps:"
echo "  1. Update APP_URL:"
echo "     gcloud run services update $SERVICE_NAME --region $REGION --set-env-vars APP_URL=$URL"
echo "  2. Store secrets (ครั้งแรก):"
echo "     echo -n 'base64:qOx...' | gcloud secrets create exapp-app-key --data-file=-"
echo "     echo -n 'a8&P;dw86TD\$' | gcloud secrets create exapp-db-password --data-file=-"
echo "  3. ตั้ง Cloud Scheduler (ครั้งแรกครั้งเดียว):"
echo "     ./scripts/setup-cloud-scheduler.sh"
echo "  4. ตั้งอีเมลติดต่อใน User-Agent ที่ยิงไปหา ปปง.:"
echo "     export SANCTION_SYNC_CONTACT_EMAIL=admin@yourshop.co.th แล้ว deploy ใหม่"
