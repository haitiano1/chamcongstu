// Bảng chấm công tháng (chủ dự án yêu cầu 12/11/2026): lịch tháng, ngày nào nhân viên có chấm công thì có tag tên
// kèm Ca 1 / Ca 2 / OT. Chỉ Admin/Quản lý (dữ liệu từ GET /report, máy chủ đã chặn nhân viên). Bấm tag xem giờ vào/ra.
const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const vnDay=ms=>new Date(ms+7*3600000).toISOString().slice(0,10);
const clock=ms=>new Date(ms+7*3600000).toISOString().slice(11,16);
const WEEK=['T2','T3','T4','T5','T6','T7','CN'];
const SHIFT={day_first:'Ca 1',day_second:'Ca 2',ot:'OT'};
const ORDER=['day_first','day_second','ot'];
const COLOURS=['#2563eb','#d9480f','#0f9d58','#9c36b5','#c2255c','#0b7285','#a87900','#495057'];
const short=n=>{const w=String(n||'').trim().split(/\s+/);return w.at(-1)||n;};

export function timesheetPage(){return `<section class="card timesheet" data-page="attendance"><div class="section-head"><div><h2>Bảng chấm công tháng</h2><p>Ngày nào nhân viên có chấm công thì hiện tên kèm Ca 1 / Ca 2 / OT. Bấm vào tên để xem giờ vào, ra.</p></div></div><div class="ts-filters"><div class="ts-month"><button type="button" class="secondary" data-ts-prev aria-label="Tháng trước">‹</button><label class="ts-month-label"><span id="ts-month-text"></span><input type="month" id="ts-month" aria-label="Chọn tháng" value="${vnDay(Date.now()).slice(0,7)}"></label><button type="button" class="secondary" data-ts-next aria-label="Tháng sau">›</button></div><label>Nhân viên<select id="ts-person"><option value="">Tất cả nhân viên</option></select></label></div><div class="ts-legend"><span class="ts-chip done">Đủ vào/ra</span><span class="ts-chip open">Đang làm</span><span class="ts-chip missing">Thiếu chấm ra</span><span class="ts-chip closed">Không tính công</span><span class="ts-chip done late">Muộn</span><span class="ts-chip done manual">Chấm hộ ✍</span></div><p id="ts-status" class="hint" role="status" aria-live="polite"></p><div id="ts-result"></div></section>`;}

// Ghép lượt vào/ra theo người + ca: lượt ra đóng lượt vào gần nhất còn mở của cùng ca; ngày lấy theo work_date của lượt vào.
function build(events,today){const byUser=new Map();const sorted=[...events].sort((a,b)=>a.time-b.time||a.id-b.id);
 for(const e of sorted){if(!byUser.has(e.user_id))byUser.set(e.user_id,{name:e.name,open:{},days:{}});const u=byUser.get(e.user_id),shift=ORDER.includes(e.shift)?e.shift:'legacy';
  const slot=d=>{const day=(u.days[d]||={});return day[shift]||={segs:[]};};
  if(e.type==='in'){const d=e.work_date||vnDay(e.time),s={in:e,out:null,day:d};slot(d).segs.push(s);u.open[shift]=s;}
  else{const s=u.open[shift];if(s&&!s.out){s.out=e;u.open[shift]=null;}else slot(e.work_date||vnDay(e.time)).segs.push({in:null,out:e});}}
 for(const u of byUser.values())for(const [d,shifts] of Object.entries(u.days))for(const [id,x] of Object.entries(shifts)){
  const last=x.segs.at(-1),first=x.segs.find(s=>s.in)?.in;x.late=first?.late&&!first.late.excused?first.late:null;x.noCredit=x.segs.some(s=>s.in?.no_credit);
  x.manual=x.segs.some(s=>s.in?.source==='manual'||s.out?.source==='manual');
  x.state=x.noCredit?'closed':x.segs.some(s=>!s.in)?'missing':last.out?'done':(d>=today||id==='ot'&&d>=vnDay(Date.now()-86400000))?'open':'missing';}
 return byUser;}

export function bindTimesheet({api,toast}){const root=document.querySelector('.timesheet');if(!root)return;
 const month=root.querySelector('#ts-month'),person=root.querySelector('#ts-person'),out=root.querySelector('#ts-result'),status=root.querySelector('#ts-status');
 let users=[],data=new Map(),version=0;const colour=id=>COLOURS[Math.max(0,users.findIndex(u=>u.id===id))%COLOURS.length];
 const label=()=>{const [y,m]=month.value.split('-');root.querySelector('#ts-month-text').textContent=`Tháng ${Number(m)}/${y}`;};
 const shiftMonth=n=>{const [y,m]=month.value.split('-').map(Number),d=new Date(Date.UTC(y,m-1+n,1));month.value=d.toISOString().slice(0,7);load();};
 function render(){label();const [y,m]=month.value.split('-').map(Number),days=new Date(Date.UTC(y,m,0)).getUTCDate(),lead=(new Date(Date.UTC(y,m-1,1)).getUTCDay()+6)%7,today=vnDay(Date.now());
  const people=users.filter(u=>!person.value||u.id===Number(person.value));let total=0;const totals=new Map(people.map(u=>[u.id,{c1:0,c2:0,ot:0,missing:0,late:0}]));
  const cells=[];for(let i=0;i<lead;i++)cells.push('<div class="ts-day empty" aria-hidden="true"></div>');
  for(let d=1;d<=days;d++){const key=`${month.value}-${String(d).padStart(2,'0')}`,dow=(lead+d-1)%7;
   const tags=people.map(u=>{const day=data.get(u.id)?.days[key];if(!day)return '';const t=totals.get(u.id);total++;
    const chips=[...ORDER,'legacy'].filter(id=>day[id]).map(id=>{const x=day[id];if(x.state==='done'&&!x.noCredit){if(id==='day_first')t.c1++;else if(id==='day_second')t.c2++;else if(id==='ot')t.ot++;}if(x.state==='missing')t.missing++;if(x.late)t.late++;
     return `<span class="ts-chip ${x.state}${x.late?' late':''}${x.manual?' manual':''}">${SHIFT[id]||'Ca cũ'}${x.manual?' ✍':''}</span>`;}).join('');
    return `<button type="button" class="ts-tag" data-ts-user="${u.id}" data-ts-day="${key}" style="--ts-c:${colour(u.id)}"><b>${esc(short(u.name))}</b><span>${chips}</span></button>`;}).join('');
   cells.push(`<div class="ts-day${key===today?' today':''}${dow>4?' weekend':''}${tags?'':' none'}"><div class="ts-date"><b>${d}</b><small>${WEEK[dow]}</small></div>${tags}</div>`);}
  out.innerHTML=`<div class="ts-grid"><div class="ts-head">${WEEK.map(w=>`<span>${w}</span>`).join('')}</div><div class="ts-body">${cells.join('')}</div></div><div class="ts-sum"><h3>Tổng tháng · ca đủ vào và ra</h3>${people.map(u=>{const t=totals.get(u.id);return `<div class="ts-sum-row"><span class="ts-sum-name"><span class="ts-dot" style="--ts-c:${colour(u.id)}"></span>${esc(u.name)}</span><span class="ts-sum-nums"><span>Ca 1 <b>${t.c1}</b></span><span>Ca 2 <b>${t.c2}</b></span><span>OT <b>${t.ot}</b></span>${t.missing?`<span class="out">Thiếu ra <b>${t.missing}</b></span>`:''}${t.late?`<span class="out">Muộn <b>${t.late}</b></span>`:''}</span></div>`;}).join('')}</div>`;
  status.textContent=total?`${total} ngày công có chấm công trong tháng · Ca 1 / Ca 2 / OT đếm ca đủ vào và ra`:'Chưa có lượt chấm công trong tháng này.';
  for(const b of out.querySelectorAll('[data-ts-user]'))b.onclick=()=>detail(Number(b.dataset.tsUser),b.dataset.tsDay);}
 function detail(id,key){const u=users.find(x=>x.id===id),day=data.get(id)?.days[key];if(!u||!day)return;const [y,m,d]=key.split('-');
  const rows=[...ORDER,'legacy'].filter(s=>day[s]).map(s=>{const x=day[s];return `<div class="ts-detail-row"><strong>${SHIFT[s]||'Ca cũ'}</strong><div>${x.segs.map(g=>`<div>${g.in?clock(g.in.time):'—'} → ${g.out?(vnDay(g.out.time)!==key?clock(g.out.time)+' (hôm sau)':clock(g.out.time)):'<span class="out">chưa chấm ra</span>'}${g.in?.source==='manual'||g.out?.source==='manual'?' <small>· chấm hộ</small>':''}${g.in?.source==='auto'||g.out?.source==='auto'?' <small>· tự chuyển ca</small>':''}</div>`).join('')}${x.late?`<small class="out">Muộn ${x.late.minutes} phút</small>`:''}${x.noCredit?'<small class="out">Đóng điểm danh · không tính công</small>':''}</div></div>`;}).join('');
  const dlg=document.createElement('dialog');dlg.className='ot-confirm-dialog ts-detail';dlg.innerHTML=`<h2><span class="ts-dot" style="--ts-c:${colour(id)}"></span>${esc(u.name)}</h2><p class="hint">Ngày ${d}/${m}/${y}</p>${rows}<div class="dialog-actions"><button type="button" class="primary" data-close>Đóng</button></div>`;
  document.body.append(dlg);dlg.querySelector('[data-close]').onclick=()=>dlg.close();dlg.addEventListener('close',()=>dlg.remove(),{once:true});dlg.showModal();}
 async function load(){const request=++version;label();status.textContent='Đang tải bảng chấm công…';try{if(!users.length){({users}=await api('users'));const keep=person.value;person.innerHTML='<option value="">Tất cả nhân viên</option>'+users.map(u=>`<option value="${u.id}">${esc(u.name)}</option>`).join('');person.value=keep;}
  const r=await api('report?month='+month.value);if(request!==version)return;const ids=new Set(users.map(u=>u.id));data=build(r.events.filter(e=>ids.has(e.user_id)),vnDay(Date.now()));render();}catch(e){if(request===version){status.textContent='Chưa tải được bảng chấm công.';toast(e.message);}}}
 month.onchange=load;person.onchange=render;root.querySelector('[data-ts-prev]').onclick=()=>shiftMonth(-1);root.querySelector('[data-ts-next]').onclick=()=>shiftMonth(1);root.addEventListener('refresh',load);load();}
