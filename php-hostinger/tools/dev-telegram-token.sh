#!/usr/bin/env bash
# Gắn token bot Telegram TEST vào bản dev local (không dùng bot thật của studio).
# Dùng: bash php-hostinger/tools/dev-telegram-token.sh <TOKEN>
set -euo pipefail
cd "$(dirname "$0")/../.."
token="${1:-}"
[[ "$token" =~ ^[0-9]+:[A-Za-z0-9_-]{20,}$ ]] || { echo 'Token không đúng dạng 123456:ABC... (lấy từ @BotFather).' >&2; exit 1; }
env_file="${NHANSU_DEV_DIR:-${XDG_STATE_HOME:-$HOME/.local/state}/anhgau-php-development}/dev.env"
[ -f "$env_file" ] || { echo "Chưa có $env_file. Chạy bash php-hostinger/dev.sh start trước." >&2; exit 1; }
tmp=$(mktemp); grep -v '^NHANSU_TELEGRAM_TOKEN=' "$env_file" > "$tmp" || true
echo "NHANSU_TELEGRAM_TOKEN=$token" >> "$tmp"; cat "$tmp" > "$env_file"; rm -f "$tmp"; chmod 600 "$env_file"
# Tạo lại container web để nhận token mới (dữ liệu nằm trong database, không mất).
docker rm -f anhgau-dev-web >/dev/null 2>&1 || true
bash php-hostinger/dev.sh start
echo 'Xong. Mở web local → Chấm công → Liên kết Telegram để nối tài khoản Admin với bot test.'
