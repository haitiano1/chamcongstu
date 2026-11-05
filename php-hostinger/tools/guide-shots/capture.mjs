// Chụp ảnh động cho trang Tìm hiểu web (vai Nhân viên test, lịch khách demo). Chạy qua run.sh, không chạy riêng.
// node capture.mjs <thư mục ra (đường dẫn Windows)> [danh sách mục, cách nhau dấu phẩy]
// Mỗi dòng S: [mục, bước (từ 0), JS chạy trước, chờ ms, [selector, chữ cần có, closest], vị trí cuộn, lấy thẻ cha].
// Mục/bước phải khớp GUIDE_ITEMS trong site/nhansu-private/lib/studio-rules.php.
import {launch,sleep} from './cdp.mjs';import fs from 'node:fs';
const OUT=process.argv[2],only=process.argv[3]||'';const W=390,H=844;
const b=await launch(9351,'ags-guide-cap',{width:W,height:H,dpr:1.5});
await b.login('nhanvien.test@local.test','X1l6MQDDSZsV');
await b.ev(`window.__find=(sel,text,up)=>{const vis=e=>e.getClientRects().length>0;let list=[...document.querySelectorAll(sel)].filter(vis);if(text){list=list.filter(e=>e.textContent.includes(text));list=list.filter(e=>![...e.children].some(c=>c.textContent.includes(text)&&vis(c)&&c.matches(sel)));}let e=list[0];if(e&&up)e=e.closest(up)||e;return e;};window.__close=()=>document.querySelectorAll('dialog[open]').forEach(d=>d.close());1`);
await b.ev(`window.__avail=()=>{[...document.querySelectorAll('[data-page="availability"] button')].find(e=>e.textContent.includes('›'))?.click();setTimeout(()=>{[...document.querySelectorAll('.avail-shift:not(:disabled)')].slice(0,3).forEach((e,k)=>setTimeout(()=>e.click(),k*400));},1800);};1`);
const nav=v=>`window.__close();document.querySelector('[data-view="${v}"]').click();`;
const click=(sel,text='')=>`window.__find(${JSON.stringify(sel)},${JSON.stringify(text)})?.click();`;
// [key, step, prep JS, wait, target [sel,text], block]
const S=[
 ['checkout',0,nav('attendance')+'scrollTo(0,0);',1500,['#check'],'center'],
 ['checkout',0,'',300,['[data-shift-choice="day_first"]'],'center'],
 ['app',0,nav('attendance')+'scrollTo(0,0);',1500,null],
 ['app',1,'scrollTo(0,0);',300,['#app-refresh']],
 ['app',2,'',200,['[data-account]']],
 ['app',3,'',200,['.bottom-nav']],
 ['telegram',0,'scrollTo(0,0);',300,['.tg-invite-go'],'center'],
 ['telegram',1,click('.tg-invite-go'),2500,['a,button','Mở Telegram'],'center'],
 ['telegram',2,'',300,['section.telegram-personal'],'center'],
 ['checkin',0,'scrollTo(0,0);',300,['[data-shift-choice]'],'center',true],
 ['checkin',1,click('[data-shift-choice="day_first"]'),600,['[data-shift-choice="day_first"]'],'center'],
 ['checkin',2,'',300,['#check'],'center'],
 ['fullday',0,'',300,['[data-shift-choice="full_day"]'],'center'],
 ['fullday',1,click('[data-shift-choice="full_day"]'),800,['dialog[open] *','Tôi xác nhận hôm nay'],'center'],
 ['ot',0,'window.__close();'+click('dialog[open] button','Không, chọn lại')+click('[data-shift-choice="day_first"]'),600,['#ot-mode'],'center'],
 ['ot',2,'',300,['section.overtime-history'],'start'],
 ['supplement',0,'',300,['#request-start'],'center'],
 ['supplement',1,click('#request-start'),900,['dialog[open] form'],'start'],
 ['supplement',2,'window.__close();',300,['section.correction-panel summary'],'center'],
 ['support',0,'window.__close();',300,['[data-support-new]'],'center'],
 ['meetings',0,'scrollTo(0,0);',300,['button','Đọc & xác nhận'],'center'],
 ['bookings',0,nav('bookings')+'scrollTo(0,0);',3000,['button','Lịch của tôi'],'center'],
 ['bookings',1,'',300,['.booking-card','Chị Lan'],'center'],
 ['bookings',1,click('.booking-card','Chị Lan'),1200,['dialog[open] .dialog-heading'],'start'],
 ['bookings',2,'window.__close();'+click('.booking-card','Team lớp'),1500,['dialog[open] .booking-team-form'],'center'],
 ['availability',0,nav('availability')+'scrollTo(0,0);',2500,['[data-page="availability"] button','›'],'center'],
 ['availability',1,'window.__avail();',4500,['.avail-shift'],'center'],
 ['availability',1,'',300,['[data-page="availability"] button.primary'],'center'],
 ['tasks',0,nav('tasks')+'scrollTo(0,0);',2500,['[data-task-status="todo"]'],'center'],
 ['tasks',1,'',300,['.task-card','Lau kính'],'center'],
 ['tasks',1,'',200,['[data-task-action="done"]'],'center'],
 ['tasks',2,'',300,['.task-decline-note','Chưa duyệt'],'center'],
 ['tasks',3,'',300,['.task-decline-button'],'center'],
 ['support',0,'',300,['[data-task-tab="support"]'],'center'],
 ['support',1,click('[data-task-tab="support"]')+'setTimeout(()=>document.querySelector("#support-create")?.click(),900);',2200,['dialog[open] .support-cats'],'center'],
 ['support',2,'window.__close();'+click('[data-task-tab="tasks"]'),400,null],
 ['payroll',0,nav('payroll')+'scrollTo(0,0);',3000,['[data-page="payroll"] *','Bảng công của tôi','.card'],'start'],
 ['payroll',1,'',400,['#bonus-panel'],'start'],
 ['advances',0,nav('advances')+'scrollTo(0,0);',2500,['#advance-create'],'center'],
 ['advances',0,click('#advance-create'),900,['dialog[open] form'],'start'],
 ['advances',1,'window.__close();',300,['[data-page="advances"] *','Quy định ứng lương'],'center'],
 ['kpi',1,nav('rules')+'scrollTo(0,0);',2500,['[data-page="rules"] *','Mức trừ KPI'],'start'],
 ['kpi',2,nav('eventlog')+'scrollTo(0,0);',2500,['[data-page="eventlog"] h1,[data-page="eventlog"] h2'],'center'],
 ['meetings',1,nav('meetings')+'scrollTo(0,0);',2500,['[data-page="meetings"] *','Họp đầu tuần','article,li,.card,button'],'center'],
 ['meetings',1,click('[data-page="meetings"] *','Họp đầu tuần'),1500,['button','Đã đọc và xác nhận'],'center'],
];
const media={};let n={};
for(const [key,step,prep,wait,target,block='center',parent] of S){if(only&&!only.split(',').includes(key))continue;
 await b.ev(prep+'1');await sleep(wait);
 let box=null;if(target){const r=await b.ev(`(()=>{let e=window.__find(${JSON.stringify(target[0])},${JSON.stringify(target[1]||'')},${JSON.stringify(target[2]||'')});if(!e)return null;${parent?'e=e.parentElement;':''}e.scrollIntoView({block:${JSON.stringify(block)}});return 1;})()`);await sleep(350);
  if(r){const rect=await b.ev(`(()=>{let e=window.__find(${JSON.stringify(target[0])},${JSON.stringify(target[1]||'')},${JSON.stringify(target[2]||'')});${parent?'e=e.parentElement;':''}const r=e.getBoundingClientRect();return [r.left,r.top,r.width,r.height];})()`);
   const p=4,[x,y,w,h]=rect,x0=Math.max(0,x-p),y0=Math.max(0,y-p),x1=Math.min(W,x+w+p),y1=Math.min(H,y+h+p);if(y1>y0&&x1>x0)box=[+(x0/W*100).toFixed(2),+(y0/H*100).toFixed(2),+((x1-x0)/W*100).toFixed(2),+((y1-y0)/H*100).toFixed(2)];}
  else console.log('MISSING',key,step,target.join(' | '));}
 n[key]=(n[key]||0)+1;const img=`guide-${key}-${n[key]}.jpg`;const size=await b.shot(OUT+'\\'+img,{format:'jpeg',quality:62});
 (media[key]??=[]).push({img,step,...(box?{box}:{})});console.log(img,step,size,box?'ring':'');
}
fs.writeFileSync(OUT+'\\media'+(only?'-'+only:'')+'.json',JSON.stringify(media));b.close();process.exit(0);
