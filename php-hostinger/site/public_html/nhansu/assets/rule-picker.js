// Chọn luật Studio khi ghi Event log: chọn mã lỗi thì điểm KPI tự điền theo luật và khóa lại.
const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
let cache=null;
export function loadRules(api){if(!cache)cache=api('studio-rules').then(d=>d.rows.filter(r=>r.status==='active')).catch(e=>{cache=null;throw e;});return cache;}
export function resetRules(){cache=null;}
export function ruleField(rules){
 if(!rules.length)return '';
 const groups=[...new Set(rules.map(r=>r.category))];
 return `<label class="rule-pick">Áp luật Studio (không bắt buộc)<select name="rule_id"><option value="">— Không chọn luật —</option>${groups.map(g=>`<optgroup label="${esc(g)}">${rules.filter(r=>r.category===g).map(r=>`<option value="${r.id}">${esc(r.code)} · ${esc(r.name)} (${r.points?'−'+r.points:'0'} điểm)</option>`).join('')}</optgroup>`).join('')}</select><small class="rule-pick-hint" hidden></small></label>`;
}
// Gắn hành vi: chọn luật → điền và khóa ô điểm, gợi ý nội dung nếu còn trống.
export function bindRuleField(form,rules){
 const select=form.querySelector('[name=rule_id]');if(!select)return;
 const points=form.querySelector('[name=deduction]'),desc=form.querySelector('[name=description]'),hint=form.querySelector('.rule-pick-hint');
 select.onchange=()=>{const r=rules.find(x=>x.id===select.value);
  if(points){if(r){if(points.tagName==='SELECT'&&![...points.options].some(o=>Number(o.value)===r.points))points.add(new Option(r.points+' điểm (theo luật)',String(r.points)));points.value=String(r.points);points.disabled=true;}else points.disabled=false;}
  hint.hidden=!r;hint.textContent=r?`Điểm theo luật ${r.code}: ${r.points?'−'+r.points+' điểm KPI':'không trừ điểm'}.`:'';
  if(r&&desc&&!desc.value.trim())desc.value=`[${r.code}] ${r.name}: `;
 };
}
// Hộp thoại ghi Event log ngắn gọn (lỗi chấm công, nghỉ gấp): submit(body) gửi API, lỗi hiện ngay trong hộp thoại.
export function ruleLogDialog({title,intro,description,suggested,rules,choices=[0,20,100,300],submit}){
 const d=document.createElement('dialog');d.className='workflow-dialog finance-dialog';
 d.innerHTML=`<div class="dialog-heading"><h2>${esc(title)}</h2><button class="dialog-close" aria-label="Đóng" type="button">×</button></div><form>${intro?`<p>${intro}</p>`:''}${ruleField(rules)}<label>Nội dung ghi nhận<textarea name="description" rows="4" required minlength="5" maxlength="2000">${esc(description)}</textarea></label><label>Điểm KPI<select name="deduction">${[...new Set([...choices,suggested])].sort((a,b)=>a-b).map(n=>`<option value="${n}" ${n===suggested?'selected':''}>${n?n+' điểm':'Không trừ điểm'}${n===suggested?' (đề xuất)':''}</option>`).join('')}</select></label><p class="hint">Điểm KPI, không tự quy đổi thành tiền.</p><p class="error" role="alert"></p><button class="primary" type="submit">Ghi vào Event log</button></form>`;
 document.body.append(d);const form=d.querySelector('form');bindRuleField(form,rules);
 d.querySelector('.dialog-close').onclick=()=>d.close();d.addEventListener('close',()=>d.remove(),{once:true});
 form.onsubmit=async e=>{e.preventDefault();const f=new FormData(form),rule=f.get('rule_id')||'',btn=form.querySelector('[type=submit]');btn.disabled=true;
  try{await submit({description:f.get('description'),deduction:rule?0:Number(f.get('deduction')),rule_id:rule||null});d.close();}catch(err){d.querySelector('.error').textContent=err.message;}finally{btn.disabled=false;}};
 d.showModal();return d;
}
