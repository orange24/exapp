#!/bin/bash
# ============================================
# Deploy ExApp to GCP Cloud Run
# ============================================
#
# Prerequisites:
#   1. gcloud CLI installed: https://cloud.google.com/sdk/docs/install
#   2. Login: gcloud auth login
#   3. Set project: gcloud config set project YOUR_PROJECT_ID
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
