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
// require_photo = số ảnh bắt buộc (0 = không cần). Ảnh 1 nằm ở task_recipients.proof_jpeg, ảnh 2 trở đi ở task_proofs.
const TASK_MAX_PHOTOS=10;
function task_photo_count(mixed $value): int {
 if(is_bool($value))return $value?1:0;
 if(!is_int($value)||$value<0||$value>TASK_MAX_PHOTOS)fail('Số ảnh bắt buộc cần từ 0 đến '.TASK_MAX_PHOTOS.'.');
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
function task_audit(App $a,string $id,string $action,?array $before,?array $after,string $note='',?int $actor=null): void {
 $a->run('INSERT INTO {{task_audit}}(task_id,actor_id,action,before_json,after_json,note,time) VALUES(?,?,?,?,?,?,?)',[$id,$actor??$a->user['id'],$action,$before===null?null:json_encode($before,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),$after===null?null:json_encode($after,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),$note,now_ms()]);
}
// Tạo việc dùng chung cho web và lệnh Telegram; $me là người giao (Admin/Quản lý).
function task_create(App $a,int $me,array $b,string $note=''): array {
  $kind=$b['kind']??'';if(!in_array($kind,TASK_KINDS,true))fail('Chọn loại: Task hoặc Nhắc nhở.');
  $title=task_text($b['title']??null,'Tiêu đề',3,150);$body=task_text($b['body']??'','Nội dung',0,2000);$due=task_due($b['due_at']??null,$kind);
  $photo=$kind==='task'?task_photo_count($b['require_photo']??false):0;
  $audience=$b['audience']??'';if(!in_array($audience,['all','people'],true))fail('Chọn người nhận.');
  $active=array_column($a->all("SELECT id FROM {{users}} WHERE archived=0 AND status!='inactive'"),'id');
  if($audience==='all')$ids=array_values(array_filter($active,fn($id)=>$id!==$me));
  else{$ids=$b['user_ids']??null;if(!is_array($ids)||!$ids||count($ids)>200)fail('Chọn ít nhất một người nhận.');foreach($ids as $id)if(!is_int($id)||!in_array($id,$active,true))fail('Người nhận không tồn tại hoặc đã nghỉ.');$ids=array_values(array_unique($ids));}
  if(!$ids)fail('Chưa có nhân viên nào để giao việc.');
  $id=task_uuid();$now=now_ms();
  $a->run("INSERT INTO {{tasks}}(id,kind,title,body,due_at,require_photo,audience,status,cancel_reason,created_by,created_at,updated_by,updated_at) VALUES(?,?,?,?,?,?,?,'open','',?,?,?,?)",[$id,$kind,$title,$body,$due,$photo,$audience,$me,$now,$me,$now]);
  foreach($ids as $uid)$a->run("INSERT INTO {{task_recipients}}(task_id,user_id,done_note,notify_state) VALUES(?,?,'','pending')",[$id,$uid]);
  task_audit($a,$id,'created',null,['kind'=>$kind,'title'=>$title,'body'=>$body,'due_at'=>$due,'require_photo'=>$photo,'audience'=>$audience,'recipients'=>$ids],$note,$me);
  return ['id'=>$id,'recipients'=>$ids,'kind'=>$kind,'title'=>$title,'due_at'=>$due,'require_photo'=>$photo,'audience'=>$audience];
}
function task_list_sql(): string {
 return "SELECT t.template_id,t.task_day,t.id,t.kind,t.title,t.body,t.due_at,t.require_photo,t.audience,t.status,t.cancel_reason,t.created_by,t.created_at,t.updated_at,t.version,(SELECT name FROM {{users}} WHERE id=t.created_by) creator_name,
 (SELECT COUNT(*) FROM {{task_recipients}} WHERE task_id=t.id) total,(SELECT COUNT(*) FROM {{task_recipients}} WHERE task_id=t.id AND seen_at IS NOT NULL) seen,(SELECT COUNT(*) FROM {{task_recipients}} WHERE task_id=t.id AND done_at IS NOT NULL) done,
 my.seen_at my_seen_at,my.done_at my_done_at,my.done_note my_done_note,(my.proof_jpeg IS NOT NULL) my_has_proof,(my.proof_jpeg IS NOT NULL)+(SELECT COUNT(*) FROM {{task_proofs}} p WHERE p.task_id=t.id AND p.user_id=my.user_id) my_proof_count,(my.user_id IS NOT NULL) mine
 FROM {{tasks}} t ";
}
function task_pending(App $a): int {
 return (int)$a->scalar("SELECT COUNT(*) FROM {{task_recipients}} r JOIN {{tasks}} t ON t.id=r.task_id WHERE r.user_id=? AND t.status='open' AND (t.task_day IS NULL OR t.task_day=?) AND (t.kind='notice' AND r.seen_at IS NULL OR t.kind='task' AND r.done_at IS NULL)",[$a->user['id'],local_day(now_ms())]);
}
function task_routes(App $a,string $path,array $b,string $method): ?array {
 if($path!=='/tasks'&&!str_starts_with($path,'/tasks/'))return null;
 $manager=$a->user['role']!=='employee';$me=$a->user['id'];
 daily_tasks_ensure($a,local_day(now_ms()));
 $r=daily_template_routes($a,$path,$b,$method);if($r!==null)return $r;
 if($path==='/tasks/summary'&&$method==='GET')return [200,['pending'=>task_pending($a)]];
 if($path==='/tasks'&&$method==='GET'){
  $rows=$manager?$a->all(task_list_sql().'LEFT JOIN {{task_recipients}} my ON my.task_id=t.id AND my.user_id=? ORDER BY t.created_at DESC LIMIT 300',[$me]):$a->all(task_list_sql().'JOIN {{task_recipients}} my ON my.task_id=t.id AND my.user_id=? ORDER BY t.created_at DESC LIMIT 300',[$me]);
  foreach($rows as &$r){$r['mine']=(bool)$r['mine'];$r['my_has_proof']=(bool)$r['my_has_proof'];if(!$manager)unset($r['seen'],$r['done']);}unset($r);
  return [200,['rows'=>$rows,'pending'=>task_pending($a),'can_manage'=>$manager,'today'=>local_day(now_ms()),'templates'=>$manager?$a->all('SELECT d.id,d.user_id,d.title,d.require_photo,d.active,d.version,u.name FROM {{task_templates}} d JOIN {{users}} u ON u.id=d.user_id WHERE u.archived=0 ORDER BY u.name,d.created_at'):[],'people'=>$manager?array_map(fn($p)=>['id'=>$p['id'],'name'=>$p['name'],'role'=>$p['role']],finance_people($a)):[]]];
 }
 if($path==='/tasks'&&$method==='POST'){
  $a->requireManager();$r=task_create($a,$me,$b);
  return [201,['id'=>$r['id'],'recipients'=>count($r['recipients'])]];
 }
 if(preg_match('~^/tasks/([a-f0-9-]{36})$~D',$path,$m)&&$method==='GET'){
  $t=task_row($a,$m[1]);$mine=$a->one('SELECT 1 FROM {{task_recipients}} WHERE task_id=? AND user_id=?',[$t['id'],$me]);
  if(!$manager&&!$mine)fail('Bạn không có quyền xem việc này.',403);
  $t['creator_name']=$a->scalar('SELECT name FROM {{users}} WHERE id=?',[$t['created_by']]);
  $recipients=$a->all('SELECT r.user_id,u.name,r.seen_at,r.done_at,r.done_note,(r.proof_jpeg IS NOT NULL) has_proof,(r.proof_jpeg IS NOT NULL)+(SELECT COUNT(*) FROM {{task_proofs}} p WHERE p.task_id=r.task_id AND p.user_id=r.user_id) proof_count,r.notify_state FROM {{task_recipients}} r JOIN {{users}} u ON u.id=r.user_id WHERE r.task_id=?'.($manager?'':' AND r.user_id=?').' ORDER BY u.name',$manager?[$t['id']]:[$t['id'],$me]);
  foreach($recipients as &$r){$r['has_proof']=(bool)$r['has_proof'];$r['proof_count']=(int)$r['proof_count'];}unset($r);
  $audit=$manager?$a->all('SELECT x.action,x.before_json,x.after_json,x.note,x.time,u.name actor_name FROM {{task_audit}} x JOIN {{users}} u ON u.id=x.actor_id WHERE x.task_id=? ORDER BY x.id',[$t['id']]):[];
  return [200,['task'=>$t,'recipients'=>$recipients,'audit'=>$audit,'can_manage'=>$manager]];
 }
 if(preg_match('~^/tasks/([a-f0-9-]{36})/photo/(\d+)(?:/(\d+))?$~D',$path,$m)&&$method==='GET'){
  $uid=(int)$m[2];$n=(int)($m[3]??1);if(!$manager&&$uid!==$me)fail('Bạn không có quyền xem ảnh này.',403);
  $photo=$n<=1?$a->scalar('SELECT proof_jpeg FROM {{task_recipients}} WHERE task_id=? AND user_id=?',[entry_uuid($m[1]),$uid]):$a->scalar('SELECT photo_jpeg FROM {{task_proofs}} WHERE task_id=? AND user_id=? AND idx=?',[entry_uuid($m[1]),$uid,$n]);if(!$photo)fail('Không có ảnh bằng chứng.',404);
  header('Content-Type: image/jpeg');header('Content-Disposition: inline');echo $photo;exit;
 }
 if(!preg_match('~^/tasks/([a-f0-9-]{36})/(seen|done|undo|edit|cancel)$~D',$path,$m)||$method!=='POST')fail('Không tìm thấy chức năng Việc.',404);
 $t=task_row($a,$m[1]);$action=$m[2];
 if($action==='seen'){$r=task_mine($a,$t);if($r['seen_at']===null)$a->run('UPDATE {{task_recipients}} SET seen_at=? WHERE task_id=? AND user_id=?',[now_ms(),$t['id'],$me]);return [200,['ok'=>true,'pending'=>task_pending($a)]];}
 if($action==='done'){
  $r=task_mine($a,$t);if($t['kind']!=='task')fail('Nhắc nhở chỉ cần bấm Đã xem.');if($r['done_at']!==null)fail('Bạn đã báo xong việc này.',409);
  $note=task_text($b['note']??'','Ghi chú',0,500);
  // Bản cũ gửi một ảnh ở 'photo'; bản mới gửi danh sách 'photos'.
  $list=array_key_exists('photos',$b)?$b['photos']:(($b['photo']??null)!==null&&$b['photo']!==''?[$b['photo']]:[]);if(!is_array($list)||!array_is_list($list)||count($list)>TASK_MAX_PHOTOS)fail('Gửi tối đa '.TASK_MAX_PHOTOS.' ảnh bằng chứng.');
  $photos=array_values(array_filter(array_map('task_photo',$list),fn($x)=>$x!==null));$need=(int)$t['require_photo'];
  if(count($photos)<$need)fail($need>1?'Việc này cần chụp đủ '.$need.' ảnh bằng chứng (mới có '.count($photos).').':'Việc này bắt buộc chụp ảnh bằng chứng bằng camera.');$now=now_ms();
  $a->run('UPDATE {{task_recipients}} SET seen_at=COALESCE(seen_at,?),done_at=?,done_note=?,proof_jpeg=? WHERE task_id=? AND user_id=?',[$now,$now,$note,$photos[0]??null,$t['id'],$me]);
  $a->run('DELETE FROM {{task_proofs}} WHERE task_id=? AND user_id=?',[$t['id'],$me]);foreach(array_slice($photos,1) as $i=>$photo)$a->run('INSERT INTO {{task_proofs}} VALUES(?,?,?,?)',[$t['id'],$me,$i+2,$photo]);
  task_audit($a,$t['id'],'done',null,['note'=>$note,'photo'=>(bool)$photos,'photos'=>count($photos)]);
  return [200,['ok'=>true,'pending'=>task_pending($a)]];
 }
 if($action==='undo'){
  $r=task_mine($a,$t);if($r['done_at']===null)fail('Việc chưa được báo xong.',409);
  $a->run("UPDATE {{task_recipients}} SET done_at=NULL,done_note='',proof_jpeg=NULL WHERE task_id=? AND user_id=?",[$t['id'],$me]);$a->run('DELETE FROM {{task_proofs}} WHERE task_id=? AND user_id=?',[$t['id'],$me]);
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
// Việc hằng ngày: Admin/Quản lý cài mẫu cho từng người; mỗi ngày tự sinh một Task (khóa template_id+task_day nên không trùng).
// Ca 2 phải xong hết việc hằng ngày của hôm đó mới chấm ra được; ca 1 không ràng buộc.
const DAILY_TASK_SEED=[['cuong','Bấm bill xuất hóa đơn cho khách',0],['tin','Chụp dọn vệ sinh 7 khu vực',1],['tin','Hoàn thành check list mỗi khách trong ngày',0],['huy','Chụp hình khu vực trang thiết bị',1]];
function daily_tasks_seed(App $a): void {
 if($a->meta('daily_tasks_seeded'))return;$a->meta('daily_tasks_seeded','1');
 $admin=$a->scalar("SELECT id FROM {{users}} WHERE role='admin' AND archived=0 ORDER BY id LIMIT 1");if(!$admin)return;
 $people=$a->all("SELECT id,name FROM {{users}} WHERE archived=0 AND status!='inactive'");
 foreach(DAILY_TASK_SEED as [$key,$title,$photo]){
  // Chỉ gán khi tên gọi khớp đúng một người; trùng hoặc không có thì để Admin tự cài.
  $hits=array_values(array_filter($people,fn($p)=>(explode(' ',task_fold($p['name']))[substr_count(task_fold($p['name']),' ')]??'')===$key));
  if(count($hits)!==1)continue;$id=task_uuid();$now=now_ms();
  $a->run('INSERT INTO {{task_templates}}(id,user_id,title,require_photo,active,created_by,created_at,updated_by,updated_at) VALUES(?,?,?,?,1,?,?,?,?)',[$id,$hits[0]['id'],$title,$photo,$admin,$now,$admin,$now]);
  task_audit($a,$id,'template_created',null,['user_id'=>$hits[0]['id'],'title'=>$title,'require_photo'=>(bool)$photo],'Cài sẵn khi cập nhật',(int)$admin);
 }
}
function daily_tasks_ensure(App $a,string $day,?int $user=null): void {
 $a->tx(function()use($a,$day,$user){daily_tasks_seed($a);
  $rows=$a->all("SELECT d.* FROM {{task_templates}} d JOIN {{users}} u ON u.id=d.user_id WHERE d.active=1 AND u.archived=0 AND u.status!='inactive'".($user!==null?' AND d.user_id=?':'').' AND NOT EXISTS(SELECT 1 FROM {{tasks}} t WHERE t.template_id=d.id AND t.task_day=?)',[...($user!==null?[$user]:[]),$day]);
  foreach($rows as $d){$id=task_uuid();$now=now_ms();
   if(!$a->run("INSERT IGNORE INTO {{tasks}}(id,kind,title,body,due_at,require_photo,audience,status,cancel_reason,created_by,created_at,updated_by,updated_at,template_id,task_day) VALUES(?,'task',?,'',NULL,?,'people','open','',?,?,?,?,?,?)",[$id,$d['title'],$d['require_photo'],$d['created_by'],$now,$d['created_by'],$now,$d['id'],$day])->rowCount())continue;
   $a->run("INSERT INTO {{task_recipients}}(task_id,user_id,done_note,notify_state) VALUES(?,?,'','skipped')",[$id,$d['user_id']]);
  }
  return null;});
}
function daily_tasks_today(App $a,int $user): array {
 $day=local_day(now_ms());daily_tasks_ensure($a,$day,$user);
 return $a->all("SELECT t.id,t.title,t.require_photo,r.done_at FROM {{tasks}} t JOIN {{task_recipients}} r ON r.task_id=t.id AND r.user_id=? WHERE t.task_day=? AND t.status='open' ORDER BY t.created_at,t.title",[$user,$day]);
}
function daily_tasks_block_checkout(App $a,array $b): void {
 if(($b['type']??'')!=='out'||($b['shift']??'')!=='day_second')return;
 $left=array_filter(daily_tasks_today($a,$a->user['id']),fn($t)=>$t['done_at']===null);
 if($left)fail('Cần hoàn thành việc hằng ngày trước khi chấm ra ca 2: '.implode('; ',array_column($left,'title')).'. Vào mục Việc để báo xong.',409);
}
function daily_template_routes(App $a,string $path,array $b,string $method): ?array {
 if($path!=='/tasks/templates'&&!str_starts_with($path,'/tasks/templates/'))return null;
 $a->requireManager();if($method!=='POST')fail('Chỉ nhận POST.',405);$me=$a->user['id'];$today=local_day(now_ms());
 $fields=function()use($b){$title=task_text($b['title']??null,'Tên việc',3,150);if(!is_bool($b['require_photo']??null)&&!is_int($b['require_photo']??null))fail('Chọn số ảnh bắt buộc.');return ['title'=>$title,'require_photo'=>task_photo_count($b['require_photo'])];};
 if($path==='/tasks/templates'){
  $f=$fields();$uid=$b['user_id']??null;if(!is_int($uid)||!$a->one("SELECT id FROM {{users}} WHERE id=? AND archived=0 AND status!='inactive'",[$uid]))fail('Chọn nhân viên đang làm.');
  $id=task_uuid();$now=now_ms();$a->run('INSERT INTO {{task_templates}}(id,user_id,title,require_photo,active,created_by,created_at,updated_by,updated_at) VALUES(?,?,?,?,1,?,?,?,?)',[$id,$uid,$f['title'],$f['require_photo'],$me,$now,$me,$now]);
  task_audit($a,$id,'template_created',null,['user_id'=>$uid]+$f);daily_tasks_ensure($a,$today,$uid);return [201,['id'=>$id]];
 }
 if(!preg_match('~^/tasks/templates/([a-f0-9-]{36})/(edit|pause|resume)$~D',$path,$m))fail('Không tìm thấy chức năng Việc hằng ngày.',404);
 $d=$a->one('SELECT * FROM {{task_templates}} WHERE id=?',[entry_uuid($m[1])]);if(!$d)fail('Không tìm thấy việc hằng ngày.',404);entry_version($b,$d);
 $before=['title'=>$d['title'],'require_photo'=>(int)$d['require_photo'],'active'=>(int)$d['active']];
 // Việc hôm nay chưa xong thì đổi theo mẫu; việc đã xong và các ngày trước giữ nguyên.
 $todayTask=$a->one('SELECT t.id FROM {{tasks}} t JOIN {{task_recipients}} r ON r.task_id=t.id WHERE t.template_id=? AND t.task_day=? AND t.status=\'open\' AND r.done_at IS NULL',[$d['id'],$today]);
 if($m[2]==='edit'){$f=$fields();if($f===['title'=>$d['title'],'require_photo'=>(int)$d['require_photo']])fail('Chưa có thay đổi nào.');
  $a->run('UPDATE {{task_templates}} SET title=?,require_photo=?,updated_by=?,updated_at=?,version=version+1 WHERE id=?',[$f['title'],$f['require_photo'],$me,now_ms(),$d['id']]);
  if($todayTask)$a->run('UPDATE {{tasks}} SET title=?,require_photo=?,updated_by=?,updated_at=?,version=version+1 WHERE id=?',[$f['title'],$f['require_photo'],$me,now_ms(),$todayTask['id']]);
  task_audit($a,$d['id'],'template_edited',$before,$f+['active'=>(int)$d['active']]);return [200,['ok'=>true]];}
 $active=$m[2]==='resume'?1:0;if((int)$d['active']===$active)fail('Việc hằng ngày đã ở trạng thái này.',409);
 $a->run('UPDATE {{task_templates}} SET active=?,updated_by=?,updated_at=?,version=version+1 WHERE id=?',[$active,$me,now_ms(),$d['id']]);
 if(!$active&&$todayTask)$a->run("UPDATE {{tasks}} SET status='cancelled',cancel_reason='Ngừng việc hằng ngày',updated_by=?,updated_at=?,version=version+1 WHERE id=?",[$me,now_ms(),$todayTask['id']]);
 task_audit($a,$d['id'],$active?'template_resumed':'template_paused',['active'=>(int)$d['active']],['active'=>$active]);
 if($active){$a->run("UPDATE {{tasks}} SET status='open',cancel_reason='',updated_by=?,updated_at=?,version=version+1 WHERE template_id=? AND task_day=? AND status='cancelled'",[$me,now_ms(),$d['id'],$today]);daily_tasks_ensure($a,$today,(int)$d['user_id']);}return [200,['ok'=>true]];
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
  $text='Anh Gấu Studio · '.($r['kind']==='task'?'Việc mới':'Nhắc nhở')."\n".$r['title'].($r['due_at']?"\nHạn: ".date('H:i d/m/Y',intdiv($r['due_at'],1000)):'').($r['require_photo']?"\nCần chụp ".($r['require_photo']>1?$r['require_photo'].' ':'')."ảnh khi báo xong":'')."\nTừ: ".$r['creator_name'].$body;
  try{
   $tg->call('sendMessage',['chat_id'=>$r['chat_id'],'text'=>$text,'disable_web_page_preview'=>true]+$tg->button('Mở mục Việc',$a->config['app_url'].'/#tasks'));
   $a->run("UPDATE {{task_recipients}} SET notify_state='sent',notified_at=?,notify_error=NULL WHERE task_id=? AND user_id=?",[$now,$r['task_id'],$r['user_id']]);
  }catch(Throwable $e){$a->run("UPDATE {{task_recipients}} SET notify_state='failed',notify_error=? WHERE task_id=? AND user_id=?",[$e->getMessage(),$r['task_id'],$r['user_id']]);}
 }
}
// Lệnh Telegram trong nhóm hoặc chat riêng:
//   /task Huy, Cường | Dọn phòng chụp 2 | 18:00 28/10 #anh
//   /nhacnho all | Mai họp 8h30
// Chỉ Telegram đã liên kết với tài khoản Admin/Quản lý mới được giao.
const TASK_TG_HELP="Cách giao việc:\n/task Huy, Cường | Dọn phòng chụp 2 | 18:00\n/nhacnho all | Mai họp 8h30\n\n• Người nhận: tên gọi, tên đầy đủ, mã NV hoặc all\n• Phần hạn (không bắt buộc): 18:00 hoặc 18h30 28/10\n• Thêm #anh nếu bắt buộc chụp ảnh khi báo xong (#anh5 = 5 ảnh)\n\nGhi Event log: /log Nội dung | Huy (gõ /log để xem hướng dẫn)";
// Ghi Event log hôm nay; chỉ Admin, Quản lý và nhân viên được Admin cấp quyền ghi Event log:
//   /log Quên checkout ca 2 | Huy
//   /log Họp rút kinh nghiệm cả studio | all        (nhật ký chung, không trừ điểm)
//   /log Đi trễ 40 phút | Huy, Cường | NG-01        (phần 3: mã luật hoặc điểm 20/100/300)
const LOG_TG_HELP="Cách ghi Event log:\n/log Quên checkout ca 2 | Huy\n/log Họp rút kinh nghiệm | all\n/log Đi trễ 40 phút | Huy, Cường | NG-01\n\n• Phần 1: nội dung sự việc (từ 5 ký tự)\n• Phần 2: người liên quan — tên gọi, tên đầy đủ, mã NV, nhiều người cách nhau dấu phẩy; all = nhật ký chung studio\n• Phần 3 (không bắt buộc): mã luật hoặc điểm KPI 20/100/300; nhật ký chung không trừ điểm\n• Ngày ghi nhận: hôm nay. Sửa hoặc xử lý tiếp trên web.";
function log_telegram_command(App $a,Telegram $tg,array $m,string $args): bool {
 $chat=(string)$m['chat']['id'];$reply=fn(string $msg)=>$tg->call('sendMessage',['chat_id'=>$chat,'text'=>$msg,'reply_to_message_id'=>$m['message_id'],'disable_web_page_preview'=>true]);
 $previous=$a->user;
 try{
  if($args===''){$reply(LOG_TG_HELP);return true;}
  $sender=$a->one("SELECT u.* FROM {{telegram_links}} l JOIN {{users}} u ON u.id=l.user_id WHERE l.chat_id=? AND u.archived=0 AND u.status!='inactive'",[(string)($m['from']['id']??'')]);
  if(!$sender)fail('Telegram của bạn chưa liên kết với tài khoản trên web. Vào web → Chấm công → Liên kết Telegram.');
  // log_routes kiểm tra quyền và ghi người tạo theo $a->user, giống khi ghi trên web.
  $a->user=$sender;
  if(!log_permissions($a)['can_record'])fail('Chỉ Admin, Quản lý và nhân viên được Admin cấp quyền mới được ghi Event log.');
  $parts=array_map('trim',explode('|',$args));
  if(count($parts)<2||$parts[0]===''||$parts[1]==='')fail("Thiếu nội dung hoặc người liên quan.\n\n".LOG_TG_HELP);
  if(count($parts)>3)fail('Dùng tối đa 3 phần: nội dung | người liên quan | luật hoặc điểm.');
  $people=$a->all("SELECT id,name,employee_code FROM {{users}} WHERE archived=0 AND status!='inactive'");
  $targets=array_values(array_filter(array_map('trim',preg_split('/\s*(?:,|;|\s+và\s+)\s*/u',$parts[1]))));
  $all=count($targets)===1&&in_array(task_fold(ltrim($targets[0],'@')),['all','tat ca','ca studio','moi nguoi','studio','chung'],true);
  $subjects=[];if(!$all)foreach($targets as $tk){$p=task_find_person($a,$people,$tk);$subjects[$p['id']]=$p;}
  $extra=['deduction'=>0];$rule=null;$kpi=$parts[2]??'';
  if($kpi!==''){
   if(preg_match('/^\d+$/D',$kpi)){if(!in_array((int)$kpi,[0,20,100,300],true))fail('Điểm KPI cần là 20, 100 hoặc 300.');$extra=['deduction'=>(int)$kpi];}
   else{$rule=$a->one("SELECT id,code,name,points FROM {{studio_rules}} WHERE code=? AND status='active'",[strtoupper($kpi)]);if(!$rule)fail('Không tìm thấy luật "'.$kpi.'" đang áp dụng. Xem mã trong Luật Studio trên web.');$extra=['rule_id'=>$rule['id']];}
  }
  $points=$rule?$rule['points']:$extra['deduction'];
  if($all&&$points!==0)fail('Nhật ký chung studio (all) không trừ điểm. Ghi tên người liên quan để áp '.($rule?'luật '.$rule['code']:'điểm KPI').'.');
  $day=local_day(now_ms());
  foreach($all?[null]:array_keys($subjects) as $uid){
   $id=uuid_v4();log_routes($a,'/event-log',['id'=>$id,'user_id'=>$uid,'event_date'=>$day,'category'=>'other','description'=>$parts[0]]+$extra,'POST');
   $a->run("UPDATE {{event_log_audit}} SET note=? WHERE event_log_id=? AND action='created'",['Ghi qua Telegram bởi người được cấp quyền chỉnh sửa Event log.',$id]);
  }
  $reply('✅ Đã ghi Event log · '.implode('/',array_reverse(explode('-',$day)))."\n".($all?'Nhật ký chung studio':'Người liên quan: '.implode(', ',array_column($subjects,'name')))."\n«".(mb_strlen($parts[0])>300?mb_substr($parts[0],0,300).'…':$parts[0]).'»'.($rule?"\nLuật ".$rule['code'].' · '.$rule['name']:'').($points?"\nTrừ ".$points.' điểm KPI'.(count($subjects)>1?' mỗi người':''):'')."\nXem trên web: ".$a->config['app_url'].'/#eventlog');
 }catch(HttpError $e){$reply('⚠️ '.$e->getMessage());}
 finally{$a->user=$previous;}
 return true;
}
function task_fold(string $s): string {
 $s=mb_strtolower(trim($s),'UTF-8');
 $map=['à'=>'a','á'=>'a','ả'=>'a','ã'=>'a','ạ'=>'a','ă'=>'a','ằ'=>'a','ắ'=>'a','ẳ'=>'a','ẵ'=>'a','ặ'=>'a','â'=>'a','ầ'=>'a','ấ'=>'a','ẩ'=>'a','ẫ'=>'a','ậ'=>'a','đ'=>'d','è'=>'e','é'=>'e','ẻ'=>'e','ẽ'=>'e','ẹ'=>'e','ê'=>'e','ề'=>'e','ế'=>'e','ể'=>'e','ễ'=>'e','ệ'=>'e','ì'=>'i','í'=>'i','ỉ'=>'i','ĩ'=>'i','ị'=>'i','ò'=>'o','ó'=>'o','ỏ'=>'o','õ'=>'o','ọ'=>'o','ô'=>'o','ồ'=>'o','ố'=>'o','ổ'=>'o','ỗ'=>'o','ộ'=>'o','ơ'=>'o','ờ'=>'o','ớ'=>'o','ở'=>'o','ỡ'=>'o','ợ'=>'o','ù'=>'u','ú'=>'u','ủ'=>'u','ũ'=>'u','ụ'=>'u','ư'=>'u','ừ'=>'u','ứ'=>'u','ử'=>'u','ữ'=>'u','ự'=>'u','ỳ'=>'y','ý'=>'y','ỷ'=>'y','ỹ'=>'y','ỵ'=>'y'];
 return preg_replace('/\s+/u',' ',strtr($s,$map));
}
// Tìm đúng một nhân viên đang làm theo tên gọi / tên đầy đủ / mã NV / tên trên Sheet.
function task_find_person(App $a,array $people,string $token): array {
 $t=task_fold(ltrim($token,'@'));if($t==='')fail('Thiếu tên người nhận.');
 $hits=[];
 foreach($people as $p){$name=task_fold($p['name']);$words=explode(' ',$name);if($name===$t||end($words)===$t||strtolower($p['employee_code'])===$t)$hits[$p['id']]=$p;}
 if(!$hits)foreach($a->all('SELECT alias_key,alias_label,user_id FROM {{booking_aliases}}') as $al)if(task_fold($al['alias_key'])===$t||task_fold($al['alias_label'])===$t)foreach($people as $p)if($p['id']===$al['user_id'])$hits[$p['id']]=$p;
 if(!$hits)foreach($people as $p)if(str_contains(' '.task_fold($p['name']).' ',' '.$t.' '))$hits[$p['id']]=$p;
 if(!$hits)fail('Không tìm thấy nhân viên "'.trim($token).'".');
 if(count($hits)>1)fail('"'.trim($token).'" trùng nhiều người: '.implode(', ',array_map(fn($p)=>$p['name'].' ('.$p['employee_code'].')',$hits)).'. Ghi rõ tên đầy đủ hoặc mã NV.');
 return array_values($hits)[0];
}
function task_parse_due(string $text,int $now): ?int {
 $text=trim($text);if($text==='')return null;
 if(!preg_match('~^(\d{1,2})(?:[:h](\d{2})?)?(?:\s+(\d{1,2})/(\d{1,2})(?:/(\d{4}))?)?$~u',$text,$m))fail('Hạn "'.$text.'" chưa đúng dạng. Ví dụ: 18:00 hoặc 18h30 28/10.');
 $hour=(int)$m[1];$minute=(int)($m[2]??0);if($hour>23||$minute>59)fail('Giờ hạn không hợp lệ.');
 $today=local_day($now);
 $year=!empty($m[5])?(int)$m[5]:(int)substr($today,0,4);
 $day=!empty($m[3])?sprintf('%04d-%02d-%02d',$year,(int)$m[4],(int)$m[3]):$today;
 valid_day($day);$due=day_time($day,0)+$hour*3600000+$minute*60000;
 // Chỉ ghi giờ mà giờ đó đã qua trong hôm nay: hiểu là ngày mai.
 if(empty($m[3])&&$due<$now)$due+=86400000;
 return $due;
}
function task_telegram_command(App $a,Telegram $tg,array $m): bool {
 $text=trim($m['text']??'');
 if(preg_match('~^/log(?:@\w+)?(?:\s+(.*))?$~su',$text,$cmd))return log_telegram_command($a,$tg,$m,trim($cmd[1]??''));
 if(!preg_match('~^/(task|nhacnho|viec)(?:@\w+)?(?:\s+(.*))?$~su',$text,$cmd))return false;
 $chat=(string)$m['chat']['id'];$reply=fn(string $msg)=>$tg->call('sendMessage',['chat_id'=>$chat,'text'=>$msg,'reply_to_message_id'=>$m['message_id'],'disable_web_page_preview'=>true]);
 try{
  if($cmd[1]==='viec'||trim($cmd[2]??'')===''){$reply(TASK_TG_HELP);return true;}
  $sender=$a->one("SELECT u.id,u.name,u.role FROM {{telegram_links}} l JOIN {{users}} u ON u.id=l.user_id WHERE l.chat_id=? AND u.archived=0 AND u.status!='inactive'",[(string)($m['from']['id']??'')]);
  if(!$sender)fail('Telegram của bạn chưa liên kết với tài khoản trên web. Vào web → Chấm công → Liên kết Telegram.');
  if(!in_array($sender['role'],['admin','manager'],true))fail('Chỉ Admin và Quản lý được giao việc.');
  $parts=array_map('trim',explode('|',$cmd[2]));
  if(count($parts)<2||$parts[1]==='')fail("Thiếu nội dung.\n\n".TASK_TG_HELP);
  if(count($parts)>3)fail("Dùng tối đa 3 phần: người nhận | nội dung | hạn.");
  // #anh = 1 ảnh, #anh5 = 5 ảnh.
  $photo=preg_match('/(?:^|\s)#anh(\d{0,2})\b/iu',$cmd[2],$shot)?max(1,(int)$shot[1]):0;
  $content=trim(preg_replace('/(^|\s)#anh\d{0,2}\b/iu','',$parts[1]));$dueText=trim(preg_replace('/(^|\s)#anh\d{0,2}\b/iu','',$parts[2]??''));
  if($content==='')fail('Thiếu nội dung.');
  $kind=$cmd[1]==='task'?'task':'notice';
  $people=$a->all("SELECT id,name,employee_code FROM {{users}} WHERE archived=0 AND status!='inactive'");
  $targets=array_values(array_filter(array_map('trim',preg_split('/\s*(?:,|;|\s+và\s+)\s*/u',$parts[0]))));
  $all=count($targets)===1&&in_array(task_fold(ltrim($targets[0],'@')),['all','tat ca','ca studio','moi nguoi'],true);
  $ids=[];if(!$all)foreach($targets as $tk)$ids[]=task_find_person($a,$people,$tk)['id'];
  $title=mb_strlen($content)>150?mb_substr($content,0,147).'…':$content;
  $r=task_create($a,$sender['id'],['kind'=>$kind,'title'=>$title,'body'=>mb_strlen($content)>150?$content:'','due_at'=>$kind==='task'?task_parse_due($dueText,now_ms()):null,'require_photo'=>$photo,'audience'=>$all?'all':'people','user_ids'=>array_values(array_unique($ids))],'Tạo qua Telegram');
  $names=$all?'tất cả nhân viên ('.count($r['recipients']).' người)':implode(', ',array_column(array_filter($people,fn($p)=>in_array($p['id'],$r['recipients'],true)),'name'));
  $reply('✅ Đã giao '.($kind==='task'?'Task':'Nhắc nhở').' cho '.$names."\n«".$r['title'].'»'.($r['due_at']?"\nHạn: ".date('H:i d/m/Y',intdiv($r['due_at'],1000)):'').($r['require_photo']?"\n📷 Bắt buộc chụp ".($r['require_photo']>1?$r['require_photo'].' ':'')."ảnh khi báo xong":'')."\nNgười nhận đã liên kết Telegram sẽ được nhắn riêng. Xem tiến độ: ".$a->config['app_url'].'/#tasks');
 }catch(HttpError $e){$reply('⚠️ '.$e->getMessage());}
 return true;
}
