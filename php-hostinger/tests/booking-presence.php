<?php
// Hoa hồng lịch khách chỉ tính khi thợ có mặt (đang chấm công) trong giờ chụp. Chạy trong giao dịch rồi hoàn tác.
require_once __DIR__.'/../site/nhansu-private/lib/bootstrap.php';require_once __DIR__.'/../site/nhansu-private/lib/bookings.php';require_once __DIR__.'/../site/nhansu-private/lib/people-finance.php';require_once __DIR__.'/../site/nhansu-private/lib/tasks.php';
$a=new App('/var/nhansu-private');$n=0;function verify($v,$message){global $n;if(!$v)throw new RuntimeException('FAIL: '.$message);$n++;}
try{$a->tx(function()use($a){
 $admin=$a->one("SELECT * FROM {{users}} WHERE role='admin' AND archived=0 LIMIT 1");$a->user=$admin;
 $a->run("INSERT INTO {{users}}(name,email,password,role,employee_code,notes) VALUES('Presence Test','presence.test@local.test','x','employee','PRESENCE-TEST','')");$id=(int)$a->scalar('SELECT LAST_INSERT_ID()');
 $alias='PRESENCE_TEST';$a->run('INSERT INTO {{booking_aliases}} VALUES(?,?,?,?,?)',[alias_key($alias),$alias,$id,$admin['id'],now_ms()]);
 $a->run('INSERT INTO {{booking_rates}} VALUES(?,?,?,?)',[$id,10000,$admin['id'],now_ms()]);
 $at=fn(string $day,string $hm)=>day_time($day)+((int)substr($hm,0,2)*60+(int)substr($hm,3,2))*60000;
 $ev=function(string $type,string $day,string $hm,string $shift)use($a,$id,$at){$a->run("INSERT INTO {{events}}(user_id,type,time,shift,work_date,source,reason) VALUES(?,?,?,?,?,'self','')",[$id,$type,$at($day,$hm),$shift,$day]);};
 // 05/11: làm cả ngày (Ca 1 09:00 → tự sang Ca 2 14:00), chấm ra 17:00.
 $ev('in','2026-11-05','09:00','day_first');$ev('out','2026-11-05','14:00','day_first');$ev('in','2026-11-05','14:00','day_second');$ev('out','2026-11-05','17:00','day_second');
 // 07/11: vào Ca 2 14:05, quên chấm ra. 08/11: vào Ca 1 muộn 12:20, ra 14:00. 09/11: Ca 2 14:00–19:00, OT 19:05–21:00.
 $ev('in','2026-11-07','14:05','day_second');
 $ev('in','2026-11-08','12:20','day_first');$ev('out','2026-11-08','14:00','day_first');
 $ev('in','2026-11-09','14:00','day_second');$ev('out','2026-11-09','19:00','day_second');$ev('in','2026-11-09','19:05','ot');$ev('out','2026-11-09','21:00','ot');
 // 10/11: chấm ra Ca 1 đúng 12:00, khách 12:00 không tính. 11/11: Team giữ 3 tiếng từ 15:00, thợ vào Ca 2 lúc 17:30.
 $ev('in','2026-11-10','09:00','day_first');$ev('out','2026-11-10','12:00','day_first');
 $ev('in','2026-11-11','17:30','day_second');$ev('out','2026-11-11','19:00','day_second');
 $rows=[];foreach([
  ['2026-11-05','10:00','699',''],['2026-11-05','16:30','699',''],['2026-11-05','18:00','699',''],['2026-11-05','19:00','Couple 999',''],
  ['2026-11-06','12:00','699',''],
  ['2026-11-07','18:00','699',''],['2026-11-07','19:40','699',''],
  ['2026-11-08','12:00','699',''],
  ['2026-11-09','19:30','699',''],
  ['2026-11-10','12:00','699',''],
  ['2026-11-11','15:00','Team 349','Giữ 3 tiếng'],
  ['2026-11-20','10:00','699',''],
 ] as [$d,$t,$pkg,$note])$rows[]=['id'=>uuid_v4(),'day'=>$d,'time'=>$t,'status'=>'active','main_alias'=>$alias,'support_alias'=>'','package_label'=>$pkg,'note'=>$note,'customer_name'=>'Presence fixture'];
 import_bookings($a,['tab'=>'presence-test','rows'=>$rows],'presence-test',null);
 $now=$at('2026-11-12','10:00');$r=booking_month_commissions($a,'2026-11',$now)[$id];
 verify($r['customers']===6,'Tính 6 khách có mặt (05/11 10:00, 16:30; 07/11 18:00 quên chấm ra; 08/11 vào muộn; 09/11 OT; 11/11 Team), được '.$r['customers']);
 verify($r['absent']===5,'Không tính 5 khách (05/11 18:00, 19:00 sau khi chấm ra 17:00; 06/11 không chấm công; 07/11 19:40 quá hạn 19:30; 10/11 chấm ra đúng 12:00), được '.$r['absent']);
 verify($r['units']===6&&$r['unpriced']===0,'Couple 999 không có mặt nên không cộng 2 suất');
 // 05/11 không chấm ra 17:00 (quên) thì khách 18:00, 19:00 vẫn tính tới hạn 19:30.
 $a->run("DELETE FROM {{events}} WHERE user_id=? AND work_date='2026-11-05' AND type='out' AND shift='day_second'",[$id]);
 $r=booking_month_commissions($a,'2026-11',$now)[$id];verify($r['customers']===8&&$r['absent']===3&&$r['units']===9,'Quên chấm ra coi như có mặt tới 19:30');
 // Khách chưa tới giờ chụp (20/11) chưa tính, cũng không đếm là vắng.
 $r=booking_month_commissions($a,'2026-11',$at('2026-11-30','23:00'))[$id];verify($r['customers']+$r['absent']===12,'Khách 20/11 tính khi đã qua giờ: '.json_encode($r));
 verify($r['absent']===4,'20/11 không chấm công thì không tính');
 // Bảng công nhân viên thấy số khách không tính, không thấy tiền khác.
 $row=visible_summary(['role'=>'employee'],['booking_absent'=>3,'booking_customers'=>8,'booking_commission'=>90000,'salary'=>1,'booking_rate'=>10000]);
 verify(($row['booking_absent']??null)===3&&!isset($row['salary'])&&!isset($row['booking_rate']),'Nhân viên thấy số khách không tính, không thấy lương/mức');
 throw new RuntimeException('ROLLBACK');
});}catch(RuntimeException $e){if($e->getMessage()!=='ROLLBACK')throw $e;}
echo "PASS $n booking presence checks: commission only while checked in\n";
