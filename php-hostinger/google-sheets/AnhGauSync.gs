/** Bound Google Apps Script. No public Sheet publication, OAuth client or service-account key needed.
 * Paste in Extensions > Apps Script of the TEST sheet. Configure via menu; do not hardcode secrets.
 */
function onOpen(){SpreadsheetApp.getUi().createMenu('Anh Gấu Studio').addItem('Cài kết nối web','caiKetNoiAnhGau').addItem('Đồng bộ ngay','dongBoAnhGau').addItem('Bật tự đồng bộ mỗi phút','batTuDongAnhGau').addItem('Tắt tự đồng bộ','tatTuDongAnhGau').addToUi();}
function caiKetNoiAnhGau(){
 const ui=SpreadsheetApp.getUi(),p=PropertiesService.getScriptProperties();
 const ask=function(title,text){const r=ui.prompt(title,text,ui.ButtonSet.OK_CANCEL);if(r.getSelectedButton()!==ui.Button.OK)throw new Error('Đã hủy cài đặt.');return r.getResponseText().trim();};
 const endpoint=ask('Địa chỉ nhận dữ liệu','Dán endpoint từ web → Lịch chụp → Kết nối Sheet.');
 if(!/^https:\/\/[A-Za-z0-9.-]+(?::\d+)?\/[A-Za-z0-9_/-]*api\.php\?route=%2Fsheet-sync%2Freceive$/i.test(endpoint))throw new Error('Endpoint cần là HTTPS của ứng dụng nhân sự.');
 const key=ask('Khóa kết nối','Dán khóa kết nối riêng được Admin tạo trên web. Không gửi khóa vào nhóm.');if(!/^[a-f0-9]{64}$/.test(key))throw new Error('Khóa kết nối cần 64 ký tự hex.');
 const year=ask('Năm của lịch trong tab','Nhập năm, ví dụ 2026. Dùng khi ô Ngày chỉ có ngày/tháng.');if(!/^20\d{2}$/.test(year))throw new Error('Năm không hợp lệ.');
 const tab=ask('Tên tab lịch cần đồng bộ','Nhập chính xác tên tab dữ liệu, không phải tên cả file Sheet.');if(!SpreadsheetApp.getActive().getSheetByName(tab))throw new Error('Không tìm thấy tab này.');
 p.setProperties({AG_ENDPOINT:endpoint,AG_SYNC_KEY:key,AG_YEAR:year,AG_TAB:tab,AG_SHEET_ID:SpreadsheetApp.getActive().getId()});ui.alert('Đã lưu kết nối. Chọn Đồng bộ ngay để kiểm tra trước khi bật tự động.');
}
function agNormalize_(v){return String(v||'').normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/đ/g,'d').replace(/Đ/g,'D').trim().toLowerCase().replace(/\s+/g,' ');}
function agColumn_(c){let s='';for(c++;c>0;c=Math.floor((c-1)/26))s=String.fromCharCode(65+(c-1)%26)+s;return 'Cột '+s;}
function agFindHeader_(headers,names){return headers.findIndex(v=>names.indexOf(agNormalize_(v))!==-1);}
function agDate_(v,year){const s=String(v).trim();let m;if((m=s.match(/^(20\d{2})-(\d{2})-(\d{2})$/)))return s;if(!(m=s.match(/^(\d{1,2})[\/.\-](\d{1,2})(?:[\/.\-](\d{2}|20\d{2}))?$/)))throw new Error('Ngày không hợp lệ: cần ngày/tháng hoặc ngày/tháng/năm.');const y=m[3]?(m[3].length===2?'20'+m[3]:m[3]):year;const day=Number(m[1]),month=Number(m[2]),d=new Date(Date.UTC(Number(y),month-1,day));if(d.getUTCDate()!==day||d.getUTCMonth()!==month-1)throw new Error('Ngày không tồn tại.');return y+'-'+String(month).padStart(2,'0')+'-'+String(day).padStart(2,'0');}
function agTime_(v){const m=String(v).trim().replace(/[hH]$/,'h00').match(/^(\d{1,2})(?:[:hH.](\d{1,2}))?(?::\d{2})?$/);if(!m||Number(m[1])>23||Number(m[2]||0)>59)throw new Error('Giờ không hợp lệ, cần dạng 12:00.');return String(Number(m[1])).padStart(2,'0')+':'+String(Number(m[2]||0)).padStart(2,'0');}
function dongBoAnhGau(){
 const lock=LockService.getScriptLock();if(!lock.tryLock(1000))return;
 try{
  const p=PropertiesService.getScriptProperties(),cfg=p.getProperties();if(!cfg.AG_ENDPOINT||!cfg.AG_SYNC_KEY||!cfg.AG_YEAR||!cfg.AG_TAB)throw new Error('Chưa cài kết nối. Mở menu Anh Gấu Studio → Cài kết nối web.');
  const book=SpreadsheetApp.openById(cfg.AG_SHEET_ID),tab=book.getSheetByName(cfg.AG_TAB);if(!tab)throw new Error('Tab dữ liệu đã đổi tên hoặc không tồn tại.');const values=tab.getDataRange().getDisplayValues();
  let header=-1,columns;
  for(let i=0;i<Math.min(values.length,40);i++){const r=values[i],date=agFindHeader_(r,['ngay','date']),time=agFindHeader_(r,['time','gio','gio chup']),name=agFindHeader_(r,['ten khach','khach','khach hang']);if(date>=0&&time>=0&&name>=0){header=i;columns={date:date,time:time,customer_name:name,package_label:agFindHeader_(r,['goi','package']),customer_phone:agFindHeader_(r,['sdt','so dien thoai']),main_alias:agFindHeader_(r,['tho chinh']),support_alias:agFindHeader_(r,['tho phu']),note:agFindHeader_(r,['ghi chu']),deposit_label:agFindHeader_(r,['da coc','coc','tien coc']),received_label:agFindHeader_(r,['da nhan','tien da nhan']),status:agFindHeader_(r,['trang thai'])};break;}}
  if(header<0)throw new Error('Không tìm thấy hàng tiêu đề gồm Ngày, Time/Giờ, Tên khách.');
  let idCol=values[header].indexOf('_AG_BOOKING_ID');if(idCol<0){idCol=values[header].length;tab.getRange(header+1,idCol+1).setValue('_AG_BOOKING_ID');}
  const rows=[],ids={};let lastDay='';
  for(let i=header+1;i<values.length;i++){
   const r=values[i],time=r[columns.time]||'',name=r[columns.customer_name]||'';if(r[columns.date])lastDay=agDate_(r[columns.date],cfg.AG_YEAR);if(!time&&!name)continue;if(!lastDay)throw new Error('Thiếu ngày ở dòng '+(i+1));if(!time)throw new Error('Thiếu giờ ở dòng '+(i+1));
   let id=String(r[idCol]||'').trim();if(!id){id=Utilities.getUuid().toLowerCase();tab.getRange(i+1,idCol+1).setValue(id);}if(!/^[a-f0-9-]{36}$/i.test(id)||ids[id])throw new Error('Mã lịch trùng hoặc không hợp lệ ở dòng '+(i+1)+'. Không sao chép cột _AG_BOOKING_ID cho lịch mới.');ids[id]=true;
   const item={id:id,day:lastDay,time:agTime_(time),source_row:i+1};['customer_name','package_label','customer_phone','main_alias','support_alias','note','deposit_label','received_label'].forEach(k=>{item[k]=columns[k]>=0?String(r[columns[k]]||'').trim():'';});
   // Gửi đủ mọi ô có dữ liệu theo tiêu đề cột để popup hiện nguyên thông tin Sheet; ô trống bỏ qua.
   item.sheet_fields=[];r.forEach((v,c)=>{const value=String(v||'').trim();if(c===idCol||!value)return;item.sheet_fields.push({label:String(values[header][c]||'').trim()||agColumn_(c),value:value});});
   const state=columns.status>=0?agNormalize_(r[columns.status]):'';item.status=['huy','da huy','cancel','cancelled'].indexOf(state)!==-1?'cancelled':'active';rows.push(item);
  }
  if(rows.length>5000)throw new Error('Tab vượt 5.000 lịch. Chia tab theo tháng để đồng bộ.');
  let imported=0;
  for(let i=0;i<Math.max(1,rows.length);i+=100){
   const body=JSON.stringify({sheet_id:cfg.AG_SHEET_ID,tab:cfg.AG_TAB,rows:rows.slice(i,i+100)}),ts=String(Date.now()),nonce=Utilities.getUuid().toLowerCase();
   const sig=Utilities.computeHmacSha256Signature(ts+'.'+nonce+'.'+body,cfg.AG_SYNC_KEY,Utilities.Charset.UTF_8).map(b=>('0'+(b&255).toString(16)).slice(-2)).join('');
   const response=UrlFetchApp.fetch(cfg.AG_ENDPOINT,{method:'post',contentType:'application/json',headers:{'X-Studio-Time':ts,'X-Studio-Nonce':nonce,'X-Studio-Signature':sig},payload:body,muteHttpExceptions:true,followRedirects:false});
   const code=response.getResponseCode();let d;try{d=JSON.parse(response.getContentText());}catch(e){throw new Error('Web không trả JSON (HTTP '+code+'). Kiểm tra endpoint, HTTPS và WAF/Hostinger.');}
   if(code<200||code>=300)throw new Error('Web từ chối: '+(d.error||code));imported+=d.received;
  }
  p.setProperty('AG_LAST_SUCCESS',new Date().toISOString());p.deleteProperty('AG_LAST_ERROR');book.toast('Đồng bộ thành công '+imported+' lịch.','Anh Gấu Studio',5);
 }catch(e){PropertiesService.getScriptProperties().setProperty('AG_LAST_ERROR',String(e.message));throw e;}finally{lock.releaseLock();}
}
function batTuDongAnhGau(){tatTuDongAnhGau();ScriptApp.newTrigger('dongBoAnhGau').timeBased().everyMinutes(1).create();SpreadsheetApp.getActive().toast('Đã bật đồng bộ định kỳ. Thời điểm chạy thực tế phụ thuộc Google.');}
function tatTuDongAnhGau(){ScriptApp.getProjectTriggers().filter(t=>t.getHandlerFunction()==='dongBoAnhGau').forEach(t=>ScriptApp.deleteTrigger(t));}
