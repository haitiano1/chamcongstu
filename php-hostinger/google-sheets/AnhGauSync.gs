/** Bound Google Apps Script. No public Sheet publication, OAuth client or service-account key needed.
 * Paste in Extensions > Apps Script of the booking sheet. Configure via menu; do not hardcode secrets.
 * Hai chiều (29/10/2026): gửi các tab "Tháng N" lên web; lấy lịch thêm/hủy trên web về ghi vào đúng tab, đúng ngày, đúng thứ tự giờ.
 */
const AG_BACK=1,AG_AHEAD=6;// Đồng bộ tab tháng trước đến 6 tháng tới: tên "Tháng 1" không bị nhầm năm.
const AG_FORCE_MS=600000;// Tab không đổi vẫn gửi lại mỗi 10 phút.
const AG_MARK='⚠ Anh Gấu: ',AG_BG='nền cũ: ',AG_RED='#f4c7c3';
function onOpen(){SpreadsheetApp.getUi().createMenu('Anh Gấu Studio').addItem('Cài kết nối web','caiKetNoiAnhGau').addItem('Đồng bộ ngay','dongBoAnhGau').addItem('Bật tự đồng bộ mỗi phút','batTuDongAnhGau').addItem('Tắt tự đồng bộ','tatTuDongAnhGau').addToUi();}
function caiKetNoiAnhGau(){
 const ui=SpreadsheetApp.getUi(),p=PropertiesService.getScriptProperties(),book=SpreadsheetApp.getActive();
 const ask=function(title,text){const r=ui.prompt(title,text,ui.ButtonSet.OK_CANCEL);if(r.getSelectedButton()!==ui.Button.OK)throw new Error('Đã hủy cài đặt.');return r.getResponseText().trim();};
 const endpoint=ask('Địa chỉ nhận dữ liệu','Dán endpoint từ web → Lịch chụp → Kết nối Sheet.');
 if(!/^https:\/\/[A-Za-z0-9.-]+(?::\d+)?\/[A-Za-z0-9_/-]*api\.php\?route=%2Fsheet-sync%2Freceive$/i.test(endpoint))throw new Error('Endpoint cần là HTTPS của ứng dụng nhân sự.');
 const key=ask('Khóa kết nối','Dán khóa kết nối riêng được Admin tạo trên web. Không gửi khóa vào nhóm.');if(!/^[a-f0-9]{64}$/.test(key))throw new Error('Khóa kết nối cần 64 ký tự hex.');
 const props={AG_ENDPOINT:endpoint,AG_SYNC_KEY:key,AG_SHEET_ID:book.getId()},months=agMonthTabs_(book,new Date());
 if(months.length){p.setProperties(props);p.deleteProperty('AG_HASHES');ui.alert('Đã lưu kết nối. Tự đồng bộ các tab: '+months.map(t=>t.name).join(', ')+'. Tab tháng mới đặt tên dạng "Tháng 11". Chọn Đồng bộ ngay để kiểm tra trước khi bật tự động.');return;}
 // Sheet chưa chia tab theo tháng: cách cũ, một tab cố định.
 const year=ask('Năm của lịch trong tab','Không thấy tab dạng "Tháng 10". Nhập năm, ví dụ 2026. Dùng khi ô Ngày chỉ có ngày/tháng.');if(!/^20\d{2}$/.test(year))throw new Error('Năm không hợp lệ.');
 const tab=ask('Tên tab lịch cần đồng bộ','Nhập chính xác tên tab dữ liệu, không phải tên cả file Sheet.');if(!book.getSheetByName(tab))throw new Error('Không tìm thấy tab này.');
 props.AG_YEAR=year;props.AG_TAB=tab;p.setProperties(props);p.deleteProperty('AG_HASHES');ui.alert('Đã lưu kết nối. Chọn Đồng bộ ngay để kiểm tra trước khi bật tự động.');
}
function agNormalize_(v){return String(v||'').normalize('NFD').replace(/[̀-ͯ]/g,'').replace(/đ/g,'d').replace(/Đ/g,'D').trim().toLowerCase().replace(/\s+/g,' ');}
function agColumn_(c){let s='';for(c++;c>0;c=Math.floor((c-1)/26))s=String.fromCharCode(65+(c-1)%26)+s;return 'Cột '+s;}
function agFindHeader_(headers,names){return headers.findIndex(v=>names.indexOf(agNormalize_(v))!==-1);}
// tabMonth: ô "2/1" trong tab Tháng 12 là tháng 1 năm sau (và ngược lại).
function agDate_(v,year,tabMonth){const s=String(v).trim();let m;if((m=s.match(/^(20\d{2})-(\d{2})-(\d{2})$/)))return s;if(!(m=s.match(/^(\d{1,2})[\/.\-](\d{1,2})(?:[\/.\-](\d{2}|20\d{2}))?$/)))throw new Error('Ngày không hợp lệ: cần ngày/tháng hoặc ngày/tháng/năm.');const day=Number(m[1]),month=Number(m[2]),y=m[3]?Number(m[3].length===2?'20'+m[3]:m[3]):Number(year)+(tabMonth===12&&month===1?1:tabMonth===1&&month===12?-1:0),d=new Date(Date.UTC(y,month-1,day));if(d.getUTCDate()!==day||d.getUTCMonth()!==month-1)throw new Error('Ngày không tồn tại.');return y+'-'+String(month).padStart(2,'0')+'-'+String(day).padStart(2,'0');}
function agTime_(v){const m=String(v).trim().replace(/[hH]$/,'h00').match(/^(\d{1,2})(?:[:hH.](\d{1,2}))?(?::\d{2})?$/);if(!m||Number(m[1])>23||Number(m[2]||0)>59)throw new Error('Giờ không hợp lệ, cần dạng 12:00.');return String(Number(m[1])).padStart(2,'0')+':'+String(Number(m[2]||0)).padStart(2,'0');}
// Mỗi khung giờ 1 khách: Couple 999 / 1499 / 2999 chiếm 2 tiếng (chụp + make up), 399/699 (hoặc ô Gói trống) 1 tiếng;
// Team 349 và gói khác theo "Giữ N tiếng" trong Ghi chú. Giống booking_length() trên web.
function agPrice_(label){const s=String(label||''),m=s.match(/\d{1,3}(?:[.,]\d{3})+|\d+/);if(!m){const t=agNormalize_(s);return t.indexOf('couple')!==-1?999:t.indexOf('team')!==-1?349:0;}const n=Number(m[0].replace(/\D/g,''));return n>=10000?Math.floor(n/1000):n;}
function agLength_(label,note){const n=agPrice_(label);if(n===999||n===1499||n===2999)return 120;if(n===399||n===699)return 60;const m=agNormalize_(note).match(/giu (?:cho )?([1-8]) ?(?:tieng|h)\b/);return m?Number(m[1])*60:60;}
function agHm_(n){return String(Math.floor(n/60)).padStart(2,'0')+':'+String(n%60).padStart(2,'0');}
function agMonthTabs_(book,now){
 const base=now.getFullYear()*12+now.getMonth(),out=[];
 book.getSheets().forEach(function(sheet){
  const m=agNormalize_(sheet.getName()).match(/^thang\s*0?(\d{1,2})(?:\s*[\/.\-]\s*(20\d{2}))?$/);if(!m)return;const month=Number(m[1]);if(month<1||month>12)return;
  const years=m[2]?[Number(m[2])]:[now.getFullYear()-1,now.getFullYear(),now.getFullYear()+1],year=years.find(y=>y*12+month-1-base>=-AG_BACK&&y*12+month-1-base<=AG_AHEAD);
  if(year!==undefined)out.push({sheet:sheet,name:sheet.getName(),year:year,month:month});
 });
 return out.sort((a,b)=>a.year*12+a.month-b.year*12-b.month);
}
function agTabs_(book,cfg){
 const months=agMonthTabs_(book,new Date());if(months.length)return months;
 if(!cfg.AG_TAB)throw new Error('Không thấy tab dạng "Tháng 10". Đặt tên tab theo tháng hoặc chạy lại Cài kết nối web.');
 const sheet=book.getSheetByName(cfg.AG_TAB);if(!sheet)throw new Error('Tab dữ liệu đã đổi tên hoặc không tồn tại.');return [{sheet:sheet,name:cfg.AG_TAB,year:Number(cfg.AG_YEAR),month:0,legacy:true}];
}
// Đọc một tab: hàng tiêu đề, các dòng lịch và vị trí từng ngày. ids dùng chung mọi tab: dòng sao chép mang theo mã của dòng khác thì cấp mã mới.
function agParse_(values,year,tabMonth,ids){
 let header=-1,columns;
 for(let i=0;i<Math.min(values.length,40);i++){const r=values[i],date=agFindHeader_(r,['ngay','date']),time=agFindHeader_(r,['time','gio','gio chup']),name=agFindHeader_(r,['ten khach','khach','khach hang']);if(date>=0&&time>=0&&name>=0){header=i;columns={date:date,time:time,customer_name:name,package_label:agFindHeader_(r,['goi','package']),customer_phone:agFindHeader_(r,['sdt','so dien thoai']),main_alias:agFindHeader_(r,['tho chinh']),support_alias:agFindHeader_(r,['tho phu']),note:agFindHeader_(r,['ghi chu']),deposit_label:agFindHeader_(r,['da coc','coc','tien coc']),received_label:agFindHeader_(r,['da nhan','tien da nhan']),status:agFindHeader_(r,['trang thai']),special:agFindHeader_(r,['dac biet']),makeup:r.findIndex(v=>/^(make ?up|trang diem)\b/.test(agNormalize_(v)))};break;}}
 if(header<0)throw new Error('Không tìm thấy hàng tiêu đề gồm Ngày, Time/Giờ, Tên khách.');
 let idCol=values[header].indexOf('_AG_BOOKING_ID');const addIdHeader=idCol<0;if(addIdHeader)idCol=values[header].length;
 const rows=[],newIds=[],entries=[];let lastDay='';
 for(let i=header+1;i<values.length;i++){
  const r=values[i],time=r[columns.time]||'',name=r[columns.customer_name]||'',dated=!!r[columns.date];if(dated)lastDay=agDate_(r[columns.date],year,tabMonth);
  if(!time&&!name){if(dated)entries.push({row:i,day:lastDay,minutes:null,id:''});continue;}
  if(!lastDay)throw new Error('Thiếu ngày ở dòng '+(i+1));if(!time)throw new Error('Thiếu giờ ở dòng '+(i+1));
  let id=String(r[idCol]||'').trim().toLowerCase();if(!/^[a-f0-9-]{36}$/.test(id)||ids[id]){id=Utilities.getUuid().toLowerCase();newIds.push({row:i,id:id});}ids[id]=true;
  const item={id:id,day:lastDay,time:agTime_(time),source_row:i+1};['customer_name','package_label','customer_phone','main_alias','support_alias','note','deposit_label','received_label'].forEach(k=>{item[k]=columns[k]>=0?String(r[columns[k]]||'').trim():'';});
  // Gửi đủ mọi ô có dữ liệu theo tiêu đề cột để popup hiện nguyên thông tin Sheet; ô trống bỏ qua.
  item.sheet_fields=[];r.forEach((v,c)=>{const value=String(v||'').trim();if(c===idCol||!value)return;item.sheet_fields.push({label:String(values[header][c]||'').trim()||agColumn_(c),value:value});});
  const state=columns.status>=0?agNormalize_(r[columns.status]):'';item.status=['huy','da huy','cancel','cancelled'].indexOf(state)!==-1?'cancelled':'active';rows.push(item);
  entries.push({row:i,day:lastDay,minutes:Number(item.time.slice(0,2))*60+Number(item.time.slice(3)),length:agLength_(item.package_label,item.note),team:agPrice_(item.package_label)===349,active:item.status==='active',name:item.customer_name,id:id});
 }
 if(rows.length>5000)throw new Error('Tab vượt 5.000 lịch.');
 return {header:header,columns:columns,idCol:idCol,addIdHeader:addIdHeader,rows:rows,newIds:newIds,entries:entries};
}
function agLoad_(t,ids,writeIds){
 t.values=t.sheet.getDataRange().getDisplayValues();t.parsed=agParse_(t.values,t.year,t.month,ids);const p=t.parsed;
 if(writeIds){if(p.addIdHeader)t.sheet.getRange(p.header+1,p.idCol+1).setValue('_AG_BOOKING_ID');p.newIds.forEach(x=>t.sheet.getRange(x.row+1,p.idCol+1).setValue(x.id));}
 return t;
}
// Tô đỏ ô giờ của các lịch chồng giờ nhau trong cùng ngày, ghi chú dòng bị trùng; hết trùng thì trả lại màu cũ.
// Các dòng gói Team cùng giờ bắt đầu là một nhóm khách (điền tay từng người), không tính là trùng nhau.
function agConflicts_(p){
 const byDay={},out={};p.entries.forEach(e=>{if(e.minutes!==null&&e.active)(byDay[e.day]=byDay[e.day]||[]).push(e);});
 Object.keys(byDay).forEach(d=>{const l=byDay[d];for(let i=0;i<l.length;i++)for(let j=i+1;j<l.length;j++){const a=l[i],b=l[j];if(a.team&&b.team&&a.minutes===b.minutes)continue;if(a.minutes<b.minutes+b.length&&b.minutes<a.minutes+a.length){(out[a.row]=out[a.row]||[]).push('dòng '+(b.row+1)+' ('+agHm_(b.minutes)+(b.name?' · '+b.name:'')+')');(out[b.row]=out[b.row]||[]).push('dòng '+(a.row+1)+' ('+agHm_(a.minutes)+(a.name?' · '+a.name:'')+')');}}});
 return out;
}
function agMarkConflicts_(t){
 const p=t.parsed,n=t.values.length-p.header-1;if(n<=0)return 0;const clash=agConflicts_(p);
 const range=t.sheet.getRange(p.header+2,p.columns.time+1,n,1),notes=range.getNotes(),bgs=range.getBackgrounds();let changed=false,count=0;
 for(let k=0;k<n;k++){const row=p.header+1+k,old=String(notes[k][0]||''),ours=old.indexOf(AG_MARK)===0,saved=ours&&old.indexOf('\n'+AG_BG)!==-1?old.slice(old.indexOf('\n'+AG_BG)+AG_BG.length+1):null;
  if(clash[row]){count++;const text=AG_MARK+'trùng giờ với '+clash[row].join(', ')+'\n'+AG_BG+(saved||bgs[k][0]);if(old!==text){notes[k][0]=text;bgs[k][0]=AG_RED;changed=true;}}
  else if(ours){notes[k][0]='';bgs[k][0]=saved||'#ffffff';changed=true;}
 }
 if(changed){range.setNotes(notes);range.setBackgrounds(bgs);}return count;
}
function agHash_(text){return Utilities.computeDigest(Utilities.DigestAlgorithm.SHA_256,text,Utilities.Charset.UTF_8).map(b=>('0'+(b&255).toString(16)).slice(-2)).join('');}
function agPost_(cfg,payload){
 const body=JSON.stringify(payload),ts=String(Date.now()),nonce=Utilities.getUuid().toLowerCase();
 const sig=Utilities.computeHmacSha256Signature(ts+'.'+nonce+'.'+body,cfg.AG_SYNC_KEY,Utilities.Charset.UTF_8).map(b=>('0'+(b&255).toString(16)).slice(-2)).join('');
 const response=UrlFetchApp.fetch(cfg.AG_ENDPOINT,{method:'post',contentType:'application/json',headers:{'X-Studio-Time':ts,'X-Studio-Nonce':nonce,'X-Studio-Signature':sig},payload:body,muteHttpExceptions:true,followRedirects:false});
 const code=response.getResponseCode();let d;try{d=JSON.parse(response.getContentText());}catch(e){throw new Error('Web không trả JSON (HTTP '+code+'). Kiểm tra endpoint, HTTPS và WAF/Hostinger.');}
 if(code<200||code>=300)throw new Error('Web từ chối: '+(d.error||code));return d;
}
// Chọn chỗ chèn lịch mới (chỉ số 0 trong values): sau dòng cuối của ngày đó theo thứ tự giờ; khách sớm nhất ngày thì đẩy dòng đầu xuống để giữ ô Ngày (kể cả ô gộp).
function agPlan_(p,day,minutes){
 const list=p.entries,same=list.filter(e=>e.day===day);
 if(same.length){const later=same.find(e=>e.minutes!==null&&e.minutes>minutes);
  // Dòng có ngày nhưng chưa ghi khách (dòng kẻ sẵn): ghi luôn vào dòng đó.
  if(same[0].minutes===null&&(later?later===same[1]:same.length===1))return {op:'fill',row:same[0].row,date:false};
  if(!later)return {op:'after',row:same[same.length-1].row,date:false};return later.row===same[0].row?{op:'shift',row:later.row,date:false}:{op:'before',row:later.row,date:false};}
 const before=list.filter(e=>e.day<day),after=list.filter(e=>e.day>day);
 if(before.length){const d=before.reduce((m,e)=>e.day>m?e.day:m,''),block=list.filter(e=>e.day===d);return {op:'after',row:block[block.length-1].row,date:true};}
 if(after.length){const d=after.reduce((m,e)=>e.day<m?e.day:m,'9999'),block=list.filter(e=>e.day===d);return {op:'before',row:block[0].row,date:true};}
 return {op:'fill',row:p.header+1,date:true};// Tab chưa có khách: ghi vào dòng trống đầu tiên dưới tiêu đề.
}
// Ô mẫu để ghi đúng kiểu dữ liệu đang dùng (ngày/giờ dạng ngày-giờ hay chữ, giá dạng số hay chữ): lấy từ tab này, không có thì từ tab tháng khác.
// Chụp lại trước khi chèn dòng (ô mẫu có thể bị đẩy xuống).
function agSample_(tabs,t,column){
 for(const x of [t].concat(tabs.filter(y=>y!==t&&y.parsed)))if(x.parsed&&x.parsed.columns[column]>=0){const c=x.parsed.columns[column],e=x.parsed.entries.find(e=>String(x.values[e.row][c]||'')!=='');if(e){const cell=x.sheet.getRange(e.row+1,c+1);return {value:cell.getValue(),format:cell.getNumberFormat(),shown:cell.getDisplayValue()};}}
 return null;
}
function agSamples_(tabs,t){const out={};['date','time','package_label','deposit_label'].forEach(k=>{out[k]=agSample_(tabs,t,k);});return out;}
function agIsDate_(v){return Object.prototype.toString.call(v)==='[object Date]';}
function agWriteDate_(cell,day,sample){
 const [y,m,d]=day.split('-').map(Number);
 if(sample&&agIsDate_(sample.value)){cell.setNumberFormat(sample.format).setValue(new Date(y,m-1,d,12));return;}
 const shown=sample?sample.shown:'',pad=n=>/^\d{2}/.test(shown)||!shown?String(n).padStart(2,'0'):String(n);
 cell.setNumberFormat('@').setValue(/^20\d{2}-/.test(shown)?day:pad(d)+'/'+pad(m)+(/^\d{1,2}[\/.\-]\d{1,2}[\/.\-]\d{2,4}$/.test(shown)?'/'+y:''));
}
function agWriteTime_(cell,hhmm,sample){
 if(sample&&agIsDate_(sample.value)){cell.setNumberFormat(sample.format).setValue(hhmm);return;}
 const [h,m]=hhmm.split(':').map(Number);cell.setNumberFormat('@').setValue(/^\d{1,2}\s*h/i.test(sample?sample.shown:'')?h+'h'+(m?String(m).padStart(2,'0'):''):hhmm);
}
// Ô là dropdown (Makeup: Nam / Nữ / Làm Tóc; Đặc Biệt: Ô TÔ / Ngoại Cảnh…; Gói: 399 / Couple 999 / Team 349…): ghi đúng chữ lựa chọn trên Sheet.
function agOptions_(cell){
 try{const rule=cell.getDataValidation(),C=SpreadsheetApp.DataValidationCriteria;if(rule){const type=rule.getCriteriaType(),value=rule.getCriteriaValues()[0];return type===C.VALUE_IN_LIST?value.map(String):type===C.VALUE_IN_RANGE?value.getDisplayValues().reduce((a,row)=>a.concat(row),[]):[];}}catch(e){}
 return [];
}
// Lịch cũ có Makeup chọn nhiều thì cách nhau ", " như dropdown chọn nhiều.
function agPickOptions_(cell,text){const options=agOptions_(cell);return text.split(',').map(x=>x.trim()).filter(Boolean).map(x=>options.find(o=>agNormalize_(o)===agNormalize_(x))||x).join(', ');}
function agWriteValues_(t,r,q,samples){
 const p=t.parsed,sheet=t.sheet,c=p.columns,v=q.values,written={};const cell=k=>sheet.getRange(r,c[k]+1);
 agWriteTime_(cell('time'),q.time,samples.time);written[c.time]=true;
 // Make up (Nam/Nữ/Làm tóc), Đặc biệt (Ô TÔ/Ngoại Cảnh…): Sheet có cột đó thì ghi vào cột, không thì ghép vào đầu Ghi chú.
 const makeup=String(v.makeup||''),special=String(v.special||''),note=[c.special<0&&special?'Đặc biệt: '+special:'',c.makeup<0&&makeup?'Make up: '+makeup:'',String(v.note||'')].filter(Boolean).join(' · '),text={makeup:makeup,special:special,note:note};
 ['customer_name','customer_phone','main_alias','support_alias','note','makeup','special','package_label','deposit_label'].forEach(k=>{if(c[k]<0)return;written[c[k]]=true;const value=String(k in text?text[k]:v[k]||'');if(!value)return;
  // Gói/cọc đang là số trên Sheet thì ghi số (giữ công thức cộng tiền); còn lại ghi chữ để giữ số 0 đầu SĐT.
  const sample=samples[k]||null,number=/^\d[\d.,]*k?$/i.test(value)?Number(value.replace(/[.,]/g,'').replace(/k$/i,''))*(/k$/i.test(value)?1000:1):NaN;
  if(k==='makeup'||k==='special'){cell(k).setValue(agPickOptions_(cell(k),value));return;}
  const option=k==='package_label'?agOptions_(cell(k)).find(o=>agPrice_(o)===agPrice_(value)):null;if(option){cell(k).setValue(option);return;}
  if(sample&&typeof sample.value==='number'&&!isNaN(number))cell(k).setValue(k==='package_label'&&number>=10000&&sample.value<10000?number/1000:number);else cell(k).setNumberFormat('@').setValue(value);});
 sheet.getRange(r,p.idCol+1).setValue(q.booking_id);written[p.idCol]=true;
 return written;
}
function agFind_(book,tabs,id,tabName){
 for(const t of tabs)if(t.parsed){const e=t.parsed.rows.find(x=>x.id===id);if(e)return {t:t,row:e.source_row};}
 const sheet=tabName&&book.getSheetByName(tabName);if(!sheet||tabs.some(t=>t.sheet.getName()===tabName))return null;
 const values=sheet.getDataRange().getDisplayValues();for(let i=0;i<Math.min(values.length,40);i++){const c=values[i].indexOf('_AG_BOOKING_ID');if(c<0)continue;for(let j=i+1;j<values.length;j++)if(String(values[j][c]).trim().toLowerCase()===id)return {t:{sheet:sheet,name:tabName,year:0,month:0},row:j+1,raw:true};}
 return null;
}
function agNewTab_(book,tabs,year,month){
 const ym=year*12+month,ready=tabs.filter(t=>t.parsed&&!t.legacy),earlier=ready.filter(t=>t.year*12+t.month<ym),template=earlier.slice(-1)[0]||ready.find(t=>t.year*12+t.month>ym);
 if(!template)throw new Error('Chưa có tab "Tháng '+month+'" và không có tab tháng nào để làm mẫu.');
 // Như cách làm tay: nhân bản tab tháng liền trước (giữ tiêu đề, dropdown Makeup, màu, độ rộng cột) rồi xóa các dòng khách; đặt ngay sau tab đó (hoặc ngay trước tab tháng sau).
 const name='Tháng '+month,sheet=template.sheet.copyTo(book).setName(name),first=template.parsed.header+2,last=sheet.getLastRow();
 if(last>=first){if(sheet.getMaxRows()<=last)sheet.insertRowsAfter(last,1);sheet.deleteRows(first,last-first+1);}
 book.setActiveSheet(sheet);book.moveActiveSheet(template.sheet.getIndex()+(earlier.length?1:0));
 const t={sheet:sheet,name:name,year:year,month:month};tabs.push(t);tabs.sort((a,b)=>a.year*12+a.month-b.year*12-b.month);return agLoad_(t,{},false);
}
// Lịch thêm trên web → chèn dòng. Đã có mã trên Sheet (lần trước ghi rồi mà chưa kịp báo) thì không chèn lại.
function agInsert_(book,tabs,q){
 const found=agFind_(book,tabs,q.booking_id,q.tab);if(found)return {id:q.id,ok:true,tab:found.t.name,row:found.row};
 const [y,m]=q.day.split('-').map(Number);let t=tabs.find(x=>!x.legacy&&x.year===y&&x.month===m)||tabs.find(x=>x.legacy);
 if(t&&t.error)throw new Error('Tab '+t.name+' đang lỗi: '+t.error);if(!t)t=agNewTab_(book,tabs,y,m);
 const p=t.parsed,sheet=t.sheet,minutes=Number(q.time.slice(0,2))*60+Number(q.time.slice(3)),plan=agPlan_(p,q.day,minutes),dateCol=p.columns.date+1,width=Math.max(sheet.getLastColumn(),p.idCol+1),samples=agSamples_(tabs,t);let r;
 if(plan.op==='shift'){
  // Khách sớm nhất ngày: chèn dưới dòng đầu, chuyển nội dung dòng đầu (trừ ô Ngày) xuống, ghi lịch mới vào dòng đầu.
  r=plan.row+1;sheet.insertRowAfter(r);[[1,dateCol-1],[dateCol+1,width]].forEach(([a,b])=>{if(b<a)return;sheet.getRange(r,a,1,b-a+1).copyTo(sheet.getRange(r+1,a,1,b-a+1));sheet.getRange(r,a,1,b-a+1).clearContent();});
 }else if(plan.op==='fill'){r=plan.row+1;if(sheet.getMaxRows()<r)sheet.insertRowAfter(r-1);}
 else if(plan.op==='before'){r=plan.row+1;sheet.insertRowBefore(r);}
 else{
  const last=plan.row+1,merged=!plan.date&&last>p.header+1?sheet.getRange(last,dateCol).getMergedRanges()[0]:null;sheet.insertRowAfter(last);r=last+1;
  if(merged&&merged.getLastRow()===last)sheet.getRange(merged.getRow(),dateCol,last-merged.getRow()+2,1).merge();
 }
 if(plan.date)agWriteDate_(sheet.getRange(r,dateCol),q.day,samples.date);
 const written=agWriteValues_(t,r,q,samples),near=plan.op==='fill'?r:plan.op==='after'?r-1:r+1;
 // Cột công thức (VD Còn lại = Giá − Cọc): chép công thức của dòng lịch kế bên.
 if(near>p.header+1&&near!==r){const f=sheet.getRange(near,1,1,width).getFormulasR1C1()[0];f.forEach((x,c)=>{if(x&&!written[c]&&c!==dateCol-1)sheet.getRange(r,c+1).setFormulaR1C1(x);});}
 agLoad_(t,{},false);return {id:q.id,ok:true,tab:t.name,row:r};
}
// Lịch hủy trên web → ghi "Hủy" vào cột Trạng thái (chưa có cột thì thêm ở cuối).
function agCancel_(book,tabs,q){
 const f=agFind_(book,tabs,q.booking_id,q.tab);if(!f)throw new Error('Không thấy dòng của lịch này trên Sheet (dòng đã bị xóa?).');
 const t=f.t,sheet=t.sheet;let col=t.parsed?t.parsed.columns.status:-1;
 if(col<0&&f.raw){const v=sheet.getDataRange().getDisplayValues();for(let i=0;i<Math.min(v.length,40)&&col<0;i++)if(v[i].indexOf('_AG_BOOKING_ID')!==-1){col=agFindHeader_(v[i],['trang thai']);if(col<0){col=v[i].length;sheet.getRange(i+1,col+1).setValue('Trạng thái');}}}
 if(col<0){col=Math.max(sheet.getLastColumn(),t.parsed.idCol+1);sheet.getRange(t.parsed.header+1,col+1).setValue('Trạng thái');}
 sheet.getRange(f.row,col+1).setValue('Hủy');if(t.parsed)agLoad_(t,{},false);return {id:q.id,ok:true,tab:t.name,row:f.row};
}
function dongBoAnhGau(e){
 const forced=!(e&&e.triggerUid),lock=LockService.getScriptLock();if(!lock.tryLock(1000))return;
 try{
  const p=PropertiesService.getScriptProperties(),cfg=p.getProperties();if(!cfg.AG_ENDPOINT||!cfg.AG_SYNC_KEY)throw new Error('Chưa cài kết nối. Mở menu Anh Gấu Studio → Cài kết nối web.');
  const book=SpreadsheetApp.openById(cfg.AG_SHEET_ID),tabs=agTabs_(book,cfg),ids={},errors=[],hashes=JSON.parse(cfg.AG_HASHES||'{}'),now=Date.now();let sent=0,clashes=0,wrote=0;
  tabs.forEach(function(t){
   try{
    agLoad_(t,ids,true);clashes+=agMarkConflicts_(t);
    const rows=t.parsed.rows,hash=agHash_(JSON.stringify(rows)),old=hashes[t.name];if(!forced&&old&&old.h===hash&&now-old.t<AG_FORCE_MS)return;
    for(let i=0;i<Math.max(1,rows.length);i+=100)sent+=agPost_(cfg,{sheet_id:cfg.AG_SHEET_ID,tab:t.name,rows:rows.slice(i,i+100)}).received;hashes[t.name]={h:hash,t:now};
   }catch(err){t.error=String(err.message);errors.push(t.name+': '+t.error);}
  });
  p.setProperty('AG_HASHES',JSON.stringify(hashes));
  const queue=agPost_(cfg,{sheet_id:cfg.AG_SHEET_ID,poll:true}).queue||[];
  if(queue.length){
   const acks=queue.map(function(q){try{return q.action==='create'?agInsert_(book,tabs,q):q.action==='cancel'?agCancel_(book,tabs,q):{id:q.id,ok:false,error:'Không hỗ trợ việc '+q.action};}catch(err){return {id:q.id,ok:false,error:String(err.message).slice(0,280)};}});
   agPost_(cfg,{sheet_id:cfg.AG_SHEET_ID,ack:acks});wrote=acks.filter(a=>a.ok).length;acks.filter(a=>!a.ok).forEach(a=>errors.push('Ghi lịch từ web: '+a.error));
  }
  if(errors.length)throw new Error(errors.join(' | '));
  p.setProperty('AG_LAST_SUCCESS',new Date().toISOString());p.deleteProperty('AG_LAST_ERROR');
  if(forced)book.toast('Gửi '+sent+' lịch ('+tabs.map(t=>t.name).join(', ')+')'+(wrote?' · ghi '+wrote+' lịch từ web':'')+(clashes?' · '+clashes+' dòng trùng giờ (ô giờ tô đỏ)':'')+'.','Anh Gấu Studio',8);
 }catch(e){PropertiesService.getScriptProperties().setProperty('AG_LAST_ERROR',String(e.message));throw e;}finally{lock.releaseLock();}
}
function batTuDongAnhGau(){tatTuDongAnhGau();ScriptApp.newTrigger('dongBoAnhGau').timeBased().everyMinutes(1).create();SpreadsheetApp.getActive().toast('Đã bật đồng bộ định kỳ. Thời điểm chạy thực tế phụ thuộc Google.');}
function tatTuDongAnhGau(){ScriptApp.getProjectTriggers().filter(t=>t.getHandlerFunction()==='dongBoAnhGau').forEach(t=>ScriptApp.deleteTrigger(t));}
