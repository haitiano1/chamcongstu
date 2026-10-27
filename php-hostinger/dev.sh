#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
case "${1:-start}" in start) ;; *) echo 'Use: bash php-hostinger/dev.sh start'; exit 1;; esac
runtime_dir="${NHANSU_DEV_DIR:-${XDG_STATE_HOME:-$HOME/.local/state}/anhgau-php-development}"
if [ -e "$runtime_dir" ] && [ ! -O "$runtime_dir" ]; then echo "Runtime dir $runtime_dir not owned by $(id -un); set NHANSU_DEV_DIR or remove it." >&2; exit 1; fi
mkdir -p "$runtime_dir/private"
# Secret env lost but DB volume kept: recover from existing DB container so passwords stay in sync.
if [ ! -f "$runtime_dir/dev.env" ] && docker container inspect anhgau-dev-db >/dev/null 2>&1; then
 (umask 077; docker inspect anhgau-dev-db --format '{{range .Config.Env}}{{println .}}{{end}}' | grep -E '^(MARIADB_(ROOT_PASSWORD|DATABASE|USER|PASSWORD)|NHANSU_[A-Z_]+|APP_PRIVATE_DIR)=' >"$runtime_dir/dev.env")
fi
chmod 755 "$runtime_dir" "$runtime_dir/private"
python3 - "$PWD" "$runtime_dir" <<'PY'
import sys,shutil,secrets
from pathlib import Path
root,run=map(Path,sys.argv[1:]);private=root/'php-hostinger/site/nhansu-private'
for name in ['lib','bin']:shutil.copytree(private/name,run/'private'/name,dirs_exist_ok=True)
shutil.copyfile(private/'config.example.php',run/'private/config.php')
for p in (run/'private').rglob('*'):
 if 'storage' not in p.parts:p.chmod(0o755 if p.is_dir() else 0o644)
secret=run/'dev.env'
if not secret.exists():
 password=secrets.token_hex(24)
 secret.write_text('MARIADB_ROOT_PASSWORD='+secrets.token_hex(24)+'\nMARIADB_DATABASE=nhansu_dev\nMARIADB_USER=nhansu_dev\nMARIADB_PASSWORD='+password+'\nNHANSU_DB_HOST=anhgau-dev-db\nNHANSU_DB_PORT=3306\nNHANSU_DB_NAME=nhansu_dev\nNHANSU_DB_USER=nhansu_dev\nNHANSU_DB_PASSWORD='+password+'\nNHANSU_APP_URL=http://127.0.0.1:8184/nhansu\nAPP_PRIVATE_DIR=/var/nhansu-private\n')
 secret.chmod(0o600)
PY
docker image inspect anhgau-php-test:local >/dev/null 2>&1 || docker build -t anhgau-php-test:local php-hostinger
docker network inspect anhgau-dev >/dev/null 2>&1 || docker network create anhgau-dev >/dev/null
if docker container inspect anhgau-dev-db >/dev/null 2>&1; then docker start anhgau-dev-db >/dev/null; else
 docker run -d --name anhgau-dev-db --network anhgau-dev --env-file "$runtime_dir/dev.env" -v anhgau-dev-mysql:/var/lib/mysql mariadb:11.4 >/dev/null
fi
if docker container inspect anhgau-dev-web >/dev/null 2>&1 && [ "$(docker inspect anhgau-dev-web --format '{{range .Mounts}}{{if eq .Destination "/var/nhansu-private"}}{{.Source}}{{end}}{{end}}')" != "$runtime_dir/private" ]; then
 docker rm -f anhgau-dev-web >/dev/null
fi
if docker container inspect anhgau-dev-web >/dev/null 2>&1; then docker start anhgau-dev-web >/dev/null; else
 docker run -d --name anhgau-dev-web --network anhgau-dev --env-file "$runtime_dir/dev.env" -p 127.0.0.1:8184:80 -v "$PWD/php-hostinger/site/public_html/nhansu:/var/www/html/nhansu:ro" -v "$runtime_dir/private:/var/nhansu-private" anhgau-php-test:local >/dev/null
fi
docker exec anhgau-dev-web sh -c 'mkdir -p /var/nhansu-private/storage && chown www-data:www-data /var/nhansu-private/storage'
for attempt in $(seq 1 20); do
 if docker exec anhgau-dev-web curl --fail -s 'http://127.0.0.1/nhansu/api.php?route=%2Fstatus' >/dev/null; then echo 'PHP/MySQL development runtime ready (port 8184; validate internally).'; exit 0; fi
 sleep 1
done
echo 'Runtime not ready. Inspect docker logs anhgau-dev-web and anhgau-dev-db.' >&2
exit 1
