import test from 'node:test';import assert from 'node:assert/strict';import {spawn} from 'node:child_process';import {mkdtempSync,readFileSync,rmSync} from 'node:fs';import {tmpdir} from 'node:os';import {join} from 'node:path';import {DatabaseSync} from 'node:sqlite';import jpeg from 'jpeg-js';import {localDay} from '../public/shifts.js';
test('photo-required automatic attendance, private proof access, OT history and commission evidence',async()=>{
 const dir=mkdtempSync(join(tmpdir(),'proof-ot-'));const child=spawn(process.execPath,['server.js'],{env:{...process.env,PORT:'3153',DATA_DIR:dir},stdio:['ignore','pipe','pipe']});
 try{await new Promise((resolve,reject)=>{child.stdout.once('data',resolve);child.once('error',reject);child.once('exit',()=>reject(new Error('Startup failed')));});const base='http://127.0.0.1:3153/api/';async function req(path,b,cookie=''){const r=await fetch(base+path,{method:b?'POST':'GET',headers:{'Content-Type':'application/json',Cookie:cookie},body:b?JSON.stringify(b):undefined});return {status:r.status,data:await r.json(),cookie:r.headers.get('set-cookie')?.split(';')[0]};}
 await req('setup',{token:readFileSync(join(dir,'setup-token'),'utf8'),name:'Admin',email:'admin@photo.vn',password:'photo-admin-password'});const admin=(await req('login',{email:'admin@photo.vn',password:'photo-admin-password'})).cookie;
 await req('settings',{latitude:10.7769,longitude:106.7009,radius:100},admin);
 for(const email of ['one@photo.vn','two@photo.vn'])await req('employees',{name:email,email,password:'photo-staff-password',role:'employee',status:'active',monthly_salary:8000000},admin);
 const staff=(await req('login',{email:'one@photo.vn',password:'photo-staff-password'})).cookie,other=(await req('login',{email:'two@photo.vn',password:'photo-staff-password'})).cookie;
 const position={latitude:10.7769,longitude:106.7009,accuracy:5,timestamp:Date.now()},photo='data:image/jpeg;base64,'+jpeg.encode({width:160,height:160,data:Buffer.alloc(160*160*4,160)},70).data.toString('base64');
 assert.equal((await req('attendance',{type:'in',shift:'ot',position,photo},staff)).status,400);
 // A fixture open OT from yesterday allows checkout at any actual test time.
 const yesterday=localDay(Date.now()-86400000),started=Date.parse(yesterday+'T19:10:00+07:00');const db=new DatabaseSync(join(dir,'studio.sqlite'));db.prepare('INSERT INTO events(user_id,type,time,distance,accuracy,shift,work_date,ot_confirmed_at) VALUES(?,?,?,?,?,?,?,?)').run(2,'in',started,0,5,'ot',yesterday,started);db.close();
 assert.equal((await req('attendance',{type:'out',shift:'ot',position},staff)).status,400);
 const oversized=await req('attendance',{type:'out',shift:'ot',position,photo:'data:image/jpeg;base64,'+'A'.repeat(520000)},staff);assert.equal(oversized.status,413);assert.match(oversized.data.error,/Ảnh gửi chấm công quá lớn/);
 const missing=await req('attendance',{type:'out',shift:'ot',position},staff);assert.equal(missing.status,400);assert.match(missing.data.error,/Chưa nhận được ảnh/);
 assert.equal((await req('attendance',{type:'out',shift:'ot',position,photo:'data:image/jpeg;base64,AAAA'},staff)).status,400);
 assert.equal((await req('attendance',{type:'out',shift:'ot',position,photo},staff)).status,201);
 const me=(await req('me',undefined,staff)).data;const row=me.events[0];assert.equal(row.work_date,yesterday);assert.equal(row.has_photo,1);assert.equal(row.photo_jpeg,undefined);assert.equal(row.type,'out');
 const ownImage=await fetch(base+'events/'+row.id+'/photo',{headers:{Cookie:staff}});assert.equal(ownImage.status,200);assert.equal(ownImage.headers.get('content-type'),'image/jpeg');assert.match(ownImage.headers.get('cache-control'),/no-store/);assert.equal(jpeg.decode(Buffer.from(await ownImage.arrayBuffer())).width,160);
 assert.equal((await fetch(base+'events/'+row.id+'/photo')).status,401);
 assert.equal((await fetch(base+'events/'+row.id+'/photo',{headers:{Cookie:other}})).status,403);
 assert.equal((await fetch(base+'events/'+row.id+'/photo',{headers:{Cookie:admin}})).status,200);
 const gallery=await req('attendance-photos?date='+localDay(row.time),undefined,admin);assert.equal(gallery.data.total,1);assert.equal(gallery.data.photos[0].id,row.id);
 assert.equal((await req('attendance-photos?date='+localDay(row.time),undefined,other)).data.total,0);
 const month=yesterday.slice(0,7),sessions=(await req('overtime?month='+month,undefined,admin)).data.sessions;assert.equal(sessions.length,1);assert.equal(sessions[0].confirmed_at,started);assert.equal(sessions[0].out_photo,true);assert.ok(sessions[0].end>=started);
 assert.equal((await req('overtime?month='+month,undefined,other)).data.sessions.length,0);
 const monthly=(await req('monthly?month='+month,undefined,admin)).data.rows.find(p=>p.id===2);assert.equal(monthly.workdays,0);assert.equal(monthly.base_salary,0);assert.ok(monthly.otHours>0);
 const customer={id:'11111111-1111-4111-8111-111111111111',user_id:2,customers:1,served_at:started-5*60000};assert.equal((await req('commissions',customer,admin)).status,409);
 assert.equal((await req('commissions',{...customer,served_at:started+60000},admin)).data.rate,30000);
 assert.equal((await req('commissions',{...customer,id:'22222222-2222-4222-8222-222222222222',served_at:Date.parse(localDay(row.time)+'T00:01:00+07:00')},admin)).data.rate,30000);
 }finally{child.kill();await new Promise(r=>child.once('close',r));rmSync(dir,{recursive:true,force:true});}
});
