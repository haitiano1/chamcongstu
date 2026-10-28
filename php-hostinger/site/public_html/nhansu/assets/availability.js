import {loadRules,ruleLogDialog} from './rule-picker.js?v=20261027-rules2';
// Đăng ký ca theo tuần: tick ca chắc chắn đi làm, tối thiểu 5 ca rồi chốt; bỏ ca đã chốt là nghỉ gấp.
const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const stamp=v=>v?new Intl.DateTimeFormat('vi-VN',{timeZone:'Asia/Ho_Chi_Minh',dateStyle:'short',timeStyle:'short'}).format(v):'—';
const WEEKDAY=['Thứ 2','Thứ 3','Thứ 4','Thứ 5','Thứ 6','Thứ 7','Chủ nhật'];
const SHIFT={day_first:{name:'Ca 1',time:'9h–14h',start:9},day_second:{name:'Ca 2',time:'14h–19h',start:14}};
const dm=day=>day.slice(8,10)+'/'+day.slice(5,7);
const addDays=(day,n)=>new Date(Date.parse(day+'T00:00:00Z')+n*86400000).toISOString().slice(0,10);
const shiftStart=(day,shift)=>Date.parse(day+'T00:00:00+07:00')+SHIFT[shift].start*3600000;
const URGENT_WARNING=`<div class="avail-warning" role="alert"><strong>Bạn chắc chứ? Nghỉ ca làm gấp sẽ bị:</strong><ul><li>Phạt cảnh cáo 100 (tái phạm bị kỉ luật 300)</li><li>Mất lương cứng cố định ca đó</li><li>Mất hoa hồng khách</li><li>Giảm ngày tính thưởng chuyên cần</li></ul></div>`;

function dialog(title,body,cls=''){const d=document.createElement('dialog');d.className='workflow-dialog finance-dialog '+cls;d.innerHTML=`<div class="dialog-heading"><h2>${esc(title)}</h2><button class="dialog-close" aria-label="Đóng" type="button">×</button></div>${body}`;document.body.append(d);d.querySelector('.dialog-close').onclick=()=>d.close();d.addEventListener('close',()=>d.remove(),{once:true});d.showModal();return d;}

export function availabilityPage(user){return `<section class="finance-page avail-page" data-page="availability" hidden><div class="finance-heading"><div><span class="eyebrow">ANH GẤU STUDIO · ĐĂNG KÝ CA</span><h1>Đăng ký ca</h1><p>Tick những ca bạn chắc chắn đi làm trong tuần, tối thiểu 5 ca, rồi bấm Chốt.</p></div></div>
<div class="card avail-week-bar"><button class="secondary" type="button" id="avail-prev" aria-label="Tuần trước">‹</button><div><strong id="avail-week-title">Tuần</strong><small id="avail-week-range"></small></div><button class="secondary" type="button" id="avail-next" aria-label="Tuần sau">›</button></div>
${user.role!=='admin'?`<section class="card avail-mine"><div class="avail-status"><div><span id="avail-badge" class="avail-badge"></span><p id="avail-count" class="avail-count"></p></div><div class="avail-progress"><i id="avail-progress"></i></div></div><p class="hint" id="avail-hint"></p><div id="avail-grid" class="avail-grid" aria-live="polite"></div><button class="primary" type="button" id="avail-commit">Chốt đăng ký tuần</button></section>`:''}
${user.role!=='employee'?`<section class="card avail-team"><div class="section-head"><h2>Đăng ký của nhân viên</h2></div><div id="avail-cancels"></div><div id="avail-team-list"></div></section>`:''}
</section>`;}

export function bindAvailability({api,user,toast}){
 const page=document.querySelector('[data-page="availability"]');if(!page)return;
 let week=null,data=null,busy=false;
 const $=s=>page.querySelector(s);
 // Còn ≤ urgent_days ngày tới ngày của ca (tính theo ngày Việt Nam) thì bỏ ca là nghỉ gấp.
 const isUrgent=day=>(Date.parse(day+'T00:00:00Z')-Date.parse(new Date(data.now+7*3600000).toISOString().slice(0,10)+'T00:00:00Z'))/86400000<=data.urgent_days;
 function renderMine(){
  const grid=$('#avail-grid');if(!grid)return;const m=data.mine,on=new Set(m.shifts.map(s=>s.day+'|'+s.shift)),cancelled=new Set(m.cancels.map(c=>c.day+'|'+c.shift)),count=m.shifts.length,min=data.min,today=new Date(data.now+7*3600000).toISOString().slice(0,10);
  $('#avail-badge').className='avail-badge '+(m.committed?'done':'draft');$('#avail-badge').textContent=m.committed?(m.auto?'Đã khóa (tuần đã bắt đầu)':'Đã chốt'):'Chưa chốt';
  $('#avail-count').innerHTML=`<strong>${count}</strong> / ${min} ca tối thiểu`;$('#avail-progress').style.width=Math.min(100,count/min*100)+'%';$('#avail-progress').classList.toggle('ok',count>=min);
  $('#avail-hint').textContent=m.committed?`Đã chốt. Bỏ ca trước từ ${data.urgent_days+1} ngày trở lên: không bị tính (vẫn giữ tối thiểu ${min} ca). Bỏ ca trong vòng ${data.urgent_days} ngày: tính là nghỉ gấp.`:`Chọn tối thiểu ${min} ca rồi bấm Chốt. Trước khi chốt có thể đổi thoải mái.`;
  grid.innerHTML=data.days.map((day,i)=>`<div class="avail-day${day===today?' today':''}"><div class="avail-day-label"><strong>${WEEKDAY[i]}</strong><span>${dm(day)}${day===today?' · Hôm nay':''}</span></div><div class="avail-shifts">${Object.keys(SHIFT).map(s=>{const key=day+'|'+s,past=shiftStart(day,s)<=data.now,sel=on.has(key),cx=!sel&&cancelled.has(key),locked=sel&&m.committed&&!past&&isUrgent(day);return `<button type="button" class="avail-shift${sel?' on':''}${cx?' cancelled':''}" data-day="${day}" data-shift="${s}" aria-pressed="${sel}" ${past?'disabled':''}><span class="avail-check" aria-hidden="true">${sel?'✓':cx?'!':''}</span><span><b>${SHIFT[s].name}</b><small>${cx?'Nghỉ gấp':SHIFT[s].time}</small>${locked?'<em class="avail-lock">Bỏ = nghỉ gấp</em>':''}</span></button>`;}).join('')}</div></div>`).join('');
  const commit=$('#avail-commit');commit.hidden=m.committed;commit.disabled=count<min;commit.textContent=count<min?`Chọn thêm ${min-count} ca để chốt`:`Chốt đăng ký tuần (${count} ca)`;
  for(const b of grid.querySelectorAll('.avail-shift'))b.onclick=()=>toggle(b.dataset.day,b.dataset.shift,b.getAttribute('aria-pressed')!=='true');
 }
 function renderTeam(){
  const list=$('#avail-team-list');if(!list||!data.people)return;
  const short=['T2','T3','T4','T5','T6','T7','CN'];
  list.innerHTML=data.people.map(p=>{const by=new Set(p.shifts.map(s=>s.day+'|'+s.shift)),cx=new Set(p.cancels.map(c=>c.day+'|'+c.shift));const state=!p.committed?['draft','Chưa chốt']:p.active<data.min?['warn','Dưới '+data.min+' ca']:['done','Đã chốt'];
   return `<article class="avail-person"><div class="avail-person-head"><strong>${esc(p.name)}</strong><span class="avail-badge ${state[0]}">${state[1]}</span><span class="avail-person-count">${p.active} ca${p.cancels.length?` · <b class="avail-red">${p.cancels.length} nghỉ gấp</b>`:''}</span></div><div class="avail-mini">${data.days.map((day,i)=>`<div><small>${short[i]}</small>${Object.keys(SHIFT).map(s=>{const k=day+'|'+s;return `<i class="${by.has(k)?'on':cx.has(k)?'cx':''}" title="${SHIFT[s].name}">${by.has(k)?SHIFT[s].name.slice(-1):cx.has(k)?'!':''}</i>`;}).join('')}</div>`).join('')}</div></article>`;}).join('')||'<p class="hint">Chưa có nhân viên.</p>';
  const cancels=$('#avail-cancels');
  cancels.innerHTML=data.cancels.length?`<div class="avail-cancel-box"><h3>⚠️ Nghỉ gấp trong tuần (${data.cancels.length})</h3>${data.cancels.map(c=>`<div class="avail-cancel"><div><strong>${esc(c.name)}</strong> · ${SHIFT[c.shift].name} ${dm(c.day)}<small>Báo lúc ${stamp(c.cancelled_at)} · Lý do: ${esc(c.reason)}</small><small>Lần ${c.ordinal} trong tháng · đề xuất ${c.suggested_deduction} điểm KPI</small></div>${c.event_log_id?'<span class="avail-badge done">Đã ghi Event log</span>':data.can_record_log?`<button class="secondary" type="button" data-cancel-log="${c.id}">Ghi Event log</button>`:''}</div>`).join('')}</div>`:'';
  for(const b of cancels.querySelectorAll('[data-cancel-log]'))b.onclick=()=>logDialog(data.cancels.find(c=>c.id===Number(b.dataset.cancelLog)));
 }
 async function logDialog(c){let rules=[];try{rules=await loadRules(api);}catch{}
  ruleLogDialog({title:'Ghi Event log · Nghỉ gấp',intro:`<strong>${esc(c.name)}</strong> nghỉ gấp ${SHIFT[c.shift].name} ngày ${dm(c.day)} (lần ${c.ordinal} trong tháng).`,description:`Nghỉ gấp ${SHIFT[c.shift].name} (${SHIFT[c.shift].time}) ngày ${dm(c.day)}. Báo lúc ${stamp(c.cancelled_at)}. Lý do: ${c.reason}`,suggested:c.suggested_deduction,choices:[0,100,300],rules,submit:async b=>{await api('availability/cancel-log',{id:c.id,...b});toast('Đã ghi vào Event log.');load();}});}
 function urgentDialog(day,shift){return new Promise(resolve=>{const d=dialog('Nghỉ ca gấp',`${URGENT_WARNING}<form><p class="avail-urgent-shift">${SHIFT[shift].name} (${SHIFT[shift].time}) · ${WEEKDAY[data.days.indexOf(day)]} ${dm(day)}</p><label>Lý do nghỉ (bắt buộc)<textarea name="reason" rows="3" required minlength="5" maxlength="500" placeholder="Ghi rõ lý do bất khả kháng"></textarea></label><p class="error" role="alert"></p><div class="avail-urgent-actions"><button class="primary" type="button" data-keep>Giữ ca, tôi vẫn đi làm</button><button class="danger-button" type="submit">Tôi vẫn nghỉ ca này</button></div></form>`,'avail-urgent-dialog');let done=false;
  d.querySelector('[data-keep]').onclick=()=>d.close();d.addEventListener('close',()=>{if(!done)resolve(null);},{once:true});
  d.querySelector('form').onsubmit=e=>{e.preventDefault();const reason=new FormData(e.target).get('reason').trim();if(reason.length<5){d.querySelector('.error').textContent='Ghi lý do tối thiểu 5 ký tự.';return;}done=true;d.close();resolve(reason);};});}
 async function toggle(day,shift,on){
  if(busy)return;let body={day,shift,on};
  if(!on&&data.mine.committed&&isUrgent(day)){const reason=await urgentDialog(day,shift);if(!reason)return;body={...body,confirm_urgent:true,reason};}
  busy=true;try{const r=await api('availability/toggle',body);if(r.urgent)toast('Đã ghi nhận nghỉ gấp và báo quản lý.');await load();}catch(e){toast(e.message);}finally{busy=false;}
 }
 async function load(){
  try{data=await api('availability'+(week?'?week='+week:''));week=data.week_start;
   const label=week===data.this_week?'Tuần này':week===addDays(data.this_week,7)?'Tuần sau':week<data.this_week?'Tuần trước':'Tuần '+dm(week);
   $('#avail-week-title').textContent=label;$('#avail-week-range').textContent=dm(data.days[0])+' – '+dm(data.days[6]);
   $('#avail-prev').disabled=week<=addDays(data.this_week,-28);$('#avail-next').disabled=week>=addDays(data.this_week,28);
   renderMine();renderTeam();
  }catch(e){toast(e.message);}
 }
 $('#avail-prev').onclick=()=>{week=addDays(week,-7);load();};$('#avail-next').onclick=()=>{week=addDays(week,7);load();};
 const commit=$('#avail-commit');if(commit)commit.onclick=async()=>{commit.disabled=true;try{await api('availability/commit',{week});toast('Đã chốt đăng ký tuần. Bỏ ca từ giờ là nghỉ gấp.');await load();}catch(e){toast(e.message);commit.disabled=false;}};
 page.addEventListener('refresh',load);
 if(!page.hidden)load();
 // Thứ 7 và Chủ nhật: popup nhắc chốt ca tuần tới khi mở web; "Không hiện hôm nay" nhớ theo ngày trên máy.
 function weekendPopup(s){
  const vn=new Date(Date.now()+7*3600000),dow=vn.getUTCDay(),today=vn.toISOString().slice(0,10),key='avail-popup-skip-'+today;
  if(dow!==6&&dow!==0)return;try{if(localStorage.getItem(key))return;}catch{}
  const d=dialog('Đăng ký ca tuần tới',`<div class="avail-popup"><div class="avail-popup-icon" aria-hidden="true">📅</div><p><strong>Bạn chưa chốt ca làm cho tuần tới</strong> (${dm(s.next_week)} – ${dm(addDays(s.next_week,6))}).</p><p>Vào tick những ca chắc chắn đi làm, tối thiểu ${s.min} ca, rồi bấm Chốt. Đã chọn: <b>${s.next_count}/${s.min}</b> ca.</p><button class="primary" type="button" data-go>Đăng ký ngay</button><label class="avail-skip"><input type="checkbox" data-skip> Không hiện hôm nay</label></div>`,'avail-popup-dialog');
  d.addEventListener('close',()=>{if(d.querySelector('[data-skip]').checked)try{localStorage.setItem(key,'1');}catch{}});
  d.querySelector('[data-go]').onclick=()=>{d.close();week=s.next_week;document.querySelector('.page-tabs [data-view="availability"]')?.click();};
 }
 // Nhắc trên trang Chấm công khi chưa chốt tuần sau.
 api('availability/summary').then(s=>{if(!s.needs_registration||s.next_committed)return;weekendPopup(s);const host=document.querySelector('[data-page="attendance"].layout')||document.querySelector('[data-page="attendance"]');if(!host||document.querySelector('.avail-reminder'))return;host.insertAdjacentHTML('beforebegin',`<div class="avail-reminder" data-page="attendance"><div><strong>Chưa chốt đăng ký ca tuần sau</strong><small>Đã chọn ${s.next_count}/${s.min} ca tối thiểu</small></div><button class="primary" type="button">Đăng ký ngay</button></div>`);const el=document.querySelector('.avail-reminder');el.hidden=host.hidden;el.querySelector('button').onclick=()=>{week=s.next_week;document.querySelector('.page-tabs [data-view="availability"]')?.click();};}).catch(()=>{});
}
