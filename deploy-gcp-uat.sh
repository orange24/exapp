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
#   1. gcloud config set account watcharaster@gmail.com
#   2. gcloud config set project exchange-app-496416
#   3. Database `cp338215_exapp_uat` created on 163.44.198.71 with
#      user `cp338215_exapp` granted access (done via hosting panel)
#   4. Secret `exapp-uat-app-key` created (already done — see below)
#
# Usage:
#   chmod +x deploy-gcp-uat.sh
#   ./deploy-gcp-uat.sh
#
# ============================================

set -e

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
