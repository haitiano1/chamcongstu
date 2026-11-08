<?php
// Hoa hồng khách OT (chủ dự án yêu cầu 08/11/2026): lịch từ 19:00 có chấm OT trong giờ chụp, 1 lịch = 1 khách × mức × 2; Tín (Trực Fanpage) chỉ có khoản này.
foreach(['bootstrap','telegram','bookings','people-finance','tasks','availability','workflows','api'] as $f)require_once __DIR__.'/../site/nhansu-private/lib/'.$f.'.php';
$a=new App('/var/nhansu-private');$n=0;function verify($v,$message){global $n;if(!$v)throw new RuntimeException('FAIL: '.$message);$n++;}
try{$a->tx(function()use($a){
 $day='2026-11-03';$month='2026-11';$a->run("UPDATE {{shoot_bookings}} SET status='cancelled' WHERE shoot_day=?",[$day]);
 $a->user=$a->one("SELECT * FROM {{users}} WHERE role='admin' AND archived=0 LIMIT 1");$admin=$a->user['id'];
 $make=function(string $code,int $rate,string $profile)use($a,$admin){$a->run("INSERT INTO {{users}}(name,email,password,role,employee_code,notes) VALUES(?,?,'x','employee',?,'')",['OT '.$code,$code.'@ot.test',$code]);$id=(int)$a->scalar('SELECT LAST_INSERT_ID()');$a->run('INSERT INTO {{salaries}}(user_id,monthly_salary) VALUES(?,3000000) ON DUPLICATE KEY UPDATE monthly_salary=3000000',[$id]);$a->run('REPLACE INTO {{booking_rates}} VALUES(?,?,?,?)',[$id,$rate,$admin,now_ms()]);$a->run('REPLACE INTO {{payroll_profiles}} VALUES(?,?,?,?)',[$id,$profile,$admin,now_ms()]);return $id;};
 $huy=$make('OTH',17500,'photo');$tin=$make('OTT',10000,'fanpage');$late=$make('OTL',15000,'photo');
 $ev=function(int $id,string $type,string $hm,string $shift)use($a,$day){$a->run("INSERT INTO {{events}}(user_id,type,time,shift,work_date,source,reason) VALUES(?,?,?,?,?,'self','')",[$id,$type,day_time($day)+((int)substr($hm,0,2)*60+(int)substr($hm,3,2))*60000,$shift,$day]);};
 $book=function(string $hm,string $pkg,string $name,array $staff)use($a,$day){$id=uuid_v4();$a->run("INSERT INTO {{shoot_bookings}}(id,source_sheet,source_tab,source_row,shoot_day,minutes,package_label,customer_name,customer_phone,main_alias,support_alias,note,status,created_at,updated_at) VALUES(?,'test','test',1,?,?,?,?,'','','','','active',?,?)",[$id,$day,(int)substr($hm,0,2)*60+(int)substr($hm,3,2),$pkg,$name,now_ms(),now_ms()]);foreach($staff as $u)$a->run("INSERT INTO {{booking_staff}} VALUES(?,?,'main')",[$id,$u]);};
 foreach([$huy,$tin,$late] as $id){$ev($id,'in','14:00','day_second');$ev($id,'out','19:00','day_second');}
 $ev($huy,'in','19:00','ot');$ev($huy,'out','21:30','ot');$ev($tin,'in','19:05','ot');$ev($tin,'out','21:30','ot');
 $book('15:00','Couple 999','Khach chieu',[$huy,$late]);$book('19:00','Couple 999','Khach OT 1',[$huy,$late]);$book('20:00','699','Khach OT 2',[$huy,$late]);
 $rows=array_column(payroll_apply($a,$month,$a->summaries($month)),null,'id');
 $h=$rows[$huy];verify($h['booking_customers']===1&&$h['booking_units']===2&&$h['booking_commission']===35000,'Huy: khách ca thường giữ như cũ (Couple ×2), không tính lịch OT');
 verify($h['ot_booking_customers']===2&&$h['ot_booking_rate']===35000&&$h['ot_bonus']===70000,'Huy: 2 lịch OT × 17.500 × 2 = 70.000');
 $t=$rows[$tin];verify(!isset($t['booking_commission'])&&$t['ot_booking_customers']===2&&$t['ot_bonus']===40000,'Tín: chỉ hoa hồng OT 2 × 10.000 × 2');
 verify($t['bonus_total']>=40000,'Tín: cộng vào Tổng thưởng');
 $l=$rows[$late];verify($l['ot_booking_customers']===0&&$l['ot_bonus']===0&&$l['booking_absent']===2,'Không chấm OT: lịch OT không tính (khách ngoài giờ chấm công)');
 verify(count($h['ot_booking_list'])===2&&$h['ot_booking_list'][0]['time']==='19:00','Danh sách lịch OT');
 $staff=visible_summary(['role'=>'employee'],$t);verify(isset($staff['ot_booking_customers'],$staff['ot_bonus'])&&!isset($staff['salary']),'Nhân viên thấy khoản OT của mình');
 // 09/11/2026: khách 18:30 chụp lúc đã vào OT 19:00 cũng tính khách OT (theo giờ chụp trùng lượt OT).
 $book('18:30','699','Khach 18h30',[$huy,$late]);$rows=array_column(payroll_apply($a,$month,$a->summaries($month)),null,'id');
 verify($rows[$huy]['ot_booking_customers']===3&&$rows[$huy]['booking_customers']===1,'Huy: khách 18:30 trùng lượt OT tính OT, không tính thường');
 verify($rows[$tin]['ot_booking_customers']===3,'Tín vào OT 19:05: khách 18:30 (chụp tới 19:30) trùng lượt OT nên tính');
 verify(count($rows[$huy]['ot_sessions'])===1&&$rows[$huy]['ot_sessions'][0]['out']>$rows[$huy]['ot_sessions'][0]['in'],'Phiếu lương có giờ lượt OT');
 // Chấm hộ cả lượt OT (Tín quên chấm): ngày 05/11, 19:00 → 00:30 hôm sau, khách 20:00 hôm đó → 1 khách OT.
 $d2='2026-11-05';$a->run("UPDATE {{shoot_bookings}} SET status='cancelled' WHERE shoot_day=?",[$d2]);$id2=uuid_v4();$a->run("INSERT INTO {{shoot_bookings}}(id,source_sheet,source_tab,source_row,shoot_day,minutes,package_label,customer_name,customer_phone,main_alias,support_alias,note,status,created_at,updated_at) VALUES(?,'test','test',1,?,1200,'699','Khach 20h','','','','','active',?,?)",[$id2,$d2,now_ms(),now_ms()]);
 $t0=day_time($d2)+19*3600000;$post=fn($b)=>management_routes($a,'/attendance/manual-shift',$b+['user_id'=>$tin,'shift'=>'ot','reason'=>'Quên chấm OT, quản lý xác nhận'],'POST');
 try{$post(['in_time'=>$t0,'out_time'=>(int)($t0+5.5*3600000)]);verify(false,'Thiếu xác nhận OT phải bị chặn');}catch(HttpError $e){verify(str_contains($e->getMessage(),'Xác nhận'),'Chấm hộ OT cần xác nhận');}
 $ids=$post(['in_time'=>$t0,'out_time'=>(int)($t0+5.5*3600000),'ot_confirmed'=>true])[1]['ids'];
 $rows=array_column(payroll_apply($a,$month,$a->summaries($month)),null,'id');verify($rows[$tin]['ot_booking_customers']===4&&$rows[$tin]['otSessions']===2,'Chấm hộ cả lượt OT qua 0h: thêm 1 khách OT cho Tín');
 try{$post(['in_time'=>$t0+3600000,'out_time'=>$t0+2*3600000,'ot_confirmed'=>true]);verify(false,'Trùng lượt OT phải chặn');}catch(HttpError $e){verify(str_contains($e->getMessage(),'đã có lượt OT'),'Không chấm hộ OT trùng giờ');}
 // Xóa lượt chấm hộ: xóa lượt vào khi còn lượt ra thì chặn; xóa ra rồi vào thì hết khách OT.
 try{workflow_routes($a,"/events/{$ids[0]}/delete",[],'POST');verify(false,'Phải chặn');}catch(HttpError $e){verify(str_contains($e->getMessage(),'sai thứ tự'),'Xóa lượt vào trước lượt ra thì chặn');}
 workflow_routes($a,"/events/{$ids[1]}/delete",['note'=>'Sai giờ'],'POST');workflow_routes($a,"/events/{$ids[0]}/delete",[],'POST');
 $rows=array_column(payroll_apply($a,$month,$a->summaries($month)),null,'id');verify($rows[$tin]['ot_booking_customers']===3,'Đã xóa lượt chấm hộ OT');
 $self=(int)$a->scalar("SELECT id FROM {{events}} WHERE user_id=? AND source='self' LIMIT 1",[$tin]);try{workflow_routes($a,"/events/$self/delete",[],'POST');verify(false,'Phải chặn');}catch(HttpError $e){verify(str_contains($e->getMessage(),'tự chấm'),'Không xóa lượt tự chấm');}
 // Gợi ý chấm hộ OT: vào 19:00, ra = khách cuối chụp xong (03/11: 699 lúc 20:00, 1 tiếng → 21:00; Couple 19:00 2 tiếng → 21:00).
 $_GET['day']=$day;$g=management_routes($a,'/attendance/ot-suggest',[],'GET')[1];verify($g['in']===day_time($day)+19*3600000&&$g['out']===day_time($day)+21*3600000&&$g['count']===3,'Gợi ý OT 19:00 → 21:00, 3 lịch còn chụp sau 19:00: '.json_encode($g));
 // Sửa giờ (ca của Tín 07/11: 22:57 → 08:56 hôm sau). Sửa giờ ra trước thành 21:30 thì sai thứ tự; sửa vào 19:00 rồi ra 21:30 thì được.
 $d3='2026-11-07';$a->run("UPDATE {{shoot_bookings}} SET status='cancelled' WHERE shoot_day=?",[$d3]);$book2=function($hm)use($a,$d3){$a->run("INSERT INTO {{shoot_bookings}}(id,source_sheet,source_tab,source_row,shoot_day,minutes,package_label,customer_name,customer_phone,main_alias,support_alias,note,status,created_at,updated_at) VALUES(?,'test','test',1,?,?,'699','Khach 0711','','','','','active',?,?)",[uuid_v4(),$d3,(int)substr($hm,0,2)*60+(int)substr($hm,3,2),now_ms(),now_ms()]);};$book2('20:00');
 $a->run("INSERT INTO {{events}}(user_id,type,time,shift,work_date,source,reason) VALUES(?,'in',?,'ot',?,'manual','Chấm hộ')",[$tin,day_time($d3)+(22*60+57)*60000,$d3]);$ein=(int)$a->scalar('SELECT LAST_INSERT_ID()');
 $a->run("INSERT INTO {{events}}(user_id,type,time,shift,work_date,source,reason) VALUES(?,'out',?,'ot',?,'self','')",[$tin,day_time($d3)+(32*60+56)*60000,$d3]);$eout=(int)$a->scalar('SELECT LAST_INSERT_ID()');
 $ot7=fn()=>count(array_filter(array_column(payroll_apply($a,$month,$a->summaries($month)),null,'id')[$tin]['ot_booking_list'],fn($x)=>$x['day']===$d3));verify($ot7()===0,'22:57 → 08:56: không trùng khách 20:00');
 $edit=fn($id,$t,$note='Chấm hộ nhầm giờ')=>workflow_routes($a,"/events/$id/time",['time'=>$t,'note'=>$note],'POST');
 try{$edit($eout,day_time($d3)+(21*60+30)*60000);verify(false,'Phải chặn');}catch(HttpError $e){verify(str_contains($e->getMessage(),'thứ tự')||str_contains($e->getMessage(),'không hợp lệ'),'Sửa giờ ra trước giờ vào thì chặn: '.$e->getMessage());}
 try{$edit($ein,day_time($d3)+19*3600000,'ngắn');verify(false,'Phải chặn');}catch(HttpError $e){verify(str_contains($e->getMessage(),'lý do'),'Bắt buộc lý do');}
 $edit($ein,day_time($d3)+19*3600000);$edit($eout,day_time($d3)+(21*60+30)*60000);verify($ot7()===1,'Sửa vào 19:00, ra 21:30: khách 20:00 tính OT');
 verify(str_contains($a->scalar('SELECT reason FROM {{events}} WHERE id=?',[$eout]),'Sửa giờ 08:56 08/11 → 21:30 07/11'),'Ghi giờ cũ → mới vào lý do');
 $a->user=$a->one('SELECT * FROM {{users}} WHERE id=?',[$tin]);try{$edit($ein,day_time($d3)+19*3600000+60000);verify(false,'Phải chặn');}catch(HttpError $e){verify($e->status===403,'Nhân viên không sửa giờ');}
 throw new RuntimeException('ROLLBACK');});}catch(RuntimeException $e){if($e->getMessage()!=='ROLLBACK'){echo $e->getMessage(),"\n";exit(1);}}
echo "OK $n kiểm tra\n";
