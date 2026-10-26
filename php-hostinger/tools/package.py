from pathlib import Path
from zipfile import ZipFile, ZIP_DEFLATED
import hashlib, shutil, sys
root=Path(__file__).resolve().parents[2]
output=Path(sys.argv[1]) if len(sys.argv)>1 else Path('/workspace/exports/hostinger')
output.mkdir(parents=True,exist_ok=True)
public=root/'php-hostinger/site/public_html/nhansu';private=root/'php-hostinger/site/nhansu-private'
with ZipFile(output/'nhansu-public.zip','w',ZIP_DEFLATED) as z:
 for f in sorted(public.rglob('*')):
  if f.is_file():z.write(f,f.relative_to(public).as_posix())
with ZipFile(output/'nhansu-private.zip','w',ZIP_DEFLATED) as z:
 for relative in ['config.example.php','.htaccess',*[p.relative_to(private).as_posix() for d in ['lib','bin'] for p in (private/d).rglob('*.php')]]:
  z.write(private/relative,'nhansu-private/'+relative)
shutil.copyfile(root/'php-hostinger/INSTALL-HOSTINGER.md',output/'HUONG-DAN-CAI-DAT.md')
shutil.copyfile(root/'php-hostinger/tools/export-from-node.mjs',output/'export-from-node.mjs')
with ZipFile(output/'anh-gau-studio-hostinger.zip','w',ZIP_DEFLATED) as z:
 for name in ['nhansu-public.zip','nhansu-private.zip','HUONG-DAN-CAI-DAT.md','export-from-node.mjs']:z.write(output/name,name)
for name in ['nhansu-public.zip','nhansu-private.zip','anh-gau-studio-hostinger.zip']:
 f=output/name
 print(name, f.stat().st_size,'bytes', 'sha256='+hashlib.sha256(f.read_bytes()).hexdigest())
