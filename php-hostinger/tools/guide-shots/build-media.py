#!/usr/bin/env python3
"""Gộp media*.json của các lượt chụp thành assets/guide-media.js và chép ảnh vào assets (xóa ảnh guide-*.jpg cũ)."""
import glob, json, os, shutil, sys
out, assets = sys.argv[1], sys.argv[2]
order = ['app','telegram','checkin','fullday','checkout','ot','supplement','bookings','availability','tasks','support','payroll','kpi','advances','meetings']
media = {}
for f in sorted(glob.glob(os.path.join(out, 'media-*.json')), key=lambda f: ',' not in f):  # lượt nhiều mục trước, lượt riêng ghi đè
    media.update(json.load(open(f, encoding='utf-8')))
media = {k: media[k] for k in order if k in media} | {k: v for k, v in media.items() if k not in order}
for old in glob.glob(os.path.join(assets, 'guide-*.jpg')):
    os.remove(old)
used = sorted({x['img'] for v in media.values() for x in v})
for img in used:
    shutil.copy(os.path.join(out, img), os.path.join(assets, img))
js = ('// Ảnh động Tìm hiểu web: khung chụp app bằng dữ liệu thử (Nhân viên test, khách demo), box = vị trí vòng đỏ (% ảnh).\n'
      '// Tạo bằng php-hostinger/tools/guide-shots/run.sh; khóa trùng GUIDE_ITEMS trong studio-rules.php, step = số thứ tự bước (từ 0).\n'
      'export const GUIDE_MEDIA=' + json.dumps(media, ensure_ascii=False, separators=(',', ':')) + ';\n')
open(os.path.join(assets, 'guide-media.js'), 'w', encoding='utf-8').write(js)
print(len(used), 'ảnh', round(sum(os.path.getsize(os.path.join(assets, i)) for i in used) / 1024), 'KB', {k: len(v) for k, v in media.items()})
