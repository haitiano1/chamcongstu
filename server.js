import { importPendingEmployees } from './pending-employee-import.js';
import { createRosterService } from './roster-service.js';
import { ATTENDANCE_BODY_MAX_BYTES,ATTENDANCE_CLIENT_VERSION } from './public/photo-upload.js';
import { createTelegramService } from './telegram-service.js';
import { createBackupService } from './backup-service.js';
import { createWorkflowService } from './workflow-service.js';
import http from 'node:http';
import { normalizeAttendancePhoto } from './photo-proof.js';
import { PROFILE_FIELDS,validateEmployee,authorizeEmployeeEdit } from './employee-service.js';
import { createLocationService } from './location-service.js';
const locationService=createLocationService();
import { DatabaseSync } from 'node:sqlite';
import { randomBytes, scryptSync, timingSafeEqual } from 'node:crypto';
import { mkdirSync, existsSync, readFileSync, writeFileSync, rmSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { join } from 'node:path';
import { validatePosition } from './attendance.js';
import { monthlySummary } from './payroll.js';
import { attendanceDecision,completedPairs,hasOvertimeAt,localDay,SHIFTS } from './public/shifts.js';
const root=fileURLToPath(new URL('.',import.meta.url));
const data=process.env.DATA_DIR || join(root,'.data'); mkdirSync(data,{recursive:true,mode:0o700});
const pidFile=join(data,'server.pid');

const db=new DatabaseSync(join(data,'studio.sqlite')); db.exec(`PRAGMA journal_mode=WAL; CREATE TABLE IF NOT EXISTS users(id INTEGER PRIMARY KEY,name TEXT NOT NULL,email TEXT UNIQUE NOT NULL,password TEXT NOT NULL,role TEXT NOT NULL); CREATE TABLE IF NOT EXISTS settings(id INTEGER PRIMARY KEY CHECK(id=1),latitude REAL,longitude REAL,radius INTEGER); CREATE TABLE IF NOT EXISTS events(id INTEGER PRIMARY KEY,user_id INTEGER NOT NULL,type TEXT NOT NULL,time INTEGER NOT NULL,distance INTEGER,accuracy REAL); CREATE TABLE IF NOT EXISTS sessions(token TEXT PRIMARY KEY,user_id INTEGER NOT NULL,expires INTEGER NOT NULL);`);
db.exec(`CREATE TABLE IF NOT EXISTS wages(user_id INTEGER PRIMARY KEY,hourly_rate REAL NOT NULL DEFAULT 0,hours_per_day REAL NOT NULL DEFAULT 8);`);
db.exec(`CREATE TABLE IF NOT EXISTS salaries(user_id INTEGER PRIMARY KEY,monthly_salary INTEGER NOT NULL DEFAULT 8000000); CREATE TABLE IF NOT EXISTS commissions(id TEXT PRIMARY KEY,user_id INTEGER NOT NULL,served_at INTEGER NOT NULL,customers INTEGER NOT NULL,rate INTEGER NOT NULL,created_by INTEGER NOT NULL,created_at INTEGER NOT NULL);`);
const columns=db.prepare('PRAGMA table_info(events)').all();
if(!columns.some(c=>c.name==='shift')) db.exec('ALTER TABLE events ADD COLUMN shift TEXT;');
if(!columns.some(c=>c.name==='work_date')) db.exec('ALTER TABLE events ADD COLUMN work_date TEXT;');
if(!columns.some(c=>c.name==='ot_confirmed_at')) db.exec('ALTER TABLE events ADD COLUMN ot_confirmed_at INTEGER;');
if(!columns.some(c=>c.name==='photo_jpeg')) db.exec('ALTER TABLE events ADD COLUMN photo_jpeg BLOB;');
for(const [name,definition] of [['source',"TEXT NOT NULL DEFAULT 'self'"],['recorded_by','INTEGER'],['recorded_at','INTEGER'],['reason',"TEXT NOT NULL DEFAULT ''"]])if(!columns.some(c=>c.name===name))db.exec(`ALTER TABLE events ADD COLUMN ${name} ${definition};`);
const settingColumns=db.prepare('PRAGMA table_info(settings)').all();
for(const name of ['location_label','province_code','ward_code','address']){if(!settingColumns.some(c=>c.name===name))db.exec(`ALTER TABLE settings ADD COLUMN ${name} TEXT NOT NULL DEFAULT '';`);}
const userColumns=db.prepare('PRAGMA table_info(users)').all();
for(const field of [...PROFILE_FIELDS,'employee_code'])if(!userColumns.some(c=>c.name===field))db.exec(`ALTER TABLE users ADD COLUMN ${field} TEXT NOT NULL DEFAULT '';`);
if(!userColumns.some(c=>c.name==='status'))db.exec("ALTER TABLE users ADD COLUMN status TEXT NOT NULL DEFAULT 'active';");
if(!userColumns.some(c=>c.name==='archived'))db.exec('ALTER TABLE users ADD COLUMN archived INTEGER NOT NULL DEFAULT 0;');
db.exec("UPDATE users SET employee_code='NV'||printf('%04d',id) WHERE employee_code=''; CREATE UNIQUE INDEX IF NOT EXISTS users_employee_code ON users(employee_code); CREATE TABLE IF NOT EXISTS employee_audit(id INTEGER PRIMARY KEY,actor_id INTEGER NOT NULL,user_id INTEGER NOT NULL,action TEXT NOT NULL,time INTEGER NOT NULL);");
function transaction(fn){db.exec('BEGIN IMMEDIATE');try{const result=fn();db.exec('COMMIT');return result;}catch(error){db.exec('ROLLBACK');throw error;}}
function employeeSelect(){return `SELECT u.id,u.name,u.email,u.role,u.employee_code,u.status,u.archived,${PROFILE_FIELDS.map(f=>'u.'+f).join(',')},COALESCE(s.monthly_salary,8000000) monthly_salary FROM users u LEFT JOIN salaries s ON s.user_id=u.id`;}
function audit(actor,id,action){db.prepare('INSERT INTO employee_audit(actor_id,user_id,action,time) VALUES(?,?,?,?)').run(actor,id,action,Date.now());}
function createEmployee(actor,b){return transaction(()=>{const r=db.prepare('INSERT INTO users(name,email,password,role,status,employee_code) VALUES(?,?,?,?,?,?)').run(b.name,b.email,hash(b.password),b.role,b.status,'pending-'+randomBytes(12).toString('hex'));const id=Number(r.lastInsertRowid);db.prepare('UPDATE users SET employee_code=? WHERE id=?').run('NV'+String(id).padStart(4,'0'),id);db.prepare(`UPDATE users SET ${PROFILE_FIELDS.map(f=>f+'=?').join(',')} WHERE id=?`).run(...PROFILE_FIELDS.map(f=>b[f]),id);db.prepare('INSERT INTO salaries VALUES(?,?)').run(id,b.monthly_salary);audit(actor,id,'create');return id;});}
const tokenPath=join(data,'setup-token');
if (!db.prepare('SELECT id FROM users LIMIT 1').get() && !existsSync(tokenPath)) writeFileSync(tokenPath,randomBytes(24).toString('hex'),{mode:0o600});
const hash=p=>{const salt=randomBytes(16).toString('hex');return salt+':'+scryptSync(p,salt,64).toString('hex')};
const verify=(p,h)=>{const [s,v]=h.split(':');return timingSafeEqual(scryptSync(p,s,64),Buffer.from(v,'hex'))};
const attempts=new Map();
const safeUser=u=>({id:u.id,name:u.name,email:u.email,role:u.role});
function visibleSummary(actor,summary){if(actor.role!=='employee')return summary;const allowed=['days','hours','workdays','completedShifts','incomplete','otHours','otSessions','otIncomplete','legacyShifts'];return Object.fromEntries(allowed.map(key=>[key,summary[key]]));}
const roster=createRosterService(db,{authorizeEmployeeEdit});
const telegram=createTelegramService(db,{authorizeEmployeeEdit,roster});
const workflows=createWorkflowService(db,{transaction,audit,authorizeEmployeeEdit,visibleSummary});
const backups=createBackupService(db,data);
backups.scheduled();const backupTimer=setInterval(()=>backups.scheduled(),3600000);backupTimer.unref();
function processPendingEmployeeImport(){const actor=db.prepare("SELECT id FROM users WHERE role='admin' AND archived=0 AND status!='inactive' ORDER BY id LIMIT 1").get();if(!actor)return;const candidates=[join(data,'pending-employees.json'),...(!process.env.DATA_DIR?[join(root,'employee-import.json')]:[])];const file=candidates.find(existsSync);if(!file)return;try{const result=importPendingEmployees({file,data,db,createEmployee,actor});backups.create();console.log(`Employee import: ${result.created} created, ${result.skipped} existing emails skipped. Set employee passwords in employee management.`);}catch(error){console.error('Employee import could not complete:',error.message);}}
function send(res,status,payload){res.writeHead(status,{'Content-Type':'application/json; charset=utf-8','Cache-Control':'no-store'});res.end(JSON.stringify(payload));}
function credentials(b){if(typeof b.name!=='string'||!b.name.trim()||b.name.length>80||typeof b.email!=='string'||!/^\S+@\S+\.\S+$/.test(b.email)||typeof b.password!=='string'||b.password.length<10||b.password.length>128)throw new Error('Nhập họ tên, email và mật khẩu từ 10–128 ký tự.');}
const server=http.createServer(async(req,res)=>{
 res.setHeader('X-Content-Type-Options','nosniff');res.setHeader('Referrer-Policy','strict-origin-when-cross-origin');res.setHeader('X-Frame-Options','DENY');
 try {
 const url=new URL(req.url,'http://localhost');const path=url.pathname;
 if(!path.startsWith('/api/')){const files={'/':'index.html','/app.js':'app.js','/style.css':'style.css','/shifts.js':'shifts.js','/location-picker.js':'location-picker.js','/geolocation.js':'geolocation.js','/employees.js':'employees.js','/camera.js':'camera.js','/history.js':'history.js','/workflows.js':'workflows.js','/telegram.js':'telegram.js','/photo-upload.js':'photo-upload.js','/roster.js':'roster.js'};if(path.startsWith('/vendor/')){const vendor={'/vendor/leaflet.js':['leaflet.js','text/javascript'],'/vendor/leaflet.css':['leaflet.css','text/css'],'/vendor/images/marker-icon.png':['images/marker-icon.png','image/png'],'/vendor/images/marker-icon-2x.png':['images/marker-icon-2x.png','image/png'],'/vendor/images/marker-shadow.png':['images/marker-shadow.png','image/png'],'/vendor/images/layers.png':['images/layers.png','image/png'],'/vendor/images/layers-2x.png':['images/layers-2x.png','image/png']}[path];if(!vendor)return send(res,404,{error:'Không tìm thấy.'});res.setHeader('Content-Type',vendor[1]);return res.end(readFileSync(join(root,'node_modules/leaflet/dist',vendor[0])));}
 if(!files[path])return send(res,404,{error:'Không tìm thấy.'});res.setHeader('Cache-Control','no-store');res.setHeader('Content-Type',path.endsWith('.js')?'text/javascript':path.endsWith('.css')?'text/css':'text/html; charset=utf-8');return res.end(readFileSync(join(root,'public',files[path])));}
 if(req.method==='POST' && req.headers.origin && req.headers.origin!==`${req.headers['x-forwarded-proto']==='https'?'https':'http'}://${req.headers.host}`)return send(res,403,{error:'Nguồn yêu cầu không hợp lệ.'});
 let b={};if(req.method==='POST'){const chunks=[];let length=0;for await(const chunk of req){length+=chunk.length;if(length>(path==='/api/attendance'?ATTENDANCE_BODY_MAX_BYTES:16384))return send(res,413,{error:path==='/api/attendance'?'Ảnh gửi chấm công quá lớn. Hãy tải lại trang để cập nhật bộ nén ảnh rồi chụp lại.':'Yêu cầu quá lớn.'});chunks.push(chunk);}b=JSON.parse(Buffer.concat(chunks).toString('utf8')||'{}');}
 if(path==='/api/status'&&req.method==='GET')return send(res,200,{setup:!db.prepare('SELECT id FROM users LIMIT 1').get()});
 if(path==='/api/setup'&&req.method==='POST'){
 if(db.prepare('SELECT id FROM users LIMIT 1').get())return send(res,409,{error:'Studio đã được khởi tạo.'});
 if(typeof b.token!=='string'||b.token!==readFileSync(tokenPath,'utf8'))return send(res,403,{error:'Mã thiết lập không đúng.'});credentials(b);
 db.prepare('INSERT INTO users(name,email,password,role) VALUES(?,?,?,?)').run(b.name.trim(),b.email.toLowerCase().trim(),hash(b.password),'admin');db.exec("UPDATE users SET employee_code='NV'||printf('%04d',id) WHERE employee_code='';");processPendingEmployeeImport();return send(res,201,{ok:true});}
 if(path==='/api/login'&&req.method==='POST'){
 const key=req.socket.remoteAddress;const now=Date.now();const attempt=attempts.get(key)||{count:0,until:now+900000};if(attempt.until<now){attempt.count=0;attempt.until=now+900000;}attempts.set(key,attempt);if(++attempt.count>20)return send(res,429,{error:'Thử quá nhiều lần. Vui lòng đợi 15 phút.'});
 const user=db.prepare('SELECT * FROM users WHERE email=?').get(String(b.email||'').toLowerCase().trim());if(!user||user.archived||user.status==='inactive'||typeof b.password!=='string'||b.password.length>128||!verify(b.password,user.password))return send(res,401,{error:'Email hoặc mật khẩu không đúng.'});
 const token=randomBytes(32).toString('hex');db.prepare('INSERT INTO sessions VALUES(?,?,?)').run(token,user.id,now+7*86400000);res.setHeader('Set-Cookie',`session=${token}; HttpOnly; SameSite=Strict; Path=/; Max-Age=604800${process.env.SECURE_COOKIES==='true'?'; Secure':''}`);attempts.delete(key);return send(res,200,{user:safeUser(user)});}
 const cookie=String(req.headers.cookie||'').split(';').map(s=>s.trim()).find(s=>s.startsWith('session='))?.slice(8);
 const user=db.prepare("SELECT u.* FROM users u JOIN sessions s ON s.user_id=u.id WHERE s.token=? AND s.expires>? AND u.archived=0 AND u.status!='inactive'").get(cookie||'',Date.now());if(!user)return send(res,401,{error:'Vui lòng đăng nhập.'});
 if(path==='/api/logout'&&req.method==='POST'){db.prepare('DELETE FROM sessions WHERE token=?').run(cookie);res.setHeader('Set-Cookie','session=; HttpOnly; SameSite=Strict; Path=/; Max-Age=0');return send(res,200,{ok:true});}
 if(await workflows.handle({path,req,b,url,user,res,send}))return;
 if(await telegram.handle({path,req,b,url,user,res,send}))return;
 if(await roster.handle({path,req,b,url,user,res,send}))return;
 if(path==='/api/backups'&&req.method==='GET'){if(user.role!=='admin')return send(res,403,{error:'Chỉ Admin được quản lý bản sao lưu.'});return send(res,200,backups.status());}
 if(path==='/api/backups'&&req.method==='POST'){if(user.role!=='admin')return send(res,403,{error:'Chỉ Admin được sao lưu dữ liệu.'});return send(res,201,backups.create());}
 const backupDownload=path.match(/^\/api\/backups\/(studio-\d{13}-[a-f0-9]+\.sqlite)$/);if(backupDownload&&req.method==='GET'){if(user.role!=='admin')return send(res,403,{error:'Chỉ Admin được tải sao lưu.'});const file=backups.path(backupDownload[1]);if(!file)return send(res,404,{error:'Không tìm thấy bản sao lưu.'});res.writeHead(200,{'Content-Type':'application/vnd.sqlite3','Content-Disposition':'attachment; filename="'+backupDownload[1]+'"','Cache-Control':'private, no-store'});return res.end(readFileSync(file));}
 if(path==='/api/me'&&req.method==='GET')return send(res,200,{clientVersion:ATTENDANCE_CLIENT_VERSION,serverTime:Date.now(),shifts:SHIFTS,user:safeUser(user),studio:db.prepare('SELECT latitude,longitude,radius,location_label,province_code,ward_code,address FROM settings WHERE id=1').get()||null,events:db.prepare('SELECT id,user_id,type,time,distance,accuracy,shift,work_date,ot_confirmed_at,source,recorded_by,recorded_at,reason,(SELECT name FROM users WHERE users.id=events.recorded_by) recorded_by_name,(photo_jpeg IS NOT NULL) has_photo FROM events WHERE user_id=? ORDER BY time DESC,id DESC').all(user.id)});
 const photoMatch=path.match(/^\/api\/events\/(\d+)\/photo$/);
 if(photoMatch&&req.method==='GET'){const row=db.prepare('SELECT user_id,photo_jpeg FROM events WHERE id=?').get(Number(photoMatch[1]));if(!row||!row.photo_jpeg)return send(res,404,{error:'Không có ảnh cho lượt chấm công này.'});if(user.role==='employee'&&row.user_id!==user.id)return send(res,403,{error:'Bạn không có quyền xem ảnh này.'});res.writeHead(200,{'Content-Type':'image/jpeg','Content-Disposition':'inline','Cache-Control':'private, no-store'});return res.end(Buffer.from(row.photo_jpeg));}
 if(path==='/api/history'&&req.method==='GET'){const month=url.searchParams.get('month');if(!/^\d{4}-(0[1-9]|1[0-2])$/.test(month||''))throw new Error('Tháng không hợp lệ.');const [y,m]=month.split('-').map(Number),start=Date.UTC(y,m-1,1)-7*3600000,end=Date.UTC(y,m,1)-7*3600000;return send(res,200,{events:db.prepare('SELECT e.id,e.user_id,e.type,e.time,e.distance,e.accuracy,e.shift,e.work_date,e.source,e.reason,e.recorded_by,e.recorded_at,(SELECT name FROM users WHERE id=e.recorded_by) recorded_by_name,(e.photo_jpeg IS NOT NULL) has_photo FROM events e WHERE e.user_id=? AND (e.time>=? AND e.time<? OR e.work_date>=? AND e.work_date<?) ORDER BY e.time DESC,e.id DESC').all(user.id,start,end,month+'-01',localDay(end))});}
 if(path==='/api/attendance-photos'&&req.method==='GET'){
 const date=url.searchParams.get('date')||localDay(Date.now()),page=Number(url.searchParams.get('page')||1);if(!/^\d{4}-\d{2}-\d{2}$/.test(date)||!Number.isFinite(Date.parse(date))||!Number.isInteger(page)||page<1||page>10000)throw new Error('Ngày hoặc trang ảnh không hợp lệ.');const start=Date.parse(date+'T00:00:00+07:00'),end=start+86400000;const where='e.photo_jpeg IS NOT NULL AND e.time>=? AND e.time<?'+(user.role==='employee'?' AND e.user_id=?':'');const params=user.role==='employee'?[start,end,user.id]:[start,end];const total=db.prepare('SELECT COUNT(*) n FROM events e WHERE '+where).get(...params).n;const photos=db.prepare('SELECT e.id,u.name,e.type,e.time,e.shift FROM events e JOIN users u ON u.id=e.user_id WHERE '+where+' ORDER BY e.id DESC LIMIT 60 OFFSET ?').all(...params,(page-1)*60);return send(res,200,{photos,total,page});}
 if(path==='/api/attendance'&&req.method==='POST'){
 if(b.shift==='ot'&&b.type==='in'&&b.ot_confirmed!==true)return send(res,400,{error:'Bạn phải xác nhận thực sự đang OT tại studio trước khi bắt đầu.'});
 const studio=db.prepare('SELECT * FROM settings WHERE id=1').get();const distance=validatePosition(b.position,studio);
 const now=Date.now();workflows.assertOpen(now);const recent=db.prepare('SELECT id,user_id,type,time,distance,accuracy,shift,work_date,ot_confirmed_at,source,recorded_by,recorded_at,reason,(SELECT name FROM users WHERE users.id=events.recorded_by) recorded_by_name,(photo_jpeg IS NOT NULL) has_photo FROM events WHERE user_id=? ORDER BY time,id').all(user.id);
 let type,workDate;try{({type,workDate}=attendanceDecision(recent,b.shift,now));}catch(e){return send(res,409,{error:e.message});}
 if(b.type!==type)return send(res,409,{error:'Trạng thái ca đã thay đổi. Vui lòng tải lại trang.'});
 const last=recent.filter(e=>e.shift===b.shift).at(-1);if(last&&now-last.time<30000)return send(res,409,{error:'Vui lòng đợi 30 giây trước lần chấm tiếp theo.'});
 const photo=normalizeAttendancePhoto(b.photo);
 db.prepare('INSERT INTO events(user_id,type,time,distance,accuracy,shift,work_date,ot_confirmed_at,photo_jpeg,recorded_by,recorded_at) VALUES(?,?,?,?,?,?,?,?,?,?,?)').run(user.id,type,now,distance,b.position.accuracy,b.shift,workDate,b.shift==='ot'&&type==='in'?now:null,photo,user.id,now);return send(res,201,{ok:true,time:now,type,shift:b.shift});}
 if(path==='/api/dashboard'&&req.method==='GET'){
 const date=url.searchParams.get('date')||localDay(Date.now());if(!/^\d{4}-\d{2}-\d{2}$/.test(date)||Number.isNaN(Date.parse(date)))throw new Error('Ngày không hợp lệ.');
 const people=user.role==='employee'?[safeUser(user)]:db.prepare("SELECT id,name,email,role FROM users WHERE archived=0 AND status!='inactive' ORDER BY name").all();
 const all=db.prepare('SELECT id,user_id,type,time,distance,accuracy,shift,work_date,ot_confirmed_at,source,recorded_by,recorded_at,reason,(SELECT name FROM users WHERE users.id=events.recorded_by) recorded_by_name,(photo_jpeg IS NOT NULL) has_photo FROM events ORDER BY time,id').all();
 const daily=all.filter(e=>localDay(e.time)===date&&people.some(p=>p.id===e.user_id));
 const month=date.slice(0,7);return send(res,200,{date,otActive:people.filter(p=>completedPairs(all.filter(e=>e.user_id===p.id)).pending.some(e=>e.shift==='ot')).length,people:people.length,checkedIn:new Set(daily.filter(e=>e.type==='in').map(e=>e.user_id)).size,completed:daily.filter(e=>e.type==='out'&&['day_first','day_second'].includes(e.shift)).length,shifts:SHIFTS.map(s=>({...s,entries:daily.filter(e=>e.shift===s.id&&e.type==='in').length,exits:daily.filter(e=>e.shift===s.id&&e.type==='out').length})),payroll:workflows.monthly(user,month).rows.filter(p=>people.some(person=>person.id===p.id))});}
 if(path==='/api/overtime'&&req.method==='GET'){
 const month=url.searchParams.get('month');if(!/^\d{4}-(0[1-9]|1[0-2])$/.test(month||''))throw new Error('Tháng không hợp lệ.');const [y,m]=month.split('-').map(Number),start=Date.UTC(y,m-1,1)-7*3600000,end=Date.UTC(y,m,1)-7*3600000;
 const people=user.role==='employee'?[safeUser(user)]:db.prepare('SELECT id,name FROM users').all();let sessions=[];
 for(const person of people){const {pairs,pending}=completedPairs(db.prepare('SELECT id,user_id,type,time,distance,accuracy,shift,work_date,ot_confirmed_at,source,recorded_by,recorded_at,reason,(SELECT name FROM users WHERE users.id=events.recorded_by) recorded_by_name,(photo_jpeg IS NOT NULL) has_photo FROM events WHERE user_id=? ORDER BY time,id').all(person.id));
 for(const p of pairs)if(p.entry.shift==='ot'&&p.end>=start&&p.start<end)sessions.push({user_id:person.id,name:person.name,start:p.start,end:p.end,in_source:p.entry.source,out_source:p.exit.source,in_reason:p.entry.reason,out_reason:p.exit.reason,in_recorded_by:p.entry.recorded_by_name,out_recorded_by:p.exit.recorded_by_name,in_event_id:p.entry.id,out_event_id:p.exit.id,in_photo:!!p.entry.has_photo,out_photo:!!p.exit.has_photo,confirmed_at:p.entry.ot_confirmed_at||null,work_date:p.entry.work_date,hours:Math.round((p.end-p.start)/3600000*100)/100});
 for(const p of pending)if(p.shift==='ot'&&p.time<end&&Date.now()>=start)sessions.push({user_id:person.id,name:person.name,start:p.time,end:null,in_source:p.source,out_source:null,in_reason:p.reason,in_recorded_by:p.recorded_by_name,in_event_id:p.id,out_event_id:null,in_photo:!!p.has_photo,out_photo:false,confirmed_at:p.ot_confirmed_at||null,work_date:p.work_date,hours:null});}
 sessions.sort((a,b)=>b.start-a.start);return send(res,200,{sessions});}
 if(path==='/api/monthly'&&req.method==='GET')return send(res,200,workflows.monthly(user,url.searchParams.get('month')));
 if(path==='/api/commissions'&&req.method==='GET'){if(user.role==='employee')return send(res,403,{error:'Chỉ Admin và Quản lý được xem hoa hồng.'});const rows=user.role==='employee'?db.prepare('SELECT c.*,u.name FROM commissions c JOIN users u ON u.id=c.user_id WHERE c.user_id=? ORDER BY served_at DESC LIMIT 200').all(user.id):db.prepare('SELECT c.*,u.name FROM commissions c JOIN users u ON u.id=c.user_id ORDER BY served_at DESC LIMIT 200').all();return send(res,200,{rows});}
 if(user.role!=='admin'&&user.role!=='manager')return send(res,403,{error:'Bạn không có quyền truy cập.'});
 if(path==='/api/attendance/manual'&&req.method==='POST'){
 if(!Number.isSafeInteger(b.user_id)||!Number.isSafeInteger(b.time)||b.time<Date.parse('2020-01-01')||b.time>Date.now()||!['in','out'].includes(b.type)||typeof b.reason!=='string'||b.reason.trim().length<10||b.reason.length>500)throw new Error('Chọn nhân viên, giờ không ở tương lai và ghi lý do chấm hộ từ 10–500 ký tự.');
 workflows.assertOpen(b.time);const target=db.prepare('SELECT * FROM users WHERE id=?').get(b.user_id);if(!target||target.archived||target.status==='inactive')throw new Error('Nhân viên không tồn tại hoặc đã nghỉ việc.');
 try{authorizeEmployeeEdit(user,target,{});}catch(error){return send(res,403,{error:error.message});}
 const events=db.prepare('SELECT id,user_id,type,time,distance,accuracy,shift,work_date,ot_confirmed_at,source,recorded_by,recorded_at,reason,(SELECT name FROM users WHERE users.id=events.recorded_by) recorded_by_name,(photo_jpeg IS NOT NULL) has_photo FROM events WHERE user_id=? ORDER BY time,id').all(target.id);
 if(b.shift==='ot'&&b.type==='in'&&b.ot_confirmed!==true)return send(res,400,{error:'Xác nhận nhân viên thực sự làm OT trước khi chấm hộ.'});
 // Insert regular events in order within a shift; OT must remain chronological globally.
 const relevant=b.shift==='ot'?events:events.filter(e=>e.shift===b.shift&&e.work_date===localDay(b.time));if(relevant.some(e=>e.time>=b.time))return send(res,409,{error:'Đã có lượt công mới hơn tại thời điểm này. Vui lòng chấm bổ sung theo thứ tự thời gian, không ghi đè lịch sử.'});
 let decision;try{decision=attendanceDecision(events.filter(e=>e.time<=b.time),b.shift,b.time,{allowLateCheckout:true});}catch(error){return send(res,409,{error:error.message});}
 if(decision.type!==b.type)return send(res,409,{error:'Loại lượt chấm không phù hợp. Cần chấm vào trước rồi mới chấm ra; ca hoàn tất không được chấm lại.'});
 const now=Date.now(),id=transaction(()=>{const r=db.prepare("INSERT INTO events(user_id,type,time,shift,work_date,source,recorded_by,recorded_at,reason,ot_confirmed_at) VALUES(?,?,?,?,?,'manual',?,?,?,?)").run(target.id,b.type,b.time,b.shift,decision.workDate,user.id,now,b.reason.trim(),b.shift==='ot'&&b.type==='in'?now:null);audit(user.id,target.id,'manual_attendance');return Number(r.lastInsertRowid);});return send(res,201,{ok:true,id});}
 if(path==='/api/employees'&&req.method==='GET'){
 const all=url.searchParams.get('archived')==='1';return send(res,200,{users:db.prepare(employeeSelect()+(all?'':' WHERE u.archived=0')+' ORDER BY u.id DESC').all()});}
 if(path==='/api/employees'&&req.method==='POST'){
 const b2=validateEmployee(b,{create:true});try{authorizeEmployeeEdit(user,null,b2);}catch(error){return send(res,403,{error:error.message});}
 const id=createEmployee(user.id,b2);return send(res,201,{id});}
 const employeeRoute=path.match(/^\/api\/employees\/(\d+)(?:\/(delete|restore))?$/);
 if(employeeRoute&&req.method==='POST'){
 const id=Number(employeeRoute[1]),action=employeeRoute[2],target=db.prepare('SELECT * FROM users WHERE id=?').get(id);if(!target)return send(res,404,{error:'Không tìm thấy nhân viên.'});
 const change=action==='delete'?{archived:true,status:'inactive'}:action==='restore'?{status:'inactive'}:validateEmployee(b);
 try{authorizeEmployeeEdit(user,target,change);}catch(error){return send(res,403,{error:error.message});}
 if(!action&&target.archived)return send(res,409,{error:'Khôi phục hồ sơ trước khi chỉnh sửa.'});
 transaction(()=>{
 if(action==='delete'){db.prepare("UPDATE users SET archived=1,status='inactive' WHERE id=?").run(id);db.prepare('DELETE FROM sessions WHERE user_id=?').run(id);audit(user.id,id,'archive');}
 else if(action==='restore'){db.prepare("UPDATE users SET archived=0,status='inactive' WHERE id=?").run(id);audit(user.id,id,'restore');}
 else{db.prepare(`UPDATE users SET name=?,email=?,role=?,status=?,${PROFILE_FIELDS.map(f=>f+'=?').join(',')} WHERE id=?`).run(change.name,change.email,change.role,change.status,...PROFILE_FIELDS.map(f=>change[f]),id);db.prepare('INSERT OR REPLACE INTO salaries VALUES(?,?)').run(id,change.monthly_salary);if(change.password)db.prepare('UPDATE users SET password=? WHERE id=?').run(hash(change.password),id);if(change.password||change.status==='inactive'||change.role!==target.role)db.prepare('DELETE FROM sessions WHERE user_id=?').run(id);audit(user.id,id,'update');}
 });return send(res,200,{ok:true,archived:action==='delete'});}
 if(path.startsWith('/api/location/')&&req.method==='GET'){

 try{if(path==='/api/location/provinces')return send(res,200,{items:await locationService.provinces()});
 if(path==='/api/location/wards')return send(res,200,{items:await locationService.wards(url.searchParams.get('province')||'')});
 if(path==='/api/location/search')return send(res,200,{items:await locationService.search(url.searchParams.get('q')||'')});
 }catch(error){return send(res,502,{error:error.message.includes('fetch')||error.name==='TimeoutError'?'Chưa kết nối được API địa chỉ. Bạn có thể chọn ghim trực tiếp trên bản đồ hoặc lấy vị trí hiện tại.':error.message});}}
 if(path==='/api/settings'&&req.method==='POST'){

 if(![b.latitude,b.longitude,b.radius].every(Number.isFinite)||Math.abs(b.latitude)>90||Math.abs(b.longitude)>180||b.radius<20||b.radius>1000)throw new Error('Tọa độ không hợp lệ hoặc bán kính ngoài 20–1.000 m.');
 for(const field of ['location_label','province_code','ward_code','address'])if(b[field]!==undefined&&(typeof b[field]!=='string'||b[field].length>500))throw new Error('Địa chỉ studio không hợp lệ.');
 db.prepare('INSERT OR REPLACE INTO settings(id,latitude,longitude,radius,location_label,province_code,ward_code,address) VALUES(1,?,?,?,?,?,?,?)').run(b.latitude,b.longitude,Math.round(b.radius),b.location_label||'',b.province_code||'',b.ward_code||'',b.address||'');return send(res,200,{ok:true});}
 if(path==='/api/users'&&req.method==='GET')return send(res,200,{users:db.prepare("SELECT u.id,u.name,u.email,u.role,COALESCE(s.monthly_salary,8000000) monthly_salary FROM users u LEFT JOIN salaries s ON s.user_id=u.id WHERE u.archived=0 AND u.status!='inactive' ORDER BY u.name").all()});
 if(path==='/api/wages'&&req.method==='POST'){if(!Number.isInteger(b.user_id)||!db.prepare('SELECT id FROM users WHERE id=?').get(b.user_id)||!Number.isSafeInteger(b.monthly_salary)||b.monthly_salary<0||b.monthly_salary>1000000000)throw new Error('Lương cứng không hợp lệ.');const target=db.prepare('SELECT * FROM users WHERE id=?').get(b.user_id);try{authorizeEmployeeEdit(user,target,{});}catch(error){return send(res,403,{error:error.message});}if(target.archived)throw new Error('Nhân viên đã xóa cần khôi phục trước.');db.prepare('INSERT OR REPLACE INTO salaries VALUES(?,?)').run(b.user_id,b.monthly_salary);audit(user.id,b.user_id,'salary');return send(res,200,{ok:true});}
 if(path==='/api/commissions'&&req.method==='POST'){
 if(typeof b.id!=='string'||! /^[a-f0-9-]{36}$/i.test(b.id)||!Number.isInteger(b.user_id)||!db.prepare('SELECT id FROM users WHERE id=?').get(b.user_id)||!Number.isInteger(b.customers)||b.customers<1||b.customers>1000||!Number.isSafeInteger(b.served_at)||b.served_at>Date.now()||b.served_at<Date.parse('2020-01-01'))throw new Error('Nhân viên, số khách hoặc thời điểm phục vụ không hợp lệ.');
 if(db.prepare('SELECT id FROM commissions WHERE id=?').get(b.id))return send(res,409,{error:'Lượt khách này đã được ghi nhận.'});
 workflows.assertOpen(b.served_at);const overtime=hasOvertimeAt(db.prepare('SELECT id,user_id,type,time,distance,accuracy,shift,work_date,ot_confirmed_at,source,recorded_by,recorded_at,reason,(SELECT name FROM users WHERE users.id=events.recorded_by) recorded_by_name,(photo_jpeg IS NOT NULL) has_photo FROM events WHERE user_id=? ORDER BY time,id').all(b.user_id),b.served_at);const hour=new Date(b.served_at+7*3600000).getUTCHours();if(hour<9&&!overtime)throw new Error('Ngoài giờ làm và không có lượt OT phù hợp.');const rate=hour>=19||overtime?30000:15000;if(rate===30000&&!overtime)return send(res,409,{error:'Nhân viên chưa có lượt chấm OT bao phủ thời điểm phục vụ. Kiểm tra giờ bắt đầu/kết thúc OT trước khi ghi khách OT.'});
 db.prepare('INSERT INTO commissions VALUES(?,?,?,?,?,?,?)').run(b.id,b.user_id,b.served_at,b.customers,rate,user.id,Date.now());return send(res,201,{ok:true,rate});}
 if(path==='/api/role'&&req.method==='POST'){if(user.role!=='admin')return send(res,403,{error:'Chỉ Admin được phân quyền.'});if(!['admin','manager','employee'].includes(b.role)||!Number.isInteger(b.user_id))throw new Error('Phân quyền không hợp lệ.');const target=db.prepare('SELECT id,role FROM users WHERE id=?').get(b.user_id);if(!target)throw new Error('Không tìm thấy tài khoản.');if(target.id===user.id)throw new Error('Bạn không thể tự thay đổi quyền của mình.');db.prepare('UPDATE users SET role=? WHERE id=?').run(b.role,b.user_id);db.prepare('DELETE FROM sessions WHERE user_id=?').run(b.user_id);audit(user.id,b.user_id,'role');return send(res,200,{ok:true});}
 if(path==='/api/users'&&req.method==='POST'){if(user.role!=='admin')return send(res,403,{error:'Chỉ Admin được tạo tài khoản qua API này.'});const data=validateEmployee({...b,role:b.role||'employee',status:b.status||'active',monthly_salary:b.monthly_salary??8000000},{create:true});return send(res,201,{id:createEmployee(user.id,data)});}
 if(path==='/api/report'&&req.method==='GET'){const month=url.searchParams.get('month');if(month&&!/^\d{4}-(0[1-9]|1[0-2])$/.test(month))throw new Error('Tháng không hợp lệ.');return send(res,200,{events:db.prepare('SELECT e.id,e.user_id,u.name,u.email,e.type,e.time,e.distance,e.shift,e.work_date,e.source,e.reason,e.recorded_by,e.recorded_at,(SELECT name FROM users WHERE id=e.recorded_by) recorded_by_name,(e.photo_jpeg IS NOT NULL) has_photo FROM events e JOIN users u ON u.id=e.user_id ORDER BY e.time DESC,e.id DESC').all().filter(e=>!month||localDay(e.time).startsWith(month)||e.work_date?.startsWith(month))});}
 send(res,404,{error:'Không tìm thấy.'});
 }catch(error){send(res,error.status||400,{error:error.message.includes('UNIQUE')?'Email này đã được sử dụng.':error instanceof SyntaxError?'Dữ liệu không hợp lệ.':error.message});}
});
processPendingEmployeeImport();
server.listen(Number(process.env.PORT||3000),process.env.HOST||'127.0.0.1',()=>{writeFileSync(pidFile,String(process.pid),{mode:0o600});telegram.start();console.log(`Studio attendance running on port ${process.env.PORT||3000}. Initial setup token (local file): ${tokenPath}`);});
process.on('exit',()=>{try{if(readFileSync(pidFile,'utf8')===String(process.pid))rmSync(pidFile);}catch{}});
for(const signal of ['SIGINT','SIGTERM'])process.on(signal,()=>{telegram.stop();server.close(()=>{db.close();process.exit(0);});});
