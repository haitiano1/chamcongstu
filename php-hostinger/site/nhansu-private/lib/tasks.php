<?php
declare(strict_types=1);
// Việc / Nhắc nhở: Admin và Quản lý giao; người nhận xác nhận Đã xem hoặc Đã xong (ảnh bằng chứng tùy chọn).
const TASK_KINDS=['task','notice'];
function task_uuid(): string {
 $h=bin2hex(random_bytes(16));$h[12]='4';$h[16]=dechex(8|hexdec($h[16])&3);
 return substr($h,0,8).'-'.substr($h,8,4).'-'.substr($h,12,4).'-'.substr($h,16,4).'-'.substr($h,20);
}
function task_text(mixed $value,string $label,int $min,int $max): string {
 if(!is_string($value)||!preg_match('//u',$value))fail($label.' không hợp lệ.');
 $value=trim($value);$length=text_length($value);
 if($length<$min||$length>$max)fail($min?$label.' cần từ '.$min.'–'.$max.' ký tự.':$label.' tối đa '.$max.' ký tự.');
 return $value;
}
function task_due(mixed $value,string $kind): ?int {
 if($value===null||$value===''||$kind==='notice')return null;
 if(!is_int($value))fail('Hạn hoàn thành không hợp lệ.');
 if($value<now_ms()-300000)fail('Hạn hoàn thành đã qua. Chọn thời điểm sau hiện tại.');
 if($value>now_ms()+366*86400000)fail('Hạn hoàn thành tối đa trong 1 năm.');
 return $value;
}
function task_photo(mixed $value): ?string {
 if($value===null||$value==='')return null;
 if(!is_string($value)||strlen($value)>350000||!preg_match('~^data:image/jpeg;base64,([A-Za-z0-9+/]+={0,2})$~D',$value,$m))fail('Ảnh bằng chứng cần là JPEG dưới 250 KB.');
 $bytes=base64_decode($m[1],true);if($bytes===false||strlen($bytes)>250000)fail('Ảnh bằng chứng quá lớn hoặc không hợp lệ.');
 $size=@getimagesizefromstring($bytes);if(!$size||$size[2]!==IMAGETYPE_JPEG||min($size[0],$size[1])<120||max($size[0],$size[1])>1280||$size[0]*$size[1]>2000000)fail('Ảnh bằng chứng không hợp lệ. Hãy chọn hoặc chụp lại.');
 $image=@imagecreatefromstring($bytes);if(!$image)fail('Không đọc được ảnh bằng chứng.');
 ob_start();imagejpeg($image,null,80);$clean=ob_get_clean();imagedestroy($image);return $clean;
}
function task_row(App $a,string $id): array {
 $t=$a->one('SELECT * FROM {{tasks}} WHERE id=?',[entry_uuid($id)]);if(!$t)fail('Không tìm thấy việc.',404);return $t;
}
function task_mine(App $a,array $t): array {
 $r=$a->one('SELECT * FROM {{task_recipients}} WHERE task_id=? AND user_id=?',[$t['id'],$a->user['id']]);if(!$r)fail('Việc này không giao cho bạn.',403);
 if($t['status']!=='open')fail('Việc đã bị hủy.',409);return $r;
}
function task_audit(App $a,string $id,string $action,?array $before,?array $after,string $note=''): void {
 $a->run('INSERT INTO {{task_audit}}(task_id,actor_id,action,before_json,after_json,note,time) VALUES(?,?,?,?,?,?,?)',[$id,$a->user['id'],$action,$before===null?null:json_encode($before,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),$after===null?null:json_encode($after,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),$note,now_ms()]);
}
function task_list_sql(): string {
 return "SELECT t.id,t.kind,t.title,t.body,t.due_at,t.require_photo,t.audience,t.status,t.cancel_reason,t.created_by,t.created_at,t.updated_at,t.version,(SELECT name FROM {{users}} WHERE id=t.created_by) creator_name,
 (SELECT COUNT(*) FROM {{task_recipients}} WHERE task_id=t.id) total,(SELECT COUNT(*) FROM {{task_recipients}} WHERE task_id=t.id AND seen_at IS NOT NULL) seen,(SELECT COUNT(*) FROM {{task_recipients}} WHERE task_id=t.id AND done_at IS NOT NULL) done,
 my.seen_at my_seen_at,my.done_at my_done_at,my.done_note my_done_note,(my.proof_jpeg IS NOT NULL) my_has_proof,(my.user_id IS NOT NULL) mine
 FROM {{tasks}} t ";
}
function task_pending(App $a): int {
 return (int)$a->scalar("SELECT COUNT(*) FROM {{task_recipients}} r JOIN {{tasks}} t ON t.id=r.task_id WHERE r.user_id=? AND t.status='open' AND (t.kind='notice' AND r.seen_at IS NULL OR t.kind='task' AND r.done_at IS NULL)",[$a->user['id']]);
}
function task_routes(App $a,string $path,array $b,string $method): ?array {
 if($path!=='/tasks'&&!str_starts_with($path,'/tasks/'))return null;
 $manager=$a->user['role']!=='employee';$me=$a->user['id'];
 if($path==='/tasks/summary'&&$method==='GET')return [200,['pending'=>task_pending($a)]];
 if($path==='/tasks'&&$method==='GET'){
  $rows=$manager?$a->all(task_list_sql().'LEFT JOIN {{task_recipients}} my ON my.task_id=t.id AND my.user_id=? ORDER BY t.created_at DESC LIMIT 300',[$me]):$a->all(task_list_sql().'JOIN {{task_recipients}} my ON my.task_id=t.id AND my.user_id=? ORDER BY t.created_at DESC LIMIT 300',[$me]);
  foreach($rows as &$r){$r['mine']=(bool)$r['mine'];$r['my_has_proof']=(bool)$r['my_has_proof'];if(!$manager)unset($r['seen'],$r['done']);}unset($r);
  return [200,['rows'=>$rows,'pending'=>task_pending($a),'can_manage'=>$manager,'people'=>$manager?array_map(fn($p)=>['id'=>$p['id'],'name'=>$p['name'],'role'=>$p['role']],finance_people($a)):[]]];
 }
 if($path==='/tasks'&&$method==='POST'){
  $a->requireManager();
  $kind=$b['kind']??'';if(!in_array($kind,TASK_KINDS,true))fail('Chọn loại: Task hoặc Nhắc nhở.');
  $title=task_text($b['title']??null,'Tiêu đề',3,150);$body=task_text($b['body']??'','Nội dung',0,2000);$due=task_due($b['due_at']??null,$kind);
  $photo=$kind==='task'&&($b['require_photo']??false)===true?1:0;
  $audience=$b['audience']??'';if(!in_array($audience,['all','people'],true))fail('Chọn người nhận.');
  $active=array_column($a->all("SELECT id FROM {{users}} WHERE archived=0 AND status!='inactive'"),'id');
  if($audience==='all')$ids=array_values(array_filter($active,fn($id)=>$id!==$me));
  else{$ids=$b['user_ids']??null;if(!is_array($ids)||!$ids||count($ids)>200)fail('Chọn ít nhất một người nhận.');foreach($ids as $id)if(!is_int($id)||!in_array($id,$active,true))fail('Người nhận không tồn tại hoặc đã nghỉ.');$ids=array_values(array_unique($ids));}
  if(!$ids)fail('Chưa có nhân viên nào để giao việc.');
  $id=task_uuid();$now=now_ms();
  $a->run("INSERT INTO {{tasks}}(id,kind,title,body,due_at,require_photo,audience,status,cancel_reason,created_by,created_at,updated_by,updated_at) VALUES(?,?,?,?,?,?,?,'open','',?,?,?,?)",[$id,$kind,$title,$body,$due,$photo,$audience,$me,$now,$me,$now]);
  foreach($ids as $uid)$a->run("INSERT INTO {{task_recipients}}(task_id,user_id,done_note,notify_state) VALUES(?,?,'','pending')",[$id,$uid]);
  task_audit($a,$id,'created',null,['kind'=>$kind,'title'=>$title,'body'=>$body,'due_at'=>$due,'require_photo'=>(bool)$photo,'audience'=>$audience,'recipients'=>$ids]);
  return [201,['id'=>$id,'recipients'=>count($ids)]];
 }
 if(preg_match('~^/tasks/([a-f0-9-]{36})$~D',$path,$m)&&$method==='GET'){
  $t=task_row($a,$m[1]);$mine=$a->one('SELECT 1 FROM {{task_recipients}} WHERE task_id=? AND user_id=?',[$t['id'],$me]);
  if(!$manager&&!$mine)fail('Bạn không có quyền xem việc này.',403);
  $t['creator_name']=$a->scalar('SELECT name FROM {{users}} WHERE id=?',[$t['created_by']]);
  $recipients=$a->all('SELECT r.user_id,u.name,r.seen_at,r.done_at,r.done_note,(r.proof_jpeg IS NOT NULL) has_proof,r.notify_state FROM {{task_recipients}} r JOIN {{users}} u ON u.id=r.user_id WHERE r.task_id=?'.($manager?'':' AND r.user_id=?').' ORDER BY u.name',$manager?[$t['id']]:[$t['id'],$me]);
  foreach($recipients as &$r)$r['has_proof']=(bool)$r['has_proof'];unset($r);
  $audit=$manager?$a->all('SELECT x.action,x.before_json,x.after_json,x.note,x.time,u.name actor_name FROM {{task_audit}} x JOIN {{users}} u ON u.id=x.actor_id WHERE x.task_id=? ORDER BY x.id',[$t['id']]):[];
  return [200,['task'=>$t,'recipients'=>$recipients,'audit'=>$audit,'can_manage'=>$manager]];
 }
 if(preg_match('~^/tasks/([a-f0-9-]{36})/photo/(\d+)$~D',$path,$m)&&$method==='GET'){
  $uid=(int)$m[2];if(!$manager&&$uid!==$me)fail('Bạn không có quyền xem ảnh này.',403);
  $photo=$a->scalar('SELECT proof_jpeg FROM {{task_recipients}} WHERE task_id=? AND user_id=?',[entry_uuid($m[1]),$uid]);if(!$photo)fail('Không có ảnh bằng chứng.',404);
  header('Content-Type: image/jpeg');header('Content-Disposition: inline');echo $photo;exit;
 }
 if(!preg_match('~^/tasks/([a-f0-9-]{36})/(seen|done|undo|edit|cancel)$~D',$path,$m)||$method!=='POST')fail('Không tìm thấy chức năng Việc.',404);
 $t=task_row($a,$m[1]);$action=$m[2];
 if($action==='seen'){$r=task_mine($a,$t);if($r['seen_at']===null)$a->run('UPDATE {{task_recipients}} SET seen_at=? WHERE task_id=? AND user_id=?',[now_ms(),$t['id'],$me]);return [200,['ok'=>true,'pending'=>task_pending($a)]];}
 if($action==='done'){
  $r=task_mine($a,$t);if($t['kind']!=='task')fail('Nhắc nhở chỉ cần bấm Đã xem.');if($r['done_at']!==null)fail('Bạn đã báo xong việc này.',409);
  $note=task_text($b['note']??'','Ghi chú',0,500);$photo=task_photo($b['photo']??null);if($t['require_photo']&&$photo===null)fail('Việc này bắt buộc chụp ảnh bằng chứng bằng camera.');$now=now_ms();
  $a->run('UPDATE {{task_recipients}} SET seen_at=COALESCE(seen_at,?),done_at=?,done_note=?,proof_jpeg=? WHERE task_id=? AND user_id=?',[$now,$now,$note,$photo,$t['id'],$me]);
  task_audit($a,$t['id'],'done',null,['note'=>$note,'photo'=>$photo!==null]);
  return [200,['ok'=>true,'pending'=>task_pending($a)]];
 }
 if($action==='undo'){
  $r=task_mine($a,$t);if($r['done_at']===null)fail('Việc chưa được báo xong.',409);
  $a->run("UPDATE {{task_recipients}} SET done_at=NULL,done_note='',proof_jpeg=NULL WHERE task_id=? AND user_id=?",[$t['id'],$me]);
  task_audit($a,$t['id'],'undone',['note'=>$r['done_note'],'photo'=>$r['proof_jpeg']!==null],null);
  return [200,['ok'=>true,'pending'=>task_pending($a)]];
 }
 $a->requireManager();entry_version($b,$t);if($t['status']!=='open')fail('Việc đã bị hủy.',409);
 if($action==='edit'){
  $title=task_text($b['title']??null,'Tiêu đề',3,150);$body=task_text($b['body']??'','Nội dung',0,2000);
  $due=($b['due_at']??null)===$t['due_at']?$t['due_at']:task_due($b['due_at']??null,$t['kind']);
  $before=['title'=>$t['title'],'body'=>$t['body'],'due_at'=>$t['due_at']];$after=['title'=>$title,'body'=>$body,'due_at'=>$due];
  if($before===$after)fail('Chưa có thay đổi nào.');
  $a->run('UPDATE {{tasks}} SET title=?,body=?,due_at=?,updated_by=?,updated_at=?,version=version+1 WHERE id=?',[$title,$body,$due,$me,now_ms(),$t['id']]);
  task_audit($a,$t['id'],'edited',$before,$after);return [200,['ok'=>true]];
 }
 $reason=task_text($b['reason']??null,'Lý do hủy',3,500);
 $a->run("UPDATE {{tasks}} SET status='cancelled',cancel_reason=?,updated_by=?,updated_at=?,version=version+1 WHERE id=?",[$reason,$me,now_ms(),$t['id']]);
 $a->run("UPDATE {{task_recipients}} SET notify_state='skipped' WHERE task_id=? AND notify_state='pending'",[$t['id']]);
 task_audit($a,$t['id'],'cancelled',['status'=>'open'],['status'=>'cancelled'],$reason);
 return [200,['ok'=>true]];
}
// Cron mỗi phút: ping Telegram cho người nhận việc mới. Không có token/liên kết thì bỏ qua, không gửi dồn việc cũ.
function task_telegram_tick(App $a,?int $clock=null): void {
 $now=$clock??now_ms();$tg=new Telegram($a);
 $a->run("UPDATE {{task_recipients}} r JOIN {{tasks}} t ON t.id=r.task_id SET r.notify_state='skipped' WHERE r.notify_state='pending' AND (t.status!='open' OR t.created_at<?)",[$now-86400000]);
 if(!$tg->configured())return;
 $a->run("UPDATE {{task_recipients}} r LEFT JOIN {{telegram_links}} l ON l.user_id=r.user_id SET r.notify_state='nolink' WHERE r.notify_state='pending' AND l.user_id IS NULL");
 $rows=$a->all("SELECT r.task_id,r.user_id,l.chat_id,t.kind,t.title,t.body,t.due_at,t.require_photo,(SELECT name FROM {{users}} WHERE id=t.created_by) creator_name FROM {{task_recipients}} r JOIN {{tasks}} t ON t.id=r.task_id JOIN {{telegram_links}} l ON l.user_id=r.user_id WHERE r.notify_state='pending' ORDER BY t.created_at LIMIT 25");
 foreach($rows as $r){
  if(!$a->run("UPDATE {{task_recipients}} SET notify_state='sending' WHERE task_id=? AND user_id=? AND notify_state='pending'",[$r['task_id'],$r['user_id']])->rowCount())continue;
  $body=$r['body']!==''?"\n\n".(text_length($r['body'])>500?mb_substr($r['body'],0,500).'…':$r['body']):'';
  $text='Anh Gấu Studio · '.($r['kind']==='task'?'Việc mới':'Nhắc nhở')."\n".$r['title'].($r['due_at']?"\nHạn: ".date('H:i d/m/Y',intdiv($r['due_at'],1000)):'').($r['require_photo']?"\nCần chụp ảnh khi báo xong":'')."\nTừ: ".$r['creator_name'].$body;
  try{
   $tg->call('sendMessage',['chat_id'=>$r['chat_id'],'text'=>$text,'disable_web_page_preview'=>true,'reply_markup'=>['inline_keyboard'=>[[['text'=>'Mở mục Việc','url'=>$a->config['app_url'].'/#tasks']]]]]);
   $a->run("UPDATE {{task_recipients}} SET notify_state='sent',notified_at=?,notify_error=NULL WHERE task_id=? AND user_id=?",[$now,$r['task_id'],$r['user_id']]);
  }catch(Throwable $e){$a->run("UPDATE {{task_recipients}} SET notify_state='failed',notify_error=? WHERE task_id=? AND user_id=?",[$e->getMessage(),$r['task_id'],$r['user_id']]);}
 }
}
