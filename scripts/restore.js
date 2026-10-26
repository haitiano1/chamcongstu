import { DatabaseSync } from 'node:sqlite';
import { existsSync,readFileSync,mkdirSync,copyFileSync,renameSync,rmSync } from 'node:fs';
import { resolve,join } from 'node:path';
import { fileURLToPath } from 'node:url';
const data=resolve(process.env.DATA_DIR||fileURLToPath(new URL('../.data',import.meta.url))),source=process.argv[2]&&resolve(process.argv[2]),dest=join(data,'studio.sqlite');
try{
 if(!source||!existsSync(source)||source===dest)throw new Error('Dùng: npm run restore -- "đường-dẫn-bản-sao-lưu.sqlite". Dừng máy chủ trước khi khôi phục.');
 const pidFile=join(data,'server.pid');if(existsSync(pidFile)){const pid=Number(readFileSync(pidFile,'utf8'));if(Number.isInteger(pid)&&pid>0){let active=false;try{process.kill(pid,0);active=true;}catch(e){if(e.code!=='ESRCH')active=true;}if(active)throw new Error('Máy chủ vẫn đang chạy. Nhấn Ctrl+C ở cửa sổ chạy web rồi thử lại.');}}
 mkdirSync(data,{recursive:true,mode:0o700});const temp=join(data,'restore-'+Date.now()+'.tmp');copyFileSync(source,temp);try{const incoming=new DatabaseSync(temp);try{if(incoming.prepare('PRAGMA integrity_check').get().integrity_check!=='ok')throw new Error('Bản sao lưu bị lỗi.');for(const table of ['users','events','salaries','settings','sessions'])if(!incoming.prepare("SELECT name FROM sqlite_master WHERE type='table' AND name=?").get(table))throw new Error('Tệp không phải bản sao lưu của studio.');incoming.exec('DELETE FROM sessions;');}finally{incoming.close();}
 if(existsSync(dest)){const safety=join(data,'backups');mkdirSync(safety,{recursive:true,mode:0o700});const current=new DatabaseSync(dest);try{current.exec("VACUUM INTO '"+join(safety,'before-restore-'+Date.now()+'.sqlite').replaceAll("'","''")+"'");}finally{current.close();}}
 for(const suffix of ['-wal','-shm'])rmSync(dest+suffix,{force:true});renameSync(temp,dest);rmSync(pidFile,{force:true});console.log('Đã khôi phục dữ liệu và ảnh. Bản dữ liệu trước khi khôi phục được giữ trong .data/backups. Chạy npm start và đăng nhập lại.');
 }finally{rmSync(temp,{force:true});}
}catch(error){console.error(error.message);process.exitCode=1;}
