<?php
// Xóa cả ca ở Bảng chấm công tháng (chủ dự án yêu cầu 12/11/2026) + quyền Xóa lịch chụp. Chạy trong giao dịch rồi hoàn tác.
foreach(['bootstrap','telegram','bookings','people-finance','tasks','availability','workflows','api'] as $f)require_once __DIR__.'/../site/nhansu-private/lib/'.$f.'.php';
$a=new App('/var/nhansu-private');$n=0;function verify($v,$message){global $n;if(!$v)throw new RuntimeException('FAIL: '.$message);$n++;}
function denied(callable $fn,string $want,string $message){try{$fn();}catch(HttpError $e){verify(str_contains($e->getMessage(),$want),$message.' ('.$e->getMessage().')');return;}verify(false,$message.' (không bị chặn)');}
try{$a->tx(function()use($a){
 $admin=$a->one("SELECT * FROM {{users}} WHERE role='admin' AND archived=0 LIMIT 1");
 $a->run("INSERT INTO {{users}}(name,email,password,role,employee_code,notes) VALUES('SD Nhan Vien','sd@local.test','x','employee','SD-1','')");$emp=$a->one('SELECT * FROM {{users}} WHERE id=LAST_INSERT_ID()');
 $day=local_day(now_ms()-86400000);$month=substr($day,0,7);$at=fn($hm)=>day_time($day)+((int)substr($hm,0,2)*60+(int)substr($hm,3,2))*60000;
 $ev=function($type,$shift,$hm,$src='self')use($a,$emp,$day,$at){$a->run('INSERT INTO {{events}}(user_id,type,time,shift,work_date,source,reason) VALUES(?,?,?,?,?,?,?)',[$emp['id'],$type,$at($hm),$shift,$day,$src,'']);return (int)$a->db->lastInsertId();};
 // Nhân viên chọn nhầm Làm cả ngày: vào Ca 1 13:30, tự chuyển ca 14:00, ra Ca 2 19:00.
 $in=$ev('in','day_first','13:30');$ev('out','day_first','14:00','auto');$ev('in','day_second','14:00','auto');$ev('out','day_second','19:00');
 $a->run('INSERT INTO {{full_days}}(user_id,work_date,in_event_id,created_at,rolled_at) VALUES(?,?,?,?,?)',[$emp['id'],$day,$in,now_ms(),now_ms()]);
 $a->run('INSERT INTO {{late_checkins}}(user_id,day,shift,expected_at,checkin_at,late_minutes,ordinal,points,created_at) VALUES(?,?,?,?,?,?,?,?,?)',[$emp['id'],$day,'day_first',$at('09:00'),$at('13:30'),270,1,0,now_ms()]);
 $me=fn()=>array_values(array_filter($a->summaries($month),fn($x)=>(int)$x['id']===(int)$emp['id']))[0];
 $before=$me();verify($before['completedShifts']===2,'Trước khi xóa: 2 ca');
 $del=fn(array $u,string $shift,string $d=null)=>(function()use($a,$u,$shift,$d,$emp,$day){$a->user=$u;return workflow_routes($a,'/events/shift/delete',['user_id'=>(int)$emp['id'],'day'=>$d??$day,'shift'=>$shift,'note'=>'Chọn nhầm cả ngày'],'POST')[1];})();
 denied(fn()=>$del($emp,'day_first'),'','Nhân viên không xóa được');
 $a->run("UPDATE {{users}} SET role='manager' WHERE id=?",[$emp['id']]);$selfMgr=$a->one('SELECT * FROM {{users}} WHERE id=?',[$emp['id']]);denied(fn()=>$del($selfMgr,'day_first'),'','Không tự xóa ca của mình');$a->run("UPDATE {{users}} SET role='employee' WHERE id=?",[$emp['id']]);
 $r=$del($admin,'day_first');verify($r['removed']===2,'Xóa Ca 1 gỡ 2 lượt (cả lượt tự chuyển ca)');
 $left=$a->events((int)$emp['id']);verify(count($left)===2&&!array_filter($left,fn($e)=>$e['shift']==='day_first'),'Ca 2 giữ nguyên');
 verify(!$a->one('SELECT 1 FROM {{full_days}} WHERE user_id=?',[$emp['id']]),'Bỏ dấu Làm cả ngày');
 verify(!$a->one('SELECT 1 FROM {{late_checkins}} WHERE user_id=?',[$emp['id']]),'Bỏ mốc đi muộn của ca đã xóa');
 $after=$me();verify($after['completedShifts']===1&&$after['hours']<$before['hours'],'Công lương chỉ còn 1 ca, giờ làm giảm');
 denied(fn()=>$del($admin,'day_first'),'không còn lượt','Xóa lại báo không còn lượt');
 denied(fn()=>$del($admin,'legacy'),'Ca không hợp lệ','Ca lạ bị chặn');
 verify($a->one("SELECT 1 FROM {{employee_audit}} WHERE user_id=? AND action LIKE 'delete_shift:%'",[$emp['id']]),'Ghi nhật ký');
 // OT: xóa các lượt OT của ngày đó.
 $ev('in','ot','19:00');$a->run('INSERT INTO {{events}}(user_id,type,time,shift,work_date,source,reason) VALUES(?,?,?,?,?,?,?)',[$emp['id'],'out',$at('23:30')+3600000,'ot',$day,'self','']);
 verify($me()['otSessions']===1,'Có 1 lượt OT');verify($del($admin,'ot')['removed']===2&&$me()['otSessions']===0,'Xóa OT (ra qua nửa đêm) mất lượt OT');
 // Quyền Xóa lịch chụp.
 $a->user=$emp;verify(booking_permissions($a)['can_delete']===false,'Nhân viên chưa tick: không xóa lịch');
 $a->user=$admin;save_user_grants($a,(int)$emp['id'],'employee',['booking_delete'=>true]);$a->user=$emp;verify(booking_permissions($a)['can_delete']===true,'Tick Xóa lịch chụp: được xóa');
 $a->user=$admin;verify(booking_permissions($a)['can_delete']===true,'Admin luôn được xóa');
 $a->user=$admin;save_user_grants($a,(int)$emp['id'],'employee',['booking_delete'=>false]);$a->user=$emp;denied(fn()=>booking_routes($a,'/bookings/delete',['id'=>'00000000-0000-4000-8000-000000000000'],'POST'),'cấp quyền xóa','Bỏ tick: API chặn');
 throw new RuntimeException('ROLLBACK');});}catch(RuntimeException $e){if($e->getMessage()!=='ROLLBACK'){echo $e->getMessage(),"\n";exit(1);}}
echo "OK $n kiểm tra\n";
