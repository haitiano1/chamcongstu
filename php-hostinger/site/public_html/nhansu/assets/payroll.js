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
export function bookingCells(p,staff){if(p.booking_commission===undefined)return staff?'<td>—</td><td>—</td>':'<td>—</td>';const note=p.booking_unpriced?`<br><small class="out">${p.booking_unpriced} khách gói chưa có mức</small>`:'';return staff?`<td>${p.booking_customers}${note}</td><td><strong>${money(p.booking_commission)}</strong></td>`:`<td>${p.booking_customers} khách<br><small>${money(p.booking_commission)}</small>${note}</td>`;}

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
 const booking=p.booking_commission!==undefined?line('Số khách chụp',p.booking_units!==p.booking_customers?`tính ${num(p.booking_units??0)} suất theo gói`:'Thợ chính + thợ phụ',`${num(p.booking_customers)} khách`)+(p.booking_unpriced?line('',`<span class="out">${p.booking_unpriced} khách gói chưa có mức, tính 0đ</span>`,''):'')+line('Thưởng hoa hồng khách chụp',`${num(p.booking_units??0)} × ${money(p.booking_rate??0)}`,money(p.booking_commission))
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

export function renderAdminPayroll(el,r,{api,toast}){const rows=r.rows,progress=rows.some(p=>p.attendance_target),cols=progress?7:6,pointVnd=r.kpi_point_vnd||1000;
 el.innerHTML=`${renderTotals(rows)}<table class="payroll-table payroll-admin"><thead><tr><th>NHÂN VIÊN</th><th>CÔNG</th><th>KPI CÒN LẠI</th>${progress?'<th>CHUYÊN CẦN</th>':''}<th>LƯƠNG THEO CÔNG</th><th>TỔNG THƯỞNG</th><th>LƯƠNG CUỐI</th></tr></thead><tbody>${rows.map(p=>personRow(p,progress)+slipRow(p,cols,r.closed,pointVnd,r.month)).join('')}</tbody></table>${rows.length?'':'<p class="hint">Chưa có nhân viên nào.</p>'}${r.closed?'<p class="hint">Tháng đã chốt: điểm KPI, thưởng và lương cuối giữ theo lúc chốt.</p>':''}`;
 const toggle=id=>{const open=!openSlips.has(id);open?openSlips.add(id):openSlips.delete(id);const row=el.querySelector(`[data-pay="${id}"]`);row.classList.toggle('open',open);row.querySelector('.pay-name').setAttribute('aria-expanded',String(open));row.querySelector('.pay-open-hint').textContent=open?'Đóng phiếu lương':'Xem phiếu lương';el.querySelector(`[data-slip="${id}"]`).hidden=!open;};
 const update=slip=>{const p=rows.find(x=>x.id===Number(slip.dataset.slip)),t=totals(p,readInputs(slip),pointVnd),set=(k,v)=>{const o=slip.querySelector(`[data-out="${k}"]`);if(o)o.innerHTML=v;};set('ot',money(t.ot));set('bonus',money(t.bonus));set('gross',money(t.gross));set('kpi','−'+money(t.kpi));set('final',money(t.final));set('cap',t.capped?' · tối đa bằng tổng thưởng':'');const save=slip.querySelector('[data-save]');if(save)save.disabled=false;};
 el.onclick=async e=>{const row=e.target.closest('.pay-row');if(row&&!e.target.closest('a,input')){toggle(Number(row.dataset.pay));return;}
  const save=e.target.closest('[data-save]');if(!save)return;const id=Number(save.dataset.save),slip=el.querySelector(`[data-slip="${id}"]`);save.disabled=true;save.textContent='Đang lưu…';
  try{await api('payroll/bonuses',{month:r.month,user_id:id,items:readInputs(slip)});const fresh=(await api('monthly?month='+r.month)).rows.find(x=>x.id===id);const i=rows.findIndex(x=>x.id===id);if(fresh&&i>=0){rows[i]=fresh;const old=el.querySelector(`[data-pay="${id}"]`);old.outerHTML=personRow(fresh,progress);slip.outerHTML=slipRow(fresh,cols,r.closed,pointVnd,r.month);el.querySelector('.pay-totals').outerHTML=renderTotals(rows);}toast('Đã lưu thưởng cho '+(fresh?.name||'nhân viên')+'.');}
  catch(error){toast(error.message);save.disabled=false;save.textContent='Lưu thưởng';}};
 el.oninput=e=>{const input=e.target.closest('.pay-input');if(!input)return;const digits=input.value.replace(/\D/g,'').replace(/^0+(?=\d)/,'').slice(0,9);input.value=digits?num(Number(digits)):'';update(input.closest('.pay-slip'));};
 el.onkeydown=e=>{if(e.key==='Enter'&&e.target.closest('.pay-input')){e.preventDefault();e.target.closest('.pay-slip').querySelector('[data-save]:not(:disabled)')?.click();}};}
