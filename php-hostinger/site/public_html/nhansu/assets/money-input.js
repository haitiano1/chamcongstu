// Ô nhập tiền có dấu chấm (chủ dự án yêu cầu 03/11/2026): gõ 50000 thì ô hiện 50.000.
// Ô cần type="text" inputmode="numeric" data-money; đọc lại bằng moneyValue(). data-money="label" (Phụ thu, Tip) là chữ tự do:
// chỉ thêm dấu chấm khi ô chỉ có số, gõ kiểu "100k" thì giữ nguyên.
const group=d=>d.replace(/\B(?=(\d{3})+(?!\d))/g,'.');
const PLAIN=/^(\d+|\d{1,3}(\.\d{3})+)$/;
export const moneyText=v=>{const d=String(v??'').replace(/\D/g,'').replace(/^0+(?=\d)/,'').slice(0,12);return d?group(d):'';};
export const moneyValue=v=>Number(String(v??'').replace(/\D/g,''))||0;
// Điền sẵn chữ Phụ thu / Tip: số thuần thì thêm dấu chấm, chữ khác (100k, 1,5) giữ nguyên.
export const moneyLabel=v=>{const s=String(v??'').trim();return PLAIN.test(s)?moneyText(s):s;};
// Gửi lại chữ cũ khi số tiền không đổi, để Sửa lịch không coi 50000 → 50.000 là đã sửa Tip.
export const keepLabel=(value,old)=>{const s=String(value??'').trim(),o=String(old??'').trim();return PLAIN.test(s)&&PLAIN.test(o)&&moneyValue(s)===moneyValue(o)?o:s;};
function format(el){const v=el.value;if(el.dataset.money==='label'&&!/^[\d.]*$/.test(v))return;
 const right=v.slice(el.selectionStart??v.length).replace(/\D/g,'').length,next=moneyText(v);if(next===v)return;el.value=next;
 if(document.activeElement!==el)return;let pos=next.length;for(let n=0;n<right&&pos>0;)if(/\d/.test(next[--pos]))n++;el.setSelectionRange(pos,pos);}
document.addEventListener('input',e=>{if(e.target instanceof HTMLInputElement&&e.target.dataset.money!==undefined)format(e.target);});
