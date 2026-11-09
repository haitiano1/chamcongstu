import {GUIDE_MEDIA} from './guide-media.js?v=20261109-ot1850';
// Tìm hiểu web (chủ dự án yêu cầu 03/11/2026): checklist từng chức năng cho Nhân viên. Mỗi mục có hướng dẫn từng bước + ảnh động
// (khung trình chiếu ảnh chụp app, vòng đỏ nhấp nháy chỗ cần bấm). Phải mở đọc đủ lâu mới tick được "đã được hướng dẫn và nắm rõ";
// đã tick là bằng chứng, không bỏ được. Admin/Quản lý xem tiến độ từng người, nhắc qua Telegram, xuất CSV.
const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const stamp=ms=>{const d=new Date(ms+7*3600000).toISOString();return `${d.slice(11,16)} ${d.slice(8,10)}/${d.slice(5,7)}/${d.slice(0,4)}`;};
const FRAME_MS=3200;

export function guidePage(user){const staff=user.role==='employee';return `<section class="finance-page guide-page" data-page="guide" hidden><div class="finance-heading"><div><span class="eyebrow">ANH GẤU STUDIO · HƯỚNG DẪN</span><h1>Tìm hiểu web</h1><p>${staff?'Đọc hướng dẫn từng chức năng, xem ảnh động, bấm "Mở trang này" để thử. Nắm rõ rồi mới tick xác nhận.':'Checklist hướng dẫn từng chức năng cho Nhân viên. Mỗi lượt tick lưu giờ xác nhận, không bỏ tick được.'}</p></div></div><div id="guide-body"><p class="hint">Đang tải…</p></div></section>`;}

function player(key,steps){const frames=GUIDE_MEDIA[key]||[];if(!frames.length)return '';
 return `<div class="guide-player" data-player="${key}"><div class="guide-screen"><img alt="" decoding="async" loading="lazy" src="assets/${esc(frames[0].img)}"><span class="guide-ring" aria-hidden="true"></span><span class="guide-tap" aria-hidden="true"></span></div><p class="guide-caption" aria-live="polite"></p><div class="guide-controls"><button type="button" data-guide-nav="-1" aria-label="Bước trước">‹</button><span class="guide-dots">${frames.map((_,i)=>`<i data-dot="${i}"></i>`).join('')}</span><button type="button" data-guide-play aria-label="Dừng / chạy">⏸</button><button type="button" data-guide-nav="1" aria-label="Bước sau">›</button></div></div>`;}

export function bindGuide({api,user,toast}){
 const page=document.querySelector('[data-page="guide"]'),body=document.querySelector('#guide-body');if(!page||!body)return;
 const staff=user.role==='employee';let data=null,loading=null;const players=new Map(),opened=new Map();
 // Khung trình chiếu: tự chạy khi mục đang mở, vòng đỏ đặt theo vị trí nút trên ảnh (tọa độ % trong guide-media.js).
 function bindPlayer(el,key,steps){const frames=GUIDE_MEDIA[key]||[];if(!frames.length)return;const img=el.querySelector('img'),ring=el.querySelector('.guide-ring'),tap=el.querySelector('.guide-tap'),cap=el.querySelector('.guide-caption'),play=el.querySelector('[data-guide-play]');
  let i=0,timer=null,paused=false,loaded=false;
  const show=n=>{i=(n+frames.length)%frames.length;const f=frames[i];img.src='assets/'+f.img;const [x,y,w,h]=f.box||[];ring.hidden=tap.hidden=!f.box;if(f.box){Object.assign(ring.style,{left:x+'%',top:y+'%',width:w+'%',height:h+'%'});Object.assign(tap.style,{left:(x+w/2)+'%',top:(y+h/2)+'%'});ring.style.animation=tap.style.animation='none';void ring.offsetWidth;ring.style.animation=tap.style.animation='';}
   cap.innerHTML=`<b>Bước ${f.step+1}</b> ${esc(steps[f.step]||'')}`;for(const d of el.querySelectorAll('[data-dot]'))d.classList.toggle('on',Number(d.dataset.dot)===i);
   const li=el.closest('.guide-item')?.querySelectorAll('.guide-steps li');li?.forEach((x,k)=>x.classList.toggle('now',k===f.step));};
  const start=()=>{stop();if(!paused)timer=setInterval(()=>show(i+1),FRAME_MS);},stop=()=>{clearInterval(timer);timer=null;};
  el.onclick=e=>{const nav=e.target.closest('[data-guide-nav]');if(nav){show(i+Number(nav.dataset.guideNav));start();}if(e.target.closest('[data-guide-play]')){paused=!paused;play.textContent=paused?'▶':'⏸';paused?stop():start();}};
  players.set(key,{start:()=>{if(!loaded){loaded=true;for(const f of frames){const pre=new Image();pre.src='assets/'+f.img;}}show(i);start();},stop});show(0);}
 function item(it,n){const at=data.mine[it.key],media=!!(GUIDE_MEDIA[it.key]||[]).length;
  return `<details class="guide-item${at?' done':''}" data-guide="${it.key}"><summary><span class="guide-num">${at?'✓':n}</span><span class="guide-title">${esc(it.title)}<small>${at?`Đã xác nhận ${stamp(at)}`:staff?'Chưa xác nhận':''}${media?' · 🎬 có ảnh động':''}</small></span></summary><div class="guide-content">${player(it.key,it.steps)}<ol class="guide-steps">${it.steps.map(s=>`<li>${esc(s)}</li>`).join('')}</ol><button type="button" class="secondary guide-open" data-guide-open="${it.page}">↗ Mở trang này để thử</button>${staff?(at?`<p class="guide-confirmed">✅ Bạn đã xác nhận đã được hướng dẫn và nắm rõ lúc ${stamp(at)}.</p>`:`<label class="guide-confirm"><input type="checkbox" data-guide-check="${it.key}" disabled><span>Tôi đã đọc, đã được hướng dẫn và nắm rõ chức năng này.<small data-guide-wait>Đọc hướng dẫn và xem ảnh động trước…</small></span></label>`):''}</div></details>`;}
 function progress(done,total){const pct=total?Math.round(done/total*100):0;return `<div class="guide-progress"><div class="guide-bar"><i style="width:${pct}%"></i></div><span><b>${done}/${total}</b> mục đã xác nhận</span></div>`;}
 function render(){players.forEach(p=>p.stop());players.clear();const items=data.items,total=data.total;let html='';
  if(staff)html+=`<section class="card guide-intro">${progress(data.done,total)}<p class="hint">${data.done>=total?'🎓 Bạn đã xác nhận đủ các mục. Có chức năng mới hoặc nội dung thay đổi thì mục đó sẽ cần xác nhận lại.':'Mở từng mục, đọc từng bước, xem ảnh động, bấm "Mở trang này" để thử. Ô xác nhận mở sau khi đọc. Đã tick là xác nhận bạn đã được hướng dẫn, không bỏ tick được.'}</p></section>`;
  if(data.can_manage){const rows=data.people.map(p=>{const c=p.checks||{},n=items.filter(it=>c[it.key]).length;return `<details class="guide-person"><summary><strong>${esc(p.name)}</strong>${progress(n,total)}</summary><ul>${items.map(it=>`<li class="${c[it.key]?'ok':''}">${c[it.key]?'✅':'⬜'} ${esc(it.title)}<small>${c[it.key]?stamp(c[it.key]):'Chưa xác nhận'}</small></li>`).join('')}</ul></details>`;}).join('');
   html+=`<section class="card guide-admin"><div class="guide-admin-head"><h2>Tiến độ nhân viên</h2><div class="guide-admin-actions"><button type="button" class="secondary" id="guide-remind">🔔 Nhắc người chưa xong</button><button type="button" class="secondary" id="guide-csv">⬇ Xuất CSV</button></div></div>${rows||'<p class="hint">Chưa có nhân viên.</p>'}<p class="hint">Mỗi lượt tick lưu giờ xác nhận và phiên bản nội dung. Sửa nội dung một mục (tăng phiên bản trong máy chủ) thì nhân viên phải xác nhận lại mục đó.</p></section><h2 class="guide-preview-title">Nội dung nhân viên thấy</h2>`;}
  html+=`<div class="guide-list">${items.map((it,i)=>item(it,i+1)).join('')}</div>`;body.innerHTML=html;
  for(const d of body.querySelectorAll('[data-guide]')){const key=d.dataset.guide,it=items.find(x=>x.key===key),pl=d.querySelector('[data-player]');if(pl)bindPlayer(pl,key,it.steps);
   d.ontoggle=()=>{const p=players.get(key);if(d.open){p?.start();armCheck(d,it);}else p?.stop();};}
  for(const b of body.querySelectorAll('[data-guide-open]'))b.onclick=()=>{document.querySelector(`[data-view="${b.dataset.guideOpen}"]`)?.click();};
  for(const c of body.querySelectorAll('[data-guide-check]'))c.onchange=()=>check(c);
  const remind=body.querySelector('#guide-remind');if(remind)remind.onclick=async()=>{remind.disabled=true;try{const r=await api('guide/remind',{});toast(r.people.length?`Đã nhắc ${r.people.length} người chưa xong (${r.sent} tin Telegram).`:'Mọi người đã xác nhận đủ.');}catch(e){toast(e.message);}remind.disabled=false;};
  const csv=body.querySelector('#guide-csv');if(csv)csv.onclick=()=>{const cell=v=>'"'+String(v??'').replace(/^[=+@\-]/,"'$&").replaceAll('"','""')+'"',lines=[['Nhân viên','Mục','Trạng thái','Giờ xác nhận']];for(const p of data.people)for(const it of items){const at=(p.checks||{})[it.key];lines.push([p.name,it.title,at?'Đã xác nhận':'Chưa xác nhận',at?stamp(at):'']);}
   const url=URL.createObjectURL(new Blob(['﻿'+lines.map(l=>l.map(cell).join(',')).join('\r\n')],{type:'text/csv;charset=utf-8;'}));const a=document.createElement('a');a.href=url;a.download='tim-hieu-web.csv';a.click();URL.revokeObjectURL(url);};
  renderCta();}
 // Ô xác nhận mở sau khi mục đã mở đủ lâu: mỗi bước ~2,5 giây (ít nhất 6 giây), tính dồn cả các lần mở trước.
 function armCheck(d,it){const c=d.querySelector('[data-guide-check]');if(!c||!c.disabled)return;const need=Math.max(6000,it.steps.length*2500),wait=d.querySelector('[data-guide-wait]');
  const tick=()=>{if(!d.open||!c.isConnected)return;const spent=(opened.get(it.key)||0)+500;opened.set(it.key,spent);const left=Math.ceil((need-spent)/1000);if(left<=0){c.disabled=false;if(wait)wait.textContent='Tick để xác nhận.';return;}if(wait)wait.textContent=`Đọc hướng dẫn và xem ảnh động trước… (${left}s)`;setTimeout(tick,500);};tick();}
 async function check(c){if(!c.checked)return;if(!confirm('Xác nhận bạn đã được hướng dẫn và nắm rõ chức năng này? Sau khi xác nhận không bỏ tick được.')){c.checked=false;return;}c.disabled=true;
  try{const r=await api('guide/check',{key:c.dataset.guideCheck,confirm:true});data.mine=r.mine;data.done=r.done;const open=c.closest('details');const key=open?.dataset.guide;render();const again=body.querySelector(`[data-guide="${key}"]`);
   const next=[...body.querySelectorAll('.guide-item:not(.done)')][0];toast(r.done>=r.total?'🎓 Bạn đã xác nhận đủ các mục. Cảm ơn bạn!':`Đã xác nhận. Còn ${r.total-r.done} mục.`);if(next){next.open=true;next.scrollIntoView({behavior:'smooth',block:'start'});}else again?.scrollIntoView({block:'start'});}
  catch(e){toast(e.message);c.checked=false;c.disabled=false;}}
 function renderCta(){const box=document.querySelector('#guide-cta');if(!box||!data)return;const left=data.total-data.done;box.hidden=!staff||left<=0;if(box.hidden)return;
  box.innerHTML=`<div><h2>📚 Tìm hiểu web</h2><p>Bạn đã xác nhận <b>${data.done}/${data.total}</b> chức năng. Đọc hướng dẫn và tick xác nhận các mục còn lại.</p>${progress(data.done,data.total)}</div><button type="button" class="secondary" data-guide-go>Xem hướng dẫn</button>`;
  box.querySelector('[data-guide-go]').onclick=()=>document.querySelector('[data-view="guide"]')?.click();}
 async function load(){if(loading)return loading;loading=(async()=>{try{data=await api('guide');render();}catch(e){body.innerHTML=`<p class="hint">${esc(e.message)}</p>`;}finally{loading=null;}})();return loading;}
 page.addEventListener('refresh',load);
 // Nhân viên: tải ngay để hiện khung nhắc ở trang Chấm công.
 if(staff)setTimeout(load,1500);
}
