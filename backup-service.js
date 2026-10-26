import { mkdirSync,readdirSync,statSync,renameSync,rmSync,existsSync } from 'node:fs';
import { join,resolve } from 'node:path';
import { DatabaseSync } from 'node:sqlite';
export function createBackupService(db,data,{directory=process.env.BACKUP_DIR||join(data,'backups'),keep=14}={}){
 directory=resolve(directory);mkdirSync(directory,{recursive:true,mode:0o700});let running=false,lastError='';
 const list=()=>readdirSync(directory).filter(n=>/^studio-\d{13}-[a-f0-9]+\.sqlite$/.test(n)).map(name=>({name,size:statSync(join(directory,name)).size,time:Number(name.split('-')[1])})).sort((a,b)=>b.time-a.time);
 function create(){if(running)throw new Error('Đang sao lưu, hãy đợi một chút.');running=true;const name=`studio-${Date.now()}-${Math.random().toString(16).slice(2,10)}.sqlite`,temp=join(directory,name+'.tmp');try{
 db.exec("VACUUM INTO '"+temp.replaceAll("'","''")+"'");const copy=new DatabaseSync(temp);try{copy.exec('DELETE FROM sessions;');if(copy.prepare('PRAGMA integrity_check').get().integrity_check!=='ok')throw new Error('Bản sao lưu không đạt kiểm tra dữ liệu.');}finally{copy.close();}renameSync(temp,join(directory,name));lastError='';for(const old of list().slice(keep))rmSync(join(directory,old.name));return {name,time:Number(name.split('-')[1])};
 }catch(error){lastError=error.message;if(existsSync(temp))rmSync(temp);throw error;}finally{running=false;}}
 function scheduled(){try{if(!list()[0]||Date.now()-list()[0].time>=86400000)create();}catch(error){console.error('Automatic backup failed:',error.message);}}
 return {list,create,scheduled,status:()=>({files:list(),lastError,directory,keep}),path:name=>/^studio-\d{13}-[a-f0-9]+\.sqlite$/.test(name)&&existsSync(join(directory,name))?join(directory,name):null};
}
