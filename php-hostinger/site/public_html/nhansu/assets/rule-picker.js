// Chọn luật Studio khi ghi Event log. Điểm KPI (chủ dự án chốt 02/11/2026): luật thường tính theo số lần mắc lỗi đó trong tháng
// (Lần 1 Nhắc nhở −20, Lần 2 Cảnh cáo −100, Lần 3 trở đi Kỷ luật −300); luật nặng có điểm riêng từ 100 trở lên luôn trừ đúng điểm đó.
const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
export const FIXED_MIN=100;
export const TIERS=[[1,'Nhắc nhở',20],[2,'Cảnh cáo',100],[3,'Kỷ luật',300]];
export const tierName=n=>TIERS[n-1]?.[1]||'';
export const fixedRule=r=>!!r&&Number(r.points)>=FIXED_MIN;
let cache=null;
export function loadRules(api){if(!cache)cache=api('studio-rules').then(d=>d.rows.filter(r=>r.status==='active')).catch(e=>{cache=null;throw e;});return cache;}
export function resetRules(){cache=null;}
export function ruleField(rules){
 if(!rules.length)return '';
 const groups=[...new Set(rules.map(r=>r.category))];
 return `<label class="rule-pick">Áp luật Studio (không bắt buộc)<select name="rule_id"><option value="">— Không chọn luật —</option>${groups.map(g=>`<optgroup label="${esc(g)}">${rules.filter(r=>r.category===g).map(r=>`<option value="${r.id}">${esc(r.code)} · ${esc(r.name)} (${fixedRule(r)?'lỗi nặng −'+r.points:'theo số lần'})</option>`).join('')}</optgroup>`).join('')}</select><small class="rule-pick-hint" hidden></small></label>`;
}
// Ô "Lần mắc lỗi này trong tháng": 0 = chỉ ghi nhận, 1/2/3 = Nhắc nhở / Cảnh cáo / Kỷ luật.
export function tierField(selected=0){
 return `<fieldset class="log-tier"><legend>Lần mắc lỗi này trong tháng</legend><div class="log-tier-options"><label class="log-tier-option tier-0"><input type="radio" name="offense" value="0" ${selected===0?'checked':''}><span><b>Chỉ ghi nhận</b><small>Không trừ điểm</small></span></label>${TIERS.map(([n,name,p])=>`<label class="log-tier-option tier-${n}"><input type="radio" name="offense" value="${n}" ${selected===n?'checked':''}><span><b>Lần ${n}${n===3?' trở đi':''}</b><small>${name} · −${p} KPI</small></span></label>`).join('')}</div><p class="log-tier-fixed" hidden></p><small class="log-tier-hint" role="status"></small></fieldset>`;
}
// Lần đã lưu của log cũ: ưu tiên cột offense, không có thì suy từ điểm.
export const tierOf=row=>row.offense?Number(row.offense):({20:1,100:2,300:3}[Math.abs(row.points)]??0);
// Gắn hành vi chọn luật:
// - Form có ô Lần (name=offense): luật nặng → ẩn ô Lần, trừ cố định; luật thường → gợi ý Lần theo số lần đã ghi trong tháng (opts.count).
// - Hộp ghi nhanh có ô điểm (select name=deduction): luật nặng → khóa ô điểm theo luật; luật thường → giữ ô điểm để chọn.
// opts.subject: người liên quan cố định khi form không có ô chọn nhân viên (null = nhật ký chung).
export function bindRuleField(form,rules,opts={}){
 const select=form.querySelector('[name=rule_id]'),points=form.querySelector('select[name=deduction]'),desc=form.querySelector('[name=description]'),hint=form.querySelector('.rule-pick-hint');
 const tiers=[...form.querySelectorAll('[name=offense]')],box=form.querySelector('.log-tier-options'),fixedNote=form.querySelector('.log-tier-fixed'),tierHint=form.querySelector('.log-tier-hint');
 const person=form.querySelector('select[name=user_id]'),day=form.querySelector('[name=event_date]');
 let touched=false,seq=0;
 for(const t of tiers)t.addEventListener('change',()=>{touched=true;});
 const rule=()=>select?rules.find(x=>x.id===select.value):null;
 const subject=()=>person?(person.value?Number(person.value):null):(opts.subject??null);
 const pick=n=>{const t=tiers.find(x=>Number(x.value)===n);if(t&&!t.disabled)t.checked=true;};
 async function sync(){
  const r=rule(),fixed=fixedRule(r);
  // Form có ô Lần thì ô Lần đã ghi rõ cách trừ điểm; hộp ghi nhanh (chỉ có ô điểm) thì ghi dưới ô chọn luật.
  if(hint){hint.hidden=!r||tiers.length>0;hint.classList.toggle('ladder',!!r&&!fixed);hint.textContent=r?(fixed?`${r.code} là lỗi nặng: trừ cố định ${r.points} điểm KPI mỗi lần.`:`${r.code} tính theo số lần mắc lỗi trong tháng: lần 1 −20, lần 2 −100, lần 3 trở đi −300.`):'';}
  if(points){if(fixed){if(![...points.options].some(o=>Number(o.value)===Number(r.points)))points.add(new Option(r.points+' điểm (luật nặng)',String(r.points)));points.value=String(r.points);points.disabled=true;}else points.disabled=false;}
  if(!tiers.length)return;
  const who=subject(),general=who===null;
  for(const t of tiers)t.disabled=fixed||(general&&t.value!=='0');
  box.hidden=fixed;fixedNote.hidden=!fixed;fixedNote.textContent=fixed?`${r.code} là lỗi nặng: trừ cố định ${r.points} điểm KPI mỗi lần, không tính theo số lần.`:'';
  if(fixed){tierHint.textContent='';return;}
  if(general){pick(0);tierHint.textContent='Nhật ký chung studio chỉ ghi nhận, không trừ điểm.';return;}
  tierHint.textContent='';
  if(!r||!opts.count)return;
  const n=++seq;tierHint.textContent='Đang đếm số lần trong tháng…';
  try{const c=await opts.count(who,r.id,day?.value||opts.day||'');if(n!==seq)return;const next=Math.min(3,c.count+1),name=person?person.selectedOptions[0]?.textContent.split(' · ')[0]:'';
   tierHint.textContent=`Tháng ${c.month.split('-').reverse().join('/')}: ${name?name+' ':''}đã bị ghi lỗi ${r.code} ${c.count} lần → lần này là Lần ${next}.`;if(!touched)pick(next);}
  catch{if(n===seq)tierHint.textContent='';}
 }
 if(select)select.addEventListener('change',()=>{const r=rule();touched=false;if(r&&desc&&!desc.value.trim())desc.value=`[${r.code}] ${r.name}: `;sync();});
 if(person)person.addEventListener('change',()=>{touched=false;sync();});
 if(day)day.addEventListener('change',sync);
 sync();
}
// Hộp thoại ghi Event log ngắn gọn (lỗi chấm công, nghỉ gấp): submit(body) gửi API, lỗi hiện ngay trong hộp thoại.
// Ô điểm theo đề xuất riêng của từng loại (nghỉ gấp, đi muộn); chọn luật nặng thì điểm theo luật.
export function ruleLogDialog({title,intro,description,suggested,rules,choices=[0,20,100,300],submit}){
 const d=document.createElement('dialog');d.className='workflow-dialog finance-dialog';
 d.innerHTML=`<div class="dialog-heading"><h2>${esc(title)}</h2><button class="dialog-close" aria-label="Đóng" type="button">×</button></div><form>${intro?`<p>${intro}</p>`:''}${ruleField(rules)}<label>Nội dung ghi nhận<textarea name="description" rows="4" required minlength="5" maxlength="2000">${esc(description)}</textarea></label><label>Điểm KPI<select name="deduction">${[...new Set([...choices,suggested])].sort((a,b)=>a-b).map(n=>`<option value="${n}" ${n===suggested?'selected':''}>${n?n+' điểm':'Không trừ điểm'}${n===suggested?' (đề xuất)':''}</option>`).join('')}</select></label><p class="hint">Điểm KPI, khấu trừ vào thưởng tháng trong Bảng lương.</p><p class="error" role="alert"></p><button class="primary" type="submit">Ghi vào Event log</button></form>`;
 document.body.append(d);const form=d.querySelector('form');bindRuleField(form,rules);
 d.querySelector('.dialog-close').onclick=()=>d.close();d.addEventListener('close',()=>d.remove(),{once:true});
 form.onsubmit=async e=>{e.preventDefault();const f=new FormData(form),rule=f.get('rule_id')||'',btn=form.querySelector('[type=submit]');btn.disabled=true;
  try{await submit({description:f.get('description'),deduction:Number(f.get('deduction')||0),rule_id:rule||null});d.close();}catch(err){d.querySelector('.error').textContent=err.message;}finally{btn.disabled=false;}};
 d.showModal();return d;
}
