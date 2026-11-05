// Cập nhật một chạm (chủ dự án yêu cầu 05/11/2026): chỉ Admin. Một nút lấy bản mới từ GitHub, máy chủ tự kiểm chữ ký, sao lưu, thay file, tự trả về nếu lỗi.
const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const stamp=ms=>{const d=new Date(ms+7*3600000).toISOString();return `${d.slice(11,16)} ${d.slice(8,10)}/${d.slice(5,7)}/${d.slice(0,4)}`;};
const list=items=>items?.length?`<ul class="update-list">${items.map(x=>`<li>${esc(x)}</li>`).join('')}</ul>`:'';
const manualBox=items=>items?.length?`<div class="update-manual"><strong>Cần làm tay sau khi cập nhật</strong>${list(items)}</div>`:'';

export function updatePage(user){if(user.role!=='admin')return '';return `<section class="finance-page update-page" data-page="update" hidden><div class="finance-heading"><div><span class="eyebrow">ANH GẤU STUDIO · QUẢN TRỊ</span><h1>Cập nhật phần mềm</h1><p>Bấm một nút, khỏi upload trên Hostinger.</p></div></div><div class="card update-card" id="update-body"><p class="hint">Đang kiểm tra bản mới…</p></div></section>`;}

function headline(d){
 if(d.error)return ['warn','Chưa kiểm tra được bản mới',d.error];
 if(d.state==='available')return ['new',`Có bản mới v${d.latest}`,`Đang chạy v${d.current}${d.date?` · bản mới ngày ${d.date}`:''}`];
 if(d.state==='repair')return ['warn',`${d.changed} file chưa khớp bản v${d.latest}`,'Thường do upload tay thiếu file. Bấm để sửa lại cho khớp.'];
 if(d.state==='ahead')return ['ok',`Đang chạy v${d.current}`,`Hosting mới hơn bản trên GitHub (v${d.latest}). Chưa có gì để cập nhật.`];
 return ['ok',`Đang dùng bản mới nhất v${d.current}`,'Mọi file trên hosting khớp bản đã ký.'];
}
function render(d){const [tone,title,sub]=headline(d),can=['available','repair'].includes(d.state)&&d.writable;
 return `<div class="update-state ${tone}"><strong>${esc(title)}</strong><span>${esc(sub)}</span></div>
 ${d.state==='available'&&d.notes?.length?`<div><span class="update-label">Có gì mới</span>${list(d.notes)}</div>`:''}
 ${can?manualBox(d.manual):''}
 ${['available','repair'].includes(d.state)&&!d.writable?'<p class="update-error">PHP không ghi được vào thư mục web trên hosting nên chưa tự cập nhật được. Dùng cách upload tay.</p>':''}
 ${can?`<button type="button" class="primary update-go" data-update-go>${d.state==='repair'?'Sửa cho khớp':`Cập nhật lên v${esc(d.latest)}`}</button>`:''}
 <div class="update-actions"><button type="button" class="secondary" data-update-check>Kiểm tra lại</button>${d.last?`<button type="button" class="secondary" data-update-back>Quay về v${esc(d.last.from)}</button>`:''}</div>
 ${d.history?.length?`<details class="update-history"><summary>Lịch sử cập nhật</summary><ul class="update-list">${d.history.map(h=>`<li><b>${h.action==='rollback'?'Quay về':'Cập nhật'} v${esc(h.from)} → v${esc(h.to)}</b> · ${h.files} file · ${esc(h.by)} · ${stamp(h.at)}</li>`).join('')}</ul></details>`:''}`;}

export function bindUpdate({api,user,toast,choiceDialog}){if(user.role!=='admin')return;
 const page=document.querySelector('[data-page="update"]'),box=document.querySelector('#update-body');let busy=false;
 const busyText=text=>{busy=true;box.innerHTML=`<div class="update-state new"><strong>${esc(text)}</strong><span>Giữ nguyên trang này, thường chỉ vài giây.</span></div>`;};
 const finish=(message)=>{toast(message);setTimeout(()=>location.reload(),1600);};
 async function load(){if(busy)return;box.innerHTML='<p class="hint">Đang kiểm tra bản mới…</p>';
  try{const d=await api('update/status');box.innerHTML=render(d);
   box.querySelector('[data-update-check]').onclick=load;
   const go=box.querySelector('[data-update-go]');if(go)go.onclick=async()=>{if(!await choiceDialog(`<h2>Cập nhật lên v${esc(d.latest)}?</h2><p>Máy chủ tự sao lưu rồi thay ${d.changed} file. Lỗi thì tự trả về bản cũ.</p>`,'Để sau','Cập nhật ngay'))return;
    busyText('Đang cập nhật…');try{const r=await api('update/install',{version:d.latest});busy=false;
     if(r.manual?.length)box.innerHTML=`<div class="update-state ok"><strong>Đã lên v${esc(r.version)}</strong><span>${r.files} file.</span></div>${manualBox(r.manual)}<button type="button" class="primary update-go" onclick="location.reload()">Xong</button>`;
     else finish(r.health==='ok'?`Đã lên v${r.version} (${r.files} file). Đang tải lại trang…`:`Đã lên v${r.version}. Máy chủ chưa tự kiểm tra được, hãy thử mở app.`);
    }catch(error){busy=false;toast(error.message);load();}};
   const back=box.querySelector('[data-update-back]');if(back)back.onclick=async()=>{if(!await choiceDialog(`<h2>Quay về v${esc(d.last.from)}?</h2><p>Trả lại ${d.last.files} file của lần cập nhật gần nhất.</p>`,'Không','Quay về'))return;
    busyText('Đang quay về bản trước…');try{const r=await api('update/rollback',{});busy=false;finish(`Đã quay về v${r.version}. Đang tải lại trang…`);}catch(error){busy=false;toast(error.message);load();}};
  }catch(error){box.innerHTML=`<p class="update-error">${esc(error.message)}</p>`;}}
 page.addEventListener('refresh',load);if(!page.hidden)load();
}
