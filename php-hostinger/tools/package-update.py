from pathlib import Path
from zipfile import ZipFile,ZIP_DEFLATED
import hashlib,shutil,runpy
root=Path(__file__).resolve().parents[2];dest=Path('/workspace/exports/cap-nhat-20261024');dest.mkdir(parents=True,exist_ok=True)
public=root/'php-hostinger/site/public_html/nhansu';private=root/'php-hostinger/site/nhansu-private'
runpy.run_path(str(root/'php-hostinger/tools/build-diagnostic.py'))
public_files=['api.php','index.php','kiemtra-capnhat.php']+sorted(p.relative_to(public).as_posix() for p in (public/'assets').rglob('*') if p.is_file())
private_files=sorted(p.relative_to(private).as_posix() for folder in ['lib','bin'] for p in (private/folder).rglob('*') if p.is_file() and p.suffix in ['.php','.json','.pdf'])
for name,folder,files in [('cap-nhat-private-20261024.zip',private,private_files),('cap-nhat-public-20261024.zip',public,public_files)]:
 with ZipFile(dest/name,'w',ZIP_DEFLATED) as z:
  for relative in files:z.write(folder/relative,relative)
  assert z.testzip() is None
  assert not any('storage/' in f or f.endswith('config.php') or f.startswith('wp-') or '..' in f.split('/') for f in z.namelist())
shutil.copyfile(root/'php-hostinger/UPDATE-2026-10-24.md',dest/'HUONG-DAN-CAP-NHAT.md')
shutil.copyfile(root/'php-hostinger/google-sheets/AnhGauSync.gs',dest/'AnhGauSync.gs')
shutil.copyfile(root/'php-hostinger/google-sheets/AnhGauSync.gs',dest/'AnhGauSync.txt')
with ZipFile(dest/'anh-gau-studio-update-20261024.zip','w',ZIP_DEFLATED) as z:
 for name in ['cap-nhat-private-20261024.zip','cap-nhat-public-20261024.zip','HUONG-DAN-CAP-NHAT.md','AnhGauSync.gs','AnhGauSync.txt']:z.write(dest/name,name)
for f in sorted(dest.glob('*.zip')):print(f.name,f.stat().st_size,'bytes',hashlib.sha256(f.read_bytes()).hexdigest())
