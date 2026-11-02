// Bảng lương. Nhân viên giữ bảng công cũ (app.js); Admin/Quản lý xem bảng gọn, bấm tên mở phiếu lương
// tính từ trên xuống theo mẫu chủ dự án (31/10/2026): lương → OT → khách chụp → thưởng → tạm tính → KPI → tiền cuối.
const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c]);
const num=v=>new Intl.NumberFormat('vi-VN').format(v);
export const money=v=>new Intl.NumberFormat('vi-VN',{style:'currency',currency:'VND'}).format(v);
// Khoản nhập tay trên web, đúng thứ tự bảng thưởng của studio (đơn giá OT nằm ở dòng OT). Khóa phải khớp PAYROLL_BONUS_KINDS.
const MANUAL=[['tips','Tiền khách Tips'],['praise','Nóng / Khen'],['referral','Giới thiệu khách'],['outdoor','Ngoại cảnh'],['full_day','Full khách ngày'],['retouch','Chỉnh ảnh'],['no_customer','Ca làm không có khách'],['meal','Ăn uống']];
const openSlips=new Set();

// Bộ đếm KPI mỗi tháng: điểm bị trừ và điểm còn lại trên quỹ 2000 (điểm, không phải tiền).
function kpiMeter(p){const quota=p.kpi_quota??2000,used=p.kpi_deducted??0,left=p.kpi_left??quota-used,ratio=Math.max(0,Math.min(1,left/quota)),level=ratio>=.75?'good':ratio>=.5?'warn':'bad';return `<div class="kpi-meter ${level}"><span><b>${num(left)}</b> / ${num(quota)}</span><i style="--kpi:${(ratio*100).toFixed(1)}%"></i></div>`;}
export function kpiCells(p){const used=p.kpi_deducted??0;return `<td class="${used?'kpi-used':'kpi-zero'}">${used?'−'+num(used):'0'}</td><td>${kpiMeter(p)}</td>`;}
// Thưởng tiến độ: thanh chuyên cần giống thanh KPI; ô khách đã chụp theo lịch khách (thợ chính + phụ).
export function progressCell(p,staff){if(!p.attendance_target)return '<td>—</td>';const done=p.days??0,target=p.attendance_target,ratio=Math.max(0,Math.min(1,done/target)),reached=done>=target,vnd=num(p.attendance_bonus_vnd??500000)+'đ';return `<td><div class="kpi-meter ${reached?'good':'progress'}"><span>${staff?'Đã đi làm ':''}<b>${done}</b> / ${target} ngày</span><i style="--kpi:${(ratio*100).toFixed(1)}%"></i><small class="${reached?'ok':''}">${reached?'Đạt · thưởng '+vnd:'Còn '+(target-done)+' ngày · thưởng '+vnd}</small></div></td>`;}
// Khách lúc thợ đã chấm ra / không chấm công thì không tính hoa hồng (chủ dự án chốt 31/10/2026).
const absentNote=p=>p.booking_absent?`<br><small class="out">${p.booking_absent} khách ngoài giờ chấm công · không tính</small>`:'';
export function bookingCells(p,staff){if(p.booking_commission===undefined)return staff?'<td>—</td><td>—</td>':'<td>—</td>';const note=(p.booking_unpriced?`<br><small class="out">${p.booking_unpriced} khách gói chưa có mức</small>`:'')+absentNote(p);return staff?`<td>${p.booking_customers}${note}</td><td><strong>${money(p.booking_commission)}</strong></td>`:`<td>${p.booking_customers} khách<br><small>${money(p.booking_commission)}</small>${note}</td>`;}

// Tiền theo các ô đang nhập (chưa lưu cũng tính lại ngay) để phiếu luôn khớp từ trên xuống.
function totals(p,values,pointVnd){const ot=(p.otSessions??0)*(values.ot_rate??p.ot_rate??0),manual=MANUAL.reduce((s,[k])=>s+(values[k]??p.bonus_items?.[k]??0),0);
 const bonus=p.bonus_total===undefined?(p.commission??0):(p.commission??0)+(p.booking_commission??0)+(p.attendance_bonus??0)+ot+manual;
 const kpi=p.bonus_total===undefined?0:Math.max(0,Math.min((p.kpi_deducted??0)*pointVnd,bonus)),gross=(p.base_salary??0)+bonus;
 return {ot,bonus,kpi,gross,final:p.bonus_total===undefined?(p.salary??gross):gross-kpi,capped:(p.kpi_deducted??0)*pointVnd>bonus};}
const bonusCount=p=>[p.attendance_bonus,p.booking_commission,p.commission,p.ot_bonus,...Object.values(p.bonus_items||{})].filter(v=>v>0).length;

function personRow(p,progress){const open=openSlips.has(p.id),t=totals(p,{},1000);
 return `<tr class="pay-row${open?' open':''}" data-pay="${p.id}"><td><button type="button" class="pay-name" aria-expanded="${open}" aria-controls="pay-slip-${p.id}"><i aria-hidden="true">▸</i><span>${esc(p.name)}</span></button><small class="pay-open-hint">${open?'Đóng phiếu lương':'Xem phiếu lương'}</small>${p.legacyShifts?'<small>Có ca cũ cần đối soát</small>':''}${p.incomplete?`<small class="out">${p.incomplete} ca thiếu chấm ra</small>`:''}</td>
<td><div class="pay-cell"><span><b>${num(p.workdays)}</b> công</span><small>${p.days} ngày · ${p.completedShifts} ca</small>${p.unbookedShifts?`<small class="out">${p.unbookedShifts} ca không khách</small>`:''}<small>OT ${p.otSessions} lượt · ${p.otHours}h</small>${p.otIncomplete?`<small class="out">${p.otIncomplete} OT chưa kết thúc</small>`:''}</div></td>
<td><div class="pay-cell">${kpiMeter(p)}${p.kpi_deducted?`<small class="out">−${num(p.kpi_deducted)} điểm</small>`:''}</div></td>${progress?progressCell(p,false):''}
<td><div class="pay-cell"><span>${money(p.base_salary)}</span><small>Cơ bản ${money(p.monthly_salary??0)}</small></div></td>
<td><div class="pay-cell"><span>${money(p.bonus_total??t.bonus)}</span><small>${bonusCount(p)} khoản</small></div></td>
<td><div class="pay-cell"><strong>${money(p.salary)}</strong>${p.kpi_applied?`<small class="out">−KPI ${money(p.kpi_applied)}</small>`:''}</div></td></tr>`;}

function slipRow(p,cols,closed,pointVnd,month){const t=totals(p,{},pointVnd),items=p.bonus_items||{},locked=closed||p.bonus_total===undefined;
 const line=(label,formula,amount,cls='')=>`<div class="slip-line ${cls}"><span>${label}</span><small>${formula}</small><b>${amount}</b></div>`;
 const input=(kind,value,label)=>`<input class="pay-input" type="text" inputmode="numeric" autocomplete="off" data-kind="${kind}" aria-label="${esc(label)} cho ${esc(p.name)}" value="${value?num(value):''}" placeholder="0"${locked?' disabled':''}>`;
 const booking=p.booking_commission!==undefined?line('Số khách chụp',p.booking_units!==p.booking_customers?`tính ${num(p.booking_units??0)} suất theo gói`:'Thợ chính + thợ phụ',`${num(p.booking_customers)} khách`)+(p.booking_unpriced?line('',`<span class="out">${p.booking_unpriced} khách gói chưa có mức, tính 0đ</span>`,''):'')+(p.booking_absent?line('',`<span class="out">${p.booking_absent} khách lúc đã chấm ra / không chấm công, không tính</span>`,''):'')+line('Thưởng hoa hồng khách chụp',`${num(p.booking_units??0)} × ${money(p.booking_rate??0)}`,money(p.booking_commission))
  :line('Hoa hồng khách chụp',month<'2026-11'?'Áp dụng từ tháng 11/2026':'Chưa có mức hoa hồng trong hồ sơ','—','muted');
 const manual=p.commission?line('Hoa hồng nhập tay',`${num(p.regular_customers??0)} khách thường · ${num(p.ot_customers??0)} khách OT`,money(p.commission)):'';
 const attendance=p.attendance_target?line('Chuyên cần tháng',`${p.days}/${p.attendance_target} ngày${p.days>=p.attendance_target?' · đạt':''}`,money(p.attendance_bonus??0),p.attendance_bonus?'':'muted'):line('Chuyên cần tháng',month<'2026-11'?'Áp dụng từ tháng 11/2026':'Chỉ áp dụng Nhân viên','—','muted');
 const hint={no_customer:p.unbookedShifts?`${p.unbookedShifts} ca không khách`:''};
 const updated=p.bonus_updated_at?`Sửa lần cuối: ${esc(p.bonus_updated_by||'')} · ${new Date(p.bonus_updated_at).toLocaleString('vi-VN',{timeZone:'Asia/Ho_Chi_Minh',day:'2-digit',month:'2-digit',hour:'2-digit',minute:'2-digit'})}`:'';
 return `<tr class="pay-slip" id="pay-slip-${p.id}" data-slip="${p.id}"${openSlips.has(p.id)?'':' hidden'}><td colspan="${cols}"><div class="slip">
<div class="slip-col"><section><h4>Lương</h4>${line('Lương cơ bản','Theo hồ sơ nhân viên',money(p.monthly_salary??0))}${line('Số ngày công',`${p.days} ngày đi làm`,`${num(p.workdays)} công`)}${line('Lương theo công',`${num(p.workdays)} × (${money(p.monthly_salary??0)} / ${p.monthDays??'—'} ngày)`,money(p.base_salary))}</section>
<section><h4>OT</h4>${line('Số công OT',`${p.otHours}h`,`${p.otSessions} lượt`)}${line('Thưởng số công OT',`${p.otSessions} × ${input('ot_rate',p.ot_rate,'Đơn giá mỗi lượt OT')}`,`<span data-out="ot">${money(t.ot)}</span>`,'with-input')}</section>
<section><h4>Khách chụp</h4>${booking}${manual}</section></div>
<div class="slip-col"><section><h4>Thưởng</h4>${attendance}${MANUAL.map(([k,label])=>line(label,hint[k]||'',input(k,items[k],label),'with-input')).join('')}</section></div>
<div class="slip-sum">${line('Tổng thưởng','',`<span data-out="bonus">${money(t.bonus)}</span>`)}${line('Tổng lương tạm tính','Lương theo công + tổng thưởng',`<span data-out="gross">${money(t.gross)}</span>`,'subtotal')}${line('KPI bị trừ',`${num(p.kpi_deducted??0)} điểm × ${num(pointVnd)}đ<span data-out="cap">${t.capped?' · tối đa bằng tổng thưởng':''}</span>`,`<span data-out="kpi">−${money(t.kpi)}</span>`,p.kpi_deducted?'deduct':'muted')}${line('KPI còn lại','',`${num(p.kpi_left??2000)} / ${num(p.kpi_quota??2000)} điểm`)}${line('Tổng tiền cuối cùng','',`<span data-out="final">${money(t.final)}</span>`,'final')}
<div class="slip-actions">${locked?`<small>${closed?'Tháng đã chốt: thưởng giữ theo lúc chốt.':'Tháng chốt trước khi có phiếu thưởng.'}</small>`:`<button type="button" class="primary" data-save="${p.id}" disabled>Lưu thưởng</button><small data-saved>${updated}</small>`}</div></div>
</div></td></tr>`;}

function readInputs(slip){const out={};for(const el of slip.querySelectorAll('.pay-input'))out[el.dataset.kind]=Number(el.value.replace(/\D/g,''))||0;return out;}
function renderTotals(rows){const sum=k=>rows.reduce((s,p)=>s+(p[k]??0),0);return `<div class="pay-totals"><div><span>Nhân viên</span><strong>${rows.length}</strong></div><div><span>Lương theo công</span><strong>${money(sum('base_salary'))}</strong></div><div><span>Tổng thưởng</span><strong>${money(rows.reduce((s,p)=>s+(p.bonus_total??p.commission??0),0))}</strong></div><div><span>Tổng tiền cuối cùng</span><strong>${money(sum('salary'))}</strong></div></div>`;}

export function renderAdminPayroll(el,r,{api,toast,onSaved}){const rows=r.rows,progress=rows.some(p=>p.attendance_target),cols=progress?7:6,pointVnd=r.kpi_point_vnd||1000;
 el.innerHTML=`${renderTotals(rows)}<table class="payroll-table payroll-admin"><thead><tr><th>NHÂN VIÊN</th><th>CÔNG</th><th>KPI CÒN LẠI</th>${progress?'<th>CHUYÊN CẦN</th>':''}<th>LƯƠNG THEO CÔNG</th><th>TỔNG THƯỞNG</th><th>LƯƠNG CUỐI</th></tr></thead><tbody>${rows.map(p=>personRow(p,progress)+slipRow(p,cols,r.closed,pointVnd,r.month)).join('')}</tbody></table>${rows.length?'':'<p class="hint">Chưa có nhân viên nào.</p>'}${r.closed?'<p class="hint">Tháng đã chốt: điểm KPI, thưởng và lương cuối giữ theo lúc chốt.</p>':''}`;
 const toggle=id=>{const open=!openSlips.has(id);open?openSlips.add(id):openSlips.delete(id);const row=el.querySelector(`[data-pay="${id}"]`);row.classList.toggle('open',open);row.querySelector('.pay-name').setAttribute('aria-expanded',String(open));row.querySelector('.pay-open-hint').textContent=open?'Đóng phiếu lương':'Xem phiếu lương';el.querySelector(`[data-slip="${id}"]`).hidden=!open;};
 const update=slip=>{const p=rows.find(x=>x.id===Number(slip.dataset.slip)),t=totals(p,readInputs(slip),pointVnd),set=(k,v)=>{const o=slip.querySelector(`[data-out="${k}"]`);if(o)o.innerHTML=v;};set('ot',money(t.ot));set('bonus',money(t.bonus));set('gross',money(t.gross));set('kpi','−'+money(t.kpi));set('final',money(t.final));set('cap',t.capped?' · tối đa bằng tổng thưởng':'');const save=slip.querySelector('[data-save]');if(save)save.disabled=false;};
 el.onclick=async e=>{const row=e.target.closest('.pay-row');if(row&&!e.target.closest('a,input')){toggle(Number(row.dataset.pay));return;}
  const save=e.target.closest('[data-save]');if(!save)return;const id=Number(save.dataset.save),slip=el.querySelector(`[data-slip="${id}"]`);save.disabled=true;save.textContent='Đang lưu…';
  try{await api('payroll/bonuses',{month:r.month,user_id:id,items:readInputs(slip)});const fresh=(await api('monthly?month='+r.month)).rows.find(x=>x.id===id);const i=rows.findIndex(x=>x.id===id);if(fresh&&i>=0){rows[i]=fresh;const old=el.querySelector(`[data-pay="${id}"]`);old.outerHTML=personRow(fresh,progress);slip.outerHTML=slipRow(fresh,cols,r.closed,pointVnd,r.month);el.querySelector('.pay-totals').outerHTML=renderTotals(rows);}toast('Đã lưu thưởng cho '+(fresh?.name||'nhân viên')+'.');onSaved?.();}
  catch(error){toast(error.message);save.disabled=false;save.textContent='Lưu thưởng';}};
 el.oninput=e=>{const input=e.target.closest('.pay-input');if(!input)return;const digits=input.value.replace(/\D/g,'').replace(/^0+(?=\d)/,'').slice(0,9);input.value=digits?num(Number(digits)):'';update(input.closest('.pay-slip'));};
 el.onkeydown=e=>{if(e.key==='Enter'&&e.target.closest('.pay-input')){e.preventDefault();e.target.closest('.pay-slip').querySelector('[data-save]:not(:disabled)')?.click();}};}

// Phiếu thưởng cho nhân viên (chủ dự án yêu cầu 02/11/2026, để có động lực): mỗi nhân viên thấy các khoản thưởng của chính mình,
// tổng thưởng, KPI bị trừ và lịch sử thưởng được ghi theo ngày. Không hiện lương cứng / lương cuối.
// Ô Nhập thưởng: Admin, Quản lý và nhân viên được Admin tick "Nhập thưởng" (VD Tín nhập mỗi ngày) cộng thêm / trừ bớt từng khoản.
const KIND_LABEL=Object.fromEntries(MANUAL);
const dayLabel=d=>d?d.slice(8,10)+'/'+d.slice(5,7):'';
const signed=v=>(v>0?'+':'−')+money(Math.abs(v));
const localToday=()=>new Date(Date.now()+7*3600000).toISOString().slice(0,10);
function entryList(entries,withName){if(!entries.length)return '<p class="hint">Chưa có khoản thưởng nào được ghi trong tháng.</p>';
 return `<ul class="bonus-entries">${entries.map(e=>`<li><span class="bonus-entry-day">${dayLabel(e.day)}</span><div><strong>${withName?esc(e.name)+' · ':''}${esc(KIND_LABEL[e.kind]||e.kind)}</strong>${e.note?`<small>${esc(e.note)}</small>`:''}<small>Nhập bởi ${esc(e.created_by_name||'')}</small></div><b class="${e.amount<0?'minus':'plus'}">${signed(e.amount)}</b></li>`).join('')}</ul>`;}
function staffSlip(p,entries,month){
 const line=(label,formula,amount,cls='')=>`<div class="slip-line ${cls}"><span>${label}</span><small>${formula}</small><b>${amount}</b></div>`;
 const head=`<div class="section-head"><h2>🎁 Phiếu thưởng của tôi · Tháng ${month.slice(5)}/${month.slice(0,4)}</h2></div>`;
 if(!p||p.bonus_total===undefined)return `<section class="card bonus-card">${head}<p class="hint">Tháng này chưa có phiếu thưởng.</p></section>`;
 const items=p.bonus_items||{},kpi=p.kpi_applied??0;
 const work=(p.attendance_target?line('Chuyên cần tháng',`${p.days}/${p.attendance_target} ngày${p.days>=p.attendance_target?' · đạt':` · còn ${p.attendance_target-p.days} ngày`}`,money(p.attendance_bonus??0),p.attendance_bonus?'':'muted'):'')
  +(p.booking_commission!==undefined?line('Hoa hồng khách chụp',`${num(p.booking_customers??0)} khách${p.booking_units!==p.booking_customers?` · ${num(p.booking_units??0)} suất`:''} × ${money(p.booking_rate??0)}`,money(p.booking_commission),p.booking_commission?'':'muted'):'')
  +(p.booking_absent?line('',`<span class="out">${p.booking_absent} khách ngoài giờ chấm công · không tính</span>`,''):'')
  +(p.commission?line('Hoa hồng nhập tay','',money(p.commission)):'')
  +((p.otSessions||p.ot_rate)?line('Thưởng OT',`${p.otSessions} lượt × ${money(p.ot_rate??0)}`,money(p.ot_bonus??0),p.ot_bonus?'':'muted'):'');
 const manual=MANUAL.map(([k,label])=>line(label,'',money(items[k]??0),items[k]?'':'muted')).join('');
 return `<section class="card bonus-card">${head}<p class="hint">Thưởng được cộng dồn theo ngày, cập nhật ngay khi Quản lý / người phụ trách nhập. Giữ KPI để nhận đủ thưởng nhé!</p><div class="slip bonus-slip"><div class="slip-col"><section><h4>Thưởng theo công việc</h4>${work||'<p class="hint">Chưa có.</p>'}</section></div><div class="slip-col"><section><h4>Thưởng ghi theo ngày</h4>${manual}</section></div>
<div class="slip-sum">${line('Tổng thưởng','',money(p.bonus_total),'subtotal')}${line('KPI bị trừ',`${num(p.kpi_deducted??0)} điểm${kpi&&kpi<(p.kpi_money??0)?' · tối đa bằng tổng thưởng':''}`,'−'+money(kpi),kpi?'deduct':'muted')}${line('KPI còn lại','',`${num(p.kpi_left??2000)} / ${num(p.kpi_quota??2000)} điểm`)}${line('Thưởng thực nhận','Tổng thưởng − KPI bị trừ',money(p.bonus_total-kpi),'final')}</div></div>
<details class="bonus-history"${entries.length?' open':''}><summary>Thưởng được ghi trong tháng (${entries.length})</summary>${entryList(entries,false)}</details></section>`;}
function entryCard(r,staff){const monthEnd=new Date(Date.UTC(+r.month.slice(0,4),+r.month.slice(5,7),0)).toISOString().slice(0,10),today=localToday(),max=monthEnd<today?monthEnd:today,future=r.month+'-01'>today;
 const kinds=Object.entries(r.kinds||{}),locked=r.closed||future||!r.people.length;
 const people=r.people.map(p=>`<div class="bonus-person"><strong>${esc(p.name)}</strong><span>${money(p.total)}</span><small>${Object.entries(p.items).filter(([,v])=>v).map(([k,v])=>`${esc(KIND_LABEL[k]||k)} ${money(v)}`).join(' · ')||'Chưa có thưởng nhập'}</small></div>`).join('');
 const others=staff?r.entries.filter(e=>e.user_id!==r.me):r.entries;
 return `<section class="card bonus-entry-card"><div class="section-head"><h2>✍ Nhập thưởng nhân viên</h2></div><p class="hint">Mỗi lần bấm là <b>cộng thêm</b> vào khoản thưởng tháng của nhân viên; nhân viên thấy ngay trong Phiếu thưởng. Nhập nhầm thì chọn <b>Trừ bớt</b>.${staff?' Bạn không nhập thưởng cho chính mình.':''}</p>
${r.closed?'<p class="out">Tháng đã chốt lương: không nhập thêm thưởng.</p>':future?'<p class="hint">Chưa tới tháng này.</p>':''}
<form class="bonus-entry-form"${locked?' inert':''}><label>Ngày<input name="day" type="date" required min="${r.month}-01" max="${max}" value="${max}"></label><label>Nhân viên<select name="user_id" required><option value="">Chọn nhân viên</option>${r.people.map(p=>`<option value="${p.id}">${esc(p.name)}</option>`).join('')}</select></label><label>Khoản thưởng<select name="kind" required><option value="">Chọn khoản</option>${kinds.map(([k,l])=>`<option value="${k}">${esc(l)}</option>`).join('')}</select></label><label>Số tiền (đ)<input name="amount" class="pay-input" type="text" inputmode="numeric" autocomplete="off" required placeholder="VD 50.000"></label><div class="bonus-sign" role="radiogroup" aria-label="Cộng hay trừ"><label><input type="radio" name="sign" value="1" checked><span>＋ Cộng thêm</span></label><label><input type="radio" name="sign" value="-1"><span>− Trừ bớt</span></label></div><label class="bonus-note">Ghi chú (không bắt buộc)<input name="note" maxlength="300" placeholder="VD: khách tip ca 2"></label><p class="bonus-error out" role="alert"></p><button class="primary" type="submit">Lưu thưởng</button></form>
<h3>Thưởng nhập trong tháng</h3><div class="bonus-people">${people||'<p class="hint">Chưa có nhân viên.</p>'}</div>
<details class="bonus-history"><summary>Lịch sử nhập (${others.length})</summary>${entryList(others,true)}</details></section>`;}
export async function renderBonusPanel(el,{api,toast,month,row,staff,reload}){if(!el)return;
 try{const r=await api('payroll/bonus-entries?month='+month);if(!el.isConnected)return;
  const mine=staff?r.entries.filter(e=>e.user_id===r.me):[];
  el.innerHTML=(staff?staffSlip(row,mine,r.month):'')+(r.can_enter?entryCard(r,staff):'');
  const form=el.querySelector('.bonus-entry-form');if(!form)return;
  form.amount.oninput=()=>{const d=form.amount.value.replace(/\D/g,'').replace(/^0+(?=\d)/,'').slice(0,9);form.amount.value=d?num(Number(d)):'';};
  form.onsubmit=async e=>{e.preventDefault();const err=form.querySelector('.bonus-error'),button=form.querySelector('button[type=submit]');err.textContent='';
   const amount=Number(form.amount.value.replace(/\D/g,''))*Number(form.sign.value);if(!amount){err.textContent='Nhập số tiền.';return;}
   button.disabled=true;button.textContent='Đang lưu…';
   try{const person=r.people.find(p=>p.id===Number(form.user_id.value));await api('payroll/bonuses/add',{id:crypto.randomUUID(),day:form.day.value,user_id:Number(form.user_id.value),kind:form.kind.value,amount,note:form.note.value.trim()});
    toast(`Đã ${amount>0?'cộng':'trừ bớt'} ${money(Math.abs(amount))} ${KIND_LABEL[form.kind.value]||''} cho ${person?.name||'nhân viên'}.`);await reload();}
   catch(error){err.textContent=error.message;button.disabled=false;button.textContent='Lưu thưởng';}};
 }catch(error){el.innerHTML=`<p class="hint">${esc(error.message)}</p>`;}}
