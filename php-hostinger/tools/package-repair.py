from pathlib import Path
from zipfile import ZipFile,ZIP_DEFLATED,ZipInfo
import hashlib,runpy,shutil
from datetime import datetime
root=Path(__file__).resolve().parents[2];out=Path('/workspace/exports/sua-loi-500-20261024');out.mkdir(parents=True,exist_ok=True)
runpy.run_path(str(root/'php-hostinger/tools/build-diagnostic.py'))
public=root/'php-hostinger/site/public_html/nhansu';private=root/'php-hostinger/site/nhansu-private'
files_public=['api.php','index.php','kiemtra-capnhat.php']+sorted(p.relative_to(public).as_posix() for p in (public/'assets').rglob('*') if p.is_file())
files_private=sorted(p.relative_to(private).as_posix() for directory in ['lib','bin'] for p in (private/directory).rglob('*') if p.is_file() and p.suffix in ['.php','.json','.pdf'])
for filename,folder,names in [('sua-loi-500-private.zip',private,files_private),('sua-loi-500-public.zip',public,files_public)]:
 with ZipFile(out/filename,'w',ZIP_DEFLATED) as z:
  for name in names:
   info=ZipInfo(name,date_time=datetime.now().timetuple()[:6]);info.compress_type=ZIP_DEFLATED;info.create_system=3;info.external_attr=0o100644<<16;z.writestr(info,(folder/name).read_bytes())
  assert z.testzip() is None
  assert not any('storage/' in n or n.endswith('config.php') or n.startswith('wp-') or '..' in n.split('/') for n in z.namelist())
shutil.copyfile(root/'php-hostinger/REPAIR-500-2026-10-24.md',out/'HUONG-DAN-SUA-LOI-500.md')
for f in out.glob('*.zip'):print(f.name,f.stat().st_size,'bytes',hashlib.sha256(f.read_bytes()).hexdigest())
