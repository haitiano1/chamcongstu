// Root entrypoint always starts the current PHP/Hostinger application.
// Keep the earlier Node runtime available only through start:legacy / dev:legacy.
import {spawnSync} from 'node:child_process';
import {fileURLToPath} from 'node:url';
const root=fileURLToPath(new URL('../',import.meta.url));
console.log('Anh Gấu Studio · Bản hiện tại PHP/MySQL v1.2.3');
console.log('Khởi động môi trường Docker riêng tại http://127.0.0.1:8184/nhansu/');
const result=spawnSync('bash',['php-hostinger/dev.sh','start'],{cwd:root,stdio:'inherit'});
if(result.error){console.error('Không khởi động được Bash. Cần Docker, Bash và Python 3; xem README.md. Windows: dùng WSL2/Git Bash với Docker Desktop.');process.exit(1);}
if(result.status!==0){console.error('Chưa khởi động được PHP. Kiểm tra Docker đang chạy và hướng dẫn trong php-hostinger/README.md.');process.exit(result.status||1);}
