// Sửa lịch → Phụ thu, Tip ghi xuống Sheet (01/11/2026): ghi dạng số vào cột có sẵn, chưa có cột thì thêm ở cuối, đọc về web, chuyển dòng giữ số.
// Chạy: node php-hostinger/tests/apps-script-money.cjs
const {Book,load}=require('./fixtures/sheet-mock.cjs');
const gs=process.argv[2]||'php-hostinger/google-sheets/AnhGauSync.gs';let pass=0,fail=0;
const ok=(c,m)=>{if(c){pass++;console.log('  ✓ '+m);}else{fail++;console.log('  ✗ '+m);}};
const ID=n=>'00000000-0000-4000-8000-'+String(n).padStart(12,'0');
const H=['Ngày','Time','Tên khách','Gói','Tip','Ghi chú','_AG_BOOKING_ID'];
const b=new Book();
b.add('Tháng 11',[H,['05/11','10:00','Lan',699,{v:20000,fmt:'#,##0'},'',ID(1)],['','13:00','Mai',699,'','',ID(2)],['09/11','10:00','An',699,'','',ID(3)]]);
const st={queue:[],acks:[],tabs:{}},server=body=>{if(body.poll)return [200,{queue:st.queue.splice(0)}];if(body.ack){st.acks.push(...body.ack);return [200,{acked:body.ack.length}];}st.tabs[body.tab]=body.rows;return [200,{received:body.rows.length}];};
const ctx=load(gs,b,server,{AG_ENDPOINT:'https://x/api.php?route=%2Fsheet-sync%2Freceive',AG_SYNC_KEY:'k'.repeat(64),AG_SHEET_ID:'SHEETID'});
const run=()=>{try{ctx.dongBoAnhGau();return null;}catch(err){return err.message;}};
const s=b.getSheetByName('Tháng 11'),head=()=>s.rows()[0],col=name=>head().indexOf(name)+1,find=id=>s.rows().findIndex(r=>r.includes(id))+1;
const upd=(n,id,day,time,changed,v)=>({id:ID(700+n),booking_id:id,action:'update',tab:'Tháng 11',day,time,length:60,changed,from_day:day,values:Object.assign({customer_name:'',customer_phone:'',package_label:'699',main_alias:'HUY',support_alias:'CƯỜNG',deposit_label:'',note:'',makeup:'',special:'',surcharge:'',tip:''},v)});

console.log('1. Đọc Sheet: có cột Tip thì gửi Tip, chưa có cột Phụ thu thì không gửi');
ok(run()===null,'chạy không lỗi');const r1=st.tabs['Tháng 11'];
ok(r1[0].tip_label==='20.000'&&!('surcharge_label' in r1[0]),'Lan: Tip 20.000, không có khóa Phụ thu');

console.log('2. Sửa lịch nhập Phụ thu 100k, Tip 50.000');
st.queue=[upd(1,ID(2),'2026-11-05','13:00',['surcharge','tip'],{customer_name:'Mai',surcharge:'100k',tip:'50.000'})];ok(run()===null,'chạy không lỗi');
const rMai=find(ID(2)),tip=s.cell(rMai,col('Tip'));
ok(tip.v===50000&&tip.fmt==='#,##0','Tip ghi số 50000, định dạng như cột: '+JSON.stringify(tip));
ok(col('Phụ thu')>col('_AG_BOOKING_ID'),'chưa có cột Phụ thu thì thêm ở cuối: '+JSON.stringify(head()));
ok(s.cell(rMai,col('Phụ thu')).v===100000,'Phụ thu 100k ghi số 100000');
ok(s.cell(rMai,col('Tên khách')).v==='Mai'&&s.cell(rMai,col('Gói')).v===699,'ô khác giữ nguyên');
ok(st.acks.length===1&&st.acks[0].ok,'báo web xong');
ok(run()===null,'đồng bộ lại không lỗi');const back=st.tabs['Tháng 11'].find(r=>r.id===ID(2));
ok(back.surcharge_label==='100.000'&&back.tip_label==='50.000','web đọc lại Phụ thu, Tip: '+JSON.stringify([back.surcharge_label,back.tip_label]));
ok(st.tabs['Tháng 11'].find(r=>r.id===ID(3)).surcharge_label==='','dòng khác có khóa Phụ thu trống');

console.log('3. Xóa Tip (để trống)');
st.queue=[upd(2,ID(2),'2026-11-05','13:00',['tip'],{customer_name:'Mai',surcharge:'100k',tip:''})];ok(run()===null,'chạy không lỗi');
ok(s.cell(find(ID(2)),col('Tip')).v===''&&s.cell(find(ID(2)),col('Phụ thu')).v===100000,'Tip trống, Phụ thu giữ');

console.log('4. Đổi giờ làm chuyển dòng: Phụ thu, Tip đi theo');
st.queue=[upd(3,ID(1),'2026-11-05','15:00',['time'],{customer_name:'Lan',surcharge:'',tip:'20000'})];ok(run()===null,'chạy không lỗi');
const rLan=find(ID(1));ok(rLan>find(ID(2)),'Lan chuyển xuống sau Mai');ok(s.cell(rLan,col('Tip')).v===20000,'Tip 20000 đi theo dòng');
console.log('5. Đổi giờ và nhập Tip cùng lúc');
st.queue=[upd(4,ID(3),'2026-11-09','08:00',['time','tip'],{customer_name:'An',tip:'30k'})];ok(run()===null,'chạy không lỗi');
ok(s.cell(find(ID(3)),col('Tip')).v===30000,'Tip mới 30000 ghi ở dòng mới');
ok(st.acks.every(a=>a.ok),'mọi việc báo xong: '+JSON.stringify(st.acks.filter(a=>!a.ok)));
console.log(`\n${pass} đạt, ${fail} lỗi`);process.exit(fail?1:0);
