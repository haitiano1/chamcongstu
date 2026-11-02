<?php
// Tiền khách Tips tự chia đều cho Nhân viên + nhật ký nhập Tip; Ca làm không có khách theo lịch Sheet cả tháng (chủ dự án yêu cầu 02/11/2026).
// Chạy trong giao dịch rồi hoàn tác.
foreach(['bootstrap','telegram','bookings','people-finance','tasks'] as $f)require_once __DIR__.'/../site/nhansu-private/lib/'.$f.'.php';
$a=new App('/var/nhansu-private');$n=0;function verify($v,$message){global $n;if(!$v)throw new RuntimeException('FAIL: '.$message);$n++;}
try{$a->tx(function()use($a){
 $admin=$a->one("SELECT * FROM {{users}} WHERE role='admin' AND archived=0 ORDER BY id LIMIT 1");$a->user=$admin;
 // Đọc số Tip: 100000, 100.000, 100k, 200,000, 50 (nghìn đồng như trên Sheet), chữ khác là 0.
 foreach(['100000'=>100000,'100.000'=>100000,'100k'=>100000,'100K'=>100000,'200,000'=>200000,'50'=>50000,'1.5'=>1500,'100.000đ'=>100000,' 30 k '=>30000,''=>0,'năm chục'=>0] as $in=>$want)verify(booking_tip_vnd((string)$in)===$want,"Tip '$in' = $want (được ".booking_tip_vnd((string)$in).')');

 // Ca làm không có khách theo lịch cả tháng: tháng 12 trống hết thì 31 ngày × 2 ca; có khách 15h thì Ca 1 vẫn trống, Ca 2 có khách.
 $a->run("UPDATE {{shoot_bookings}} SET status='cancelled' WHERE shoot_day LIKE '2026-12-%' OR shoot_day LIKE '2026-11-%'");
 $book=function(string $day,int $min,string $tip='',string $name='Khách')use($a){$id=uuid_v4();$a->run("INSERT INTO {{shoot_bookings}}(id,source_sheet,source_tab,source_row,shoot_day,minutes,package_label,customer_name,customer_phone,main_alias,support_alias,note,tip_label,status,created_at,updated_at) VALUES(?,'SHEETTEST','Tháng 11',2,?,?,'699',?,'','HUY','CƯỜNG','',?,'active',?,?)",[$id,$day,$min,$name,$tip,now_ms(),now_ms()]);return $id;};
 $e=studio_empty_shifts($a,'2026-12',day_time('2027-01-01'));verify(count($e['past'])===62&&!$e['upcoming'],'Tháng 12 không có lịch: 62 ca không khách');
 $book('2026-12-05',900);$e=studio_empty_shifts($a,'2026-12',day_time('2027-01-01'));
 verify(count($e['past'])===61&&in_array('2026-12-05:day_first',$e['past'],true)&&!in_array('2026-12-05:day_second',$e['past'],true),'Khách đầu tiên 15h: Ca 1 không khách, Ca 2 có khách');
 $e=studio_empty_shifts($a,'2026-12',day_time('2026-12-05',14));verify(in_array('2026-12-05:day_first',$e['past'],true)&&in_array('2026-12-06:day_first',$e['upcoming'],true)&&count($e['past'])===9,'Ca chưa hết giờ là "sắp tới": '.count($e['past']));
 verify(studio_empty_shifts($a,'2026-10')===null,'Trước tháng 11/2026 không đếm');

 // Nhân viên chấm công ca không khách: unbookedDays liệt kê ngày + ca.
 $a->run("INSERT INTO {{users}}(name,email,password,role,employee_code,notes) VALUES('ZZ Tip A','zz.tip.a@local.test','x','employee','ZZTIPA','')");$ua=(int)$a->scalar('SELECT LAST_INSERT_ID()');
 $ev=fn(string $type,string $day,int $hour,string $shift)=>$a->run("INSERT INTO {{events}}(user_id,type,time,shift,work_date,source,reason) VALUES(?,?,?,?,?,'self','')",[$ua,$type,day_time($day,$hour),$shift,$day]);
 $ev('in','2026-11-01',9,'day_first');$ev('out','2026-11-01',14,'day_first');
 $row=fn()=>array_values(array_filter($a->monthly('2026-11')['rows'],fn($r)=>(int)$r['id']===$ua))[0];
 $r=$row();verify($r['unbookedShifts']===1&&$r['unbookedDays']===['2026-11-01:day_first'],'Ca không khách của nhân viên kèm ngày: '.json_encode($r['unbookedDays']));
 verify(isset(visible_summary(['role'=>'employee'],$r)['unbookedDays']),'Nhân viên thấy ngày ca không khách của mình');
 verify($r['bonus_items']['no_customer']===(int)round(8000000/30/2)&&$r['bonus_profile']==='photo','Ca làm không có khách = 1 ca × lương 1/2 ngày (8tr / 30 / 2)');
 verify(!isset(visible_summary(['role'=>'employee'],$r)['monthly_salary']),'Nhân viên vẫn không thấy lương cứng');
 verify(is_array($a->monthly('2026-11')['studio_empty']),'Bảng lương trả số ca không khách của cả studio');

 // Tip: nhập trên web (Sửa lịch) và trên Sheet, mỗi lần đổi ghi nhật ký; tổng tháng chia đều cho Nhân viên đang làm.
 // Nhóm thưởng: Trực Fanpage (VD Tín) không nhận Tips / Ngoại cảnh / Chỉnh ảnh / Ăn uống / OT; chỉ Chuyên cần, Ca không khách, Trực Fanpage 2tr.
 $a->run("INSERT INTO {{users}}(name,email,password,role,employee_code,notes) VALUES('ZZ Tip Fanpage','zz.tip.f@local.test','x','employee','ZZTIPF','')");$uf=(int)$a->scalar('SELECT LAST_INSERT_ID()');
 payroll_profile_save($a,$uf,'fanpage');verify(payroll_profile($a,$uf)==='fanpage'&&payroll_profile($a,$ua)==='photo','Lưu nhóm thưởng, mặc định Thợ chụp');
 $a->run("INSERT INTO {{events}}(user_id,type,time,shift,work_date,source,reason) VALUES(?,'in',?,'day_first','2026-11-01','self',''),(?,'out',?,'day_first','2026-11-01','self','')",[$uf,day_time('2026-11-01',9),$uf,day_time('2026-11-01',14)]);
 $split=count(finance_people($a));verify($split>=2,'Tips chia cho mọi Nhân viên, kể cả Trực Fanpage: '.$split);
 $b1=$book('2026-11-01',720,'','Chị Lan');$b2=$book('2026-11-01',900,'','Anh Minh');
 $form=fn(string $id)=>booking_form($a->one('SELECT * FROM {{shoot_bookings}} WHERE id=?',[$id]));
 $save=function(string $id,string $tip)use($a,$form){$e=$form($id);return booking_routes($a,'/bookings/update',['id'=>$id,'day'=>$e['day'],'time'=>$e['time'],'package'=>$e['package'],'customer_name'=>$e['customer_name'],'customer_phone'=>$e['customer_phone'],'makeup'=>'','special'=>'','note'=>$e['note'],'tip'=>$tip],'POST')[1];};
 $save($b1,'100k');$log=$a->all('SELECT * FROM {{booking_tip_log}} WHERE booking_id=?',[$b1]);
 verify(count($log)===1&&(int)$log[0]['amount']===100000&&(int)$log[0]['old_amount']===0&&(int)$log[0]['actor_id']===(int)$admin['id']&&$log[0]['source']==='web','Nhập Tip trên web ghi nhật ký người nhập');
 // Sheet trả về cùng số (dạng số) thì không ghi thêm; sửa trên Sheet thì ghi nguồn Google Sheet.
 $a->run("UPDATE {{booking_sheet_queue}} SET status='done' WHERE booking_id=?",[$b1]);
 $send=fn(string $id,string $name,string $time,string $tip)=>import_bookings($a,['tab'=>'Tháng 11','rows'=>[['id'=>$id,'day'=>'2026-11-01','time'=>$time,'package_label'=>'699','customer_name'=>$name,'customer_phone'=>'','main_alias'=>'HUY','support_alias'=>'CƯỜNG','note'=>'','tip_label'=>$tip]]],'SHEETTEST',null);
 $send($b1,'Chị Lan','12:00','100000');verify((int)$a->scalar('SELECT COUNT(*) FROM {{booking_tip_log}} WHERE booking_id=?',[$b1])===1,'Sheet ghi lại cùng số thì không thêm nhật ký');
 $send($b2,'Anh Minh','15:00','50');verify((int)$a->scalar("SELECT COUNT(*) FROM {{booking_tip_log}} WHERE booking_id=? AND source='sheet' AND actor_id IS NULL AND amount=50000",[$b2])===1,'Tip ghi trên Sheet (50 = 50.000đ) ghi nhật ký nguồn Sheet');
 $t=tip_month($a,'2026-11');verify($t['total']===150000&&$t['split']===$split&&$t['share']===(int)round(150000/$split),'Tổng Tip tháng 150.000đ chia '.$split);

 // Phiếu lương: ô Tiền khách Tips tự điền phần chia, khóa không sửa (chủ dự án chốt "để nó set cứng"); Tip ghi sau tự cộng.
 $share=(int)round(150000/$split);$r=$row();
 verify($r['tip_total']===150000&&$r['tip_split']===$split&&$r['tip_share']===$share&&$r['bonus_items']['tips']===$share,'Phiếu lương tự điền Tips = '.$share);
 $a->run("INSERT INTO {{payroll_bonuses}} VALUES('2026-11',?,'tips',99000,'web',?,?)",[$ua,$admin['id'],now_ms()]);verify($row()['bonus_items']['tips']===$share,'Số Tips nhập tay cũ không cộng vào');
 $_GET['month']='2026-11';payroll_bonus_routes($a,'/payroll/bonuses',['month'=>'2026-11','user_id'=>$ua,'items'=>['tips'=>500000,'meal'=>20000]],'POST');
 verify($row()['bonus_items']['tips']===$share&&$row()['bonus_items']['meal']===20000&&!$a->one("SELECT 1 FROM {{payroll_bonus_entries}} WHERE user_id=? AND kind='tips'",[$ua]),'Phiếu lương không sửa được Tips, khoản khác vẫn lưu');
 try{payroll_bonus_routes($a,'/payroll/bonuses/add',['id'=>uuid_v4(),'day'=>'2026-11-01','user_id'=>$ua,'kind'=>'tips','amount'=>10000,'note'=>''],'POST');verify(false,'Nhập thưởng Tips phải bị chặn');}catch(HttpError $e){verify(str_contains($e->getMessage(),'không nhập tay'),'Nhập thưởng không nhập Tips');}
 $send($b2,'Anh Minh','15:00','80000');$share2=(int)round(180000/$split);verify($row()['tip_share']===$share2&&$row()['bonus_items']['tips']===$share2,'Tip mới ghi sau tự cộng');
 $res=payroll_bonus_routes($a,'/payroll/bonus-entries',[],'GET')[1];$mine=array_values(array_filter($res['people'],fn($p)=>$p['id']===$ua))[0];
 verify(!isset($mine['items']['tips'])&&!isset($res['kinds']['tips'])&&!in_array('tips',$mine['kinds'],true),'Khung Nhập thưởng không có ô Tips (tự chia)');

 // Nhật ký Tip: tổng tháng, Tip từng khách kèm ngày, ca, giờ, người nhập; lịch hủy thì không tính.
 $j=payroll_bonus_routes($a,'/payroll/tips',[],'GET')[1];$items=array_column($j['items'],null,'id');
 verify($j['total']===180000&&count($j['items'])===2&&$j['split']===$split&&count($j['people'])===$split,'Nhật ký Tip có tổng tháng');
 verify($items[$b1]['shift']==='Ca 1'&&$items[$b1]['time']==='12:00'&&$items[$b1]['entered_by']===$admin['name']&&$items[$b1]['amount']===100000,'Tip khách 12:00 Ca 1, người nhập: '.json_encode($items[$b1],JSON_UNESCAPED_UNICODE));
 verify($items[$b2]['shift']==='Ca 2'&&$items[$b2]['entered_by']==='Google Sheet'&&$items[$b2]['edits']===2,'Tip nhập trên Sheet, sửa 2 lần');
 verify(count($j['log'])===3&&$j['log'][0]['amount']===80000&&$j['log'][0]['old_amount']===50000,'Nhật ký giữ mọi lần nhập, mới nhất trước');
 $a->run("UPDATE {{shoot_bookings}} SET status='cancelled' WHERE id=?",[$b2]);$j=payroll_bonus_routes($a,'/payroll/tips',[],'GET')[1];
 verify($j['total']===100000&&!array_filter($j['log'],fn($l)=>$l['customer_name']==='Anh Minh'&&$l['live']),'Lịch hủy không tính vào Tip tháng');
 verify(tip_share($a,'2026-10',$ua)===null,'Tháng 10 không tự chia Tip');
 // Ngoại cảnh: lịch có chữ "ngoại cảnh" (ô Đặc Biệt trên Sheet hoặc Ghi chú) + nhân viên có chấm công trong giờ chụp → +150.000đ/lịch, khóa không sửa.
 $o1=$book('2026-11-01',600,'','Chị Mai');$a->run("UPDATE {{shoot_bookings}} SET sheet_fields_json=? WHERE id=?",[json_encode([['label'=>'Đặc Biệt','value'=>'Ngoại Cảnh']],JSON_UNESCAPED_UNICODE),$o1]);
 $o2=$book('2026-11-01',1020,'','Anh Tú');$a->run("UPDATE {{shoot_bookings}} SET note='Chụp NGOẠI CẢNH hồ Tây' WHERE id=?",[$o2]);
 $book('2026-11-01',840,'','Khách thường');$book(local_day(now_ms()+3*86400000),600,'','Ngoại cảnh tương lai');
 verify(booking_is_outdoor(['note'=>'chụp ngoại cảnh'])&&!booking_is_outdoor(['note'=>'Ô TÔ']),'Nhận ra chữ ngoại cảnh');
 $r=$row();verify($r['outdoor_count']===1&&$r['bonus_items']['outdoor']===150000&&$r['outdoor_days']===['2026-11-01 10:00']&&$r['outdoor_missed']===['2026-11-01 17:00'],'Có chấm công lúc 10:00 thì +150k, 17:00 đã chấm ra thì không: '.json_encode([$r['outdoor_count'],$r['outdoor_days'],$r['outdoor_missed']]));
 $ev('in','2026-11-01',16,'day_second');$ev('out','2026-11-01',19,'day_second');$r=$row();
 verify($r['outdoor_count']===2&&$r['bonus_items']['outdoor']===300000&&!$r['outdoor_missed'],'Chấm công Ca 2 thì lịch 17:00 cũng tính: 300k');
 payroll_bonus_routes($a,'/payroll/bonuses',['month'=>'2026-11','user_id'=>$ua,'items'=>['outdoor'=>1000]],'POST');verify($row()['bonus_items']['outdoor']===300000,'Phiếu lương không sửa được Ngoại cảnh');
 try{payroll_bonus_routes($a,'/payroll/bonuses/add',['id'=>uuid_v4(),'day'=>'2026-11-01','user_id'=>$ua,'kind'=>'outdoor','amount'=>10000,'note'=>''],'POST');verify(false,'Nhập thưởng Ngoại cảnh phải bị chặn');}catch(HttpError $e){verify(str_contains($e->getMessage(),'không nhập tay'),'Nhập thưởng không nhập Ngoại cảnh');}
 verify(isset(visible_summary(['role'=>'employee'],$r)['outdoor_days']),'Nhân viên thấy lịch ngoại cảnh của mình');
 // Chỉnh ảnh: nhập số ảnh trên phiếu lương, tự × 2.000đ; không nhập tiền trực tiếp, không nhập ở Nhập thưởng.
 payroll_bonus_routes($a,'/payroll/bonuses',['month'=>'2026-11','user_id'=>$ua,'items'=>['retouch_count'=>150,'retouch'=>999]],'POST');$r=$row();
 verify($r['retouch_count']===150&&$r['bonus_items']['retouch']===300000,'150 ảnh × 2.000đ = 300.000đ');
 $entry=$a->one("SELECT * FROM {{payroll_bonus_entries}} WHERE user_id=? AND kind='retouch' ORDER BY created_at DESC LIMIT 1",[$ua]);verify((int)$entry['amount']===300000&&str_contains($entry['note'],'150 ảnh'),'Lịch sử thưởng ghi số ảnh: '.$entry['note']);
 try{payroll_bonus_routes($a,'/payroll/bonuses/add',['id'=>uuid_v4(),'day'=>'2026-11-01','user_id'=>$ua,'kind'=>'retouch','amount'=>10000,'note'=>''],'POST');verify(false,'Nhập thưởng Chỉnh ảnh phải bị chặn');}catch(HttpError $e){verify(str_contains($e->getMessage(),'số ảnh'),'Nhập thưởng không nhập tiền Chỉnh ảnh');}
 try{payroll_bonus_routes($a,'/payroll/bonuses',['month'=>'2026-11','user_id'=>$ua,'items'=>['retouch_count'=>-1]],'POST');verify(false,'Số ảnh âm phải bị chặn');}catch(HttpError $e){verify(true,'Số ảnh âm bị chặn');}
 // Ăn uống: mặc định 300.000đ; đi ăn thì trừ bớt; sửa trên phiếu là tổng.
 $a->run("DELETE FROM {{payroll_bonuses}} WHERE month='2026-11' AND user_id=? AND kind='meal'",[$ua]);
 $r=$row();verify($r['bonus_items']['meal']===300000&&$r['meal_base']===300000,'Ăn uống mặc định 300.000đ');
 payroll_bonus_routes($a,'/payroll/bonuses/add',['id'=>uuid_v4(),'day'=>'2026-11-01','user_id'=>$ua,'kind'=>'meal','amount'=>-120000,'note'=>'Đi ăn trưa'],'POST');
 $r=$row();verify($r['bonus_items']['meal']===180000&&$r['meal_adjust']===-120000,'Đi ăn trừ 120k còn 180k');
 try{payroll_bonus_routes($a,'/payroll/bonuses/add',['id'=>uuid_v4(),'day'=>'2026-11-01','user_id'=>$ua,'kind'=>'meal','amount'=>-200000,'note'=>''],'POST');verify(false,'Trừ quá phải bị chặn');}catch(HttpError $e){verify(str_contains($e->getMessage(),'180.000đ'),'Không trừ quá số còn lại: '.$e->getMessage());}
 payroll_bonus_routes($a,'/payroll/bonuses',['month'=>'2026-11','user_id'=>$ua,'items'=>['meal'=>250000]],'POST');$r=$row();
 $entry=$a->one("SELECT * FROM {{payroll_bonus_entries}} WHERE user_id=? AND kind='meal' ORDER BY created_at DESC,amount DESC LIMIT 1",[$ua]);
 verify($r['bonus_items']['meal']===250000&&(int)$entry['amount']===70000&&str_contains($entry['note'],'180.000đ → 250.000đ'),'Sửa tổng Ăn uống trên phiếu: 180k → 250k');
 $count=fn()=>(int)$a->scalar("SELECT COUNT(*) FROM {{payroll_bonus_entries}} WHERE user_id=? AND kind='meal'",[$ua]);$before=$count();
 payroll_bonus_routes($a,'/payroll/bonuses',['month'=>'2026-11','user_id'=>$ua,'items'=>['meal'=>250000]],'POST');verify($count()===$before,'Lưu lại không đổi thì không ghi thêm');
 $res=payroll_bonus_routes($a,'/payroll/bonus-entries',[],'GET')[1];$mine=array_values(array_filter($res['people'],fn($p)=>$p['id']===$ua))[0];
 verify($mine['items']['meal']===250000&&!isset($mine['items']['retouch_count'])&&!isset($res['kinds']['retouch'])&&isset($res['kinds']['meal'])&&in_array('meal',$mine['kinds'],true)&&!in_array('retouch',$mine['kinds'],true),'Khung Nhập thưởng hiện đúng Ăn uống, không nhập tiền Chỉnh ảnh');
 // Trực Fanpage: Chuyên cần + Ca không khách + Tips (chia 3) + 2.000.000đ; không nhận Ngoại cảnh, Chỉnh ảnh, Ăn uống, OT; Nhập thưởng không có khoản nào.
 $f=array_values(array_filter($a->monthly('2026-11')['rows'],fn($x)=>(int)$x['id']===$uf))[0];
 $fs=$f['tip_share'];verify($f['bonus_profile']==='fanpage'&&$f['bonus_items']===['tips'=>$fs,'no_customer'=>0,'fanpage'=>2000000]&&$fs===tip_month($a,'2026-11')['share']&&!isset($f['outdoor_count'])&&!isset($f['booking_commission'])&&$f['ot_bonus']===0,'Trực Fanpage: '.json_encode($f['bonus_items']));
 verify($f['bonus_total']===2000000+$fs+($f['attendance_bonus']??0),'Tổng thưởng Trực Fanpage = 2tr + Tips + chuyên cần');
 try{payroll_bonus_routes($a,'/payroll/bonuses/add',['id'=>uuid_v4(),'day'=>'2026-11-01','user_id'=>$uf,'kind'=>'meal','amount'=>-1000,'note'=>''],'POST');verify(false,'Ăn uống cho Trực Fanpage phải bị chặn');}catch(HttpError $e){verify(str_contains($e->getMessage(),'Trực Fanpage'),'Không nhập Ăn uống cho Trực Fanpage');}
 payroll_bonus_routes($a,'/payroll/bonuses',['month'=>'2026-11','user_id'=>$uf,'items'=>['praise'=>50000,'ot_rate'=>30000]],'POST');$f=array_values(array_filter($a->monthly('2026-11')['rows'],fn($x)=>(int)$x['id']===$uf))[0];
 verify($f['bonus_items']===['tips'=>$fs,'no_customer'=>0,'fanpage'=>2000000]&&$f['ot_rate']===0,'Phiếu lương không lưu khoản khác cho Trực Fanpage');
 $res=payroll_bonus_routes($a,'/payroll/bonus-entries',[],'GET')[1];$fp=array_values(array_filter($res['people'],fn($p)=>$p['id']===$uf))[0];verify($fp['kinds']===[]&&$fp['items']===[],'Nhập thưởng: Trực Fanpage không có khoản nhập tay');
 verify(in_array('ZZ Tip Fanpage',payroll_bonus_routes($a,'/payroll/tips',[],'GET')[1]['people'],true),'Nhật ký Tip chia cả cho Trực Fanpage');
 verify(!isset($f['outdoor_count']),'Ngoại cảnh chỉ tính cho nhóm Thợ chụp');
 // Gói chưa có mức: phiếu ghi rõ lịch nào (ô Gói trống hoặc gói lạ) để chọn lại gói.
 $a->run('INSERT INTO {{booking_rates}} VALUES(?,15000,?,?) ON DUPLICATE KEY UPDATE rate=15000',[$ua,$admin['id'],now_ms()]);
 $u1=$book('2026-11-01',630,'','Chị Trống');$a->run("UPDATE {{shoot_bookings}} SET package_label='' WHERE id=?",[$u1]);$u2=$book('2026-11-01',690,'','Anh Lạ');$a->run("UPDATE {{shoot_bookings}} SET package_label='Combo 500' WHERE id=?",[$u2]);
 foreach([$u1,$u2] as $id)$a->run("INSERT INTO {{booking_staff}} VALUES(?,?,'main')",[$id,$ua]);
 $r=$row();$list=$r['booking_unpriced_list']??[];
 verify($r['booking_unpriced']===2&&count($list)===2&&$list[0]['customer_name']==='Chị Trống'&&$list[0]['package_label']===''&&$list[1]['package_label']==='Combo 500'&&$list[0]['time']==='10:30','Ghi rõ 2 lịch chưa có mức: '.json_encode($list,JSON_UNESCAPED_UNICODE));
 verify(isset(visible_summary(['role'=>'employee'],$r)['booking_unpriced_list']),'Nhân viên thấy lịch chưa có mức của mình');
 throw new RuntimeException('ROLLBACK');
});}catch(RuntimeException $e){if($e->getMessage()!=='ROLLBACK')throw $e;}
echo "PASS $n payroll tips + outdoor + retouch + meal + empty shift checks\n";
