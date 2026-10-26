import test from 'node:test';import assert from 'node:assert/strict';import {spawn} from 'node:child_process';import {mkdtempSync,readFileSync,rmSync} from 'node:fs';import {tmpdir} from 'node:os';import {join} from 'node:path';import {localDay} from '../public/shifts.js';
test('authorized manual attendance requires reason, records actor, credits work, and cannot bypass privileges',async()=>{
 const dir=mkdtempSync(join(tmpdir(),'manual-attendance-')),child=spawn(process.execPath,['server.js'],{env:{...process.env,PORT:'3154',DATA_DIR:dir},stdio:['ignore','pipe','pipe']});
 try{await new Promise((resolve,reject)=>{child.stdout.once('data',resolve);child.once('error',reject);child.once('exit',()=>reject(new Error('Startup failed')));});const base='http://127.0.0.1:3154/api/';async function req(path,b,cookie=''){const r=await fetch(base+path,{method:b?'POST':'GET',headers:{'Content-Type':'application/json',Cookie:cookie},body:b?JSON.stringify(b):undefined});return {status:r.status,data:await r.json(),cookie:r.headers.get('set-cookie')?.split(';')[0]};}
 await req('setup',{token:readFileSync(join(dir,'setup-token'),'utf8'),name:'Admin test',email:'admin@manual.vn',password:'manual-password-admin'});const admin=(await req('login',{email:'admin@manual.vn',password:'manual-password-admin'})).cookie;
 const person={name:'Employee test',email:'staff@manual.vn',password:'manual-password-staff',role:'employee',status:'active',monthly_salary:8000000};const staffId=(await req('employees',person,admin)).data.id;await req('employees',{...person,name:'Manager',email:'manager@manual.vn',role:'manager'},admin);
 const staff=(await req('login',{email:person.email,password:person.password})).cookie,manager=(await req('login',{email:'manager@manual.vn',password:person.password})).cookie;
 const date=localDay(Date.now()-86400000),body={user_id:staffId,shift:'day_first',type:'in',time:Date.parse(date+'T09:00:00+07:00'),reason:'Đi công tác theo phân công, không thể đến studio.'};
 assert.equal((await req('attendance/manual',body,staff)).status,403);
 assert.equal((await req('attendance/manual',{...body,reason:''},manager)).status,400);
 assert.equal((await req('attendance/manual',{...body,time:Date.now()+3600000},manager)).status,400);
 assert.equal((await req('attendance/manual',{...body,user_id:1},manager)).status,403);
 const entry=await req('attendance/manual',body,manager);assert.equal(entry.status,201);assert.equal((await req('attendance/manual',body,manager)).status,409);
 const out=await req('attendance/manual',{...body,type:'out',time:Date.parse(date+'T14:00:00+07:00')},admin);assert.equal(out.status,201);
 const me=(await req('me',undefined,staff)).data;const event=me.events.find(e=>e.id===entry.data.id);assert.equal(event.source,'manual');assert.equal(event.has_photo,0);assert.equal(event.distance,null);assert.equal(event.accuracy,null);assert.equal(event.recorded_by,3);assert.equal(event.recorded_by_name,'Manager');assert.equal(event.reason,body.reason);assert.ok(event.recorded_at>=body.time);
 const summary=(await req('monthly?month='+date.slice(0,7),undefined,staff)).data.rows[0];assert.equal(summary.workdays,.5);assert.equal(summary.base_salary,undefined);const adminSummary=(await req('monthly?month='+date.slice(0,7),undefined,admin)).data.rows.find(p=>p.id===staffId);assert.equal(adminSummary.base_salary,Math.round(8000000/31*.5));
 // Normal self route still requires camera/GPS; source=manual supplied by staff does not grant exception.
 assert.equal((await req('attendance',{...body,source:'manual'},staff)).status,400);
 assert.equal((await fetch(base+'events/'+entry.data.id+'/photo',{headers:{Cookie:admin}})).status,404);
 const ot={...body,shift:'ot',time:Date.parse(date+'T19:00:00+07:00')};assert.equal((await req('attendance/manual',ot,admin)).status,400);assert.equal((await req('attendance/manual',{...ot,ot_confirmed:true},admin)).status,201);assert.equal((await req('attendance/manual',{...ot,type:'out',time:Date.parse(date+'T20:00:00+07:00')},manager)).status,201);
 const otReport=(await req('overtime?month='+date.slice(0,7),undefined,admin)).data.sessions[0];assert.equal(otReport.in_source,'manual');assert.equal(otReport.out_source,'manual');assert.equal(otReport.in_recorded_by,'Admin test');
 }finally{child.kill();await new Promise(r=>child.once('close',r));rmSync(dir,{recursive:true,force:true});}
});
