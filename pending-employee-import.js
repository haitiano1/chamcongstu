import { readFileSync,renameSync } from 'node:fs';
import { join } from 'node:path';
import { randomBytes } from 'node:crypto';
import { validateEmployee } from './employee-service.js';
export function importPendingEmployees({file,data,db,createEmployee,actor}){
 const rows=JSON.parse(readFileSync(file,'utf8'));if(!Array.isArray(rows)||rows.length<1||rows.length>100)throw new Error('Tệp nhập cần có từ 1 đến 100 hồ sơ.');
 const validated=rows.map(row=>validateEmployee({...row,role:'employee',status:row.status||'active',monthly_salary:row.monthly_salary??8000000,password:randomBytes(24).toString('base64url')},{create:true}));let created=0,skipped=0;
 for(const row of validated){if(db.prepare('SELECT id FROM users WHERE email=?').get(row.email)){skipped++;continue;}createEmployee(actor.id,row);created++;}
 renameSync(file,join(data,'employee-import-processed-'+Date.now()+'.json'));return {created,skipped};
}
