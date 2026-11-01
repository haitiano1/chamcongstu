<?php
// Ca không khách tính theo cả studio (chủ dự án chốt 01/11/2026): ca studio có khách thì mọi nhân viên chấm đủ ca đều có công.
require __DIR__.'/../site/nhansu-private/lib/bootstrap.php';require __DIR__.'/../site/nhansu-private/lib/bookings.php';
$a=new App('/var/nhansu-private');$n=0;function verify($v,$message){global $n;if(!$v)throw new RuntimeException($message);$n++;}
class Rollback extends Exception {}
try{$a->tx(function()use($a){
 $day='2026-11-27';$a->run("UPDATE {{shoot_bookings}} SET status='cancelled' WHERE shoot_day=?",[$day]);
 $hash=password_hash('x',PASSWORD_DEFAULT);
 $make=function(string $name)use($a,$hash){$a->run("INSERT INTO {{users}}(name,email,password,role,employee_code,notes) VALUES(?,?,?,'employee',?,'')",[$name,strtolower(str_replace(' ','',$name)).'@studio-credit.test',$hash,'ZZSC'.substr(md5($name),0,6)]);$id=(int)$a->one('SELECT id FROM {{users}} WHERE email=?',[strtolower(str_replace(' ','',$name)).'@studio-credit.test'])['id'];$a->run('INSERT INTO {{salaries}} VALUES(?,3000000) ON DUPLICATE KEY UPDATE monthly_salary=3000000',[$id]);return $id;};
 $tin=$make('ZZ Test Tin');$tho=$make('ZZ Test Tho');
 $ev=function(int $id,string $type,string $hm,string $shift)use($a,$day){$a->run("INSERT INTO {{events}}(user_id,type,time,shift,work_date,source,reason) VALUES(?,?,?,?,?,'self','')",[$id,$type,day_time($day)+((int)substr($hm,0,2)*60+(int)substr($hm,3))*60000,$shift,$day]);};
 foreach([$tin,$tho] as $id){$ev($id,'in','09:00','day_first');$ev($id,'out','14:00','day_first');$ev($id,'in','14:00','day_second');$ev($id,'out','19:00','day_second');}
 $row=fn($id)=>array_values(array_filter($a->summaries('2026-11'),fn($r)=>(int)$r['id']===$id))[0];
 $r=$row($tin);verify($r['workdays']===0&&$r['unbookedShifts']===2&&$r['base_salary']===0,'Studio không có khách: 0 công, 2 ca không khách');
 // Khách chỉ ca 1, giao cho người khác (không phải Tín).
 $bid=uuid_v4();$a->run("INSERT INTO {{shoot_bookings}}(id,source_sheet,source_tab,source_row,shoot_day,minutes,package_label,customer_name,customer_phone,main_alias,support_alias,note,status,created_at,updated_at) VALUES(?,'test','test',1,?,600,'699','Khach A','','','','','active',?,?)",[$bid,$day,now_ms(),now_ms()]);
 $a->run('INSERT INTO {{booking_staff}} VALUES(?,?,?)',[$bid,$tho,'main']);
 $r=$row($tin);verify($r['workdays']===0.5&&$r['unbookedShifts']===1&&$r['base_salary']===50000,'Studio có khách ca 1 (người khác chụp): Tín được 0,5 công = 50.000đ');
 $r=$row($tho);verify($r['workdays']===0.5,'Thợ chụp cũng 0,5 công');
 $bid2=uuid_v4();$a->run("INSERT INTO {{shoot_bookings}}(id,source_sheet,source_tab,source_row,shoot_day,minutes,package_label,customer_name,customer_phone,main_alias,support_alias,note,status,created_at,updated_at) VALUES(?,'test','test',2,?,960,'699','Khach B','','','','','active',?,?)",[$bid2,$day,now_ms(),now_ms()]);
 $r=$row($tin);verify($r['workdays']===1&&$r['unbookedShifts']===0&&$r['base_salary']===100000,'Studio có khách cả 2 ca, chưa gán thợ: Tín 1 công = 100.000đ (lương 3tr / 30 ngày)');
 $a->run("UPDATE {{shoot_bookings}} SET status='cancelled' WHERE id=?",[$bid2]);
 $r=$row($tin);verify($r['workdays']===0.5&&$r['unbookedShifts']===1,'Lịch hủy không tính là có khách');
 throw new Rollback();
});}catch(Rollback){}
echo "studio-credit: $n checks OK\n";
