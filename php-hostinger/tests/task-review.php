<?php
// Task cần duyệt (03/11/2026): báo xong → Chờ duyệt (Telegram Admin/Quản lý kèm ảnh); Duyệt → Đã xong; Chưa duyệt → lý do, làm lại.
// Chạy trong giao dịch rồi hoàn tác; Telegram giả lập.
require_once __DIR__.'/../site/nhansu-private/lib/bootstrap.php';require_once __DIR__.'/../site/nhansu-private/lib/people-finance.php';require_once __DIR__.'/../site/nhansu-private/lib/bookings.php';require_once __DIR__.'/../site/nhansu-private/lib/tasks.php';require_once __DIR__.'/../site/nhansu-private/lib/telegram.php';
$a=new App('/var/nhansu-private');$n=0;function verify($v,$message){global $n;if(!$v)throw new RuntimeException('FAIL: '.$message);$n++;}
try{$a->tx(function()use($a){
 $a->run('DELETE FROM {{telegram_links}}');$a->config['telegram_token']='12345:'.str_repeat('x',30);
 $sent=[];support_telegram(new Telegram($a,function($method,$b)use(&$sent){$sent[]=['method'=>$method]+$b;return ['ok'=>true,'result'=>['message_id'=>1]];}));
 $mk=function(string $name,string $role)use($a){$a->run("INSERT INTO {{users}}(name,email,password,role,employee_code,notes) VALUES(?,?,'x',?,?,'')",[$name,strtolower(str_replace(' ','.',$name)).'@review.test',$role,strtoupper(substr(md5($name),0,8))]);return $a->one('SELECT * FROM {{users}} WHERE id=LAST_INSERT_ID()');};
 $staff=$mk('Review Staff','employee');$manager=$mk('Review Manager','manager');
 $admins=array_map('intval',array_column($a->all("SELECT id FROM {{users}} WHERE role IN ('admin','manager') AND archived=0 AND status!='inactive'"),'id'));
 foreach([...$admins,(int)$staff['id']] as $uid)$a->run('INSERT INTO {{telegram_links}}(user_id,chat_id,linked_at) VALUES(?,?,?)',[$uid,'chat-'.$uid,now_ms()]);
 $as=function(array $u)use($a){$a->user=$u;};$call=fn(string $path,array $b=[],string $m='POST')=>task_routes($a,$path,$b,$m);
 $fail=function(callable $fn,string $msg){try{$fn();verify(false,$msg.' phải bị chặn');}catch(HttpError $e){verify(true,$msg);return $e->getMessage();}return '';};
 $row=function(string $id)use($a,$call){foreach($call('/tasks',[],'GET')[1]['rows'] as $r)if($r['id']===$id)return $r;return null;};
 $img=imagecreatetruecolor(300,200);ob_start();imagejpeg($img,null,80);$jpg='data:image/jpeg;base64,'.base64_encode(ob_get_clean());
 $due=day_time(local_day(now_ms()))+23*3600000;
 // Giao Task cần duyệt, cần 1 ảnh, hạn hôm nay.
 $as($manager);$t=task_create($a,(int)$manager['id'],['kind'=>'task','title'=>'Dọn phòng chụp 2','body'=>'','due_at'=>$due,'require_photo'=>1,'require_review'=>true,'audience'=>'people','user_ids'=>[(int)$staff['id']]]);$id=$t['id'];
 verify($t['require_review']===1,'Tạo Task cần duyệt');$fail(fn()=>task_create($a,(int)$manager['id'],['kind'=>'task','title'=>'Sai kiểu','require_review'=>'yes','audience'=>'all']),'Ô cần duyệt sai kiểu');
 $review0=task_review_pending($a);
 // Nhân viên báo xong → Chờ duyệt; không còn khóa chấm ra; Telegram báo Admin/Quản lý kèm ảnh.
 $as($staff);verify(count(task_due_today($a,(int)$staff['id']))>=1,'Trước khi báo xong: việc hạn hôm nay khóa chấm ra');
 $sent=[];$res=$call("/tasks/$id/done",['note'=>'Đã dọn xong','photos'=>[$jpg]])[1];
 verify($res['review']===true&&$res['notified']===count($admins),'Báo xong: Telegram '.count($admins).' Admin/Quản lý; được '.$res['notified']);
 verify(count(array_filter($sent,fn($x)=>$x['method']==='sendPhoto'&&($x['photo_bytes']??0)>0))===count($admins)&&str_contains($sent[0]['caption'],'chờ duyệt')&&str_contains($sent[0]['caption'],'Dọn phòng chụp 2'),'Tin Telegram là ảnh bằng chứng kèm chú thích');
 verify(!in_array('chat-'.$staff['id'],array_column($sent,'chat_id'),true),'Không báo lại cho người làm');
 $r=$row($id);verify($r['my_review_state']==='pending'&&$r['my_done_at']!==null,'Nhân viên thấy Chờ duyệt');
 verify(!array_filter(task_due_today($a,(int)$staff['id']),fn($x)=>$x['id']===$id),'Đang chờ duyệt thì không khóa chấm ra');
 $as($manager);$r=$row($id);verify((int)$r['done']===0&&(int)$r['reviewing']===1,'Quản lý: 0 xong, 1 chờ duyệt');
 verify(task_review_pending($a)===$review0+1&&count(array_filter($call('/tasks',[],'GET')[1]['reviews'],fn($x)=>$x['task_id']===$id&&$x['proof_count']===1))===1,'Khung Việc chờ duyệt có lượt này kèm 1 ảnh');
 // Nhân viên không tự duyệt; Chưa duyệt phải có lý do.
 $as($staff);$fail(fn()=>$call("/tasks/$id/review",['user_id'=>(int)$staff['id'],'decision'=>'approve']),'Nhân viên không duyệt');
 $as($manager);$fail(fn()=>$call("/tasks/$id/review",['user_id'=>(int)$staff['id'],'decision'=>'reject','note'=>'']),'Chưa duyệt không lý do');
 $sent=[];$call("/tasks/$id/review",['user_id'=>(int)$staff['id'],'decision'=>'reject','note'=>'Góc trái còn bừa, dọn lại']);
 verify(array_column($sent,'chat_id')===['chat-'.$staff['id']]&&str_contains($sent[0]['text'],'chưa được duyệt')&&str_contains($sent[0]['text'],'Góc trái còn bừa'),'Chưa duyệt: Telegram báo nhân viên kèm lý do');
 $fail(fn()=>$call("/tasks/$id/review",['user_id'=>(int)$staff['id'],'decision'=>'approve']),'Lượt đã xử lý không duyệt lại');
 $as($staff);$r=$row($id);verify($r['my_done_at']===null&&$r['my_review_state']==='rejected'&&$r['my_review_note']==='Góc trái còn bừa, dọn lại'&&$r['my_reviewer_name']==='Review Manager','Nhân viên thấy lý do Chưa duyệt, việc quay lại Cần làm');
 verify(task_pending($a)>=1&&array_filter(task_due_today($a,(int)$staff['id']),fn($x)=>$x['id']===$id),'Chưa duyệt: lại tính việc cần làm và khóa chấm ra');
 // Làm lại → Chờ duyệt → Duyệt → Đã xong; đã duyệt không hoàn tác.
 $call("/tasks/$id/done",['note'=>'Đã dọn lại','photos'=>[$jpg]]);
 $as($manager);$sent=[];$call("/tasks/$id/review",['user_id'=>(int)$staff['id'],'decision'=>'approve']);
 verify(str_contains($sent[0]['text']??'','đã được duyệt'),'Duyệt: Telegram báo nhân viên');$r=$row($id);verify((int)$r['done']===1&&(int)$r['reviewing']===0,'Duyệt xong: 1/1 xong');verify(task_review_pending($a)===$review0,'Hết chờ duyệt');
 $as($staff);$fail(fn()=>$call("/tasks/$id/undo"),'Đã duyệt không hoàn tác');
 $audit=array_column($a->all('SELECT action FROM {{task_audit}} WHERE task_id=? ORDER BY id',[$id]),'action');verify($audit===['created','submitted','review_rejected','submitted','review_approved'],'Lịch sử: '.implode(',',$audit));
 // Đang chờ duyệt thì nhân viên hoàn tác được.
 $as($manager);$t2=task_create($a,(int)$manager['id'],['kind'=>'task','title'=>'Kiểm tra đèn','require_review'=>true,'audience'=>'people','user_ids'=>[(int)$staff['id']]]);
 $as($staff);$call('/tasks/'.$t2['id'].'/done',['note'=>'']);$call('/tasks/'.$t2['id'].'/undo');$r=$row($t2['id']);verify($r['my_done_at']===null&&$r['my_review_state']===null,'Hoàn tác khi chờ duyệt');
 // Bỏ tick cần duyệt khi đang chờ → coi như Đã xong.
 $call('/tasks/'.$t2['id'].'/done',['note'=>'']);$as($manager);$v=(int)$a->scalar('SELECT version FROM {{tasks}} WHERE id=?',[$t2['id']]);
 $call('/tasks/'.$t2['id'].'/edit',['version'=>$v,'title'=>'Kiểm tra đèn','body'=>'','due_at'=>null,'require_review'=>false]);$r=$row($t2['id']);verify((int)$r['done']===1&&(int)$r['require_review']===0,'Bỏ tick cần duyệt: lượt chờ duyệt thành Đã xong');
 // Task không cần duyệt: báo xong là Đã xong, không nhắn Telegram.
 $t3=task_create($a,(int)$manager['id'],['kind'=>'task','title'=>'Tưới cây','audience'=>'people','user_ids'=>[(int)$staff['id']]]);
 $as($staff);$sent=[];$res=$call('/tasks/'.$t3['id'].'/done',['note'=>''])[1];verify($res['review']===false&&!$sent,'Không cần duyệt: không Telegram');
 $as($manager);$r=$row($t3['id']);verify((int)$r['done']===1&&(int)$r['reviewing']===0,'Không cần duyệt: Đã xong ngay');
 throw new RuntimeException('ROLLBACK');
});}catch(RuntimeException $e){if($e->getMessage()!=='ROLLBACK')throw $e;}
echo "PASS $n task review checks: submit → pending (Telegram photo), reject with reason, redo, approve, undo rules, toggle off\n";
