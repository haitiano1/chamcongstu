<?php
declare(strict_types=1);
// Việc / Nhắc nhở: Admin và Quản lý giao và sửa (cả đổi người nhận); người nhận xác nhận Đã xem hoặc Đã xong (ảnh bằng chứng tùy chọn).
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
 if($value===null||$value==='')return null;
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
// Người nhận: chỉ tài khoản Nhân viên đang làm (chủ dự án yêu cầu 02/11/2026; Admin, Quản lý không nhận việc). @all = mọi nhân viên trừ người giao;
// hoặc danh sách người cụ thể. Dùng chung khi giao và khi sửa việc; $keep: người nhận cũ của việc đang sửa vẫn giữ được.
function task_recipient_ids(App $a,int $creator,mixed $audience,mixed $ids,array $keep=[]): array {
 if(!in_array($audience,['all','people'],true))fail('Chọn người nhận.');
 $active=array_column($a->all("SELECT id FROM {{users}} WHERE archived=0 AND status!='inactive' AND role='employee'"),'id');
 if($audience==='all')$ids=array_values(array_filter($active,fn($id)=>$id!==$creator));
 else{if(!is_array($ids)||!$ids||count($ids)>200)fail('Chọn ít nhất một người nhận.');foreach($ids as $id)if(!is_int($id)||!in_array($id,$active,true)&&!in_array($id,$keep,true))fail('Người nhận không tồn tại, đã nghỉ hoặc không phải Nhân viên.');$ids=array_values(array_unique($ids));}
 if(!$ids)fail('Chưa có nhân viên nào để giao việc.');
 return $ids;
}
// Tạo việc dùng chung cho web và lệnh Telegram; $me là người giao (Admin/Quản lý).
function task_create(App $a,int $me,array $b,string $note=''): array {
  $kind=$b['kind']??'';if(!in_array($kind,TASK_KINDS,true))fail('Chọn loại: Task hoặc Nhắc nhở.');
  $title=task_text($b['title']??null,'Tiêu đề',3,150);$body=task_text($b['body']??'','Nội dung',0,2000);$due=task_due($b['due_at']??null,$kind);
  $photo=$kind==='task'?task_photo_count($b['require_photo']??false):0;
  // Task cần duyệt (chủ dự án yêu cầu 03/11/2026): người nhận báo xong thì Chờ duyệt, Admin/Quản lý Duyệt mới thành Đã xong.
  if(array_key_exists('require_review',$b)&&!is_bool($b['require_review']))fail('Ô Task cần duyệt không hợp lệ.');$review=$kind==='task'&&!empty($b['require_review'])?1:0;
  $audience=$b['audience']??'';$ids=task_recipient_ids($a,$me,$audience,$b['user_ids']??null);
  $id=task_uuid();$now=now_ms();
  $a->run("INSERT INTO {{tasks}}(id,kind,title,body,due_at,require_photo,require_review,audience,status,cancel_reason,created_by,created_at,updated_by,updated_at) VALUES(?,?,?,?,?,?,?,?,'open','',?,?,?,?)",[$id,$kind,$title,$body,$due,$photo,$review,$audience,$me,$now,$me,$now]);
  foreach($ids as $uid)$a->run("INSERT INTO {{task_recipients}}(task_id,user_id,done_note,notify_state) VALUES(?,?,'','pending')",[$id,$uid]);
  if(function_exists('tg_group_queue'))tg_group_queue($a,'task',$id);
  task_audit($a,$id,'created',null,['kind'=>$kind,'title'=>$title,'body'=>$body,'due_at'=>$due,'require_photo'=>$photo,'require_review'=>$review,'audience'=>$audience,'recipients'=>$ids],$note,$me);
  return ['id'=>$id,'recipients'=>$ids,'kind'=>$kind,'title'=>$title,'due_at'=>$due,'require_photo'=>$photo,'require_review'=>$review,'audience'=>$audience];
}
function task_list_sql(): string {
 return "SELECT t.template_id,t.task_day,t.id,t.kind,t.title,t.body,t.due_at,t.require_photo,t.require_review,t.audience,t.status,t.cancel_reason,t.created_by,t.created_at,t.updated_at,t.version,(SELECT name FROM {{users}} WHERE id=t.created_by) creator_name,
 (SELECT COUNT(*) FROM {{task_recipients}} WHERE task_id=t.id) total,(SELECT COUNT(*) FROM {{task_recipients}} WHERE task_id=t.id AND seen_at IS NOT NULL) seen,(SELECT COUNT(*) FROM {{task_recipients}} WHERE task_id=t.id AND done_at IS NOT NULL AND COALESCE(review_state,'')<>'pending') done,(SELECT COUNT(*) FROM {{task_recipients}} WHERE task_id=t.id AND done_at IS NOT NULL AND review_state='pending') reviewing,
 my.seen_at my_seen_at,my.done_at my_done_at,my.done_note my_done_note,my.review_state my_review_state,my.review_note my_review_note,my.reviewed_at my_reviewed_at,(SELECT name FROM {{users}} WHERE id=my.reviewed_by) my_reviewer_name,(my.proof_jpeg IS NOT NULL) my_has_proof,(my.proof_jpeg IS NOT NULL)+(SELECT COUNT(*) FROM {{task_proofs}} p WHERE p.task_id=t.id AND p.user_id=my.user_id) my_proof_count,(my.user_id IS NOT NULL) mine
 FROM {{tasks}} t ";
}
// Admin/Quản lý: cộng thêm số lượt báo xong đang Chờ duyệt (không tính lượt của chính mình).
function task_review_pending(App $a): int {
 if(!in_array($a->user['role'],['admin','manager'],true))return 0;
 return (int)$a->scalar("SELECT COUNT(*) FROM {{task_recipients}} r JOIN {{tasks}} t ON t.id=r.task_id WHERE t.status='open' AND r.done_at IS NOT NULL AND r.review_state='pending' AND r.user_id<>?",[$a->user['id']]);
}
function task_reviews(App $a): array {
 return array_map(fn($r)=>['task_id'=>$r['task_id'],'title'=>$r['title'],'user_id'=>(int)$r['user_id'],'name'=>$r['name'],'done_at'=>(int)$r['done_at'],'done_note'=>$r['done_note'],'proof_count'=>(int)$r['proof_count'],'due_at'=>$r['due_at']===null?null:(int)$r['due_at']],
  $a->all("SELECT r.task_id,t.title,t.due_at,r.user_id,u.name,r.done_at,r.done_note,(r.proof_jpeg IS NOT NULL)+(SELECT COUNT(*) FROM {{task_proofs}} p WHERE p.task_id=r.task_id AND p.user_id=r.user_id) proof_count FROM {{task_recipients}} r JOIN {{tasks}} t ON t.id=r.task_id JOIN {{users}} u ON u.id=r.user_id WHERE t.status='open' AND r.done_at IS NOT NULL AND r.review_state='pending' AND r.user_id<>? ORDER BY r.done_at LIMIT 100",[$a->user['id']]));
}
// Telegram khi báo xong việc cần duyệt: Admin + Quản lý (trừ người báo), kèm ảnh bằng chứng đầu tiên nếu có.
function task_review_notify(App $a,array $t,string $note,?string $photo,int $count): int {
 if(!class_exists('Telegram'))return 0;$tg=support_telegram()??new Telegram($a);if(!$tg->configured())return 0;$sent=0;
 $text="📋 {$a->user['name']} báo xong việc · chờ duyệt\n📝 {$t['title']}".($note!==''?"\n💬 ".support_clip($note):'').($count?"\n📷 $count ảnh bằng chứng":'')."\n\nMở mục Việc để Duyệt hoặc Chưa duyệt.";
 $button=$tg->button('Mở Việc',$a->config['app_url'].'/#tasks');
 foreach($a->all("SELECT l.chat_id FROM {{telegram_links}} l JOIN {{users}} u ON u.id=l.user_id WHERE u.role IN ('admin','manager') AND u.archived=0 AND u.status!='inactive' AND u.id!=? ORDER BY u.id",[$a->user['id']]) as $m){
  if($photo!==null&&$tg->photo($m['chat_id'],$photo,$text,$button)){$sent++;continue;}
  try{$tg->call('sendMessage',['chat_id'=>$m['chat_id'],'text'=>$text,'disable_web_page_preview'=>true]+$button);$sent++;}catch(Throwable $e){$a->meta('telegram_error',$e->getMessage());}
 }
 return $sent;
}
function task_review_reply(App $a,int $uid,string $text): void {
 if(!class_exists('Telegram'))return;$tg=support_telegram()??new Telegram($a);if(!$tg->configured())return;
 foreach($a->all('SELECT chat_id FROM {{telegram_links}} WHERE user_id=?',[$uid]) as $m){try{$tg->call('sendMessage',['chat_id'=>$m['chat_id'],'text'=>$text,'disable_web_page_preview'=>true]+$tg->button('Mở Việc',$a->config['app_url'].'/#tasks'));}catch(Throwable $e){$a->meta('telegram_error',$e->getMessage());}}
}
function task_pending(App $a): int {
 return task_review_pending($a)+(int)$a->scalar("SELECT COUNT(*) FROM {{task_recipients}} r JOIN {{tasks}} t ON t.id=r.task_id WHERE r.user_id=? AND t.status='open' AND (t.task_day IS NULL OR t.task_day=?) AND (t.kind='notice' AND r.seen_at IS NULL OR t.kind='task' AND r.done_at IS NULL)",[$a->user['id'],local_day(now_ms())]);
}
function task_routes(App $a,string $path,array $b,string $method): ?array {
 if($path!=='/tasks'&&!str_starts_with($path,'/tasks/'))return null;
 if($path==='/tasks/support'||str_starts_with($path,'/tasks/support/'))return support_routes($a,$path,$b,$method);
 $manager=$a->user['role']!=='employee';$me=$a->user['id'];
 daily_tasks_ensure($a,local_day(now_ms()));
 $r=daily_template_routes($a,$path,$b,$method);if($r!==null)return $r;
 if($path==='/tasks/summary'&&$method==='GET')return [200,['pending'=>task_pending($a),'support'=>support_pending($a)]];
 if($path==='/tasks'&&$method==='GET'){
  $rows=$manager?$a->all(task_list_sql()."LEFT JOIN {{task_recipients}} my ON my.task_id=t.id AND my.user_id=? WHERE t.status<>'deleted' ORDER BY t.created_at DESC LIMIT 300",[$me]):$a->all(task_list_sql()."JOIN {{task_recipients}} my ON my.task_id=t.id AND my.user_id=? WHERE t.status<>'deleted' ORDER BY t.created_at DESC LIMIT 300",[$me]);
  // Danh sách việc chia theo ngày (chủ dự án yêu cầu 03/11/2026): Admin/Quản lý thấy tên người nhận trên từng thẻ.
  $names=[];if($manager&&$rows)foreach($a->all('SELECT r.task_id,u.name FROM {{task_recipients}} r JOIN {{users}} u ON u.id=r.user_id WHERE r.task_id IN ('.implode(',',array_fill(0,count($rows),'?')).') ORDER BY u.name',array_column($rows,'id')) as $x)$names[$x['task_id']][]=$x['name'];
  foreach($rows as &$r){$r['mine']=(bool)$r['mine'];$r['my_has_proof']=(bool)$r['my_has_proof'];if(!$manager)unset($r['seen'],$r['done']);else $r['recipients']=$names[$r['id']]??[];}unset($r);
  return [200,['rows'=>$rows,'reviews'=>$manager?task_reviews($a):[],'pending'=>task_pending($a),'support'=>support_pending($a),'can_manage'=>$manager,'decline_requests'=>$manager?task_decline_list($a,null):[],'my_declines'=>task_decline_list($a,$me),'decline_followups'=>$manager?task_decline_followups($a):[],'today'=>local_day(now_ms()),'templates'=>$manager?$a->all('SELECT d.id,d.user_id,d.title,d.require_photo,d.active,d.version,u.name FROM {{task_templates}} d JOIN {{users}} u ON u.id=d.user_id WHERE u.archived=0 ORDER BY u.name,d.created_at'):[],'people'=>$manager?array_map(fn($p)=>['id'=>$p['id'],'name'=>$p['name'],'role'=>$p['role']],finance_people($a)):[]]];
 }
 if($path==='/tasks'&&$method==='POST'){
  $a->requireManager();$r=task_create($a,$me,$b);
  return [201,['id'=>$r['id'],'recipients'=>count($r['recipients'])]];
 }
 if(preg_match('~^/tasks/([a-f0-9-]{36})$~D',$path,$m)&&$method==='GET'){
  $t=task_row($a,$m[1]);$mine=$a->one('SELECT 1 FROM {{task_recipients}} WHERE task_id=? AND user_id=?',[$t['id'],$me]);
  if(!$manager&&!$mine)fail('Bạn không có quyền xem việc này.',403);
  $t['creator_name']=$a->scalar('SELECT name FROM {{users}} WHERE id=?',[$t['created_by']]);
  $recipients=$a->all('SELECT r.user_id,u.name,r.seen_at,r.done_at,r.done_note,r.review_state,r.review_note,r.reviewed_at,(SELECT name FROM {{users}} WHERE id=r.reviewed_by) reviewer_name,(r.proof_jpeg IS NOT NULL) has_proof,(r.proof_jpeg IS NOT NULL)+(SELECT COUNT(*) FROM {{task_proofs}} p WHERE p.task_id=r.task_id AND p.user_id=r.user_id) proof_count,r.notify_state FROM {{task_recipients}} r JOIN {{users}} u ON u.id=r.user_id WHERE r.task_id=?'.($manager?'':' AND r.user_id=?').' ORDER BY u.name',$manager?[$t['id']]:[$t['id'],$me]);
  foreach($recipients as &$r){$r['has_proof']=(bool)$r['has_proof'];$r['proof_count']=(int)$r['proof_count'];}unset($r);
  $audit=$manager?$a->all('SELECT x.action,x.before_json,x.after_json,x.note,x.time,u.name actor_name FROM {{task_audit}} x JOIN {{users}} u ON u.id=x.actor_id WHERE x.task_id=? ORDER BY x.id',[$t['id']]):[];
  return [200,['task'=>$t,'recipients'=>$recipients,'audit'=>$audit,'can_manage'=>$manager]];
 }
 if(preg_match('~^/tasks/([a-f0-9-]{36})/photo/(\d+)(?:/(\d+))?$~D',$path,$m)&&$method==='GET'){
  $uid=(int)$m[2];$n=(int)($m[3]??1);if(!$manager&&$uid!==$me)fail('Bạn không có quyền xem ảnh này.',403);
  $photo=$n<=1?$a->scalar('SELECT proof_jpeg FROM {{task_recipients}} WHERE task_id=? AND user_id=?',[entry_uuid($m[1]),$uid]):$a->scalar('SELECT photo_jpeg FROM {{task_proofs}} WHERE task_id=? AND user_id=? AND idx=?',[entry_uuid($m[1]),$uid,$n]);if(!$photo)fail('Không có ảnh bằng chứng.',404);
  header('Content-Type: image/jpeg');header('Content-Disposition: inline');echo $photo;exit;
 }
 if(preg_match('~^/tasks/declines/([a-f0-9-]{36})/(approve|reject|withdraw)$~D',$path,$m)&&$method==='POST')return task_decline_review($a,$m[1],$m[2],$b);
 if(!preg_match('~^/tasks/([a-f0-9-]{36})/(seen|done|undo|edit|cancel|decline|due|assign|delete|review)$~D',$path,$m)||$method!=='POST')fail('Không tìm thấy chức năng Việc.',404);
 $t=task_row($a,$m[1]);$action=$m[2];
 if($action==='seen'){$r=task_mine($a,$t);if($r['seen_at']===null)$a->run('UPDATE {{task_recipients}} SET seen_at=? WHERE task_id=? AND user_id=?',[now_ms(),$t['id'],$me]);return [200,['ok'=>true,'pending'=>task_pending($a)]];}
 if($action==='done'){
  $r=task_mine($a,$t);if($t['kind']!=='task')fail('Nhắc nhở chỉ cần bấm Đã xem.');if($r['done_at']!==null)fail('Bạn đã báo xong việc này.',409);
  $note=task_text($b['note']??'','Ghi chú',0,500);
  // Bản cũ gửi một ảnh ở 'photo'; bản mới gửi danh sách 'photos'.
  $list=array_key_exists('photos',$b)?$b['photos']:(($b['photo']??null)!==null&&$b['photo']!==''?[$b['photo']]:[]);if(!is_array($list)||!array_is_list($list)||count($list)>TASK_MAX_PHOTOS)fail('Gửi tối đa '.TASK_MAX_PHOTOS.' ảnh bằng chứng.');
  $photos=array_values(array_filter(array_map('task_photo',$list),fn($x)=>$x!==null));$need=(int)$t['require_photo'];
  if(count($photos)<$need)fail($need>1?'Việc này cần chụp đủ '.$need.' ảnh bằng chứng (mới có '.count($photos).').':'Việc này bắt buộc chụp ảnh bằng chứng bằng camera.');$now=now_ms();
  $review=(int)($t['require_review']??0)===1;
  $a->run('UPDATE {{task_recipients}} SET seen_at=COALESCE(seen_at,?),done_at=?,done_note=?,proof_jpeg=?,review_state=?,reviewed_by=NULL,reviewed_at=NULL WHERE task_id=? AND user_id=?',[$now,$now,$note,$photos[0]??null,$review?'pending':null,$t['id'],$me]);
  $a->run('DELETE FROM {{task_proofs}} WHERE task_id=? AND user_id=?',[$t['id'],$me]);foreach(array_slice($photos,1) as $i=>$photo)$a->run('INSERT INTO {{task_proofs}} VALUES(?,?,?,?)',[$t['id'],$me,$i+2,$photo]);
  task_audit($a,$t['id'],$review?'submitted':'done',null,['note'=>$note,'photo'=>(bool)$photos,'photos'=>count($photos)]);
  $a->run("UPDATE {{task_declines}} SET status='withdrawn',reviewed_at=? WHERE task_id=? AND user_id=? AND status='pending'",[$now,$t['id'],$me]);
  $notified=$review?task_review_notify($a,$t,$note,$photos[0]??null,count($photos)):0;
  return [200,['ok'=>true,'pending'=>task_pending($a),'review'=>$review,'notified'=>$notified]];
 }
 if($action==='undo'){
  $r=task_mine($a,$t);if($r['done_at']===null)fail('Việc chưa được báo xong.',409);if(($r['review_state']??null)==='approved')fail('Việc đã được duyệt, không hoàn tác được.',409);
  $a->run("UPDATE {{task_recipients}} SET done_at=NULL,done_note='',proof_jpeg=NULL,review_state=NULL WHERE task_id=? AND user_id=?",[$t['id'],$me]);$a->run('DELETE FROM {{task_proofs}} WHERE task_id=? AND user_id=?',[$t['id'],$me]);
  task_audit($a,$t['id'],'undone',['note'=>$r['done_note'],'photo'=>$r['proof_jpeg']!==null],null);
  return [200,['ok'=>true,'pending'=>task_pending($a)]];
 }
 if($action==='decline')return task_decline_request($a,$t,$b);
 // Duyệt / Chưa duyệt lượt báo xong của Task cần duyệt (chủ dự án yêu cầu 03/11/2026). Chưa duyệt: bắt buộc lý do, việc quay lại Cần làm
 // cho người đó (ảnh cũ giữ tới khi báo xong lại), Telegram báo người làm. Không tự duyệt lượt của mình.
 if($action==='review'){
  if(!$manager||$a->user['role']==='makeup')fail('Chỉ Admin hoặc Quản lý duyệt việc.',403);if($t['status']!=='open')fail('Việc không còn mở.',409);
  $uid=$b['user_id']??null;if(!is_int($uid))fail('Thiếu người làm.');if($uid===$me)fail('Không tự duyệt việc của chính mình.',403);
  $decision=$b['decision']??'';if(!in_array($decision,['approve','reject'],true))fail('Chọn Duyệt hoặc Chưa duyệt.');
  $note=task_text($b['note']??'','Lý do',$decision==='reject'?3:0,1000);
  $r=$a->one('SELECT r.*,u.name FROM {{task_recipients}} r JOIN {{users}} u ON u.id=r.user_id WHERE r.task_id=? AND r.user_id=?',[$t['id'],$uid]);
  if(!$r||$r['done_at']===null||$r['review_state']!=='pending')fail('Lượt báo xong này không còn chờ duyệt (có thể người khác vừa duyệt hoặc người làm đã hoàn tác).',409);
  $now=now_ms();
  if($decision==='approve')$a->run("UPDATE {{task_recipients}} SET review_state='approved',review_note=?,reviewed_by=?,reviewed_at=? WHERE task_id=? AND user_id=? AND review_state='pending'",[$note,$me,$now,$t['id'],$uid]);
  else $a->run("UPDATE {{task_recipients}} SET review_state='rejected',review_note=?,reviewed_by=?,reviewed_at=?,done_at=NULL WHERE task_id=? AND user_id=? AND review_state='pending'",[$note,$me,$now,$t['id'],$uid]);
  task_audit($a,$t['id'],$decision==='approve'?'review_approved':'review_rejected',['user'=>$r['name'],'done_at'=>(int)$r['done_at']],['user'=>$r['name']],$note);
  task_review_reply($a,$uid,$decision==='approve'?"✅ Việc của bạn đã được duyệt · Đã xong\n📝 {$t['title']}\n👤 Duyệt: {$a->user['name']}".($note!==''?"\n💬 $note":''):"❌ Việc của bạn chưa được duyệt\n📝 {$t['title']}\n👤 {$a->user['name']}: $note\n\nLàm lại rồi bấm Báo đã xong trong mục Việc.");
  return [200,['ok'=>true,'pending'=>task_pending($a)]];
 }
 // Xóa việc đã giao (chủ dự án yêu cầu 02/11/2026: danh sách việc lộn xộn). Admin / Quản lý xóa được việc đang mở hoặc đã hủy.
 // Xóa mềm: status='deleted', ẩn khỏi mọi danh sách, không còn tính việc chờ, không khóa chấm ra, không nhắn Telegram; giữ dòng + lịch sử.
 // Việc hằng ngày chỉ xóa việc của ngày đó (không tự tạo lại trong ngày); muốn ngừng hẳn thì xóa mẫu ở Việc hằng ngày.
 if($action==='delete'){
  $a->requireManager();entry_version($b,$t);if($t['status']==='deleted')fail('Việc đã bị xóa.',409);
  $note=task_text($b['reason']??'','Lý do xóa',0,500);
  $a->run("UPDATE {{tasks}} SET status='deleted',updated_by=?,updated_at=?,version=version+1 WHERE id=?",[$me,now_ms(),$t['id']]);
  $a->run("UPDATE {{task_recipients}} SET notify_state='skipped' WHERE task_id=? AND notify_state='pending'",[$t['id']]);
  $a->run("UPDATE {{task_declines}} SET status='withdrawn',reviewed_at=? WHERE task_id=? AND status='pending'",[now_ms(),$t['id']]);
  task_audit($a,$t['id'],'deleted',['status'=>$t['status']],['status'=>'deleted'],$note);
  return [200,['ok'=>true,'pending'=>task_pending($a)]];
 }
 $a->requireManager();entry_version($b,$t);if($t['status']!=='open')fail('Việc đã bị hủy.',409);
 // Chỉnh hạn ở khung Theo dõi hạn (chủ dự án yêu cầu 01/11/2026): chỉ đổi hạn, để trống là bỏ hạn.
 if($action==='due'){
  $old=$t['due_at']===null?null:(int)$t['due_at'];$raw=$b['due_at']??null;$due=$raw===null||$raw===''?null:($raw===$old?$old:task_due($raw,$t['kind']));
  if($due===$old)fail('Hạn chưa thay đổi.');
  $a->run('UPDATE {{tasks}} SET due_at=?,updated_by=?,updated_at=?,version=version+1 WHERE id=?',[$due,$me,now_ms(),$t['id']]);
  task_audit($a,$t['id'],'due_changed',['due_at'=>$old],['due_at'=>$due]);return [200,['ok'=>true]];
 }
 // Giao người làm thay ở Theo dõi hạn (việc không còn ai nhận sau khi duyệt từ chối); đặt hạn cùng lúc.
 if($action==='assign'){
  $ids=task_recipient_ids($a,(int)$t['created_by'],'people',$b['user_ids']??null);
  $have=array_map('intval',array_column($a->all('SELECT user_id FROM {{task_recipients}} WHERE task_id=?',[$t['id']]),'user_id'));
  $add=array_values(array_diff($ids,$have));if(!$add)fail('Người này đang nhận việc rồi.');
  $old=$t['due_at']===null?null:(int)$t['due_at'];$raw=$b['due_at']??null;$due=!array_key_exists('due_at',$b)?$old:($raw===null||$raw===''?null:($raw===$old?$old:task_due($raw,$t['kind'])));
  $a->run("UPDATE {{tasks}} SET audience='people',due_at=?,updated_by=?,updated_at=?,version=version+1 WHERE id=?",[$due,$me,now_ms(),$t['id']]);
  // Việc hằng ngày không nhắn Telegram (giống lúc tự tạo mỗi ngày); việc thường nhắn như việc mới.
  foreach($add as $uid)$a->run("INSERT INTO {{task_recipients}}(task_id,user_id,done_note,notify_state) VALUES(?,?,'',?)",[$t['id'],$uid,$t['template_id']===null?'pending':'skipped']);
  $names=array_column($a->all('SELECT name FROM {{users}} WHERE id IN ('.implode(',',array_fill(0,count($add),'?')).') ORDER BY name',$add),'name');
  task_audit($a,$t['id'],'assigned',['due_at'=>$old],['recipients'=>$names,'due_at'=>$due]);return [200,['ok'=>true]];
 }
 if($action==='edit'){
  $title=task_text($b['title']??null,'Tiêu đề',3,150);$body=task_text($b['body']??'','Nội dung',0,2000);
  $due=($b['due_at']??null)===$t['due_at']?$t['due_at']:task_due($b['due_at']??null,$t['kind']);
  $before=['title'=>$t['title'],'body'=>$t['body'],'due_at'=>$t['due_at']];$after=['title'=>$title,'body'=>$body,'due_at'=>$due];
  $review=(int)($t['require_review']??0);if(array_key_exists('require_review',$b)&&$t['kind']==='task'){if(!is_bool($b['require_review']))fail('Ô Task cần duyệt không hợp lệ.');$review=$b['require_review']?1:0;}
  if($review!==(int)($t['require_review']??0)){$before['require_review']=(int)$t['require_review'];$after['require_review']=$review;}
  // Đổi người nhận (chủ dự án yêu cầu 30/10/2026): giao cho người khác. Người đã báo xong giữ lại; người mới nhận tin Telegram như việc mới.
  $old=array_map('intval',array_column($a->all('SELECT user_id FROM {{task_recipients}} WHERE task_id=?',[$t['id']]),'user_id'));sort($old);$new=$old;$audience=$t['audience'];
  if(array_key_exists('audience',$b)){$audience=$b['audience'];$new=task_recipient_ids($a,(int)$t['created_by'],$audience,$b['user_ids']??null,$old);
   if($audience==='all')foreach($a->all('SELECT user_id FROM {{task_recipients}} WHERE task_id=? AND done_at IS NOT NULL',[$t['id']]) as $x)if(!in_array((int)$x['user_id'],$new,true))$new[]=(int)$x['user_id'];sort($new);
   foreach($a->all('SELECT r.user_id,u.name FROM {{task_recipients}} r JOIN {{users}} u ON u.id=r.user_id WHERE r.task_id=? AND r.done_at IS NOT NULL',[$t['id']]) as $x)if(!in_array((int)$x['user_id'],$new,true))fail($x['name'].' đã báo xong việc này nên không bỏ khỏi người nhận được.',409);}
  if($before===$after&&$new===$old&&$audience===$t['audience'])fail('Chưa có thay đổi nào.');
  $a->run('UPDATE {{tasks}} SET title=?,body=?,due_at=?,require_review=?,audience=?,updated_by=?,updated_at=?,version=version+1 WHERE id=?',[$title,$body,$due,$review,$audience,$me,now_ms(),$t['id']]);
  // Bỏ tick Task cần duyệt: các lượt đang Chờ duyệt coi như Đã xong.
  if(!$review)$a->run("UPDATE {{task_recipients}} SET review_state='approved',reviewed_by=?,reviewed_at=? WHERE task_id=? AND review_state='pending'",[$me,now_ms(),$t['id']]);
  if($new!==$old){
   foreach(array_diff($old,$new) as $uid){$a->run('DELETE FROM {{task_proofs}} WHERE task_id=? AND user_id=?',[$t['id'],$uid]);$a->run('DELETE FROM {{task_recipients}} WHERE task_id=? AND user_id=?',[$t['id'],$uid]);}
   // Việc hằng ngày không nhắn Telegram (giống lúc tự tạo mỗi ngày).
   foreach(array_diff($new,$old) as $uid)$a->run("INSERT INTO {{task_recipients}}(task_id,user_id,done_note,notify_state) VALUES(?,?,'',?)",[$t['id'],$uid,$t['template_id']===null?'pending':'skipped']);
   $names=fn(array $ids)=>$ids?array_column($a->all('SELECT name FROM {{users}} WHERE id IN ('.implode(',',array_fill(0,count($ids),'?')).') ORDER BY name',array_values($ids)),'name'):[];
   $before['recipients']=$names($old);$after['recipients']=$names($new);
  }
  task_audit($a,$t['id'],'edited',$before,$after);return [200,['ok'=>true]];
 }
 $reason=task_text($b['reason']??null,'Lý do hủy',3,500);
 $a->run("UPDATE {{tasks}} SET status='cancelled',cancel_reason=?,updated_by=?,updated_at=?,version=version+1 WHERE id=?",[$reason,$me,now_ms(),$t['id']]);
 $a->run("UPDATE {{task_recipients}} SET notify_state='skipped' WHERE task_id=? AND notify_state='pending'",[$t['id']]);
 task_audit($a,$t['id'],'cancelled',['status'=>'open'],['status'=>'cancelled'],$reason);
 return [200,['ok'=>true]];
}
// Xin từ chối Task / Nhắc nhở (chủ dự án yêu cầu 01/11/2026): người nhận ghi lý do / ý kiến, Admin hoặc Quản lý khác duyệt.
// Duyệt: gỡ người đó khỏi việc (không còn ai nhận thì việc vẫn mở, chờ giao người khác ở Theo dõi hạn). Không duyệt: việc vẫn treo cho người đó tới khi báo xong;
// Nhắc nhở bị không duyệt thì quay lại Chưa xem. Báo xong trong lúc chờ thì yêu cầu tự rút. Telegram báo Admin/Quản lý khi gửi, báo người gửi khi có kết quả.
const TASK_DECLINE_STATUS=['pending'=>'Chờ duyệt','approved'=>'Đã duyệt','rejected'=>'Không duyệt','withdrawn'=>'Đã rút'];
function task_decline_list(App $a,?int $user): array {
 $sql="SELECT d.id,d.task_id,d.user_id,d.reason,d.status,d.requested_at,d.reviewed_at,d.review_note,u.name,t.title,t.kind,t.task_day,(SELECT name FROM {{users}} WHERE id=d.reviewed_by) reviewer_name FROM {{task_declines}} d JOIN {{users}} u ON u.id=d.user_id JOIN {{tasks}} t ON t.id=d.task_id WHERE ";
 if($user===null)return $a->all($sql."d.status='pending' AND t.status='open' AND EXISTS(SELECT 1 FROM {{task_recipients}} r WHERE r.task_id=d.task_id AND r.user_id=d.user_id) ORDER BY d.requested_at",[]);
 return $a->all($sql."d.user_id=? AND d.status!='withdrawn' AND t.status<>'deleted' AND (d.status='pending' OR d.reviewed_at>=?) ORDER BY d.requested_at DESC LIMIT 30",[$user,now_ms()-14*86400000]);
}
// Theo dõi hạn (chủ dự án yêu cầu 01/11/2026): Task / Nhắc nhở đã có yêu cầu từ chối được Duyệt hoặc Không duyệt vẫn hiện cho
// Admin/Quản lý kèm hạn để tự chỉnh, tới khi mọi người nhận còn lại báo xong (Nhắc nhở: đã xem), việc bị hủy hoặc việc hằng ngày đã qua ngày.
// Duyệt mà không còn ai nhận thì việc vẫn mở, hiện ở đây để giao người khác (assign) hoặc hủy.
function task_decline_followups(App $a): array {
 $open="(t.kind='task' AND r.done_at IS NULL OR t.kind='notice' AND r.seen_at IS NULL)";
 $rows=$a->all("SELECT t.id,t.kind,t.title,t.due_at,t.version,t.task_day,t.template_id FROM {{tasks}} t WHERE t.status='open' AND (t.task_day IS NULL OR t.task_day>=?) AND EXISTS(SELECT 1 FROM {{task_declines}} d WHERE d.task_id=t.id AND d.status IN ('approved','rejected')) AND (EXISTS(SELECT 1 FROM {{task_recipients}} r WHERE r.task_id=t.id AND $open) OR NOT EXISTS(SELECT 1 FROM {{task_recipients}} r WHERE r.task_id=t.id)) ORDER BY t.due_at IS NULL,t.due_at,t.created_at DESC LIMIT 50",[local_day(now_ms())]);
 foreach($rows as &$r){
  $r['declines']=$a->all("SELECT d.status,d.reviewed_at,d.review_note,u.name,(SELECT name FROM {{users}} WHERE id=d.reviewed_by) reviewer_name FROM {{task_declines}} d JOIN {{users}} u ON u.id=d.user_id WHERE d.task_id=? AND d.status IN ('approved','rejected') ORDER BY d.reviewed_at",[$r['id']]);
  $r['waiting']=array_column($a->all("SELECT u.name FROM {{task_recipients}} r JOIN {{tasks}} t ON t.id=r.task_id JOIN {{users}} u ON u.id=r.user_id WHERE r.task_id=? AND $open ORDER BY u.name",[$r['id']]),'name');
 }unset($r);return $rows;
}
function task_decline_notify(App $a,array $chats,string $text,?Telegram $tg=null): void {
 if(!$chats||!class_exists('Telegram'))return;$tg??=new Telegram($a);if(!$tg->configured())return;
 foreach($chats as $chat){try{$tg->call('sendMessage',['chat_id'=>$chat,'text'=>$text,'disable_web_page_preview'=>true]+$tg->button('Mở mục Việc',$a->config['app_url'].'/#tasks'));}catch(Throwable $e){$a->meta('telegram_error',$e->getMessage());}}
}
function task_decline_manager_chats(App $a,int $except): array {return array_column($a->all("SELECT l.chat_id FROM {{telegram_links}} l JOIN {{users}} u ON u.id=l.user_id WHERE u.role IN ('admin','manager') AND u.archived=0 AND u.status!='inactive' AND u.id!=? ORDER BY u.id",[$except]),'chat_id');}
function task_decline_request_text(string $name,array $t,string $reason): string {return "📝 Anh Gấu Studio · Xin từ chối ".($t['kind']==='task'?'Task':'Nhắc nhở')."\n".$name.' xin từ chối: «'.$t['title']."»\nLý do / ý kiến: ".$reason."\n\nVào mục Việc để Duyệt hoặc Không duyệt. Không duyệt thì việc vẫn giữ cho ".$name.' tới khi báo xong.';}
function task_decline_result_text(array $t,bool $approved,string $reviewer,string $note): string {
 $kind=$t['kind']==='task'?'Task':'Nhắc nhở';
 return $approved?"✅ Anh Gấu Studio · Đã duyệt từ chối\n".$kind.': «'.$t['title']."»\n".$reviewer.' đã duyệt, bạn không cần làm việc này nữa.'.($note!==''?"\nGhi chú: ".$note:'')
  :"❌ Anh Gấu Studio · Không duyệt từ chối\n".$kind.': «'.$t['title']."»\n".$reviewer.' không duyệt. Lý do: '.$note."\n".($t['kind']==='task'?'Việc vẫn giữ cho bạn, hãy hoàn thành rồi bấm Báo đã xong.':'Nhắc nhở vẫn giữ cho bạn, hãy đọc lại và bấm Đã xem.');
}
function task_decline_request(App $a,array $t,array $b): array {
 $me=$a->user['id'];$r=task_mine($a,$t);
 if($t['kind']==='task'&&$r['done_at']!==null)fail('Bạn đã báo xong việc này, không cần từ chối.',409);
 if($t['task_day']!==null&&$t['task_day']<local_day(now_ms()))fail('Việc hằng ngày của ngày trước đã qua, không cần từ chối.',409);
 if($a->one("SELECT id FROM {{task_declines}} WHERE task_id=? AND user_id=? AND status='pending'",[$t['id'],$me]))fail('Bạn đã gửi yêu cầu từ chối việc này, đang chờ Quản lý/Admin duyệt.',409);
 $reason=task_text($b['reason']??null,'Lý do / ý kiến',5,1000);$id=task_uuid();
 $a->run("INSERT INTO {{task_declines}}(id,task_id,user_id,reason,status,requested_at,review_note) VALUES(?,?,?,?,'pending',?,'')",[$id,$t['id'],$me,$reason,now_ms()]);
 task_audit($a,$t['id'],'decline_requested',null,['reason'=>$reason],$reason);
 task_decline_notify($a,task_decline_manager_chats($a,$me),task_decline_request_text($a->user['name'],$t,$reason));
 return [201,['id'=>$id,'pending'=>task_pending($a)]];
}
function task_decline_review(App $a,string $id,string $action,array $b): array {
 $me=$a->user['id'];$d=$a->one('SELECT * FROM {{task_declines}} WHERE id=?',[entry_uuid($id)]);if(!$d)fail('Không tìm thấy yêu cầu từ chối.',404);
 if($d['status']!=='pending')fail('Yêu cầu đã được xử lý ('.(TASK_DECLINE_STATUS[$d['status']]??$d['status']).'). Tải lại.',409);
 $t=task_row($a,$d['task_id']);$uid=(int)$d['user_id'];$now=now_ms();
 $close=fn(string $status,string $note)=>$a->run('UPDATE {{task_declines}} SET status=?,reviewed_by=?,reviewed_at=?,review_note=? WHERE id=?',[$status,$me,$now,$note,$d['id']]);
 if($action==='withdraw'){if($uid!==$me)fail('Chỉ người gửi được rút yêu cầu.',403);$close('withdrawn','');task_audit($a,$t['id'],'decline_withdrawn',null,null,$d['reason']);return [200,['ok'=>true,'pending'=>task_pending($a)]];}
 $a->requireManager();if($uid===$me)fail('Không được tự duyệt yêu cầu từ chối của mình. Nhờ Quản lý hoặc Admin khác.',403);
 $person=$a->one('SELECT r.*,u.name FROM {{task_recipients}} r JOIN {{users}} u ON u.id=r.user_id WHERE r.task_id=? AND r.user_id=?',[$t['id'],$uid]);
 if($t['status']!=='open'||!$person)fail('Việc đã bị hủy hoặc đã đổi người nhận, không cần duyệt nữa. Tải lại.',409);
 $note=task_text($b['note']??'','Lý do không duyệt',$action==='reject'?3:0,500);
 if($action==='approve'){
  $a->run('DELETE FROM {{task_proofs}} WHERE task_id=? AND user_id=?',[$t['id'],$uid]);$a->run('DELETE FROM {{task_recipients}} WHERE task_id=? AND user_id=?',[$t['id'],$uid]);$close('approved',$note);
  task_audit($a,$t['id'],'decline_approved',['recipient'=>$person['name']],null,'Lý do của '.$person['name'].': '.$d['reason'].($note!==''?' | Ghi chú duyệt: '.$note:''));
  // Không còn ai nhận thì việc vẫn mở (chủ dự án yêu cầu 01/11/2026): hiện ở Theo dõi hạn để giao người khác, chỉnh hạn hoặc hủy.
 }else{
  $close('rejected',$note);if($t['kind']==='notice')$a->run('UPDATE {{task_recipients}} SET seen_at=NULL WHERE task_id=? AND user_id=?',[$t['id'],$uid]);
  task_audit($a,$t['id'],'decline_rejected',null,null,'Lý do của '.$person['name'].': '.$d['reason'].' | Không duyệt: '.$note);
 }
 $chat=$a->scalar('SELECT chat_id FROM {{telegram_links}} WHERE user_id=?',[$uid]);
 task_decline_notify($a,$chat?[(string)$chat]:[],task_decline_result_text($t,$action==='approve',$a->user['name'],$note));
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
// Task / Nhắc nhở có hạn (chủ dự án yêu cầu 02/11/2026): hạn rơi vào hôm nay mà chưa xong (Nhắc nhở: chưa bấm Đã xem) thì không chấm ra ca 2 được,
// giống việc hằng ngày. Hạn ở ngày khác (VD giao 48h) thì hôm nay, ngày mai vẫn chấm ra bình thường; đúng ngày đến hạn mới khóa.
// Quá hạn mà vẫn chưa xong thì các ngày sau vẫn khóa (chỉ tính việc có hạn từ TASK_DUE_LOCK_FROM). Đang xin từ chối chờ duyệt thì không khóa.
const TASK_DUE_LOCK_FROM='2026-11-02';
function task_due_today(App $a,int $user,?int $now=null): array {
 $day=local_day($now??now_ms());$tomorrow=day_time(gmdate('Y-m-d',strtotime($day.'T00:00:00Z')+86400));
 return $a->all("SELECT t.id,t.kind,t.title,t.due_at,t.require_photo FROM {{tasks}} t JOIN {{task_recipients}} r ON r.task_id=t.id AND r.user_id=? WHERE t.status='open' AND t.template_id IS NULL AND t.due_at IS NOT NULL AND t.due_at>=? AND t.due_at<? AND ((t.kind='task' AND r.done_at IS NULL) OR (t.kind<>'task' AND r.seen_at IS NULL)) AND NOT EXISTS(SELECT 1 FROM {{task_declines}} d WHERE d.task_id=t.id AND d.user_id=r.user_id AND d.status='pending') ORDER BY t.due_at,t.title",[$user,day_time(TASK_DUE_LOCK_FROM),$tomorrow]);
}
// Khóa chấm ra: Ca 2, hoặc kết thúc OT (chủ dự án yêu cầu 09/11/2026: vào OT thì Ca 2 tự chấm ra không khóa, khóa chuyển sang lúc kết thúc OT).
// OT kết thúc sau nửa đêm thì không khóa nữa (việc hằng ngày của hôm trước không báo xong được).
function daily_tasks_block_checkout(App $a,array $b): void {
 $shift=$b['shift']??'';if(($b['type']??'')!=='out'||!in_array($shift,['day_second','ot'],true))return;
 if($shift==='ot'){$open=array_values(array_filter(pairs($a->events($a->user['id']))['pending'],fn($e)=>$e['shift']==='ot'));if(!$open||$open[0]['work_date']!==local_day(now_ms()))return;}
 $left=array_column(array_filter(daily_tasks_today($a,$a->user['id']),fn($t)=>$t['done_at']===null),'title');
 foreach(task_due_today($a,$a->user['id']) as $t)$left[]=($t['kind']==='task'?'':'Nhắc nhở: ').$t['title'].' (hạn '.date('H:i d/m',intdiv((int)$t['due_at'],1000)).')';
 if($left)fail('Cần hoàn thành trước khi chấm ra '.($shift==='ot'?'OT':'ca 2').': '.implode('; ',$left).'. Vào mục Việc để báo xong (Nhắc nhở: bấm Đã xem).',409);
}
function daily_template_routes(App $a,string $path,array $b,string $method): ?array {
 if($path!=='/tasks/templates'&&!str_starts_with($path,'/tasks/templates/'))return null;
 $a->requireManager();if($method!=='POST')fail('Chỉ nhận POST.',405);$me=$a->user['id'];$today=local_day(now_ms());
 $fields=function()use($b){$title=task_text($b['title']??null,'Tên việc',3,150);if(!is_bool($b['require_photo']??null)&&!is_int($b['require_photo']??null))fail('Chọn số ảnh bắt buộc.');return ['title'=>$title,'require_photo'=>task_photo_count($b['require_photo'])];};
 if($path==='/tasks/templates'){
  $f=$fields();$uid=$b['user_id']??null;if(!is_int($uid)||!$a->one("SELECT id FROM {{users}} WHERE id=? AND archived=0 AND status!='inactive' AND role='employee'",[$uid]))fail('Chọn nhân viên đang làm.');
  $id=task_uuid();$now=now_ms();$a->run('INSERT INTO {{task_templates}}(id,user_id,title,require_photo,active,created_by,created_at,updated_by,updated_at) VALUES(?,?,?,?,1,?,?,?,?)',[$id,$uid,$f['title'],$f['require_photo'],$me,$now,$me,$now]);
  task_audit($a,$id,'template_created',null,['user_id'=>$uid]+$f);daily_tasks_ensure($a,$today,$uid);return [201,['id'=>$id]];
 }
 if(!preg_match('~^/tasks/templates/([a-f0-9-]{36})/(edit|pause|resume|delete)$~D',$path,$m))fail('Không tìm thấy chức năng Việc hằng ngày.',404);
 $d=$a->one('SELECT * FROM {{task_templates}} WHERE id=?',[entry_uuid($m[1])]);if(!$d)fail('Không tìm thấy việc hằng ngày.',404);entry_version($b,$d);
 $before=['title'=>$d['title'],'require_photo'=>(int)$d['require_photo'],'active'=>(int)$d['active']];
 // Việc hôm nay chưa xong thì đổi theo mẫu; việc đã xong và các ngày trước giữ nguyên.
 $todayTask=$a->one('SELECT t.id FROM {{tasks}} t JOIN {{task_recipients}} r ON r.task_id=t.id WHERE t.template_id=? AND t.task_day=? AND t.status=\'open\' AND r.done_at IS NULL',[$d['id'],$today]);
 if($m[2]==='edit'){$f=$fields();
  // Đổi người làm (chủ dự án yêu cầu 30/10/2026): việc hôm nay chưa xong chuyển sang người mới; việc đã xong và các ngày trước giữ nguyên.
  $was=(int)$d['user_id'];$uid=$b['user_id']??$was;if(!is_int($uid)||$uid!==$was&&!$a->one("SELECT id FROM {{users}} WHERE id=? AND archived=0 AND status!='inactive' AND role='employee'",[$uid]))fail('Chọn nhân viên đang làm.');
  if($f===['title'=>$d['title'],'require_photo'=>(int)$d['require_photo']]&&$uid===$was)fail('Chưa có thay đổi nào.');
  $a->run('UPDATE {{task_templates}} SET user_id=?,title=?,require_photo=?,updated_by=?,updated_at=?,version=version+1 WHERE id=?',[$uid,$f['title'],$f['require_photo'],$me,now_ms(),$d['id']]);
  if($todayTask){$a->run('UPDATE {{tasks}} SET title=?,require_photo=?,updated_by=?,updated_at=?,version=version+1 WHERE id=?',[$f['title'],$f['require_photo'],$me,now_ms(),$todayTask['id']]);
   if($uid!==$was)$a->run('UPDATE {{task_recipients}} SET user_id=?,seen_at=NULL WHERE task_id=? AND user_id=?',[$uid,$todayTask['id'],$was]);}
  task_audit($a,$d['id'],'template_edited',$before+['user_id'=>$was],$f+['active'=>(int)$d['active'],'user_id'=>$uid]);return [200,['ok'=>true]];}
 // Xóa việc hằng ngày (chủ dự án yêu cầu 01/11/2026): bỏ mẫu, từ mai không giao nữa; việc hôm nay chưa xong thì hủy, việc đã xong và các ngày trước giữ nguyên.
 if($m[2]==='delete'){
  if($todayTask)$a->run("UPDATE {{tasks}} SET status='cancelled',cancel_reason='Đã xóa việc hằng ngày',updated_by=?,updated_at=?,version=version+1 WHERE id=?",[$me,now_ms(),$todayTask['id']]);
  $a->run('DELETE FROM {{task_templates}} WHERE id=?',[$d['id']]);
  task_audit($a,$d['id'],'template_deleted',$before+['user_id'=>(int)$d['user_id']],null);return [200,['ok'=>true]];
 }
 $active=$m[2]==='resume'?1:0;if((int)$d['active']===$active)fail('Việc hằng ngày đã ở trạng thái này.',409);
 $a->run('UPDATE {{task_templates}} SET active=?,updated_by=?,updated_at=?,version=version+1 WHERE id=?',[$active,$me,now_ms(),$d['id']]);
 if(!$active&&$todayTask)$a->run("UPDATE {{tasks}} SET status='cancelled',cancel_reason='Ngừng việc hằng ngày',updated_by=?,updated_at=?,version=version+1 WHERE id=?",[$me,now_ms(),$todayTask['id']]);
 task_audit($a,$d['id'],$active?'template_resumed':'template_paused',['active'=>(int)$d['active']],['active'=>$active]);
 if($active){$a->run("UPDATE {{tasks}} SET status='open',cancel_reason='',updated_by=?,updated_at=?,version=version+1 WHERE template_id=? AND task_day=? AND status='cancelled'",[$me,now_ms(),$d['id'],$today]);daily_tasks_ensure($a,$today,(int)$d['user_id']);}return [200,['ok'=>true]];
}
// Cron mỗi phút: ping Telegram cho người nhận việc mới. Không có token/liên kết thì bỏ qua, không gửi dồn việc cũ.
function task_telegram_tick(App $a,?int $clock=null): void {
 $now=$clock??now_ms();$tg=new Telegram($a);
 $a->run("UPDATE {{task_recipients}} r JOIN {{tasks}} t ON t.id=r.task_id SET r.notify_state='skipped' WHERE r.notify_state='pending' AND (t.status!='open' OR GREATEST(t.created_at,t.updated_at)<?)",[$now-86400000]);
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
const TASK_TG_HELP="Cách giao việc:\n/task Huy, Cường | Dọn phòng chụp 2 | 18:00\n/nhacnho all | Mai họp 8h30\n\n• Người nhận: tên gọi, tên đầy đủ, mã NV hoặc all\n• Phần hạn (không bắt buộc): 18:00 hoặc 18h30 28/10\n• Thêm #anh nếu bắt buộc chụp ảnh khi báo xong (#anh5 = 5 ảnh)\n\nGhi Event log: /log Huy | Nội dung (gõ /log để xem hướng dẫn)";
// Ghi Event log hôm nay; chỉ Admin, Quản lý và nhân viên được Admin cấp quyền ghi Event log. Tên trước, giống /task và /nhacnho
// (chủ dự án yêu cầu 02/11/2026); gõ kiểu cũ nội dung | tên thì bot tự đổi lại:
//   /log Huy | Quên checkout ca 2
//   /log all | Họp rút kinh nghiệm cả studio        (nhật ký chung, không trừ điểm)
//   /log Huy, Cường | Quên chấm ra ca 2 | CC-01     (phần 3: mã luật hoặc điểm 20/100/300)
const LOG_TG_HELP="Cách ghi Event log (tên trước, giống /task):\n/log Huy | Quên checkout ca 2\n/log all | Họp rút kinh nghiệm\n/log Huy, Cường | Quên chấm ra ca 2 | CC-01\n\n• Phần 1: người liên quan — tên gọi, tên đầy đủ, mã NV, nhiều người cách nhau dấu phẩy; all = nhật ký chung studio\n• Phần 2: nội dung sự việc (từ 5 ký tự)\n• Phần 3 (không bắt buộc): mã luật hoặc điểm KPI 20/100/300. Luật thường tự tính theo số lần mắc lỗi đó trong tháng: lần 1 −20, lần 2 −100, lần 3 trở đi −300; luật nặng (từ 100 điểm) trừ đúng điểm của luật. Nhật ký chung không trừ điểm.\n• Ngày ghi nhận: hôm nay. Sửa hoặc xử lý tiếp trên web.";
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
  if(count($parts)<2||$parts[0]===''||$parts[1]==='')fail("Thiếu người liên quan hoặc nội dung.\n\n".LOG_TG_HELP);
  if(count($parts)>3)fail('Dùng tối đa 3 phần: người liên quan | nội dung | luật hoặc điểm.');
  $people=$a->all("SELECT id,name,employee_code FROM {{users}} WHERE archived=0 AND status!='inactive' AND role='employee'");
  $resolve=function(string $text)use($a,$people): array {
   $targets=array_values(array_filter(array_map('trim',preg_split('/\s*(?:,|;|\s+và\s+)\s*/u',$text))));
   $all=count($targets)===1&&in_array(task_fold(ltrim($targets[0],'@')),['all','tat ca','ca studio','moi nguoi','studio','chung'],true);
   $subjects=[];if(!$all)foreach($targets as $tk){$p=task_find_person($a,$people,$tk);$subjects[$p['id']]=$p;}
   return [$all,$subjects];
  };
  // Tên trước (giống /task). Gõ kiểu cũ nội dung | tên thì tự đổi lại.
  try{[$all,$subjects]=$resolve($parts[0]);}
  catch(HttpError $e){try{[$all,$subjects]=$resolve($parts[1]);}catch(HttpError){throw $e;}[$parts[0],$parts[1]]=[$parts[1],$parts[0]];}
  $text=$parts[1];
  $extra=['deduction'=>0];$rule=null;$kpi=$parts[2]??'';
  if($kpi!==''){
   if(preg_match('/^\d+$/D',$kpi)){if(!in_array((int)$kpi,[0,20,100,300],true))fail('Điểm KPI cần là 20, 100 hoặc 300.');$extra=['deduction'=>(int)$kpi];}
   else{$rule=$a->one("SELECT id,code,name,points FROM {{studio_rules}} WHERE code=? AND status='active'",[strtoupper($kpi)]);if(!$rule)fail('Không tìm thấy luật "'.$kpi.'" đang áp dụng. Xem mã trong Luật Studio trên web.');$extra=['rule_id'=>$rule['id']];}
  }
  // Luật thường: log_routes tự đếm số lần trong tháng cho từng người; luật nặng trừ đúng điểm của luật.
  $fixed=$rule&&(int)$rule['points']>=LOG_FIXED_POINTS;
  if($all&&($rule?$fixed:$extra['deduction']!==0))fail('Nhật ký chung studio (all) không trừ điểm. Ghi tên người liên quan để áp '.($rule?'luật '.$rule['code']:'điểm KPI').'.');
  $day=local_day(now_ms());$lines=[];
  foreach($all?[null]:array_keys($subjects) as $uid){
   $id=uuid_v4();log_routes($a,'/event-log',['id'=>$id,'user_id'=>$uid,'event_date'=>$day,'category'=>'other','description'=>$text]+$extra,'POST');
   $row=$a->one('SELECT points,offense FROM {{event_log}} WHERE id=?',[$id]);$pts=-(int)$row['points'];$n=$row['offense']===null?null:(int)$row['offense'];
   $a->run("UPDATE {{event_log_audit}} SET note=? WHERE event_log_id=? AND action='created'",['Ghi qua Telegram bởi người được cấp quyền chỉnh sửa Event log.'.log_points_note($pts,$n,$rule),$id]);
   if($pts)$lines[]=($uid!==null&&count($subjects)>1?$subjects[$uid]['name'].': ':'').($n?'Lần '.$n.' trong tháng ('.LOG_TIER_NAMES[$n].') · ':'').'Trừ '.$pts.' điểm KPI';
  }
  $reply('✅ Đã ghi Event log · '.implode('/',array_reverse(explode('-',$day)))."\n".($all?'Nhật ký chung studio':'Người liên quan: '.implode(', ',array_column($subjects,'name')))."\n«".(mb_strlen($text)>300?mb_substr($text,0,300).'…':$text).'»'.($rule?"\nLuật ".$rule['code'].' · '.$rule['name'].($fixed?' (lỗi nặng, điểm cố định)':''):'').($lines?"\n".implode("\n",$lines):'')."\nXem trên web: ".$a->config['app_url'].'/#eventlog');
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
  $people=$a->all("SELECT id,name,employee_code FROM {{users}} WHERE archived=0 AND status!='inactive' AND role='employee'");
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
// ===== Hỗ trợ (chủ dự án yêu cầu 03/11/2026, tab Việc → Hỗ trợ, #support) =====
// Nhân viên gửi yêu cầu (loại, tiêu đề, nội dung, tối đa 3 ảnh) để bớt nhắn tin riêng; Telegram báo Admin + Quản lý.
// Hai bên trao đổi trong yêu cầu; Admin/Quản lý Duyệt / Từ chối (bắt buộc lý do) / Đã xử lý xong, mỗi bước Telegram báo nhân viên.
// Nhân viên chỉ thấy yêu cầu của mình, rút được khi còn Chờ xử lý. Đường dẫn /tasks/support để nằm cùng file Việc.
const SUPPORT_CATEGORIES=['equipment'=>'Thiết bị / cơ sở vật chất','shift'=>'Đổi ca / nghỉ','pay'=>'Lương / thưởng','other'=>'Khác'];
const SUPPORT_STATUS=['pending'=>'Chờ xử lý','approved'=>'Đã duyệt','rejected'=>'Từ chối','done'=>'Đã xong','withdrawn'=>'Đã rút'];
const SUPPORT_MAX_PHOTOS=3;
function support_manager(App $a): bool {return in_array($a->user['role'],['admin','manager'],true);}
// Số trên nút Việc: Admin/Quản lý = yêu cầu chưa xong (Chờ xử lý, Đã duyệt); nhân viên = yêu cầu có trả lời / đổi trạng thái chưa xem.
function support_pending(App $a): int {
 if(support_manager($a))return (int)$a->scalar("SELECT COUNT(*) FROM {{support_requests}} WHERE status IN ('pending','approved')");
 return (int)$a->scalar('SELECT COUNT(*) FROM {{support_requests}} WHERE user_id=? AND staff_notice_at>staff_seen_at',[$a->user['id']]);
}
// Test gắn Telegram giả lập qua support_telegram($tg); chạy thật thì tạo Telegram mới.
function support_telegram(?Telegram $set=null): ?Telegram {static $tg=null;if($set!==null)$tg=$set;return $tg;}
function support_notify(App $a,?int $to,string $text,string $id): int {
 if(!class_exists('Telegram'))return 0;$tg=support_telegram()??new Telegram($a);if(!$tg->configured())return 0;$sent=0;
 $rows=$to===null?$a->all("SELECT l.chat_id FROM {{telegram_links}} l JOIN {{users}} u ON u.id=l.user_id WHERE u.role IN ('admin','manager') AND u.archived=0 AND u.status!='inactive' AND u.id!=? ORDER BY u.id",[$a->user['id']]):$a->all('SELECT chat_id FROM {{telegram_links}} WHERE user_id=?',[$to]);
 foreach($rows as $m){try{$tg->call('sendMessage',['chat_id'=>$m['chat_id'],'text'=>$text,'disable_web_page_preview'=>true]+$tg->button('Mở yêu cầu',$a->config['app_url'].'/#support:'.$id));$sent++;}catch(Throwable $e){$a->meta('telegram_error',$e->getMessage());}}
 return $sent;
}
function support_clip(string $text,int $max=300): string {return text_length($text)>$max?mb_substr($text,0,$max,'UTF-8').'…':$text;}
function support_find(App $a,string $id): array {
 $r=$a->one('SELECT s.*,u.name FROM {{support_requests}} s JOIN {{users}} u ON u.id=s.user_id WHERE s.id=?',[$id]);
 if(!$r||(!support_manager($a)&&(int)$r['user_id']!==(int)$a->user['id']))fail('Không tìm thấy yêu cầu.',404);
 return $r;
}
function support_row(array $r): array {
 foreach(['user_id','photos','created_at','updated_at','staff_seen_at','staff_notice_at','closed_at','version','messages','last_at'] as $k)if(isset($r[$k]))$r[$k]=(int)$r[$k];
 $r['unread']=$r['staff_notice_at']>$r['staff_seen_at'];return $r;
}
function support_routes(App $a,string $path,array $b,string $method): ?array {
 $manager=support_manager($a);$me=(int)$a->user['id'];$now=now_ms();
 if($path==='/tasks/support'&&$method==='GET'){
  $rows=$a->all("SELECT s.id,s.user_id,u.name,s.category,s.title,s.body,s.status,s.photos,s.created_at,s.updated_at,s.staff_seen_at,s.staff_notice_at,s.closed_at,s.version,(SELECT COUNT(*) FROM {{support_messages}} m WHERE m.request_id=s.id AND m.kind='comment') messages,GREATEST(s.updated_at,s.created_at) last_at FROM {{support_requests}} s JOIN {{users}} u ON u.id=s.user_id".($manager?'':' WHERE s.user_id=?').' ORDER BY s.created_at DESC LIMIT 300',$manager?[]:[$me]);
  return [200,['rows'=>array_map('support_row',$rows),'pending'=>support_pending($a),'can_manage'=>$manager,'can_create'=>$a->user['role']==='employee','categories'=>SUPPORT_CATEGORIES,'today'=>local_day($now)]];
 }
 if($path==='/tasks/support'&&$method==='POST'){
  if($a->user['role']!=='employee')fail('Chỉ nhân viên gửi yêu cầu hỗ trợ.',403);
  $id=entry_uuid($b['id']??null);if($a->one('SELECT id FROM {{support_requests}} WHERE id=?',[$id]))return [200,['ok'=>true,'id'=>$id,'already_saved'=>true]];
  $category=$b['category']??'';if(!is_string($category)||!isset(SUPPORT_CATEGORIES[$category]))fail('Chọn loại yêu cầu.');
  $title=input_text($b['title']??'','Tiêu đề',3,150);$body=input_text($b['body']??'','Nội dung',5,3000);
  $list=$b['photos']??[];if(!is_array($list)||!array_is_list($list)||count($list)>SUPPORT_MAX_PHOTOS)fail('Gửi tối đa '.SUPPORT_MAX_PHOTOS.' ảnh.');
  $photos=array_values(array_filter(array_map('task_photo',$list),fn($x)=>$x!==null));
  $a->tx(function()use($a,$id,$me,$category,$title,$body,$photos,$now){
   $a->run("INSERT INTO {{support_requests}}(id,user_id,category,title,body,status,photos,created_at,updated_at,staff_seen_at,staff_notice_at,version) VALUES(?,?,?,?,?,'pending',?,?,?,?,0,1)",[$id,$me,$category,$title,$body,count($photos),$now,$now,$now]);
   foreach($photos as $i=>$p)$a->run('INSERT INTO {{support_photos}}(request_id,idx,photo_jpeg) VALUES(?,?,?)',[$id,$i+1,$p]);
  });
  $sent=support_notify($a,null,"🆘 Yêu cầu hỗ trợ mới\n👤 {$a->user['name']}\n📂 ".SUPPORT_CATEGORIES[$category]."\n📝 $title\n\n".support_clip($body).($photos?"\n📷 ".count($photos).' ảnh':''),$id);
  return [201,['ok'=>true,'id'=>$id,'notified'=>$sent]];
 }
 if(!preg_match('~^/tasks/support/([a-f0-9-]{36})(?:/(photo/(\d)|reply|status|withdraw))?$~D',$path,$m))return null;
 $id=entry_uuid($m[1]);$r=support_find($a,$id);$owner=(int)$r['user_id']===$me;$action=$m[2]??'';
 if($action===''&&$method==='GET'){
  $messages=$a->all('SELECT m.id,m.user_id,u.name,u.role,m.kind,m.status,m.body,m.created_at FROM {{support_messages}} m LEFT JOIN {{users}} u ON u.id=m.user_id WHERE m.request_id=? ORDER BY m.id',[$id]);
  if($owner&&$r['staff_notice_at']>$r['staff_seen_at']){$a->run('UPDATE {{support_requests}} SET staff_seen_at=? WHERE id=?',[$now,$id]);$r['staff_seen_at']=$now;}
  unset($r['closed_by']);return [200,['request'=>support_row($r),'messages'=>array_map(fn($x)=>['id'=>(int)$x['id'],'user_id'=>$x['user_id']===null?null:(int)$x['user_id'],'name'=>$x['name'],'staff'=>$x['role']==='employee','kind'=>$x['kind'],'status'=>$x['status'],'body'=>$x['body'],'created_at'=>(int)$x['created_at']],$messages),'pending'=>support_pending($a)]];
 }
 if(str_starts_with($action,'photo/')&&$method==='GET'){
  $photo=$a->scalar('SELECT photo_jpeg FROM {{support_photos}} WHERE request_id=? AND idx=?',[$id,(int)$m[3]]);if(!$photo)fail('Không có ảnh.',404);
  header('Content-Type: image/jpeg');header('Content-Disposition: inline');header('Cache-Control: private, max-age=86400');echo $photo;exit;
 }
 if($method!=='POST')return null;
 if($action==='reply'){
  if(!$owner&&!$manager)fail('Bạn không trả lời được yêu cầu này.',403);if($r['status']==='withdrawn')fail('Yêu cầu đã rút.',409);
  $body=input_text($b['body']??'','Nội dung trả lời',1,2000);
  $a->tx(function()use($a,$id,$me,$body,$now,$owner){$a->run("INSERT INTO {{support_messages}}(request_id,user_id,kind,status,body,created_at) VALUES(?,?,'comment',NULL,?,?)",[$id,$me,$body,$now]);
   $a->run('UPDATE {{support_requests}} SET updated_at=?'.($owner?',staff_seen_at=?':',staff_notice_at=?').' WHERE id=?',[$now,$now,$id]);});
  $owner?support_notify($a,null,"💬 {$a->user['name']} trả lời yêu cầu hỗ trợ\n📝 {$r['title']}\n\n".support_clip($body),$id):support_notify($a,(int)$r['user_id'],"💬 {$a->user['name']} trả lời yêu cầu của bạn\n📝 {$r['title']}\n\n".support_clip($body),$id);
  return [201,['ok'=>true]];
 }
 if($action==='withdraw'){
  if(!$owner)fail('Chỉ người gửi rút được yêu cầu.',403);if($r['status']!=='pending')fail('Chỉ rút được yêu cầu đang Chờ xử lý.',409);
  $a->tx(function()use($a,$id,$me,$now){$a->run("UPDATE {{support_requests}} SET status='withdrawn',updated_at=?,staff_seen_at=?,closed_by=?,closed_at=?,version=version+1 WHERE id=? AND status='pending'",[$now,$now,$me,$now,$id]);
   $a->run("INSERT INTO {{support_messages}}(request_id,user_id,kind,status,body,created_at) VALUES(?,?,'status','withdrawn','',?)",[$id,$me,$now]);});
  support_notify($a,null,"↩️ {$a->user['name']} đã rút yêu cầu hỗ trợ\n📝 {$r['title']}",$id);
  return [200,['ok'=>true]];
 }
 if($action==='status'){
  if(!$manager)fail('Chỉ Admin hoặc Quản lý xử lý yêu cầu.',403);
  $to=$b['status']??'';$allowed=['pending'=>['approved','rejected','done'],'approved'=>['rejected','done']][$r['status']]??[];
  if(!is_string($to)||!in_array($to,$allowed,true))fail('Yêu cầu đang '.SUPPORT_STATUS[$r['status']].', không chuyển được sang trạng thái này.',409);
  $note=input_text($b['note']??'','Ghi chú',$to==='rejected'?5:0,1000);if(($b['version']??null)!==(int)$r['version'])fail('Yêu cầu vừa được người khác cập nhật. Mở lại để xem bản mới.',409);
  $a->tx(function()use($a,$id,$me,$to,$note,$now,$r){$closed=in_array($to,['rejected','done'],true);
   $a->run('UPDATE {{support_requests}} SET status=?,updated_at=?,staff_notice_at=?,closed_by=?,closed_at=?,version=version+1 WHERE id=? AND version=?',[$to,$now,$now,$closed?$me:null,$closed?$now:null,$id,(int)$r['version']]);
   $a->run("INSERT INTO {{support_messages}}(request_id,user_id,kind,status,body,created_at) VALUES(?,?,'status',?,?,?)",[$id,$me,$to,$note,$now]);});
  $icon=['approved'=>'✅','rejected'=>'❌','done'=>'✔️'][$to];
  support_notify($a,(int)$r['user_id'],"$icon Yêu cầu hỗ trợ của bạn: ".SUPPORT_STATUS[$to]."\n📝 {$r['title']}\n👤 Xử lý: {$a->user['name']}".($note!==''?"\n\n".support_clip($note):''),$id);
  return [200,['ok'=>true,'status'=>$to]];
 }
 return null;
}
