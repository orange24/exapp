#!/bin/bash
# ============================================
# Deploy ExApp to GCP Cloud Run — UAT environment
# ============================================
#
# Clone of deploy-gcp.sh pointed at the UAT service/domain/database.
# Same GCP project, same Artifact Registry repo, same DB host/user/password
# as production — only the service name, domain, database name, and
# APP_KEY secret differ.
#
# Prerequisites (one-time):
#   1. Named-config setup (keeps this project isolated from any other
#      gcloud project/account you use on this machine):
#      gcloud config configurations create exapp
#      gcloud config configurations activate exapp
#      gcloud config set account watcharaster@gmail.com
#      gcloud config set project exchange-app-496416
#   2. Database `cp338215_exapp_uat` created on 163.44.198.71 with
#      user `cp338215_exapp` granted access (done via hosting panel)
#   3. Secret `exapp-uat-app-key` created (already done — see below)
#
# Usage:
#   chmod +x deploy-gcp-uat.sh
#   ./deploy-gcp-uat.sh
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
SERVICE_NAME="exapp-uat"
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
echo "  Deploying ExApp (UAT) to Cloud Run"
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
  --set-env-vars "APP_URL=https://exapp-uat.softernity.com" \
  --set-env-vars "DB_CONNECTION=mysql" \
  --set-env-vars "GOOGLE_CLOUD_PROJECT=$PROJECT_ID" \
  --set-env-vars "SANCTION_SYNC_JOB=exapp-sanctions-sync" \
  --set-env-vars "SANCTION_SYNC_JOB_REGION=$REGION" \
  --set-env-vars "DB_HOST=163.44.198.71" \
  --set-env-vars "DB_PORT=3306" \
  --set-env-vars "DB_DATABASE=cp338215_exapp_uat" \
  --set-env-vars "DB_USERNAME=cp338215_exapp" \
  --set-env-vars "SESSION_DRIVER=database" \
  --set-env-vars "CACHE_STORE=database" \
  --set-env-vars "QUEUE_CONNECTION=database" \
  --set-env-vars "LOG_CHANNEL=stderr" \
  --set-env-vars "HIDDEN_MENUS=" \
  --update-secrets "APP_KEY=exapp-uat-app-key:latest" \
  --update-secrets "DB_PASSWORD=exapp-db-password:latest"

# --- Step 3: Get URL ---
echo ""
URL=$(gcloud run services describe "$SERVICE_NAME" --region "$REGION" --format='value(status.url)')
echo "========================================"
echo "  Deployed successfully!"
echo "  URL: $URL"
echo "========================================"
echo ""
echo "Next steps:"
echo "  1. Map custom domain (one-time):"
echo "     gcloud beta run domain-mappings create --service $SERVICE_NAME --domain exapp-uat.softernity.com --region $REGION"
echo "     Then add the DNS records it prints at your DNS provider for softernity.com."
