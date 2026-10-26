from pathlib import Path
from zipfile import ZipFile
import tempfile,shutil,subprocess,json,hashlib
root=Path(__file__).resolve().parents[2];exports=Path('/workspace/exports/sua-loi-500-20261024')
stage=Path(tempfile.mkdtemp(prefix='nhansu-repair-layout-',dir='/tmp'));private=stage/'private';public=stage/'public';private.mkdir();public.mkdir()
# Reproduce the user's directory layout: old lib renamed aside, incomplete new lib in place.
with ZipFile('/workspace/exports/hostinger/nhansu-private.zip') as z:
 for name in z.namelist():
  if name.startswith('nhansu-private/lib/'):
   dest=private/'lib.3523'/Path(name).name;dest.parent.mkdir(exist_ok=True);dest.write_bytes(z.read(name))
(private/'lib').mkdir()
for name in ['bootstrap.php','schema.php','api.php','workflows.php','telegram.php','transfer.php','people-finance.php','bookings.php']:
 shutil.copyfile(root/'php-hostinger/site/nhansu-private/lib'/name,private/'lib'/name)
shutil.copyfile(root/'php-hostinger/site/nhansu-private/config.example.php',private/'config.php');config_hash=hashlib.sha256((private/'config.php').read_bytes()).hexdigest()
(private/'storage').mkdir();(private/'storage/backups').mkdir();(private/'storage/cache').mkdir();(private/'storage/user-owned-sentinel').write_text('preserve-storage')
for zipname,dest in [('sua-loi-500-private.zip',private),('sua-loi-500-public.zip',public)]:
 with ZipFile(exports/zipname) as z:
  assert z.testzip() is None;z.extractall(dest)
assert (private/'lib/rules.php').is_file() and (public/'assets/paths.js').is_file()
assert (public/'assets/vendor/leaflet.js').is_file()
assert config_hash==hashlib.sha256((private/'config.php').read_bytes()).hexdigest()
assert (private/'storage/user-owned-sentinel').read_text()=='preserve-storage'
assert (private/'lib.3523/rules.php').is_file()
args=['docker','run','--rm','--network','nhansu-php-test','--env-file','/tmp/nhansu-test.env','-e','APP_PRIVATE_DIR=/fixture/private','-v',str(stage)+':/fixture:ro','anhgau-php-test:local','php','-r','$_GET["route"]="/status";$_SERVER["REQUEST_METHOD"]="GET";require "/fixture/public/api.php";']
result=subprocess.run(args,capture_output=True,text=True);data=json.loads(result.stdout);assert result.returncode==0 and data.get('setup') is False,(result.returncode,data)
print('PASS recovery package simulation: renamed old lib + incomplete new lib repaired; config/storage/old backup preserved; actual PHP API returns setup=false')
shutil.rmtree(stage)
