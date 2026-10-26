import test from 'node:test';import assert from 'node:assert/strict';import {spawn} from 'node:child_process';import {mkdtempSync,readFileSync,rmSync} from 'node:fs';import {tmpdir} from 'node:os';import {join} from 'node:path';import {DatabaseSync} from 'node:sqlite';import {scryptSync} from 'node:crypto';
test('employee CRUD, role boundary, salary sync, account lock and archival history',async()=>{
 const data=mkdtempSync(join(tmpdir(),'employee-test-'));const child=spawn(process.execPath,['server.js'],{env:{...process.env,PORT:'3151',DATA_DIR:data},stdio:['ignore','pipe','pipe']});
 try{await new Promise((resolve,reject)=>{child.stdout.once('data',resolve);child.once('error',reject);child.once('exit',()=>reject(new Error('Server startup failed')));});
 const base='http://127.0.0.1:3151/api/';
 async function request(path,body,cookie=''){const r=await fetch(base+path,{method:body?'POST':'GET',headers:{'Content-Type':'application/json',Cookie:cookie},body:body?JSON.stringify(body):undefined});return {status:r.status,data:await r.json(),cookie:r.headers.get('set-cookie')?.split(';')[0]};}
 await request('setup',{token:readFileSync(join(data,'setup-token'),'utf8'),name:'Admin',email:'admin@test.vn',password:'password-admin-123'});
 const admin=(await request('login',{email:'admin@test.vn',password:'password-admin-123'})).cookie;
 const profile={name:'Nhân viên Một',email:'one@test.vn',role:'employee',status:'active',monthly_salary:8000000,phone:'0901234567',job_title:'Photographer',department:'Hình ảnh',bank_name:'VietinBank',bank_account:'00123456789',bank_holder:'NHAN VIEN MOT',start_date:'2026-10-01',password:'password-staff-123'};
 const created=await request('employees',profile,admin);assert.equal(created.status,201);const id=created.data.id;
 const managerId=(await request('employees',{...profile,name:'Quản lý',email:'manager@test.vn',role:'manager'},admin)).data.id;
 const manager=(await request('login',{email:'manager@test.vn',password:profile.password})).cookie;
 const staff=(await request('login',{email:profile.email,password:profile.password})).cookie;
 assert.equal((await request('employees',undefined,staff)).status,403);
 assert.equal((await request('employees/'+id,{...profile,role:'admin'},staff)).status,403);
 assert.equal((await request('employees',undefined,manager)).status,200);
 const row=(await request('employees',undefined,admin)).data.users.find(u=>u.id===id);assert.equal(row.bank_account,'00123456789');assert.equal(row.department,'Hình ảnh');assert.equal(row.employee_code,'NV0002');assert.equal(row.password,undefined);
 assert.equal((await request('employees',{...profile,email:'invalid'},admin)).status,400);
 assert.equal((await request('employees',{...profile,bank_account:'x'},admin)).status,400);
 assert.equal((await request('employees',profile,admin)).status,400);
 const updated={...profile,name:'Nhân viên đã sửa',email:'changed@test.vn',monthly_salary:9000000,password:''};assert.equal((await request('employees/'+id,updated,manager)).status,200);
 assert.equal((await request('monthly?month=2026-10',undefined,admin)).data.rows.find(u=>u.id===id).monthly_salary,9000000);
 assert.equal((await request('employees/'+id,{...updated,role:'admin'},manager)).status,403);
 assert.equal((await request('employees/1',{...updated,role:'admin'},manager)).status,403);
 assert.equal((await request('employees/1/delete',{},admin)).status,403);
 assert.equal((await request('employees/1',{...updated,email:'admin@test.vn',status:'inactive',role:'admin'},admin)).status,403);
 assert.equal((await request('employees',{...profile,email:'new-admin@test.vn',role:'admin'},manager)).status,403);
 // Insert historical work via local fixture; deletion must preserve it and payroll.
 const db=new DatabaseSync(join(data,'studio.sqlite'));db.prepare('INSERT INTO events(user_id,type,time,distance,accuracy,shift,work_date) VALUES(?,?,?,?,?,?,?)').run(id,'in',Date.parse('2026-10-01T09:00:00+07:00'),0,5,'day_first','2026-10-01');db.prepare('INSERT INTO events(user_id,type,time,distance,accuracy,shift,work_date) VALUES(?,?,?,?,?,?,?)').run(id,'out',Date.parse('2026-10-01T14:00:00+07:00'),0,5,'day_first','2026-10-01');db.close();
 assert.equal((await request('employees/'+id+'/delete',{},manager)).status,200);
 assert.equal((await request('me',undefined,staff)).status,401);
 assert.equal((await request('login',{email:updated.email,password:profile.password})).status,401);
 assert.equal((await request('employees',undefined,admin)).data.users.some(u=>u.id===id),false);
 assert.equal((await request('employees?archived=1',undefined,admin)).data.users.find(u=>u.id===id).archived,1);
 assert.equal((await request('monthly?month=2026-10',undefined,admin)).data.rows.find(u=>u.id===id).workdays,.5);
 assert.equal((await request('employees/'+id+'/restore',{},manager)).status,200);
 assert.equal((await request('login',{email:updated.email,password:profile.password})).status,401);
 assert.equal((await request('employees/'+id,{...updated,status:'active',password:'new-password-staff'},admin)).status,200);
 assert.equal((await request('login',{email:updated.email,password:profile.password})).status,401);
 assert.equal((await request('login',{email:updated.email,password:'new-password-staff'})).status,200);
 const db2=new DatabaseSync(join(data,'studio.sqlite'));assert.ok(db2.prepare('SELECT COUNT(*) n FROM employee_audit WHERE user_id=?').get(id).n>=5);db2.close();
 }finally{child.kill();await new Promise(r=>child.once('close',r));rmSync(data,{recursive:true,force:true});}
});
test('upgrades original users schema preserving password, accounts and stable employee codes',async()=>{
 const data=mkdtempSync(join(tmpdir(),'employee-migration-'));let child;
 try{const db=new DatabaseSync(join(data,'studio.sqlite'));db.exec('CREATE TABLE users(id INTEGER PRIMARY KEY,name TEXT NOT NULL,email TEXT UNIQUE NOT NULL,password TEXT NOT NULL,role TEXT NOT NULL)');const salt='test-salt';const hash=salt+':'+scryptSync('migration-password',salt,64).toString('hex');db.prepare('INSERT INTO users VALUES(?,?,?,?,?)').run(7,'Tài khoản cũ','old@test.vn',hash,'admin');db.close();
 child=spawn(process.execPath,['server.js'],{env:{...process.env,PORT:'3152',DATA_DIR:data},stdio:['ignore','pipe','pipe']});await new Promise((resolve,reject)=>{child.stdout.once('data',resolve);child.once('error',reject);child.once('exit',()=>reject(new Error('Migration startup failed')));});
 const login=await fetch('http://127.0.0.1:3152/api/login',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({email:'old@test.vn',password:'migration-password'})});assert.equal(login.status,200);const r=await fetch('http://127.0.0.1:3152/api/employees',{headers:{Cookie:login.headers.get('set-cookie').split(';')[0]}});const rows=(await r.json()).users;assert.equal(rows[0].id,7);assert.equal(rows[0].employee_code,'NV0007');assert.equal(rows[0].monthly_salary,8000000);assert.equal(rows[0].status,'active');
 }finally{if(child){child.kill();await new Promise(r=>child.once('close',r));}rmSync(data,{recursive:true,force:true});}
});
