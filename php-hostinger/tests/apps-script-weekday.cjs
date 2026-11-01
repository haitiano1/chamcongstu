// Cột Thứ bên phải cột Ngày (01/11/2026): script tự ghi thứ kiểu tắt T2 … T7, CN theo ngày, đi cùng ô Ngày khi chèn / xóa / chuyển dòng (kể cả ô gộp).
// Chạy: node php-hostinger/tests/apps-script-weekday.cjs
const {Book,load}=require('./fixtures/sheet-mock.cjs');
const gs=process.argv[2]||'php-hostinger/google-sheets/AnhGauSync.gs';let pass=0,fail=0;
const ok=(c,m)=>{if(c){pass++;console.log('  ✓ '+m);}else{fail++;console.log('  ✗ '+m);}};
const H=['Ngày','Thứ','Time','Tên khách','Gói','SĐT','Thợ chính','Thợ phụ','Ghi chú','_AG_BOOKING_ID'];
const ID=n=>'00000000-0000-4000-8000-'+String(n).padStart(12,'0');
const row=(d,w,t,name,id)=>[d,w,t,name,699,'','Huy','','',id?ID(id):''];
function book(){
 const b=new Book();
 // 01/11/2026 là Chủ nhật: 05/11 thứ 5, 09/11 thứ 2, 10/11 thứ 3, 12/11 thứ 5, 07/11 thứ 7.
 const n=b.add('Tháng 11',[H,row('05/11','','09:00','Bình',1),row('','','13:00','Chi',2),row('09/11','Thứ 3','11:00','Dũng',3),row('','','14:00','Én',4),row('10/11','','','',0),row('11/11','ca sáng nghỉ','10:00','Giang',5)]);
 n.merges.push({c:1,r1:2,r2:3},{c:2,r1:2,r2:3});
 b.add('Tháng 12',[H,row('01/12','T3','10:00','Hà',6),row('02/12','','10:00','Ích',7),row('03/12','Thứ 5','10:00','Khánh',9),row('06/12','chủ nhật','10:00','Lâm',10)]);
 b.add('Tháng 10',[['Ngày','Time','Tên khách','Gói','_AG_BOOKING_ID'],['30/10','10:00','Khoa',699,ID(8)]]);
 return b;
}
function server(state){return body=>{if(body.poll)return [200,{queue:state.queue.splice(0)}];if(body.ack){state.acks.push(...body.ack);return [200,{acked:body.ack.length}];}state.tabs[body.tab]=body.rows;return [200,{received:body.rows.length}];};}
const run=(ctx,e)=>{try{ctx.dongBoAnhGau(e);return null;}catch(err){return err.message;}};
const cfg={AG_ENDPOINT:'https://x/api.php?route=%2Fsheet-sync%2Freceive',AG_SYNC_KEY:'k'.repeat(64),AG_SHEET_ID:'SHEETID'};
const create=(n,day,time)=>({id:ID(900+n),booking_id:ID(500+n),action:'create',tab:'Tháng '+Number(day.slice(5,7)),day,time,length:60,values:{customer_name:'Web'+n,customer_phone:'',package_label:'699',main_alias:'Huy',support_alias:'',deposit_label:'',note:''}});
const del=(n,id)=>({id:ID(800+n),booking_id:id,action:'delete',tab:'Tháng 11',day:'',time:'',values:{}});
const upd=(n,id,day,time)=>({id:ID(700+n),booking_id:id,action:'update',tab:'Tháng 11',day,time,length:60,changed:['day'],from_day:day,values:{customer_name:'',customer_phone:'',package_label:'',main_alias:'Huy',support_alias:'',deposit_label:'',note:'',makeup:'',special:''}});

const b=book(),st={queue:[],acks:[],tabs:{}},ctx=load(gs,b,server(st),cfg),s11=b.getSheetByName('Tháng 11'),s12=b.getSheetByName('Tháng 12');
const view=s=>s.rows().slice(1).map(r=>[r[0],r[1],r[2],r[3]].join('|'));
const merged=(s,c)=>s.merges.filter(m=>m.c===c).map(m=>m.r1+'-'+m.r2).sort().join(',');

console.log('1. Đồng bộ: tự ghi thứ kiểu tắt, sửa thứ sai, đổi kiểu viết dài, chữ khác để nguyên');
ok(run(ctx)===null,'chạy không lỗi');
let v=view(s11);console.log('     Tháng 11:',JSON.stringify(v));
ok(v[0]==='05/11|T5|09:00|Bình','05/11 ghi "T5"');
ok(v[2]==='09/11|T2|11:00|Dũng','09/11 ghi sai "Thứ 3" → sửa thành "T2"');
ok(v[4]==='10/11|T3||','dòng có ngày chưa có khách cũng ghi thứ');
ok(v[5]==='11/11|ca sáng nghỉ|10:00|Giang','ô Thứ ghi chữ khác thì để nguyên');
ok(v[1]==='||13:00|Chi'&&v[3]==='||14:00|Én','dòng không có ngày thì không ghi thứ');
ok(view(s12)[0]==='01/12|T3|10:00|Hà'&&view(s12)[1]==='02/12|T4|10:00|Ích','01/12 giữ "T3", 02/12 ghi "T4"');
ok(view(s12)[2]==='03/12|T5|10:00|Khánh'&&view(s12)[3]==='06/12|CN|10:00|Lâm','đúng thứ nhưng viết dài ("Thứ 5", "chủ nhật") → đổi thành "T5", "CN"');
ok(st.tabs['Tháng 11'].length===5&&st.tabs['Tháng 11'][0].time==='09:00'&&st.tabs['Tháng 11'][0].customer_name==='Bình','đọc đúng giờ, tên khách khi có thêm cột Thứ');
ok(!st.tabs['Tháng 11'].some(r=>r.sheet_fields.some(f=>f.label==='Thứ')),'không gửi cột Thứ vào popup (đã có ngày)');
ok(b.getSheetByName('Tháng 10').rows()[1].join('|')==='30/10|10:00|Khoa|699|'+ID(8),'tab không có cột Thứ thì không đổi gì');

console.log('2. Lịch web là khách sớm nhất ngày gộp ô: ô Ngày, ô Thứ giữ ở dòng đầu');
st.queue=[create(1,'2026-11-05','08:00')];ok(run(ctx)===null,'chạy không lỗi');v=view(s11);
ok(v[0]==='05/11|T5|08:00|Web1'&&v[1]==='||09:00|Bình'&&v[2]==='||13:00|Chi','dòng đầu: 05/11 T5 08:00, Bình chuyển xuống');
ok(merged(s11,1).includes('2-4')&&merged(s11,2).includes('2-4'),'ô gộp Ngày và Thứ giãn ra 3 dòng: '+merged(s11,1)+' / '+merged(s11,2));

console.log('3. Lịch web cuối ngày gộp ô: gộp Ngày và Thứ cùng giãn');
st.queue=[create(2,'2026-11-05','15:00')];ok(run(ctx)===null,'chạy không lỗi');v=view(s11);
ok(v[3]==='||15:00|Web2','15:00 thêm cuối ngày 05/11');ok(merged(s11,1).includes('2-5')&&merged(s11,2).includes('2-5'),'ô gộp Ngày và Thứ giãn ra 4 dòng');

console.log('4. Lịch web ngày mới: ghi ngày và thứ');
st.queue=[create(3,'2026-11-07','10:00')];ok(run(ctx)===null,'chạy không lỗi');v=view(s11);
ok(v.includes('07/11|T7|10:00|Web3'),'07/11 ghi "T7": '+JSON.stringify(v));

console.log('5. Xóa khách đầu ngày: ô Ngày và Thứ chuyển xuống khách kế tiếp');
st.queue=[del(1,ID(501))];ok(run(ctx)===null,'chạy không lỗi');v=view(s11);
ok(v[0]==='05/11|T5|09:00|Bình','ô gộp: Bình nhận 05/11 T5');ok(merged(s11,1).includes('2-4')&&merged(s11,2).includes('2-4'),'gộp lại 3 dòng còn lại');
const iD=v.indexOf('09/11|T2|11:00|Dũng');st.queue=[del(2,ID(3))];ok(run(ctx)===null,'chạy không lỗi');v=view(s11);
ok(v[iD]==='09/11|T2|14:00|Én','không gộp: Én nhận 09/11 T2');

console.log('6. Sửa lịch đổi ngày: dòng chuyển sang ngày mới có thứ mới, không mang thứ cũ');
st.queue=[upd(1,ID(5),'2026-11-12','10:00')];ok(run(ctx)===null,'chạy không lỗi');v=view(s11);console.log('     Tháng 11:',JSON.stringify(v));
ok(v.includes('12/11|T5|10:00|Giang'),'Giang sang 12/11 T5');ok(!v.some(r=>r.includes('ca sáng nghỉ')&&r.includes('12/11')),'không mang ô Thứ của dòng cũ');
ok(st.acks.every(a=>a.ok),'mọi việc báo web thành công: '+JSON.stringify(st.acks.filter(a=>!a.ok)));

console.log(`\n${pass} đạt, ${fail} lỗi`);process.exit(fail?1:0);
