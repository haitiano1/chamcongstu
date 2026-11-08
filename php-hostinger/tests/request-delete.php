<?php
// Xóa Yêu cầu bổ sung công (chủ dự án yêu cầu 08/11/2026) + bổ sung Cả 2 ca / OT. Chạy trong giao dịch rồi hoàn tác.
foreach(['bootstrap','telegram','bookings','people-finance','tasks','availability','workflows','api'] as $f)require_once __DIR__.'/../site/nhansu-private/lib/'.$f.'.php';
$a=new App('/var/nhansu-private');$n=0;function verify($v,$message){global $n;if(!$v)throw new RuntimeException('FAIL: '.$message);$n++;}
function denied(callable $fn,string $want,string $message){try{$fn();}catch(HttpError $e){verify(str_contains($e->getMessage(),$want),$message.' ('.$e->getMessage().')');return;}verify(false,$message.' (không bị chặn)');}
try{$a->tx(function()use($a){
 $admin=$a->one("SELECT * FROM {{users}} WHERE role='admin' AND archived=0 LIMIT 1");
 $a->run("INSERT INTO {{users}}(name,email,password,role,employee_code,notes) VALUES('RD Nhan Vien','rd@local.test','x','employee','RD-1','')");$emp=$a->one('SELECT * FROM {{users}} WHERE id=LAST_INSERT_ID()');
 $day=local_day(now_ms()-3*86400000);$at=fn($hm)=>day_time($day)+((int)substr($hm,0,2)*60+(int)substr($hm,3,2))*60000;
 $req=function(string $shift,string $type,string $hm,bool $ot=false)use($a,$emp,$at){$a->user=$emp;return workflow_routes($a,'/attendance-requests',['shift'=>$shift,'type'=>$type,'time'=>$at($hm),'reason'=>'Quên chấm công hôm đó','ot_confirmed'=>$ot],'POST')[1]['id'];};
 $ok=function(int $id)use($a,$admin){$a->user=$admin;workflow_routes($a,"/attendance-requests/$id/review",['status'=>'approved','note'=>'Đã kiểm tra'],'POST');};
 $del=function(array $u,int $id,string $note='')use($a){$a->user=$u;return workflow_routes($a,"/attendance-requests/$id/delete",['note'=>$note],'POST')[1];};
 $list=function(array $u)use($a){$a->user=$u;return array_column(workflow_routes($a,'/attendance-requests',[],'GET')[1]['rows'],'id');};
 // Nhân viên xóa yêu cầu đang chờ của mình.
 $p=$req('day_first','in','09:00');verify($del($emp,$p)['removed']===false,'Nhân viên xóa yêu cầu chờ');verify(!in_array($p,$list($emp))&&!in_array($p,$list($admin)),'Đã xóa thì ẩn khỏi danh sách');
 denied(fn()=>$del($emp,$p),'đã bị xóa','Không xóa lại');
 // Cả 2 ca = 4 lượt, duyệt lần lượt.
 $ids=[$req('day_first','in','09:05'),$req('day_first','out','14:00'),$req('day_second','in','14:00'),$req('day_second','out','19:00')];foreach($ids as $id)$ok($id);
 verify(count($a->events($emp['id']))===4,'Cả 2 ca: 4 lượt chấm công');
 denied(fn()=>$del($emp,$ids[0]),'Chờ xử lý','Nhân viên không xóa yêu cầu đã duyệt');
 // Gỡ lượt vào Ca 2 khi còn lượt ra phía sau: chặn.
 denied(fn()=>$del($admin,$ids[2]),'sai thứ tự','Gỡ lượt vào khi còn lượt ra phía sau thì chặn');
 $r=$del($admin,$ids[3],'Nhân viên khai sai');verify($r['removed']===true&&count($a->events($emp['id']))===3,'Admin xóa yêu cầu đã duyệt gỡ luôn lượt chấm công');
 verify($del($admin,$ids[2])['removed']===true&&count($a->events($emp['id']))===2,'Sau đó gỡ được lượt vào Ca 2');
 $row=$a->one('SELECT * FROM {{attendance_requests}} WHERE id=?',[$ids[3]]);verify($row['status']==='deleted'&&$row['deleted_by']===$admin['id']&&str_contains($row['delete_note'],'Nhân viên khai sai'),'Lưu người xóa, lý do');
 // OT vào + ra.
 $o1=$req('ot','in','19:30',true);$o2=$req('ot','out','21:00');$ok($o1);$ok($o2);$s=$a->summaries(substr($day,0,7));$me=array_values(array_filter($s,fn($x)=>(int)$x['id']===(int)$emp['id']))[0];verify($me['otSessions']===1,'OT bổ sung tính 1 lượt');
 // Yêu cầu bị từ chối: Admin xóa được, không đụng chấm công.
 $x=$req('day_second','in','15:00');$a->user=$admin;workflow_routes($a,"/attendance-requests/$x/review",['status'=>'rejected','note'=>'Không đúng giờ'],'POST');verify($del($admin,$x)['removed']===false,'Xóa yêu cầu từ chối');
 // Còn yêu cầu chờ bị xóa thì không chặn chốt lương: không còn pending.
 verify(!$a->one("SELECT id FROM {{attendance_requests}} WHERE user_id=? AND status='pending'",[$emp['id']]),'Không còn yêu cầu chờ');
 throw new RuntimeException('ROLLBACK');});}catch(RuntimeException $e){if($e->getMessage()!=='ROLLBACK'){echo $e->getMessage(),"\n";exit(1);}}
echo "OK $n kiểm tra\n";
