#!/usr/bin/env bash
# Chụp lại ảnh động Tìm hiểu web trên bản dev (bash php-hostinger/dev.sh start trước).
#   bash php-hostinger/tools/guide-shots/run.sh            # chụp tất cả
#   bash php-hostinger/tools/guide-shots/run.sh tasks,support   # chỉ chụp lại vài mục
# Nên chạy trong giờ Ca 1 (10:00–13:30): lịch Team 09:30 đã qua giờ, chọn được Làm cả ngày.
# Dữ liệu demo tạo rồi xóa ngay; ảnh chỉ có khách demo + Nhân viên test. Sau khi chụp: xem lại ảnh, tăng 'v' mục đã đổi bước.
set -euo pipefail
HERE="$(cd "$(dirname "$0")" && pwd)"; ROOT="$(cd "$HERE/../.." && pwd)"; ASSETS="$ROOT/site/public_html/nhansu/assets"
NODE="/mnt/c/Program Files/nodejs/node.exe"; OUT="$(mktemp -d "${TMPDIR:-/tmp}/guide-shots.XXXX")"; ONLY="${1:-}"
docker cp "$HERE/fixtures.php" anhgau-dev-web:/tmp/guide-fixtures.php >/dev/null
fx(){ docker exec -u www-data anhgau-dev-web php /tmp/guide-fixtures.php "$1"; }
trap 'fx clean || true; docker exec anhgau-dev-web rm -f /tmp/guide-fixtures.php' EXIT
fx clean; fx setup
cd "$HERE"; W="$(wslpath -w "$OUT")"
MAIN="app,telegram,checkin,fullday,ot,supplement,support,meetings,bookings,tasks,payroll,advances,kpi"
pick(){ if [ -z "$ONLY" ]; then echo "$1"; else python3 -c "import sys;a=set(sys.argv[1].split(','));b=sys.argv[2].split(',');print(','.join(x for x in b if x in a))" "$ONLY" "$1"; fi; }
M="$(pick "$MAIN")"; [ -n "$M" ] && "$NODE" capture.mjs "$W" "$M"
[ -n "$(pick availability)" ] && "$NODE" capture.mjs "$W" availability
if [ -n "$(pick checkout)" ]; then fx checkin; "$NODE" capture.mjs "$W" checkout; fi
if [ -n "$ONLY" ]; then cp -n "$ASSETS"/guide-*.jpg "$OUT"/ 2>/dev/null || true; python3 - "$ASSETS/guide-media.js" "$OUT" <<'PY'
import json,re,sys
s=open(sys.argv[1],encoding='utf-8').read();m=json.loads(re.search(r'GUIDE_MEDIA=(\{.*\});',s).group(1))
json.dump(m,open(sys.argv[2]+'/media-all,old.json','w',encoding='utf-8'),ensure_ascii=False)
PY
fi
python3 "$HERE/build-media.py" "$OUT" "$ASSETS"
echo "Xong. Ảnh tạm ở $OUT — nhớ đổi ?v= của guide-media.js / guide.js trong app.js + index.php."
