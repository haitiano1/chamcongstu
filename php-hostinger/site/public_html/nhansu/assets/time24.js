// Ô giờ 24h (chủ dự án yêu cầu 02/11/2026): ô type="time" của trình duyệt hiện SA/CH khi máy để 12 giờ, nên dùng 2 ô chọn Giờ : Phút.
// Ô ẩn name=… giữ giá trị "HH:MM" như ô cũ (FormData và .value dùng như trước); đổi Giờ / Phút thì phát sự kiện input + change trên ô ẩn.
const pad=n=>String(n).padStart(2,'0');
const parts=v=>/^\d{2}:\d{2}$/.test(v||'')?v.split(':'):['',''];
export function time24(name,value='',{from=0,to=23,step=5,id='',label=''}={}){
 const [h,m]=parts(value),hours=[],mins=[];
 for(let i=from;i<=to;i++)hours.push(pad(i));if(h&&!hours.includes(h)){hours.push(h);hours.sort();}
 for(let i=0;i<60;i+=step)mins.push(pad(i));if(m&&!mins.includes(m)){mins.push(m);mins.sort();}
 const opts=(list,v)=>'<option value="">--</option>'+list.map(x=>`<option value="${x}"${x===v?' selected':''}>${x}</option>`).join('');
 return `<span class="time24"><select data-part="h" aria-label="${label?label+' · ':''}Giờ (0–23)">${opts(hours,h)}</select><b aria-hidden="true">:</b><select data-part="m" aria-label="${label?label+' · ':''}Phút">${opts(mins,m)}</select><input type="hidden" name="${name}"${id?` id="${id}"`:''} value="${h&&m?h+':'+m:''}"></span>`;
}
// Gắn hành vi cho mọi ô giờ 24h trong root. Chọn giờ mà chưa chọn phút thì phút = 00.
export function bindTime24(root){
 for(const box of root.querySelectorAll('.time24')){if(box.dataset.bound)continue;box.dataset.bound='1';
  const [hs,ms]=box.querySelectorAll('select'),input=box.querySelector('input');
  const update=()=>{if(hs.value&&!ms.value)ms.value='00';input.value=hs.value&&ms.value?hs.value+':'+ms.value:'';input.dispatchEvent(new Event('input',{bubbles:true}));input.dispatchEvent(new Event('change',{bubbles:true}));};
  hs.addEventListener('change',update);ms.addEventListener('change',update);
 }
}
// Đặt giá trị từ code (điền sẵn khi sửa, xóa khi chọn ô giờ có sẵn…), không phát sự kiện.
export function setTime24(input,value){
 const box=input.closest('.time24');if(!box){input.value=value||'';return;}
 const [hs,ms]=box.querySelectorAll('select'),[h,m]=parts(value);
 for(const [sel,v] of [[hs,h],[ms,m]]){if(v&&![...sel.options].some(o=>o.value===v))sel.add(new Option(v,v));sel.value=v;}
 input.value=h&&m?h+':'+m:'';
}
