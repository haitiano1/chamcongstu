<?php
declare(strict_types=1);
// Biên bản họp (chủ dự án yêu cầu 02/11/2026), tab Studio → Biên bản họp.
// Admin, Quản lý và Nhân viên được Admin tick "Sửa Biên bản họp" (meeting_editors) lập và sửa biên bản.
// Bấm Xác nhận: Telegram báo mọi người có mặt; mỗi người vào web bấm "Đã đọc và xác nhận hiểu rõ nội dung" (lưu giờ bấm).
// Mọi người có mặt xác nhận xong thì biên bản tự khóa; đã khóa chỉ Admin sửa. Sửa nội dung sau khi gửi thì mọi người có mặt xác nhận lại.
// Nháp chỉ người lập biên bản thấy; biên bản đã gửi mọi tài khoản (trừ Makeup) xem được. Xóa mềm, giữ lịch sử trước/sau.
const MEETING_CONTENT=['title','meeting_day','start_min','end_min','purpose','opinions','actions_json','conclusion'];

function meeting_permissions(App $a): array {
 $role=$a->user['role'];$full=in_array($role,['admin','manager'],true);
 $granted=$role==='employee'&&(bool)$a->one('SELECT user_id FROM {{meeting_editors}} WHERE user_id=?',[$a->user['id']]);
 return ['can_edit'=>$full||$granted,'is_admin'=>$role==='admin','can_grant'=>$role==='admin'];
}
function meeting_people(App $a): array {return $a->all("SELECT id,name,role FROM {{users}} WHERE archived=0 AND status!='inactive' AND role IN ('admin','manager','employee') ORDER BY name");}
function meeting_minutes(mixed $v,string $label): int {if(!is_string($v)||!preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/D',$v,$m))fail($label.' không hợp lệ (giờ:phút).');return (int)$m[1]*60+(int)$m[2];}
function meeting_hm(int $m): string {return sprintf('%02d:%02d',intdiv($m,60),$m%60);}
function meeting_row(App $a,mixed $id): array {$row=$a->one("SELECT * FROM {{meetings}} WHERE id=? AND status!='deleted'",[entry_uuid($id)]);if(!$row)fail('Không tìm thấy biên bản hoặc biên bản đã bị xóa.',404);return $row;}
function meeting_attendees(App $a,string $id): array {return $a->all('SELECT x.user_id,x.present,x.ack_at,x.ack_revision,u.name FROM {{meeting_attendees}} x JOIN {{users}} u ON u.id=x.user_id WHERE x.meeting_id=? ORDER BY x.present DESC,u.name',[$id]);}
function meeting_snapshot(App $a,array $row): array {return array_intersect_key($row,array_flip(MEETING_CONTENT))+['attendees'=>array_map(fn($x)=>['name'=>$x['name'],'present'=>(bool)$x['present']],meeting_attendees($a,$row['id']))];}
function meeting_audit(App $a,string $id,string $action,?array $before,?array $after,string $note='',?int $actor=-1): void {
 $a->run('INSERT INTO {{meeting_audit}}(meeting_id,actor_id,action,before_json,after_json,note,time) VALUES(?,?,?,?,?,?,?)',[$id,$actor===-1?$a->user['id']:$actor,$action,$before===null?null:json_encode($before,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),$after===null?null:json_encode($after,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),$note,now_ms()]);
}
// Đọc form. Nháp chỉ cần tên + ngày + giờ; Xác nhận cần thêm người có mặt, mục đích, kết luận.
// $known: người đã có trong biên bản (có thể đã nghỉ) vẫn được giữ khi sửa biên bản cũ.
function meeting_input(App $a,array $b,bool $publish,array $known=[]): array {
 $title=input_text($b['title']??null,'Tên / Chủ đề cuộc họp',2,200);$day=valid_day($b['meeting_day']??null);
 $start=meeting_minutes($b['start']??null,'Giờ bắt đầu');$end=meeting_minutes($b['end']??null,'Giờ kết thúc');if($end<=$start)fail('Giờ kết thúc phải sau giờ bắt đầu.');
 $purpose=input_text($b['purpose']??'','Mục đích / Vấn đề đưa ra',$publish?2:0,5000);
 $opinions=input_text($b['opinions']??'','Ý kiến & Góp ý của nhân viên',0,10000);
 $conclusion=input_text($b['conclusion']??'','Kết luận & Quyết định cuối cùng',$publish?2:0,5000);
 $people=array_column(meeting_people($a),'name','id')+$known;
 $list=$b['actions']??[];if(!is_array($list)||!array_is_list($list)||count($list)>30)fail('Phương án xử lý tối đa 30 dòng.');$actions=[];
 foreach($list as $i=>$x){
  if(!is_array($x))fail('Phương án xử lý không hợp lệ.');$n=$i+1;$text=input_text($x['text']??'','Phương án dòng '.$n,0,1000);$uid=$x['user_id']??null;$due=$x['due']??'';
  if($uid==='')$uid=null;if($text===''&&$uid===null&&$due==='')continue;if($text==='')fail('Phương án dòng '.$n.' chưa có nội dung.');
  if($uid!==null&&(!is_int($uid)||!isset($people[$uid])))fail('Người phụ trách dòng '.$n.' không hợp lệ. Tải lại.');
  if($due!=='')$due=valid_day($due);
  $actions[]=['text'=>$text,'user_id'=>$uid,'name'=>$uid!==null?$people[$uid]:'','due'=>$due];
 }
 $rows=$b['attendees']??[];if(!is_array($rows)||!array_is_list($rows))fail('Thành phần tham gia không hợp lệ.');$att=[];
 foreach($rows as $r){$uid=is_array($r)?($r['user_id']??null):null;if(!is_int($uid)||!isset($people[$uid])||!is_bool($r['present']??null))fail('Thành phần tham gia không hợp lệ. Tải lại.');$att[$uid]=$r['present'];}
 if($publish&&!in_array(true,$att,true))fail('Chọn ít nhất 1 người có mặt.');
 return ['row'=>['title'=>$title,'meeting_day'=>$day,'start_min'=>$start,'end_min'=>$end,'purpose'=>$purpose,'opinions'=>$opinions,'actions_json'=>json_encode($actions,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),'conclusion'=>$conclusion],'attendees'=>$att];
}
// Ghi danh sách có mặt/vắng mặt. Trả về người mới chuyển sang có mặt (cần đọc và xác nhận).
// Người chuyển có mặt ↔ vắng mặt thì bỏ lượt xác nhận cũ.
function meeting_save_attendees(App $a,string $id,array $att): array {
 $old=array_column($a->all('SELECT user_id,present FROM {{meeting_attendees}} WHERE meeting_id=?',[$id]),'present','user_id');$fresh=[];
 foreach($att as $uid=>$present){
  if(!array_key_exists($uid,$old))$a->run('INSERT INTO {{meeting_attendees}}(meeting_id,user_id,present) VALUES(?,?,?)',[$id,$uid,$present?1:0]);
  elseif((bool)$old[$uid]!==$present)$a->run("UPDATE {{meeting_attendees}} SET present=?,ack_at=NULL,ack_revision=NULL,notify_state=IF(notify_state='pending','skipped',notify_state) WHERE meeting_id=? AND user_id=?",[$present?1:0,$id,$uid]);
  if($present&&empty($old[$uid]))$fresh[]=$uid;
 }
 foreach(array_diff(array_keys($old),array_keys($att)) as $uid)$a->run('DELETE FROM {{meeting_attendees}} WHERE meeting_id=? AND user_id=?',[$id,$uid]);
 return $fresh;
}
// Xếp Telegram cho người có mặt chưa xác nhận ($only: chỉ những người này). Cron gửi (meeting_telegram_tick).
function meeting_queue(App $a,string $id,string $kind,?array $only=null,int $gap=0): int {
 $rows=$a->all('SELECT user_id,queued_at FROM {{meeting_attendees}} WHERE meeting_id=? AND present=1 AND ack_at IS NULL',[$id]);$n=0;
 foreach($rows as $r){if($only!==null&&!in_array((int)$r['user_id'],$only,true))continue;if($gap&&$r['queued_at']&&now_ms()-(int)$r['queued_at']<$gap)continue;
  $a->run("UPDATE {{meeting_attendees}} SET notify_state='pending',notify_kind=?,queued_at=?,notify_error=NULL WHERE meeting_id=? AND user_id=?",[$kind,now_ms(),$id,$r['user_id']]);$n++;}
 return $n;
}
function meeting_ack(App $a,array $row,int $user,string $note=''): void {
 if(!$a->run('UPDATE {{meeting_attendees}} SET ack_at=?,ack_revision=? WHERE meeting_id=? AND user_id=? AND present=1 AND ack_at IS NULL',[now_ms(),(int)$row['revision'],$row['id'],$user])->rowCount())return;
 meeting_audit($a,$row['id'],'ack',null,['revision'=>(int)$row['revision']],$note,$user);
}
// Mọi người có mặt đã xác nhận bản hiện tại thì tự khóa: từ đó chỉ Admin sửa.
function meeting_autolock(App $a,string $id): bool {
 $row=$a->one('SELECT * FROM {{meetings}} WHERE id=?',[$id]);if(!$row||$row['status']!=='published')return false;
 $left=(int)$a->scalar('SELECT COUNT(*) FROM {{meeting_attendees}} WHERE meeting_id=? AND present=1 AND ack_at IS NULL',[$id]);
 if($left||!(int)$a->scalar('SELECT COUNT(*) FROM {{meeting_attendees}} WHERE meeting_id=? AND present=1',[$id]))return false;
 $a->run("UPDATE {{meetings}} SET status='locked',locked_by=NULL,locked_at=?,version=version+1 WHERE id=?",[now_ms(),$id]);
 meeting_audit($a,$id,'locked',['status'=>'published'],['status'=>'locked'],'Tự khóa: mọi người có mặt đã xác nhận.',null);return true;
}
// Biên bản chờ người này xác nhận (hiện ở Chấm công và đầu trang Biên bản họp).
function meeting_pending_for(App $a,int $user): array {
 return array_map(fn($r)=>['id'=>$r['id'],'title'=>$r['title'],'meeting_day'=>$r['meeting_day']],$a->all("SELECT m.id,m.title,m.meeting_day FROM {{meetings}} m JOIN {{meeting_attendees}} x ON x.meeting_id=m.id WHERE x.user_id=? AND x.present=1 AND x.ack_at IS NULL AND m.status IN ('published','locked') ORDER BY m.meeting_day DESC,m.start_min DESC LIMIT 20",[$user]));
}
function meeting_list(App $a,array $p): array {
 $me=(int)$a->user['id'];$mine="EXISTS(SELECT 1 FROM {{meeting_attendees}} own WHERE own.meeting_id=m.id AND own.user_id=? AND own.present=1 AND own.ack_at IS NULL)";
 $rows=$a->all("SELECT m.*,(SELECT name FROM {{users}} WHERE id=m.created_by) creator_name,(SELECT name FROM {{users}} WHERE id=m.updated_by) updater_name,(SELECT name FROM {{users}} WHERE id=m.published_by) publisher_name,(SELECT name FROM {{users}} WHERE id=m.locked_by) locker_name,$mine mine_pending FROM {{meetings}} m WHERE m.status IN ('published','locked'".($p['can_edit']?",'draft'":'').") ORDER BY $mine DESC,m.meeting_day DESC,m.start_min DESC LIMIT 200",[$me,$me]);
 $people=[];if($rows){$ids=array_column($rows,'id');foreach($a->all('SELECT x.meeting_id,x.user_id,x.present,x.ack_at,x.ack_revision,x.notify_state,x.notified_at,u.name FROM {{meeting_attendees}} x JOIN {{users}} u ON u.id=x.user_id WHERE x.meeting_id IN ('.implode(',',array_fill(0,count($ids),'?')).') ORDER BY u.name',$ids) as $x)$people[$x['meeting_id']][]=['user_id'=>(int)$x['user_id'],'name'=>$x['name'],'present'=>(bool)$x['present'],'ack_at'=>$x['ack_at']!==null?(int)$x['ack_at']:null,'notify'=>$x['notify_state'],'notified_at'=>$x['notified_at']!==null?(int)$x['notified_at']:null];}
 foreach($rows as &$r){$r['actions']=json_decode($r['actions_json'],true)?:[];unset($r['actions_json']);$r['attendees']=$people[$r['id']]??[];$r['start']=meeting_hm((int)$r['start_min']);$r['end']=meeting_hm((int)$r['end_min']);$r['mine_pending']=(bool)$r['mine_pending'];
  $r['can_edit']=$r['status']==='locked'?$p['is_admin']:$p['can_edit'];$r['can_delete']=$r['status']==='draft'?$p['can_edit']:$p['is_admin'];}
 unset($r);return $rows;
}
function meeting_routes(App $a,string $path,array $b,string $method): ?array {
 if($path!=='/meetings'&&!str_starts_with($path,'/meetings/'))return null;
 $p=meeting_permissions($a);$me=(int)$a->user['id'];
 if($path==='/meetings'&&$method==='GET')return [200,['rows'=>meeting_list($a,$p),'permissions'=>$p,'me'=>$me,'people'=>$p['can_edit']?meeting_people($a):[]]];
 if(preg_match('~^/meetings/([a-f0-9-]{36})/audit$~D',$path,$m)&&$method==='GET'){
  $row=meeting_row($a,$m[1]);if($row['status']==='draft'&&!$p['can_edit'])fail('Không tìm thấy biên bản.',404);
  return [200,['rows'=>$a->all('SELECT x.action,x.before_json,x.after_json,x.note,x.time,u.name actor_name FROM {{meeting_audit}} x LEFT JOIN {{users}} u ON u.id=x.actor_id WHERE x.meeting_id=? ORDER BY x.id DESC LIMIT 300',[$row['id']])]];
 }
 if($method!=='POST')fail('Không tìm thấy chức năng Biên bản họp.',404);
 // Người có mặt bấm "Đã đọc và xác nhận hiểu rõ nội dung": phải đúng bản đang xem (revision), lưu giờ bấm.
 if(preg_match('~^/meetings/([a-f0-9-]{36})/ack$~D',$path,$m)){
  $row=meeting_row($a,$m[1]);if(!in_array($row['status'],['published','locked'],true))fail('Biên bản chưa gửi xác nhận.',409);
  if(!is_int($b['revision']??null)||$b['revision']!==(int)$row['revision'])fail('Biên bản vừa được sửa. Tải lại và đọc bản mới trước khi xác nhận.',409);
  $x=$a->one('SELECT present,ack_at FROM {{meeting_attendees}} WHERE meeting_id=? AND user_id=?',[$row['id'],$me]);if(!$x||!$x['present'])fail('Bạn không có trong danh sách có mặt của cuộc họp này.',403);
  if($x['ack_at']===null){meeting_ack($a,$row,$me);$locked=meeting_autolock($a,$row['id']);}
  return [200,['ok'=>true,'locked'=>$locked??false]];
 }
 if(!$p['can_edit'])fail('Bạn chưa được Admin cấp quyền Sửa Biên bản họp.',403);
 if($path==='/meetings'){
  $publish=($b['publish']??false)===true;$in=meeting_input($a,$b,$publish);$id=uuid_v4();$now=now_ms();$r=$in['row'];
  $a->run('INSERT INTO {{meetings}}(id,title,meeting_day,start_min,end_min,purpose,opinions,actions_json,conclusion,status,revision,published_by,published_at,created_by,created_at,updated_by,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',[$id,$r['title'],$r['meeting_day'],$r['start_min'],$r['end_min'],$r['purpose'],$r['opinions'],$r['actions_json'],$r['conclusion'],$publish?'published':'draft',$publish?1:0,$publish?$me:null,$publish?$now:null,$me,$now,$me,$now]);
  meeting_save_attendees($a,$id,$in['attendees']);$row=$a->one('SELECT * FROM {{meetings}} WHERE id=?',[$id]);
  meeting_audit($a,$id,$publish?'published':'created',null,meeting_snapshot($a,$row));
  if($publish){meeting_ack($a,$row,$me,'Người lập biên bản');$sent=meeting_queue($a,$id,'new');$locked=meeting_autolock($a,$id);}
  return [201,['id'=>$id,'queued'=>$sent??0,'locked'=>$locked??false]];
 }
 if(!preg_match('~^/meetings/([a-f0-9-]{36})/(edit|lock|unlock|remind|delete)$~D',$path,$m))fail('Không tìm thấy chức năng Biên bản họp.',404);
 $row=meeting_row($a,$m[1]);$id=$row['id'];$action=$m[2];
 if($action==='remind'){
  if(!in_array($row['status'],['published','locked'],true))fail('Biên bản chưa gửi xác nhận.',409);
  $n=meeting_queue($a,$id,'remind',null,600000);if($n)meeting_audit($a,$id,'reminded',null,['count'=>$n]);
  return [200,['ok'=>true,'queued'=>$n]];
 }
 entry_version($b,$row);
 if($action==='lock'){if($row['status']!=='published')fail('Chỉ khóa được biên bản đang chờ xác nhận.',409);
  $a->run("UPDATE {{meetings}} SET status='locked',locked_by=?,locked_at=?,version=version+1 WHERE id=?",[$me,now_ms(),$id]);meeting_audit($a,$id,'locked',['status'=>'published'],['status'=>'locked'],input_text($b['note']??'','Ghi chú',0,500));return [200,['ok'=>true]];}
 if($action==='unlock'){if(!$p['is_admin'])fail('Chỉ Admin mở khóa biên bản.',403);if($row['status']!=='locked')fail('Biên bản chưa khóa.',409);
  $a->run("UPDATE {{meetings}} SET status='published',locked_by=NULL,locked_at=NULL,version=version+1 WHERE id=?",[$id]);meeting_audit($a,$id,'unlocked',['status'=>'locked'],['status'=>'published'],input_text($b['note']??'','Lý do mở khóa',0,500));return [200,['ok'=>true]];}
 if($action==='delete'){
  if($row['status']!=='draft'&&!$p['is_admin'])fail('Biên bản đã gửi chỉ Admin xóa được.',403);
  $reason=input_text($b['reason']??'','Lý do xóa',$row['status']==='draft'?0:5,500);
  $a->run("UPDATE {{meetings}} SET status='deleted',updated_by=?,updated_at=?,version=version+1 WHERE id=?",[$me,now_ms(),$id]);
  $a->run("UPDATE {{meeting_attendees}} SET notify_state='skipped' WHERE meeting_id=? AND notify_state='pending'",[$id]);
  meeting_audit($a,$id,'deleted',['status'=>$row['status']],['status'=>'deleted'],$reason);return [200,['ok'=>true]];
 }
 // Sửa: nháp và biên bản chờ xác nhận thì người có quyền sửa; đã khóa chỉ Admin.
 if($row['status']==='locked'&&!$p['is_admin'])fail('Biên bản đã khóa. Chỉ Admin được sửa.',403);
 $known=array_column(meeting_attendees($a,$id),'name','user_id');foreach(json_decode($row['actions_json'],true)?:[] as $x)if($x['user_id']!==null)$known[(int)$x['user_id']]=$x['name'];
 $draft=$row['status']==='draft';$publish=$draft&&($b['publish']??false)===true;$in=meeting_input($a,$b,$publish||!$draft,$known);$r=$in['row'];
 $before=meeting_snapshot($a,$row);$content=array_intersect_key($row,$r);$content['start_min']=(int)$content['start_min'];$content['end_min']=(int)$content['end_min'];$changed=$content!==$r;
 $attendeesChanged=array_map(fn($x)=>(bool)$x['present'],array_column(meeting_attendees($a,$id),null,'user_id'))!=$in['attendees'];
 if(!$changed&&!$attendeesChanged&&!$publish)fail('Chưa có thay đổi nào.');
 $revision=(int)$row['revision']+($publish||(!$draft&&$changed)?1:0);$now=now_ms();
 $a->run('UPDATE {{meetings}} SET title=?,meeting_day=?,start_min=?,end_min=?,purpose=?,opinions=?,actions_json=?,conclusion=?,status=?,revision=?,published_by=?,published_at=?,updated_by=?,updated_at=?,version=version+1 WHERE id=?',[$r['title'],$r['meeting_day'],$r['start_min'],$r['end_min'],$r['purpose'],$r['opinions'],$r['actions_json'],$r['conclusion'],$publish?'published':$row['status'],$revision,$publish?$me:$row['published_by'],$publish?$now:$row['published_at'],$me,$now,$id]);
 $fresh=meeting_save_attendees($a,$id,$in['attendees']);$row=$a->one('SELECT * FROM {{meetings}} WHERE id=?',[$id]);
 meeting_audit($a,$id,$publish?'published':'edited',$before,meeting_snapshot($a,$row),input_text($b['note']??'','Ghi chú thay đổi',0,500));
 $queued=0;$reset=false;
 if(!$draft&&$changed){
  // Nội dung đổi sau khi gửi: mọi người có mặt phải đọc lại và xác nhận bản mới.
  $a->run('UPDATE {{meeting_attendees}} SET ack_at=NULL,ack_revision=NULL WHERE meeting_id=? AND present=1',[$id]);$reset=true;
  meeting_ack($a,$row,$me,'Người sửa biên bản');$queued=meeting_queue($a,$id,'edit');
 }elseif($publish){meeting_ack($a,$row,$me,'Người lập biên bản');$queued=meeting_queue($a,$id,'new');}
 elseif(!$draft&&$fresh){if(in_array($me,$fresh,true))meeting_ack($a,$row,$me,'Người sửa biên bản');$queued=meeting_queue($a,$id,'new',$fresh);}
 $locked=!$draft&&meeting_autolock($a,$id);
 return [200,['ok'=>true,'queued'=>$queued,'reset'=>$reset,'locked'=>$locked]];
}
// Cron mỗi phút: gửi Telegram cho người có mặt cần đọc biên bản (mới gửi, đã sửa, nhắc lại). Chưa liên kết Telegram thì bỏ qua.
function meeting_telegram_tick(App $a,?int $clock=null,?Telegram $tg=null): void {
 $now=$clock??now_ms();
 $a->run("UPDATE {{meeting_attendees}} x JOIN {{meetings}} m ON m.id=x.meeting_id SET x.notify_state='skipped' WHERE x.notify_state='pending' AND (m.status NOT IN ('published','locked') OR x.present=0 OR x.ack_at IS NOT NULL OR x.queued_at<?)",[$now-3*86400000]);
 if(!class_exists('Telegram'))return;$tg??=new Telegram($a);if(!$tg->configured())return;
 $a->run("UPDATE {{meeting_attendees}} x LEFT JOIN {{telegram_links}} l ON l.user_id=x.user_id SET x.notify_state='nolink' WHERE x.notify_state='pending' AND l.user_id IS NULL");
 $rows=$a->all("SELECT x.meeting_id,x.user_id,x.notify_kind,l.chat_id,m.title,m.meeting_day,m.start_min,m.end_min,m.actions_json,(SELECT name FROM {{users}} WHERE id=m.updated_by) editor_name FROM {{meeting_attendees}} x JOIN {{meetings}} m ON m.id=x.meeting_id JOIN {{telegram_links}} l ON l.user_id=x.user_id WHERE x.notify_state='pending' ORDER BY x.queued_at LIMIT 25");
 foreach($rows as $r){
  if(!$a->run("UPDATE {{meeting_attendees}} SET notify_state='sending' WHERE meeting_id=? AND user_id=? AND notify_state='pending'",[$r['meeting_id'],$r['user_id']])->rowCount())continue;
  $mine=array_values(array_filter(json_decode($r['actions_json'],true)?:[],fn($x)=>(int)($x['user_id']??0)===(int)$r['user_id']));
  $lead=['edit'=>'Biên bản vừa được '.$r['editor_name'].' sửa. Bạn cần đọc lại và xác nhận bản mới.','remind'=>'Nhắc lại: bạn chưa xác nhận biên bản này.'][$r['notify_kind']]??'Bạn có mặt trong cuộc họp này.';
  $text="📋 Anh Gấu Studio · Biên bản họp\n«".$r['title']."»\n".date('d/m/Y',strtotime($r['meeting_day'].'T12:00:00Z')).' · '.meeting_hm((int)$r['start_min']).'–'.meeting_hm((int)$r['end_min'])."\n\n".$lead
   .($mine?"\n\nViệc bạn phụ trách:\n".implode("\n",array_map(fn($x)=>'• '.$x['text'].($x['due']?' (hạn '.date('d/m/Y',strtotime($x['due'].'T12:00:00Z')).')':''),$mine)):'')
   ."\n\nVào web đọc biên bản và bấm «Đã đọc và xác nhận hiểu rõ nội dung».";
  try{$tg->call('sendMessage',['chat_id'=>$r['chat_id'],'text'=>$text,'disable_web_page_preview'=>true]+$tg->button('Mở Biên bản họp',$a->config['app_url'].'/#meetings'));
   $a->run("UPDATE {{meeting_attendees}} SET notify_state='sent',notified_at=?,notify_error=NULL WHERE meeting_id=? AND user_id=?",[$now,$r['meeting_id'],$r['user_id']]);}
  catch(Throwable $e){$a->run("UPDATE {{meeting_attendees}} SET notify_state='failed',notify_error=? WHERE meeting_id=? AND user_id=?",[$e->getMessage(),$r['meeting_id'],$r['user_id']]);}
 }
}
