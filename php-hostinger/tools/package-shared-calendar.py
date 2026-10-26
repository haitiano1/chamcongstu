"""File-only update bundles: upload into existing directories, never rename/replace folders."""
from pathlib import Path
from zipfile import ZipFile, ZIP_DEFLATED, ZipInfo
from datetime import datetime
import runpy,shutil,hashlib
root=Path(__file__).resolve().parents[1];out=Path('/workspace/exports/lich-chung-20261025');out.mkdir(parents=True,exist_ok=True)
runpy.run_path(str(root/'tools/build-diagnostic.py'))
public=root/'site/public_html/nhansu';private=root/'site/nhansu-private'
packages=[('1-private-lib.zip',private/'lib',['people-finance.php','bookings.php','workflows.php','telegram.php']),('2-public-assets.zip',public/'assets',['app.js','bookings.js','roster.js','people-finance.js','studio-journal.css']),('3-public-root.zip',public,['index.php','kiemtra-capnhat.php'])]
manifest=[]
for name,folder,files in packages:
 with ZipFile(out/name,'w',ZIP_DEFLATED) as z:
  for file in files:
   assert '/' not in file and file not in ['config.php']
   raw=(folder/file).read_bytes();info=ZipInfo(file,date_time=datetime.now().timetuple()[:6]);info.compress_type=ZIP_DEFLATED;info.create_system=3;info.external_attr=0o100644<<16;z.writestr(info,raw)
   manifest.append(f'{name} / {file}  {hashlib.sha256(raw).hexdigest()}')
  assert z.testzip() is None
shutil.copyfile(root/'UPDATE-SHARED-CALENDAR-2026-10-25.md',out/'HUONG-DAN-CAP-NHAT.md')
(out/'SHA256.txt').write_text('\n'.join(manifest)+'\n')
# A single download containing the three flat ZIPs and plain-text directions.
with ZipFile(out/'Anh-Gau-Studio-Lich-chung-20261025.zip','w',ZIP_DEFLATED) as z:
 for name in ['1-private-lib.zip','2-public-assets.zip','3-public-root.zip','HUONG-DAN-CAP-NHAT.md','SHA256.txt']:z.write(out/name,name)
 assert z.testzip() is None
for name,_,files in packages:print(name,', '.join(files))
print('Ready:',out/'Anh-Gau-Studio-Lich-chung-20261025.zip')
