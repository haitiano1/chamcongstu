from pathlib import Path
import tempfile,shutil,subprocess,json,os
root=Path(__file__).resolve().parents[2]
source=root/'php-hostinger/site'
run=Path(tempfile.mkdtemp(prefix='nhansu-api-startup-',dir='/tmp'));checks=0

def fixture(name,api_source=None):
 path=run/name;(path/'private/lib').mkdir(parents=True)
 for f in (source/'nhansu-private/lib').glob('*.php'):shutil.copyfile(f,path/'private/lib'/f.name)
 shutil.copyfile(api_source or source/'public_html/nhansu/api.php',path/'api.php')
 for p in [path,*path.rglob('*')]:p.chmod(0o755 if p.is_dir() else 0o644)
 return path

def call(path,user=None):
 args=['docker','run','--rm','--network','none','-e','APP_PRIVATE_DIR=/fixture/private','-v',str(path)+':/fixture:ro']
 if user:args+=['--user',user]
 args+=['anhgau-php-test:local','php','/fixture/api.php']
 return subprocess.run(args,capture_output=True,text=True)

def response(case,pattern,user=None):
 global checks
 result=call(case,user)
 try:data=json.loads(result.stdout)
 except Exception:raise AssertionError(case.name+': missing JSON; stdout='+repr(result.stdout))
 assert pattern in data.get('error',''),(case.name,data)
 assert '/fixture' not in result.stdout and 'DO_NOT_EXPOSE' not in result.stdout
 checks+=1
 return data

old=fixture('old_missing','/tmp/nhansu-api-before-startup-fix.php');(old/'private/lib/bookings.php').unlink();old_result=call(old)
assert not old_result.stdout.strip() and old_result.returncode!=0,'Original failure not reproduced';checks+=1
missing_rules=fixture('missing_rules');(missing_rules/'private/lib/rules.php').unlink();response(missing_rules,'lib/rules.php')
missing=fixture('missing');(missing/'private/lib/bookings.php').unlink();response(missing,'lib/bookings.php')
blank=fixture('blank');(blank/'private/lib/bookings.php').write_text('');response(blank,'lib/bookings.php')
corrupt=fixture('corrupt');(corrupt/'private/lib/bookings.php').write_text('<?php function DO_NOT_EXPOSE( {');data=response(corrupt,'lib/bookings.php');assert data.get('reference');checks+=1
unreadable=fixture('unreadable');(unreadable/'private/lib/bookings.php').chmod(0);run.chmod(0o755);response(unreadable,'lib/bookings.php','www-data')
config=fixture('missing_config');response(config,'Chưa cấu hình')
bad_config=fixture('bad_config');(bad_config/'private/config.php').write_text('<?php return [; /* DO_NOT_EXPOSE */');response(bad_config,'lỗi PHP')
plain_config=fixture('plain_config');(plain_config/'private/config.php').write_text('DO_NOT_EXPOSE');response(plain_config,'lỗi PHP')
bom=fixture('library_output');(bom/'private/lib/bookings.php').write_text('DO_NOT_EXPOSE'+(bom/'private/lib/bookings.php').read_text());response(bom,'lib/bookings.php')
extra=fixture('extra_output');(extra/'private/lib/bookings.php').write_text((extra/'private/lib/bookings.php').read_text()+'\necho "DO_NOT_EXPOSE";');response(extra,'nội dung thừa')
fatal=fixture('fatal');(fatal/'private/lib/bookings.php').write_text('<?php trigger_error("DO_NOT_EXPOSE",E_USER_ERROR);');response(fatal,'lib/bookings.php')
print('PASS',checks,'startup failure checks; original empty-500 failure reproduced; new guard returns sanitized JSON without network/database access')
shutil.rmtree(run)
