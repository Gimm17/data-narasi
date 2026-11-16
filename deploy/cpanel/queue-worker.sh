#!/usr/bin/env bash
#
# Worker queue untuk shared hosting cPanel.
#
# Dipanggil cron setiap menit. flock -n memastikan hanya satu worker berjalan
# sehingga job tidak diproses dua kali. --max-time membuat worker berhenti
# sendiri; Laravel memeriksa batas ini ANTAR job, jadi job yang sedang jalan
# tetap diselesaikan sampai --timeout.
#
# Batas waktu yang harus dijaga:
#     timeout job (230)  <  retry_after queue (300)
# Bila terbalik, job yang masih berjalan akan diambil ulang dan diproses
# dua kali — panggilan AI dan biayanya ikut berlipat.
#
# Pemakaian:
#   ./queue-worker.sh                 (default)
#   CPANEL_PHP_BIN=/usr/local/bin/php ./queue-worker.sh

set -euo pipefail

APP_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/../.." && pwd -P)"
PHP_BIN="${CPANEL_PHP_BIN:-/opt/alt/php84/usr/bin/php}"

case "$PHP_BIN" in
    /*) ;;
    *)  PHP_BIN="$(command -v "$PHP_BIN" || echo "$PHP_BIN")" ;;
esac

LOG_DIR="$APP_DIR/storage/logs"
LOG_FILE="$LOG_DIR/queue-worker.log"
LOCK_FILE="$APP_DIR/storage/framework/queue.lock"

mkdir -p "$LOG_DIR" "$APP_DIR/storage/framework"

# Jaga ukuran log agar tidak memenuhi kuota shared hosting
if [ -f "$LOG_FILE" ] && [ "$(wc -c < "$LOG_FILE")" -gt 5242880 ]; then
    mv -f "$LOG_FILE" "$LOG_FILE.1"
fi

cd -- "$APP_DIR"

{
    echo "--- $(date '+%Y-%m-%d %H:%M:%S') mulai worker ---"
    /usr/bin/flock -n "$LOCK_FILE" \
        "$PHP_BIN" -d memory_limit=512M "$APP_DIR/artisan" \
        queue:work database \
        --sleep=3 \
        --tries=1 \
        --timeout=230 \
        --memory=384 \
        --max-time=58 \
        2>&1
    echo "--- $(date '+%Y-%m-%d %H:%M:%S') selesai (exit $?) ---"
} >> "$LOG_FILE" 2>&1
