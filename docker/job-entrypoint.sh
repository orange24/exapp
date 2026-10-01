#!/bin/sh
set -e

cd /var/www/html

# ต้อง cache config ตอน runtime เพราะ env var มาจาก Cloud Run ไม่ได้อยู่ในอิมเมจ
php artisan config:cache

# ไม่รัน migrate ที่นี่ — service entrypoint ทำให้แล้วตอน deploy
# ถ้ารันซ้ำทุกครั้งที่ job ถูกปลุก จะกลายเป็นวันละหลายร้อยครั้งโดยไม่จำเป็น

exec "$@"
