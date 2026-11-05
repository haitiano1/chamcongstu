#!/usr/bin/env python3
"""Đóng bản cho Cập nhật một chạm. Chạy sau khi chốt code, trước khi commit:
  python3 php-hostinger/tools/build-release.py --note "Thêm gói Body 999k" --manual "Dán lại AnhGauSync.gs vào Apps Script"
Số phiên bản lấy từ dòng vX.Y.Z · dd/mm/yyyy trong assets/app.js. Khóa ký chỉ nằm ở ~/anhgau-data, không đưa lên Git.
"""
import argparse, base64, hashlib, json, re, subprocess, sys
from pathlib import Path

repo = Path(__file__).resolve().parents[2]
ph = repo / 'php-hostinger'
zones = {'private': ph / 'site/nhansu-private', 'public': ph / 'site/public_html/nhansu'}
key = Path.home() / 'anhgau-data/update-signing-key.pem'
rules = zones['private'] / 'lib/rules.php'
# Giống update_allowed() trong lib/update.php.
allowed = {
    'private': re.compile(r'^(lib/[a-z0-9-]+\.php|bin/cron\.php)$'),
    'public': re.compile(r'^((api|index|kiemtra-capnhat)\.php|assets/([A-Za-z0-9_-][A-Za-z0-9._-]*/)*[A-Za-z0-9_-][A-Za-z0-9._-]*\.(js|css|map|png|jpe?g|webp|svg|ico|woff2?|webmanifest))$'),
}

ap = argparse.ArgumentParser()
ap.add_argument('--note', action='append', default=[], help='Dòng "Có gì mới" hiện cho Admin')
ap.add_argument('--manual', action='append', default=[], help='Việc phải làm tay sau khi cập nhật (VD dán lại Apps Script)')
args = ap.parse_args()
if not key.is_file():
    sys.exit(f'Thiếu khóa ký {key}. Chép từ máy cũ; mất khóa thì tạo khóa mới, đổi UPDATE_PUBLIC_KEY trong lib/update.php và upload tay một lần.')

found = re.search(r'v(\d+\.\d+\.\d+) · (\d\d/\d\d/\d{4})', (zones['public'] / 'assets/app.js').read_text(encoding='utf-8'))
if not found:
    sys.exit('Không thấy số phiên bản trong assets/app.js')
version, day = found.groups()
text = rules.read_text(encoding='utf-8')
new = re.sub(r"const APP_RELEASE='[^']*';", f"const APP_RELEASE='{version}';", text)
if "const APP_RELEASE=" not in new:
    sys.exit('Thiếu const APP_RELEASE trong lib/rules.php')
rules.write_text(new, encoding='utf-8')
subprocess.run([sys.executable, str(ph / 'tools/build-diagnostic.py')], check=True)

files = []
for zone, root in zones.items():
    listed = subprocess.run(['git', 'ls-files', '-co', '--exclude-standard', '.'], cwd=root, check=True, capture_output=True, text=True).stdout.split('\n')
    for name in sorted(x for x in listed if x):
        if allowed[zone].match(name) and (root / name).is_file():
            files.append([zone, name, hashlib.sha256((root / name).read_bytes()).hexdigest()])

path, sig = ph / 'release.json', ph / 'release.sig'
old = json.loads(path.read_text(encoding='utf-8')) if path.is_file() else {}
if old.get('version') == version and old.get('files') != files:
    print(f'Cảnh báo: file đã đổi nhưng vẫn là v{version}; nên tăng số phiên bản trong app.js.')
body = json.dumps({'version': version, 'date': day, 'notes': args.note, 'manual': args.manual, 'files': files}, ensure_ascii=False, separators=(',', ':')).encode()
path.write_bytes(body)
raw = subprocess.run(['openssl', 'dgst', '-sha256', '-sign', str(key), str(path)], check=True, capture_output=True).stdout
sig.write_text(base64.b64encode(raw).decode() + '\n')
print(f'Đã đóng v{version}: {len(files)} file, {len(args.note)} ghi chú, {len(args.manual)} việc làm tay.')
