<?php
// Đóng điểm danh (chủ dự án yêu cầu 08/11/2026): chấm vào quá 1 tiếng sau khách đầu tiên thì không tính công ca đó, vẫn tính hoa hồng.
foreach(['bootstrap','telegram','bookings','people-finance','tasks','availability','workflows','api'] as $f)require_once __DIR__.'/../site/nhansu-private/lib/'.$f.'.php';
$a=new App('/var/nhansu-private');$n=0;function verify($v,$message){global $n;if(!$v)throw new RuntimeException('FAIL: '.$message);$n++;}
function denied(callable $fn,string $want,string $message){try{$fn();}catch(HttpError $e){verify(str_contains($e->getMessage(),$want),$message.' ('.$e->getMessage().')');return;}verify(false,$message.' (không bị chặn)');}
try{$a->tx(function()use($a){
 $day='2026-11-04';$month='2026-11';$a->run("UPDATE {{shoot_bookings}} SET status='cancelled' WHERE shoot_day IN (?,?)",[$day,local_day(now_ms())]);
 $admin=$a->one("SELECT * FROM {{users}} WHERE role='admin' AND archived=0 LIMIT 1");
 $a->run("INSERT INTO {{users}}(name,email,password,role,employee_code,notes) VALUES('CC Nhan Vien','cc@close.test','x','employee','CC-1','')");$emp=$a->one('SELECT * FROM {{users}} WHERE id=LAST_INSERT_ID()');$id=(int)$emp['id'];
 $a->run('INSERT INTO {{salaries}}(user_id,monthly_salary) VALUES(?,3000000) ON DUPLICATE KEY UPDATE monthly_salary=3000000',[$id]);$a->run('REPLACE INTO {{booking_rates}} VALUES(?,17500,?,?)',[$id,$admin['id'],now_ms()]);
 $at=fn($d,$hm)=>day_time($d)+((int)substr($hm,0,2)*60+(int)substr($hm,3,2))*60000;
 $book=function($d,$hm,$name)use($a,$id){$b=uuid_v4();$a->run("INSERT INTO {{shoot_bookings}}(id,source_sheet,source_tab,source_row,shoot_day,minutes,package_label,customer_name,customer_phone,main_alias,support_alias,note,status,created_at,updated_at) VALUES(?,'test','test',1,?,?,'699',?,'','','','','active',?,?)",[$b,$d,(int)substr($hm,0,2)*60+(int)substr($hm,3,2),$name,now_ms(),now_ms()]);$a->run("INSERT INTO {{booking_staff}} VALUES(?,?,'main')",[$b,$id]);};
 $book($day,'10:00','Khach 10h');$book($day,'11:30','Khach 11h30');$book($day,'15:00','Khach 15h');
 verify(checkin_closed($a,$emp,'day_first',$at($day,'11:00'))===null,'11:00 đúng 1 tiếng: vẫn chấm được');
 $c=checkin_closed($a,$emp,'day_first',$at($day,'11:01'));verify($c&&$c['closed_at']===$at($day,'11:00'),'11:01: đã đóng điểm danh');
 verify(str_contains(checkin_closed_message('day_first',$c),'đóng lúc 11:00'),'Thông báo ghi giờ đóng');
 verify(checkin_closed($a,$admin+['role'=>'admin'],'day_first',$at($day,'11:01'))===null,'Admin/Quản lý không áp dụng');
 verify(checkin_closed($a,$emp,'ot',$at($day,'21:00'))===null,'OT không áp dụng');
 // Ghi có mặt 11:01 (no_credit) → Ca 1 không tính công; Ca 2 bình thường.
 $ev=function($type,$hm,$shift,$nc=0)use($a,$id,$day,$at){$a->run("INSERT INTO {{events}}(user_id,type,time,shift,work_date,source,reason,no_credit) VALUES(?,?,?,?,?,'self','',?)",[$id,$type,$at($day,$hm),$shift,$day,$nc]);};
 $ev('in','11:01','day_first',1);$ev('out','14:00','day_first');
 verify(checkin_closed($a,$emp,'day_first',$at($day,'12:00'))===null,'Đã có lượt trong ca thì không hỏi lại (chấm ra / làm tiếp như thường)');
 $ev('in','14:05','day_second');$ev('out','19:00','day_second');
 $rows=array_column(payroll_apply($a,$month,$a->summaries($month)),null,'id');$r=$rows[$id];
 verify($r['workdays']===0.5&&$r['closedShifts']===1&&$r['closedDays']===[$day.':day_first'],'Ca 1 đóng điểm danh không tính công, Ca 2 tính 0,5');
 verify($r['booking_customers']===2&&$r['booking_absent']===1,'Hoa hồng: khách 11:30 và 15:00 vẫn tính; khách 10:00 lúc chưa có mặt thì không');
 verify(isset(visible_summary(['role'=>'employee'],$r)['closedShifts']),'Nhân viên thấy số ca đóng điểm danh');
 // Đường chấm công: hôm nay khách đầu 09:00 thì giờ này (sau 10:00) chấm vào Ca 1 bị chặn khi không gửi presence_only.
 if(now_ms()>$at(local_day(now_ms()),'10:01')){$book(local_day(now_ms()),'09:00','Khach som');$a->user=$emp;
  denied(fn()=>main_routes($a,'/attendance',['type'=>'in','shift'=>'day_first'],'POST'),'Đã đóng điểm danh Ca 1','Chấm vào bình thường bị chặn');}
 throw new RuntimeException('ROLLBACK');});}catch(RuntimeException $e){if($e->getMessage()!=='ROLLBACK'){echo $e->getMessage(),"\n";exit(1);}}
echo "OK $n kiểm tra\n";
