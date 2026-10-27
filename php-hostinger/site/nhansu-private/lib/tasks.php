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
function task_audit(App $a,string $id,string $action,?array $before,?array $after,string $note='',?int $actor=null): void {
 $a->run('INSERT INTO {{task_audit}}(task_id,actor_id,action,before_json,after_json,note,time) VALUES(?,?,?,?,?,?,?)',[$id,$actor??$a->user['id'],$action,$before===null?null:json_encode($before,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),$after===null?null:json_encode($after,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),$note,now_ms()]);
}
// Tạo việc dùng chung cho web và lệnh Telegram; $me là người giao (Admin/Quản lý).
function task_create(App $a,int $me,array $b,string $note=''): array {
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
  task_audit($a,$id,'created',null,['kind'=>$kind,'title'=>$title,'body'=>$body,'due_at'=>$due,'require_photo'=>(bool)$photo,'audience'=>$audience,'recipients'=>$ids],$note,$me);
  return ['id'=>$id,'recipients'=>$ids,'kind'=>$kind,'title'=>$title,'due_at'=>$due,'require_photo'=>(bool)$photo,'audience'=>$audience];
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
  $a->requireManager();$r=task_create($a,$me,$b);
  return [201,['id'=>$r['id'],'recipients'=>count($r['recipients'])]];
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
   $tg->call('sendMessage',['chat_id'=>$r['chat_id'],'text'=>$text,'disable_web_page_preview'=>true]+$tg->button('Mở mục Việc',$a->config['app_url'].'/#tasks'));
   $a->run("UPDATE {{task_recipients}} SET notify_state='sent',notified_at=?,notify_error=NULL WHERE task_id=? AND user_id=?",[$now,$r['task_id'],$r['user_id']]);
  }catch(Throwable $e){$a->run("UPDATE {{task_recipients}} SET notify_state='failed',notify_error=? WHERE task_id=? AND user_id=?",[$e->getMessage(),$r['task_id'],$r['user_id']]);}
 }
}
// Lệnh Telegram trong nhóm hoặc chat riêng:
//   /task Huy, Cường | Dọn phòng chụp 2 | 18:00 28/10 #anh
//   /nhacnho all | Mai họp 8h30
// Chỉ Telegram đã liên kết với tài khoản Admin/Quản lý mới được giao.
const TASK_TG_HELP="Cách giao việc:\n/task Huy, Cường | Dọn phòng chụp 2 | 18:00\n/nhacnho all | Mai họp 8h30\n\n• Người nhận: tên gọi, tên đầy đủ, mã NV hoặc all\n• Phần hạn (không bắt buộc): 18:00 hoặc 18h30 28/10\n• Thêm #anh nếu bắt buộc chụp ảnh khi báo xong";
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
  $photo=(bool)preg_match('/(^|\s)#anh\b/iu',$cmd[2]);
  $content=trim(preg_replace('/(^|\s)#anh\b/iu','',$parts[1]));$dueText=trim(preg_replace('/(^|\s)#anh\b/iu','',$parts[2]??''));
  if($content==='')fail('Thiếu nội dung.');
  $kind=$cmd[1]==='task'?'task':'notice';
  $people=$a->all("SELECT id,name,employee_code FROM {{users}} WHERE archived=0 AND status!='inactive'");
  $targets=array_values(array_filter(array_map('trim',preg_split('/\s*(?:,|;|\s+và\s+)\s*/u',$parts[0]))));
  $all=count($targets)===1&&in_array(task_fold(ltrim($targets[0],'@')),['all','tat ca','ca studio','moi nguoi'],true);
  $ids=[];if(!$all)foreach($targets as $tk)$ids[]=task_find_person($a,$people,$tk)['id'];
  $title=mb_strlen($content)>150?mb_substr($content,0,147).'…':$content;
  $r=task_create($a,$sender['id'],['kind'=>$kind,'title'=>$title,'body'=>mb_strlen($content)>150?$content:'','due_at'=>$kind==='task'?task_parse_due($dueText,now_ms()):null,'require_photo'=>$photo,'audience'=>$all?'all':'people','user_ids'=>array_values(array_unique($ids))],'Tạo qua Telegram');
  $names=$all?'tất cả nhân viên ('.count($r['recipients']).' người)':implode(', ',array_column(array_filter($people,fn($p)=>in_array($p['id'],$r['recipients'],true)),'name'));
  $reply('✅ Đã giao '.($kind==='task'?'Task':'Nhắc nhở').' cho '.$names."\n«".$r['title'].'»'.($r['due_at']?"\nHạn: ".date('H:i d/m/Y',intdiv($r['due_at'],1000)):'').($r['require_photo']?"\n📷 Bắt buộc chụp ảnh khi báo xong":'')."\nNgười nhận đã liên kết Telegram sẽ được nhắn riêng. Xem tiến độ: ".$a->config['app_url'].'/#tasks');
 }catch(HttpError $e){$reply('⚠️ '.$e->getMessage());}
 return true;
}
