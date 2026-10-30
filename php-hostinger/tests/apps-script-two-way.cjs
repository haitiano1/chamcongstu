// Sheet 2 chiều (29/10/2026): chạy AnhGauSync.gs trên Sheet giả lập — chèn lịch web, ghi Hủy, xóa dòng, sửa lịch (30/10), tô trùng giờ, tab Tháng N. Chạy: node php-hostinger/tests/apps-script-two-way.cjs
const {Book,load}=require('./fixtures/sheet-mock.cjs');
const gs=process.argv[2]||'php-hostinger/google-sheets/AnhGauSync.gs';let pass=0,fail=0;
const ok=(c,m)=>{if(c){pass++;console.log('  ✓ '+m);}else{fail++;console.log('  ✗ '+m);}};
const H=['Ngày','Time','Tên khách','Gói','SĐT','Thợ chính','Thợ phụ','Đã cọc','Còn lại','Ghi chú'];
const ID=(n)=>('00000000-0000-4000-8000-'+String(n).padStart(12,'0'));
function book(){
 const b=new Book();
 // Tab tháng 10: ngày dạng chữ, giờ dạng chữ, gói là số, có công thức Còn lại.
 b.add('Tháng 10',[['LỊCH CHỤP THÁNG 10'],H,
  ['28/10','10:00','Lan',699,'0901','Huy','','200k',{v:'=f',formula:'=R[0]C[-5]-1'},''],
  ['','14:00','Mai',1499,'','Cường','','',{v:'=f',formula:'=R[0]C[-5]-1'},''],
  ['29/10','09:00','An',399,'','Huy','','','',''],
 ]);
 // Tab tháng 11: ô ngày gộp theo ngày, giờ là kiểu thời gian, có mã sẵn.
 const n=b.add('Tháng 11',[H.concat(['_AG_BOOKING_ID']),
  ['05/11',{v:new Date(1899,11,30,9,0),fmt:'HH:mm'},'Bình',699,'','Huy','','','','',ID(1)],
  ['',{v:new Date(1899,11,30,13,0),fmt:'HH:mm'},'Chi',1499,'','Huy','','','','',ID(2)],
  ['09/11',{v:new Date(1899,11,30,11,0),fmt:'HH:mm'},'Dũng',399,'','Cường','','','','',ID(3)],
  ['10/11','','','','','','','','','',''],
 ]);n.merges.push({c:1,r1:2,r2:3});
 b.add('Tháng 5',[H,['03/05','10:00','Cũ',699,'','','','','','']]);// ngoài khoảng: bỏ qua
 b.add('Tháng 1',[H,['04/01','10:00','Tết',2999,'','','','','','']]);// năm sau
 return b;
}
function server(state){return (body)=>{state.posts.push(body);if(body.poll)return [200,{queue:state.queue.splice(0)}];if(body.ack){state.acks.push(...body.ack);return [200,{acked:body.ack.length}];}state.tabs[body.tab]=body.rows;return [200,{received:body.rows.length}];};}
const run=(ctx,e)=>{try{ctx.dongBoAnhGau(e);return null;}catch(err){return err.message;}};
const cfg={AG_ENDPOINT:'https://x/api.php?route=%2Fsheet-sync%2Freceive',AG_SYNC_KEY:'k'.repeat(64),AG_SHEET_ID:'SHEETID',AG_TAB:'LICH_CHUP',AG_YEAR:'2026'};

console.log('1. Đồng bộ các tab tháng');
let b=book(),st={posts:[],queue:[],acks:[],tabs:{}},ctx=load(gs,b,server(st),cfg);
ok(run(ctx)===null,'chạy không lỗi');
ok(Object.keys(st.tabs).join()==='Tháng 10,Tháng 11,Tháng 1','gửi Tháng 10, 11, 1 (bỏ Tháng 5 cũ, bỏ tab cố định LICH_CHUP cũ)');
ok(st.tabs['Tháng 1'][0].day==='2027-01-04','Tháng 1 hiểu là năm 2027');
ok(st.tabs['Tháng 10'][1].day==='2026-10-28'&&st.tabs['Tháng 10'][1].time==='14:00','dòng không ghi ngày thuộc ngày phía trên');
ok(st.tabs['Tháng 11'][0].id===ID(1)&&st.tabs['Tháng 11'][1].time==='13:00','giữ mã cũ, đọc giờ kiểu thời gian');
ok(b.getSheetByName('Tháng 10').rows()[1][10]==='_AG_BOOKING_ID','thêm cột mã cho tab chưa có');
// Gói 1499 14:00 và 699 10:00 không chồng; 5/11: 699 9:00 (đến 10:00), 1499 13:00 → không trùng.
ok(!st.tabs['Tháng 10'].some(()=>false)&&b.getSheetByName('Tháng 10').cell(3,2).bg!=='#f4c7c3','không trùng thì không tô');

console.log('2. Lần chạy tự động sau, Sheet không đổi: không gửi lại tab');
st.posts=[];ok(run(ctx,{triggerUid:'t'})===null,'chạy không lỗi');
ok(st.posts.length===1&&st.posts[0].poll===true,'chỉ 1 yêu cầu hỏi lịch mới từ web');

console.log('3. Ghi lịch thêm trên web vào đúng chỗ');
const q=(id,day,time,v)=>({id:ID(900+id),booking_id:ID(500+id),action:'create',tab:'Tháng '+Number(day.slice(5,7)),day,time,length:60,values:Object.assign({customer_name:'Web'+id,customer_phone:'0912345678',package_label:'699',main_alias:'Huy',support_alias:'',deposit_label:'',note:''},v)});
st.queue=[q(1,'2026-11-05','11:00'),q(2,'2026-11-05','08:00',{deposit_label:'300k'}),q(3,'2026-11-07','10:00'),q(4,'2026-11-10','15:00'),q(5,'2026-12-03','10:00'),q(6,'2026-10-28','12:00',{makeup:'Nữ',note:'Concept cưới'}),{id:ID(999),booking_id:ID(3),action:'cancel',tab:'Tháng 11',day:'2026-11-09',time:'11:00',values:{}}];
st.posts=[];ok(run(ctx)===null,'chạy không lỗi');
const n=b.getSheetByName('Tháng 11').rows().map(r=>[r[0],r[1],r[2]].join('|'));
console.log('     Tháng 11:',JSON.stringify(n));
ok(n[1]==='05/11|08:00|Web2'&&n[2]==='|09:00|Bình'&&n[3]==='|11:00|Web1'&&n[4]==='|13:00|Chi','05/11: 08:00 (khách sớm nhất, giữ ô ngày) → 09:00 → 11:00 → 13:00');
ok(b.getSheetByName('Tháng 11').merges.some(m=>m.r1===2&&m.r2===5),'ô ngày gộp của 05/11 giãn ra đủ 4 dòng');
ok(n[5]==='07/11|10:00|Web3'||n[6]==='07/11|10:00|Web3','07/11 (ngày mới) chèn giữa 05/11 và 09/11, có ghi ngày');
ok(n.indexOf('07/11|10:00|Web3')<n.indexOf('09/11|11:00|Dũng'),'07/11 đứng trước 09/11');
ok(n.includes('10/11|15:00|Web4')&&n.length===8,'10/11 có dòng ngày trống: ghi luôn vào dòng đó');
const s11=b.getSheetByName('Tháng 11'),r2=s11.cell(2,2);
ok(r2.v instanceof Date&&r2.fmt==='HH:mm','giờ ghi đúng kiểu thời gian như các dòng khác');
ok(s11.cell(2,5).v==='0912345678'&&s11.cell(2,5).fmt==='@','SĐT giữ số 0 đầu');
ok(s11.cell(2,4).v===699&&typeof s11.cell(2,4).v==='number','gói ghi dạng số như Sheet');
ok(s11.cell(2,11).v===ID(502),'ghi mã lịch web vào cột _AG_BOOKING_ID');
const dung=s11.rows().findIndex(r=>r[2]==='Dũng');ok(s11.rows()[0][11]==='Trạng thái'&&s11.rows()[dung][11]==='Hủy','hủy trên web → thêm cột Trạng thái, ghi Hủy');
const s12=b.getSheetByName('Tháng 12');ok(s12&&b.getSheets().indexOf(s12)===b.getSheets().indexOf(s11)+1,'chưa có tab Tháng 12 → tạo ngay sau Tháng 11');
ok(s12&&s12.rows()[0][0]==='Ngày'&&s12.rows()[1].slice(0,3).join('|')==='03/12|10:00|Web5','Tháng 12 có tiêu đề và lịch 03/12');
ok(s12&&s12.rows().length===2&&!s12.rows().some(r=>r[2]==='Bình'||r[2]==='Chi'),'Tháng 12 nhân bản từ Tháng 11 nhưng không còn khách của tháng 11');
const t10=b.getSheetByName('Tháng 10').rows().map(r=>[r[0],r[1],r[2]].join('|'));console.log('     Tháng 10:',JSON.stringify(t10));
ok(t10[3]==='|12:00|Web6'&&t10[4]==='|14:00|Mai','28/10 12:00 chèn giữa 10:00 và 14:00');
ok(b.getSheetByName('Tháng 10').cell(4,9).formula==='=R[0]C[-5]-1','chép công thức cột Còn lại');
ok(b.getSheetByName('Tháng 10').cell(4,10).v==='Make up: Nữ · Concept cưới','Sheet không có cột Makeup → ghi vào Ghi chú');
ok(b.getSheetByName('Tháng 10').cell(4,2).fmt==='@'&&b.getSheetByName('Tháng 10').cell(4,2).v==='12:00','giờ ghi dạng chữ như tab Tháng 10');
ok(st.acks.length===7&&st.acks.every(a=>a.ok),'báo lại web đủ 7 việc thành công');
const a2=st.acks.find(a=>a.id===ID(902));ok(a2.tab==='Tháng 11'&&a2.row===2,'báo đúng tab và dòng');

console.log('4. Lịch web đã có trên Sheet (lần trước chưa kịp báo) thì không chèn lại');
st.queue=[q(1,'2026-11-05','11:00')];st.acks=[];const before=s11.rows().length;ok(run(ctx)===null,'chạy không lỗi');
ok(s11.rows().length===before&&st.acks[0].ok&&st.acks[0].row===4,'không thêm dòng, báo dòng 4');

console.log('5. Trùng giờ: tô đỏ ô giờ, sửa xong trả màu cũ');
s11.cell(4,2).bg='#fff2cc';s11.getRange(4,2).setValue('08:30');// Web1 11:00 → 08:30, chồng 08:00 gói 1499 (đến 10:00) và 09:00
ok(run(ctx)===null,'chạy không lỗi');
ok(s11.cell(4,2).bg==='#f4c7c3'&&/trùng giờ với dòng 2/.test(s11.cell(4,2).note),'08:30 tô đỏ, ghi chú trùng dòng 2');
ok(s11.cell(2,2).bg==='#f4c7c3'&&s11.cell(3,2).bg==='#f4c7c3','08:00 và 09:00 cũng tô đỏ');
ok(/1 dòng|3 dòng trùng giờ/.test(b.toasts.at(-1)),'thông báo số dòng trùng: '+b.toasts.at(-1));
s11.getRange(4,2).setValue('11:00');ok(run(ctx)===null,'chạy không lỗi');
ok(s11.cell(4,2).bg==='#fff2cc'&&!s11.cell(4,2).note&&s11.cell(2,2).bg==='#ffffff','hết trùng: bỏ ghi chú, trả màu vàng cũ');

console.log('6. Dòng sao chép sang tab khác mang theo mã: cấp mã mới');
s12.cell(2,11).v=ID(502);st.tabs={};ok(run(ctx)===null,'chạy không lỗi');
ok(st.tabs['Tháng 12'][0].id!==ID(502)&&st.tabs['Tháng 11'][0].id===ID(502),'dòng chép sang Tháng 12 nhận mã mới, Tháng 11 giữ mã gốc');

console.log('7. Lỗi một tab không chặn tab khác, báo lỗi ghi Sheet');
s12.cell(2,1).v='32/12';st.tabs={};st.queue=[q(7,'2026-12-05','10:00')];st.acks=[];const err=run(ctx);
ok(/Tháng 12: Ngày không tồn tại/.test(err||''),'báo lỗi tab Tháng 12: '+err);
ok(st.tabs['Tháng 11']&&st.tabs['Tháng 10'],'Tháng 10, 11 vẫn gửi');
ok(st.acks[0]&&!st.acks[0].ok&&/Tháng 12 đang lỗi/.test(st.acks[0].error),'lịch 05/12 báo lỗi về web: '+(st.acks[0]&&st.acks[0].error));

console.log('8. Sheet cũ không chia tab tháng: dùng tab cố định');
const b2=new Book();b2.add('LICH_CHUP',[H,['21/10','10:00','X',699,'','','','','','']]);const st2={posts:[],queue:[q(8,'2026-11-02','10:00',{makeup:'Nam',special:'Ô TÔ',note:'Concept'})],acks:[],tabs:{}};
ok(run(load(gs,b2,server(st2),cfg))===null,'chạy không lỗi');ok(st2.tabs.LICH_CHUP[0].day==='2026-10-21'&&b2.getSheetByName('LICH_CHUP').rows()[2].slice(0,3).join('|')==='02/11|10:00|Web8','ghi lịch vào tab cố định');
ok(b2.getSheetByName('LICH_CHUP').rows()[2][9]==='Đặc biệt: Ô TÔ · Make up: Nam · Concept','Sheet không có cột Đặc Biệt / Makeup → ghép vào đầu Ghi chú');
console.log('9. Cột Makeup, Đặc Biệt là dropdown, có cột Tip');
const HM=['Ngày','Time','Tên khách','Gói','SĐT','Thợ chính','Thợ phụ','Makeup','Tip','Ghi chú','Đặc Biệt'],dv={type:'VALUE_IN_LIST',values:['Nam','Nữ','Làm Tóc']},mk=v=>({v,fmt:'',dv});
const sdv={type:'VALUE_IN_LIST',values:['Ô TÔ','Ngoại Cảnh','Nhóm tại Stu','Sản Phẩm']},sp=v=>({v,fmt:'',dv:sdv});
const pdv={type:'VALUE_IN_LIST',values:['399','699','Couple 999','1499','2999','Team 349']},pk=v=>({v,fmt:'@',dv:pdv});
const b3=new Book(),m11=b3.add('Tháng 11',[HM,['05/11','10:00','Lan cọc 200k',pk('699'),'0901','HUY','CƯỜNG',mk('Nam'),'50k','',sp('Ô TÔ')],['06/11','09:00','Mai',pk('1499'),'','HUY','',mk(''),'','',sp('')],['','','',pk(''),'','','',mk(''),'','',sp('')],['','','',pk(''),'','','',mk(''),'','',sp('')]]);
const st3={posts:[],queue:[q(10,'2026-11-05','14:00',{makeup:'Làm tóc',special:'NGOẠI CẢNH',note:'Concept áo dài',main_alias:'HUY',support_alias:'CƯỜNG'}),q(11,'2026-12-02','10:00',{makeup:'Nam',special:'Sản Phẩm'}),q(12,'2026-11-06','13:00',{package_label:'999'}),q(13,'2026-11-06','15:00',{package_label:'349',note:'Giữ 2 tiếng'})],acks:[],tabs:{}};
ok(run(load(gs,b3,server(st3),cfg))===null,'chạy không lỗi');
const r10=m11.rows().find(r=>r[2]==='Web10');ok(r10&&r10[7]==='Làm Tóc'&&r10[10]==='Ngoại Cảnh','ghi đúng chữ lựa chọn dropdown Makeup / Đặc Biệt: '+(r10&&r10[7]+' / '+r10[10]));
ok(m11.rows().find(r=>r[2]==='Web11')===undefined&&st3.tabs['Tháng 11'][0].sheet_fields.some(f=>f.label==='Đặc Biệt'&&f.value==='Ô TÔ'),'cột Đặc Biệt có trong dữ liệu gửi lên web');
ok(r10&&r10[9]==='Concept áo dài'&&r10[5]==='HUY'&&r10[6]==='CƯỜNG','Ghi chú không lặp Make up; thợ HUY - CƯỜNG');
const r12=m11.rows().find(r=>r[2]==='Web12'),r13=m11.rows().find(r=>r[2]==='Web13');ok(r12&&r12[3]==='Couple 999'&&r13&&r13[3]==='Team 349'&&r13[9]==='Giữ 2 tiếng','cột Gói là dropdown → ghi đúng lựa chọn "Couple 999", "Team 349"');
ok(st3.tabs['Tháng 11'][0].sheet_fields.some(f=>f.label==='Tip'&&f.value==='50k'),'cột Tip có trong dữ liệu gửi lên web');
const m12=b3.getSheetByName('Tháng 12');ok(m12&&b3.getSheets().indexOf(m12)===1&&m12.rows()[0].join('|')===HM.concat(['_AG_BOOKING_ID']).join('|'),'Tháng 12 nhân bản đủ cột (Makeup, Tip) từ Tháng 11, đặt ngay sau');
ok(m12&&m12.rows()[1][7]==='Nam'&&m12.cell(2,8).dv&&m12.rows()[1][10]==='Sản Phẩm'&&m12.cell(2,11).dv,'Tháng 12 giữ dropdown Makeup / Đặc Biệt, ghi Nam / Sản Phẩm');
ok(m12&&m12.rows().length===2,'Tháng 12 chỉ còn tiêu đề + lịch mới');
console.log('10. Gói Team cùng giờ là một nhóm; Couple 2 tiếng; Team theo "Giữ N tiếng"');
const b4=new Book(),g=b4.add('Tháng 11',[H,
 ['12/11','10:00','Team Lan',349,'','HUY','','','','Giữ 2 tiếng'],['','10:00','Thành viên 2',349,'','','','','',''],['','10:00','Thành viên 3',349,'','','','','',''],
 ['','11:00','Khách lẻ',699,'','','','','',''],['','13:00','Mai','Couple','','','','','',''],['','14:00','Hà',399,'','','','','',''],['','16:00','Chưa chọn gói','','','','','','','']]);
ok(run(load(gs,b4,server({posts:[],queue:[],acks:[],tabs:{}}),cfg))===null,'chạy không lỗi');
const red=r=>g.cell(r,2).bg==='#f4c7c3';
ok(red(2)&&red(5),'Team giữ 2 tiếng (10:00–12:00) trùng khách lẻ 11:00 → tô đỏ cả hai');
ok(!red(3)&&!red(4),'các dòng Team cùng 10:00 không bị tô là trùng nhau');
ok(/dòng 5/.test(g.cell(2,2).note)&&!/dòng 3|dòng 4/.test(g.cell(2,2).note),'ghi chú dòng Team chỉ nhắc dòng khách lẻ');
ok(red(6)&&red(7),'Couple (chữ, 2 tiếng) 13:00 trùng 399 lúc 14:00');
ok(!red(8),'ô Gói trống 16:00 = 1 tiếng, không trùng');
console.log('11. Xóa lịch trên web → xóa dòng, ô Ngày chuyển xuống khách còn lại');
const b5=new Book(),x=b5.add('Tháng 11',[H.concat(['_AG_BOOKING_ID']),
 ['05/11','09:00','A1',699,'','','','','','',ID(11)],['','11:00','A2',699,'','','','','','',ID(12)],['','13:00','A3',699,'','','','','','',ID(13)],
 ['06/11','09:00','B1',699,'','','','','','',ID(21)],['','11:00','B2',699,'','','','','','',ID(22)],
 ['07/11','09:00','C1',699,'','','','','','',ID(31)],['','',  '',  '','','','','','','',''],['','11:00','C2',699,'','','','','','',ID(32)],
 ['08/11','09:00','D1',699,'','','','','','',ID(41)],['09/11','10:00','E1',699,'','','','','','',ID(51)]]);
x.merges.push({c:1,r1:2,r2:4},{c:1,r1:5,r2:6});
const del=(n,id,tab)=>({id:ID(800+n),booking_id:id,action:'delete',tab:tab||'Tháng 11',day:'',time:'',values:{}});
const st5={posts:[],queue:[del(1,ID(11)),del(2,ID(22)),del(3,ID(31)),del(4,ID(41)),del(5,ID(777))],acks:[],tabs:{}},c5=load(gs,b5,server(st5),cfg);
ok(run(c5)===null,'chạy không lỗi');
const rx=x.rows().map(r=>[r[0],r[1],r[2]].join('|'));console.log('     Tháng 11:',JSON.stringify(rx));
ok(rx[1]==='05/11|11:00|A2'&&rx[2]==='|13:00|A3'&&x.merges.some(m=>m.r1===2&&m.r2===3),'xóa khách đầu ngày (ô Ngày gộp 3 dòng) → A2 nhận ô Ngày, gộp lại 2 dòng');
ok(rx[3]==='06/11|09:00|B1'&&!x.merges.some(m=>m.r1<=4&&m.r2>=5),'xóa dòng cuối của ô gộp → ngày 06/11 còn B1, giữ ô Ngày');
ok(rx[4]==='||'&&rx[5]==='07/11|11:00|C2','ô Ngày không gộp: khách sau dòng trống nhận ngày 07/11');
ok(rx[6]==='09/11|10:00|E1'&&!rx.some(r=>/D1/.test(r)),'xóa khách duy nhất của 08/11 → xóa dòng, ngày 09/11 giữ nguyên');
ok(st5.acks.length===5&&st5.acks.every(a=>a.ok),'báo web đủ 5 việc thành công (mã không còn trên Sheet cũng xong)');
st5.tabs={};ok(run(c5)===null,'chạy lại không lỗi');
ok(JSON.stringify(st5.tabs['Tháng 11'].map(r=>r.day+' '+r.customer_name))===JSON.stringify(['2026-11-05 A2','2026-11-05 A3','2026-11-06 B1','2026-11-07 C2','2026-11-09 E1']),'web đọc lại đúng ngày của các khách còn lại');
x.cell(2,1).v='32/11';st5.queue=[del(6,ID(999))];st5.acks=[];run(c5);
ok(st5.acks[0]&&!st5.acks[0].ok&&/Tháng 11 đang lỗi/.test(st5.acks[0].error),'tab đang lỗi: không báo xong, để thử lại: '+(st5.acks[0]&&st5.acks[0].error));
const b6=new Book(),y=b6.add('Tháng 11',[H.concat(['_AG_BOOKING_ID']),['05/11','09:00','Chỉ 1',699,'','','','','','',ID(61)]]);y.maxRows=2;
const st6={posts:[],queue:[del(7,ID(61))],acks:[],tabs:{}};ok(run(load(gs,b6,server(st6),cfg))===null&&y.rows().length===1&&st6.acks[0].ok,'tab chỉ còn 1 dòng khách: xóa được, giữ tiêu đề');
console.log('12. Sửa lịch trên web → chỉ ghi ô được sửa; đổi giờ/ngày thì chuyển dòng, giữ các ô khác');
const HU=['Ngày','Time','Tên khách','Gói','SĐT','Thợ chính','Thợ phụ','Makeup','Tip','Ghi chú','Còn lại','_AG_BOOKING_ID'],F={v:'=f',formula:'=R[0]C[-7]-1'};
const b7=new Book(),u11=b7.add('Tháng 11',[HU,
 ['05/11','09:00','A1',699,'0901','TÍN','',mk('Nam'),'50k','n1',{...F},ID(101)],['','11:00','A2',699,'','HUY','CƯỜNG',mk(''),'','',{...F},ID(102)],['','13:00','A3',1499,'','','',mk(''),'','',{...F},ID(103)],
 ['06/11','10:00','B1',399,'','HUY','',mk(''),'','',{...F},ID(104)],['07/11','10:00','C1',399,'','HUY','',mk(''),'','',{...F},ID(105)]]);u11.merges.push({c:1,r1:2,r2:4});
const u5=b7.add('Tháng 5',[HU,['03/05','10:00','Cũ',699,'','HUY','','','','x','',ID(201)],['','14:00','Cũ2',699,'','HUY','','','','y','',ID(202)]]);
const upd=(n,id,day,time,changed,v,tab)=>({id:ID(700+n),booking_id:id,action:'update',tab:tab||'Tháng 11',day,time,length:60,changed,from_day:day,values:Object.assign({customer_name:'',customer_phone:'',package_label:'',main_alias:'HUY',support_alias:'CƯỜNG',deposit_label:'',note:'',makeup:'',special:''},v)});
const st7={posts:[],queue:[],acks:[],tabs:{}},c7=load(gs,b7,server(st7),cfg),row7=(s,id)=>s.rows().findIndex(r=>r[11]===id)+1,cells=(s,id)=>s.rows()[row7(s,id)-1];
st7.queue=[upd(1,ID(101),'2026-11-05','09:00',['customer_name','customer_phone','note'],{customer_name:'A1 mới',note:'ghi chú mới',makeup:'Nam'}),upd(2,ID(102),'2026-11-05','11:00',['makeup'],{customer_name:'A2',makeup:'Làm tóc'}),upd(3,ID(103),'2026-11-05','12:00',['time'],{customer_name:'A3'})];
ok(run(c7)===null,'chạy không lỗi');let a1=cells(u11,ID(101));
ok(a1[2]==='A1 mới'&&a1[4]===''&&a1[9]==='ghi chú mới','sửa tại chỗ: tên, xóa SĐT, ghi chú');
ok(a1[5]==='TÍN'&&a1[8]==='50k'&&a1[7]==='Nam'&&a1[3]==='699','ô không sửa giữ nguyên (thợ TÍN trên Sheet, Tip, Makeup, Gói)');
ok(cells(u11,ID(102))[7]==='Làm Tóc'&&cells(u11,ID(102))[2]==='A2','chỉ sửa Make up → ghi đúng chữ dropdown "Làm Tóc"');
ok(row7(u11,ID(103))===4&&cells(u11,ID(103))[1]==='12:00','13:00 → 12:00 vẫn sau 11:00: sửa giờ tại chỗ, không chuyển dòng');
ok(st7.acks.length===3&&st7.acks.every(a=>a.ok),'báo web 3 việc xong');
st7.queue=[upd(4,ID(101),'2026-11-05','14:00',['time'],{customer_name:'A1 mới'})];st7.acks=[];ok(run(c7)===null,'chạy không lỗi');
let r7=u11.rows().map(r=>[r[0],r[1],r[2]].join('|'));console.log('     Tháng 11:',JSON.stringify(r7));
ok(r7[1]==='05/11|11:00|A2'&&r7[2]==='|12:00|A3'&&r7[3]==='|14:00|A1 mới'&&u11.merges.some(m=>m.r1===2&&m.r2===4),'09:00 → 14:00: chuyển xuống cuối ngày 05/11, ô Ngày chuyển cho A2, vẫn gộp 3 dòng');
a1=cells(u11,ID(101));ok(a1[5]==='TÍN'&&a1[8]==='50k'&&a1[7]==='Nam'&&a1[9]==='ghi chú mới'&&u11.cell(row7(u11,ID(101)),11).formula==='=R[0]C[-7]-1','dòng chuyển giữ thợ, Tip, Makeup, Ghi chú, công thức');
ok(st7.acks[0].ok&&st7.acks[0].tab==='Tháng 11'&&st7.acks[0].row===4,'báo web dòng mới: Tháng 11 dòng 4');
st7.queue=[upd(5,ID(104),'2026-11-09','10:00',['day'],{customer_name:'B1'})];st7.acks=[];ok(run(c7)===null,'chạy không lỗi');r7=u11.rows().map(r=>[r[0],r[1],r[2]].join('|'));console.log('     Tháng 11:',JSON.stringify(r7));
ok(!r7.some(r=>r.startsWith('06/11'))&&r7.indexOf('09/11|10:00|B1')>r7.indexOf('07/11|10:00|C1'),'06/11 → 09/11: dòng chuyển sau 07/11, ngày 06/11 không còn dòng');
st7.queue=[upd(6,ID(102),'2026-12-03','15:00',['day','time'],{customer_name:'A2',makeup:'Làm tóc'})];st7.acks=[];ok(run(c7)===null,'chạy không lỗi');
const u12=b7.getSheetByName('Tháng 12'),moved=u12&&cells(u12,ID(102));
ok(moved&&moved[0]==='03/12'&&moved[1]==='15:00'&&moved[5]==='HUY'&&moved[6]==='CƯỜNG'&&moved[7]==='Làm Tóc'&&row7(u11,ID(102))===0,'đổi sang 03/12: tạo tab Tháng 12, dòng mang theo thợ, Makeup; Tháng 11 không còn');
ok(st7.acks[0].ok&&st7.acks[0].tab==='Tháng 12','báo web tab mới Tháng 12');
r7=u11.rows().map(r=>[r[0],r[1],r[2]].join('|'));ok(r7[1]==='05/11|12:00|A3'&&r7[2]==='|14:00|A1 mới','05/11 còn A3, A1 đúng ngày');
console.log('13. Sửa lịch đã qua ở tab tháng cũ (ngoài khoảng đồng bộ) và các trường hợp lỗi');
st7.queue=[upd(7,ID(201),'2026-05-03','10:00',['customer_name','package_label'],{customer_name:'Cũ sửa',package_label:'1499'},'Tháng 5'),upd(8,ID(202),'2026-05-03','08:00',['time'],{customer_name:'Cũ2'},'Tháng 5')];st7.acks=[];ok(run(c7)===null,'chạy không lỗi');
const r5=u5.rows().map(r=>[r[0],r[1],r[2],r[3]].join('|'));console.log('     Tháng 5:',JSON.stringify(r5));
ok(r5[1]==='03/05|08:00|Cũ2|699'&&r5[2]==='|10:00|Cũ sửa|1.499','tab Tháng 5 (ngoài khoảng): sửa tên, gói; 14:00 → 08:00 lên đầu ngày, giữ ô Ngày');
ok(st7.acks.every(a=>a.ok&&a.tab==='Tháng 5'),'báo web xong, đúng tab Tháng 5');
u11.getRange(row7(u11,ID(103)),2).setValue('12:30');st7.queue=[upd(9,ID(103),'2026-11-05','12:00',['customer_name'],{customer_name:'A3 mới'}),upd(10,ID(999),'2026-11-05','10:00',['customer_name'],{})];st7.acks=[];run(c7);
ok(cells(u11,ID(103))[1]==='12:30'&&cells(u11,ID(103))[2]==='A3 mới','chỉ sửa tên: giờ vừa đổi tay trên Sheet (12:30) giữ nguyên');
ok(st7.acks[1]&&!st7.acks[1].ok&&/Không thấy dòng/.test(st7.acks[1].error),'lịch không còn trên Sheet: báo lỗi về web');
console.log('14. Gói khách chụp (popup lịch) → chỉ ghi ô Gói, đúng chữ dropdown');
const GD={type:'VALUE_IN_LIST',values:['399','699','Couple 999','1499','2999','Team 349']},gk=v=>({v,fmt:'',dv:GD});
const b8=new Book(),g11=b8.add('Tháng 11',[HU,['05/11','09:00','G1',gk(''),'0901','TÍN','',mk('Nữ'),'50k','n1',{...F},ID(301)],['','10:00','G2',gk(''),'','HUY','CƯỜNG',mk(''),'','',{...F},ID(302)],['','11:00','G3',gk(699),'','HUY','',mk(''),'','',{...F},ID(303)]]);
const st8={posts:[],queue:[],acks:[],tabs:{}},c8=load(gs,b8,server(st8),cfg),g8=id=>g11.rows().find(r=>r[11]===id);
st8.queue=[upd(11,ID(301),'2026-11-05','09:00',['package_label'],{customer_name:'G1',package_label:'1499'}),upd(12,ID(302),'2026-11-05','10:00',['package_label'],{customer_name:'G2',package_label:'999'}),upd(13,ID(303),'2026-11-05','11:00',['package_label'],{customer_name:'G3',package_label:'349'})];
ok(run(c8)===null,'chạy không lỗi');
ok(g8(ID(301))[3]==='1.499'&&g8(ID(302))[3]==='Couple 999'&&g8(ID(303))[3]==='Team 349','ghi đúng lựa chọn dropdown: 1499, Couple 999, Team 349 (699 → Team 349)');
ok(g8(ID(301)).slice(0,3).join('|')==='05/11|09:00|G1'&&g8(ID(301))[4]==='0901'&&g8(ID(301))[5]==='TÍN'&&g8(ID(301))[7]==='Nữ'&&g8(ID(301))[8]==='50k'&&g8(ID(301))[9]==='n1'&&g11.cell(2,11).formula==='=R[0]C[-7]-1','ô khác giữ nguyên: ngày, giờ, tên, SĐT, thợ TÍN, Makeup, Tip, ghi chú, công thức');
ok(g11.rows().slice(1).map(r=>r[11]).join()===[ID(301),ID(302),ID(303)].join(','),'không chuyển dòng');
ok(st8.acks.length===3&&st8.acks.every(a=>a.ok&&a.tab==='Tháng 11'),'báo web 3 việc xong');
st8.queue=[upd(14,ID(303),'2026-11-05','11:00',['package_label'],{customer_name:'G3',package_label:'399'})];st8.acks=[];ok(run(c8)===null&&g8(ID(303))[3]==='399','đổi Team 349 → 399: ghi "399"');
console.log(`\n${pass} đạt, ${fail} lỗi`);process.exit(fail?1:0);
