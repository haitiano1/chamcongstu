#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/../.."
# Dedicated disposable fixture. Never set production database variables here.
python3 - "$PWD" <<'PY'
from pathlib import Path
import secrets,shutil,sys
root=Path(sys.argv[1]);run=Path('/tmp/nhansu-php-fixture');run.mkdir(exist_ok=True)
shutil.copytree(root/'php-hostinger/tests/fixtures/wordpress-root',run/'public',dirs_exist_ok=True)
(run/'public/nhansu').mkdir(exist_ok=True)
for p in [run,run/'public',*(run/'public').iterdir()]:p.chmod(0o755 if p.is_dir() else 0o644)
f=Path('/tmp/nhansu-test.env')
if not f.exists():
 pwd=secrets.token_hex(24)
 f.write_text('MARIADB_ROOT_PASSWORD='+secrets.token_hex(24)+'\nMARIADB_DATABASE=nhansu_test\nMARIADB_USER=nhansu_test\nMARIADB_PASSWORD='+pwd+'\nNHANSU_DB_HOST=nhansu-php-db\nNHANSU_DB_PORT=3306\nNHANSU_DB_NAME=nhansu_test\nNHANSU_DB_USER=nhansu_test\nNHANSU_DB_PASSWORD='+pwd+'\nNHANSU_APP_URL=http://127.0.0.1:8183/nhansu\nAPP_PRIVATE_DIR=/var/nhansu-private\n');f.chmod(0o600)
private=root/'php-hostinger/site/nhansu-private'
if not (private/'config.php').exists():shutil.copyfile(private/'config.example.php',private/'config.php')
for p in [private,private/'lib',private/'bin',*(private/'lib').glob('*.php'),*(private/'bin').glob('*.php'),private/'config.php']:p.chmod(0o755 if p.is_dir() else 0o644)
PY
docker network inspect nhansu-php-test >/dev/null 2>&1 || docker network create nhansu-php-test >/dev/null
if docker container inspect nhansu-php-db >/dev/null 2>&1; then docker start nhansu-php-db >/dev/null; else docker run -d --name nhansu-php-db --network nhansu-php-test --env-file /tmp/nhansu-test.env mariadb:11.4 >/dev/null; fi
# Recreate only this disposable web container; database is kept intact.
if docker container inspect nhansu-php-web >/dev/null 2>&1; then docker stop nhansu-php-web >/dev/null; docker rm nhansu-php-web >/dev/null; fi
docker run -d --name nhansu-php-web --network nhansu-php-test --env-file /tmp/nhansu-test.env -p 127.0.0.1:8183:80 -v /tmp/nhansu-php-fixture/public:/var/www/html:ro -v "$PWD/php-hostinger/site/public_html/nhansu:/var/www/html/nhansu:ro" -v "$PWD/php-hostinger/site/nhansu-private:/var/nhansu-private" anhgau-php-test:local >/dev/null
docker exec nhansu-php-web sh -c 'mkdir -p /var/nhansu-private/storage && chown www-data:www-data /var/nhansu-private/storage'
for attempt in $(seq 1 20);do if docker exec nhansu-php-web curl --fail -s 'http://127.0.0.1/nhansu/api.php?route=%2Fstatus' >/dev/null;then echo 'Disposable PHP/MySQL fixture ready on port 8183.';exit 0;fi;sleep 1;done
exit 1
