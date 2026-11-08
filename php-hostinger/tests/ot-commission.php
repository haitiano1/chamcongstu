<?php
// Hoa hồng khách OT (chủ dự án yêu cầu 08/11/2026): lịch từ 19:00 có chấm OT trong giờ chụp, 1 lịch = 1 khách × mức × 2; Tín (Trực Fanpage) chỉ có khoản này.
foreach(['bootstrap','telegram','bookings','people-finance','tasks','availability'] as $f)require_once __DIR__.'/../site/nhansu-private/lib/'.$f.'.php';
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
 throw new RuntimeException('ROLLBACK');});}catch(RuntimeException $e){if($e->getMessage()!=='ROLLBACK'){echo $e->getMessage(),"\n";exit(1);}}
echo "OK $n kiểm tra\n";
