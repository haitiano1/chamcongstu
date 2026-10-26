import test from 'node:test';
import jpeg from 'jpeg-js';
import {DatabaseSync} from 'node:sqlite';
import assert from 'node:assert/strict';
import {spawn} from 'node:child_process';
import {mkdtempSync,readFileSync,rmSync} from 'node:fs';
import {tmpdir} from 'node:os';
import {join} from 'node:path';
test('setup, permissions, geofence, attendance, duplicates and logout',async()=>{
const data=mkdtempSync(join(tmpdir(),'studio-test-'));const child=spawn(process.execPath,['server.js'],{env:{...process.env,PORT:'3109',DATA_DIR:data},stdio:['ignore','pipe','pipe']});
try{
await new Promise((resolve,reject)=>{child.stdout.once('data',resolve);child.once('error',reject);child.once('exit',()=>reject(new Error('Server exited before startup')));});
let cookie='';async function req(path,body){const r=await fetch('http://127.0.0.1:3109/api/'+path,{method:body?'POST':'GET',headers:{...(body?{'Content-Type':'application/json'}:{}),Cookie:cookie},body:body?JSON.stringify(body):undefined});if(r.headers.get('set-cookie'))cookie=r.headers.get('set-cookie').split(';')[0];return {status:r.status,data:await r.json()};}
const homepage=await fetch('http://127.0.0.1:3109/');assert.equal(homepage.headers.get('referrer-policy'),'strict-origin-when-cross-origin');
assert.equal((await req('me')).status,401);
assert.equal((await req('setup',{token:'invalid'})).status,403);
assert.equal((await req('setup',{token:readFileSync(join(data,'setup-token'),'utf8'),name:'Quản lý',email:'admin@example.com',password:'test-password-123'})).status,201);
assert.equal((await req('login',{email:'admin@example.com',password:'test-password-123'})).status,200);
assert.equal((await req('settings',{latitude:10.7769,longitude:106.7009,radius:100,location_label:'Studio kiểm thử',province_code:'79',ward_code:'1',address:'123 Nguyễn Trãi'})).status,200);
assert.equal((await req('me')).data.studio.location_label,'Studio kiểm thử');
assert.equal((await req('me')).data.studio.province_code,'79');
assert.equal((await req('users',{name:'Nhân viên',email:'staff@example.com',password:'test-password-456'})).status,201);
await req('logout',{});await req('login',{email:'staff@example.com',password:'test-password-456'});
assert.equal((await req('settings',{latitude:0,longitude:0,radius:100})).status,403);
assert.equal((await req('report')).status,403);
assert.equal((await req('location/provinces')).status,403);
assert.equal((await req('commissions',{id:'11111111-1111-4111-8111-111111111111',user_id:2,served_at:Date.parse('2026-10-01T19:00:00+07:00'),customers:1})).status,403);
assert.equal((await req('monthly?month=2026-10')).data.rows.length,1);
const ownDashboard=await req('dashboard?date=2026-10-19');assert.equal(ownDashboard.status,200);assert.equal(ownDashboard.data.people,1);assert.equal(ownDashboard.data.payroll[0].id,2);assert.equal(ownDashboard.data.shifts.length,2);
const privateFields=['salary','base_salary','monthly_salary','commission','regular_customers','ot_customers','hourly_rate','rate'];for(const response of [ownDashboard.data.payroll[0],(await req('monthly?month=2026-10')).data.rows[0]])for(const field of privateFields)assert.equal(Object.hasOwn(response,field),false,field+' leaked to employee');
assert.equal((await req('commissions')).status,403);
assert.equal((await req('dashboard?date=invalid')).status,400);
assert.equal((await req('wages',{user_id:2,monthly_salary:8000000})).status,403);
await req('logout',{});await req('login',{email:'admin@example.com',password:'test-password-123'});
assert.equal((await req('role',{user_id:2,role:'manager'})).status,200);
await req('logout',{});await req('login',{email:'staff@example.com',password:'test-password-456'});
assert.equal((await req('report')).status,200);
assert.equal((await req('monthly?month=2026-10')).data.rows.length,2);
assert.equal((await req('dashboard?date=2026-10-19')).data.people,2);
assert.equal((await req('wages',{user_id:2,monthly_salary:8000000})).status,403);
assert.equal((await req('role',{user_id:2,role:'admin'})).status,403);
assert.equal((await req('employees')).status,200);
assert.equal((await req('settings',{latitude:10.7769,longitude:106.7009,radius:100})).status,200);
const fixture=new DatabaseSync(join(data,'studio.sqlite'));for(const [type,time] of [['in','19:00'],['out','20:00']])fixture.prepare('INSERT INTO events(user_id,type,time,shift,work_date) VALUES(?,?,?,?,?)').run(2,type,Date.parse('2026-10-01T'+time+':00+07:00'),'ot','2026-10-01');fixture.close();
const photo='data:image/jpeg;base64,'+jpeg.encode({width:160,height:160,data:Buffer.alloc(160*160*4,200)},75).data.toString('base64');
const customer={id:'11111111-1111-4111-8111-111111111111',user_id:2,served_at:Date.parse('2026-10-01T19:00:00+07:00'),customers:2};
assert.equal((await req('commissions',customer)).data.rate,30000);
assert.equal((await req('commissions',customer)).status,409);
assert.equal((await req('commissions',{...customer,id:'22222222-2222-4222-8222-222222222222',served_at:Date.parse('2026-10-01T18:59:00+07:00')})).data.rate,15000);
const monthly=await req('monthly?month=2026-10');const staff=monthly.data.rows.find(r=>r.id===2);assert.equal(staff.commission,90000);assert.equal(staff.salary,90000);
const position={latitude:10.7769,longitude:106.7009,accuracy:10,timestamp:Date.now()};
assert.equal((await req('attendance',{type:'in',position:{...position,latitude:11}})).status,400);
// The integration runner may execute outside working hours: use the actual server time.
const hour=new Date(Date.now()+7*3600000).getUTCHours();
const shift=hour>=9&&hour<14?'day_first':hour>=14&&hour<19?'day_second':null;
assert.equal((await req('attendance',{type:'in',shift:'invalid',position})).status,409);
if(shift){
assert.equal((await req('attendance',{type:'in',shift,position,photo})).status,201);
assert.equal((await req('attendance',{type:'in',shift,position,photo})).status,409);
assert.equal((await req('attendance',{type:'out',shift,position,photo})).status,409);
const me=await req('me');assert.equal(me.data.events.length,3);assert.equal(me.data.events[0].shift,shift);assert.equal(me.data.events[0].type,'in');
}else{assert.equal((await req('attendance',{type:'in',shift:'day_first',position})).status,409);assert.equal((await req('me')).data.events.length,2);}
await req('logout',{});assert.equal((await req('me')).status,401);
}finally{child.kill();await new Promise(r=>child.once('close',r));rmSync(data,{recursive:true,force:true});}
});
