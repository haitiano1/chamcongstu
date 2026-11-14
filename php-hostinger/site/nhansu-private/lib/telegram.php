<?php
declare(strict_types=1);
require_once __DIR__.'/workflows.php';
function remote_json(string $url,?array $body=null,int $limit=2000000): mixed {$ch=curl_init($url);$data='';curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>false,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>10,CURLOPT_HTTPHEADER=>['Accept: application/json','Content-Type: application/json'],CURLOPT_USERAGENT=>'AnhGauStudioAttendance/2.0',CURLOPT_WRITEFUNCTION=>function($ch,$chunk)use(&$data,$limit){if(strlen($data)+strlen($chunk)>$limit)return 0;$data.=$chunk;return strlen($chunk);}]);if($body!==null){curl_setopt($ch,CURLOPT_POST,true);curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($body,JSON_THROW_ON_ERROR));}$ok=curl_exec($ch);$status=curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);if($ok===false||$status<200||$status>=300)fail('Chưa kết nối được dịch vụ bên ngoài. Kiểm tra Internet hoặc thử lại.',502);$parsed=json_decode($data,true,512,JSON_THROW_ON_ERROR);return $parsed;}
class Telegram {
 public function __construct(public App $a,public mixed $transport=null){}
 public function configured(): bool {return (bool)preg_match('/^\d+:[A-Za-z0-9_-]{20,}$/D',$this->a->config['telegram_token']??'');}
 public function call(string $method,array $b=[]): mixed {if(!$this->configured())fail('Chưa cấu hình token Telegram hợp lệ trong config.php.');try{$data=$this->transport?($this->transport)($method,$b):remote_json('https://api.telegram.org/bot'.$this->a->config['telegram_token'].'/'.$method,$b);}catch(Throwable){fail('Không kết nối được Telegram. Kiểm tra token/Internet của máy chủ.',502);}if(!($data['ok']??false)){$code=$data['error_code']??0;fail($code===401?'Telegram không chấp nhận token.':($code===409?'Bot đang chạy ở nơi khác hoặc có webhook. Chỉ chạy một bản bot.':($code===403?'Người nhận đã chặn bot hoặc chưa nhấn Start.':'Telegram tạm không gửi được tin.')),502);}return $data['result'];}
 public function identity(): array {$fingerprint=hash('sha256',$this->a->config['telegram_token']);$info=json_decode($this->a->meta('telegram_identity')??'null',true);if(!$info||$this->a->meta('telegram_fingerprint')!==$fingerprint){$bot=$this->call('getMe');if(!($bot['is_bot']??false)||empty($bot['username']))fail('Token không thuộc bot Telegram.');$previous=$this->a->meta('telegram_bot_id');if($previous&&$previous!==(string)$bot['id']){$this->a->run('DELETE FROM {{telegram_links}}');$this->a->run('DELETE FROM {{telegram_link_codes}}');$this->a->meta('telegram_offset','0');}$info=['username'=>$bot['username'],'name'=>$bot['first_name']??''];$this->a->meta('telegram_bot_id',(string)$bot['id']);$this->a->meta('telegram_identity',json_encode($info,JSON_THROW_ON_ERROR));$this->a->meta('telegram_fingerprint',$fingerprint);}return $info;}
 // Telegram không nhận nút mở địa chỉ nội bộ (bản dev 127.0.0.1): khi đó gửi tin không kèm nút.
 public function button(string $label,string $url): array {return in_array(parse_url($url,PHP_URL_HOST),['127.0.0.1','localhost'],true)?[]:['reply_markup'=>['inline_keyboard'=>[[['text'=>$label,'url'=>$url]]]]];}
 // Gửi ảnh JPEG kèm chú thích (multipart, Việc cần duyệt 03/11/2026). Lỗi thì trả false để nơi gọi gửi tin chữ thay thế.
 public function photo(string $chat,string $jpeg,string $caption,array $extra=[]): bool {
  if(!$this->configured())return false;
  $fields=['chat_id'=>$chat,'caption'=>mb_substr($caption,0,1000,'UTF-8')]+array_map(fn($v)=>is_array($v)?json_encode($v,JSON_UNESCAPED_UNICODE):$v,$extra);
  try{
   if($this->transport)$data=($this->transport)('sendPhoto',$fields+['photo_bytes'=>strlen($jpeg)]);
   else{$tmp=null;if(class_exists('CURLStringFile'))$file=new CURLStringFile($jpeg,'proof.jpg','image/jpeg');else{$tmp=tempnam(sys_get_temp_dir(),'agtg');file_put_contents($tmp,$jpeg);$file=new CURLFile($tmp,'image/jpeg','proof.jpg');}
    $ch=curl_init('https://api.telegram.org/bot'.$this->a->config['telegram_token'].'/sendPhoto');curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$fields+['photo'=>$file],CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>20,CURLOPT_USERAGENT=>'AnhGauStudio/1.0']);
    $raw=curl_exec($ch);curl_close($ch);if($tmp)@unlink($tmp);$data=is_string($raw)?json_decode($raw,true):null;}
  }catch(Throwable){return false;}
  return (bool)($data['ok']??false);
 }
 public function message(string $chat,string $text): void {$this->call('sendMessage',['chat_id'=>$chat,'text'=>$text,'disable_web_page_preview'=>true]+$this->button('Mở trang chấm công',$this->a->config['app_url']));}
 public function update(array $u): void {$m=$u['message']??null;if($m&&tg_group_command($this->a,$this,$m))return;if($m&&!($m['from']['is_bot']??false)&&function_exists('task_telegram_command')&&task_telegram_command($this->a,$this,$m))return;if(!$m||($m['chat']['type']??'')!=='private'||(string)$m['chat']['id']!==(string)($m['from']['id']??'')||($m['from']['is_bot']??false))return;$chat=(string)$m['chat']['id'];$text=$m['text']??'';if(preg_match('/^\/stop(?:@\w+)?$/D',$text)){$this->a->run('DELETE FROM {{telegram_links}} WHERE chat_id=?',[$chat]);$this->message($chat,'Đã ngừng nhắc chấm công. Muốn bật lại, liên kết Telegram trong web nhân sự.');return;}if(!preg_match('/^\/start(?:@\w+)?(?:\s+([A-Za-z0-9_-]{43}))?$/D',$text,$match))return;if(empty($match[1])){$this->message($chat,'Đăng nhập web → Chấm công → Liên kết Telegram, sau đó nhấn Start.');return;}$code=$this->a->one("SELECT c.user_id FROM {{telegram_link_codes}} c JOIN {{users}} u ON u.id=c.user_id WHERE c.code_hash=? AND c.expires>? AND u.archived=0 AND u.status!='inactive'",[hash('sha256',$match[1]),now_ms()]);if(!$code){$this->message($chat,'Liên kết đã hết hạn hoặc đã dùng. Hãy tạo liên kết mới từ web.');return;}$owner=$this->a->one('SELECT user_id FROM {{telegram_links}} WHERE chat_id=?',[$chat]);if($owner&&$owner['user_id']!==$code['user_id']){$this->message($chat,'Telegram đã gắn với tài khoản khác. Gửi /stop để ngắt liên kết cũ.');return;}$this->a->run('INSERT INTO {{telegram_links}} VALUES(?,?,?) ON DUPLICATE KEY UPDATE chat_id=VALUES(chat_id),linked_at=VALUES(linked_at)',[$code['user_id'],$chat,now_ms()]);$this->a->run('DELETE FROM {{telegram_link_codes}} WHERE user_id=?',[$code['user_id']]);$this->message($chat,'Đã liên kết Anh Gấu Studio. Checkin được nhắc theo Phân ca; checkout tự nhắc sau 5 phút. Gửi /stop để ngừng nhận.');}
 public function tick(?int $clock=null): void {try{$this->a->rollFullDays();}catch(Throwable $e){error_log('Nhansu full day: '.$e->getMessage());}if(!$this->configured())return;$now=$clock??now_ms();try{$this->identity();$updates=$this->call('getUpdates',['offset'=>(int)($this->a->meta('telegram_offset')??0),'timeout'=>0,'limit'=>30,'allowed_updates'=>['message']]);foreach($updates as $u){try{$this->a->tx(fn()=>$this->update($u));}finally{$this->a->meta('telegram_offset',(string)($u['update_id']+1));}}$this->a->meta('telegram_poll',(string)$now);$this->a->meta('telegram_error','');$sent=0;foreach($this->a->all("SELECT u.id,l.chat_id FROM {{users}} u JOIN {{telegram_links}} l ON l.user_id=u.id WHERE u.archived=0 AND u.status!='inactive'") as $person){$events=$this->a->events($person['id']);$due=due_reminders(roster_schedule($this->a,$person['id'],local_day($now)),$events,$now);foreach($due as $r){if($sent>=25)return;$reserved=$this->a->run("INSERT IGNORE INTO {{telegram_deliveries}}(user_id,day,shift,kind,state,attempted_at) VALUES(?,?,?,?,'pending',?)",[$person['id'],$r['day'],$r['shift'],$r['kind'],$now])->rowCount();if(!$reserved)continue;$shift=$r['shift']==='day_first'?SHIFTS[0]:SHIFTS[1];$text='Anh Gấu Studio · Nhắc '.($r['kind']==='in'?'checkin':'checkout')."\n".$shift['name'].' ('.$shift['start'].':00–'.$shift['end'].":00) · ".$r['day']."\n".($r['kind']==='in'?'Khách đầu tiên '.date('H:i',(int)(($r['expected_at']??0)/1000)).'. Checkin sau '.date('H:i',(int)(($r['expected_at']??0)/1000)+1800).' mới tính muộn. Bạn chưa checkin; hãy mở web để chấm công nếu đang làm việc.':'Ca đã có checkin nhưng chưa checkout. Hãy mở web để chấm ra hoặc gửi yêu cầu bổ sung nếu đã quá hạn.');try{$this->message($person['chat_id'],$text);$this->a->run("UPDATE {{telegram_deliveries}} SET state='sent',sent_at=?,error=NULL WHERE user_id=? AND day=? AND shift=? AND kind=?",[$now,$person['id'],$r['day'],$r['shift'],$r['kind']]);}catch(Throwable $e){$this->a->run("UPDATE {{telegram_deliveries}} SET state='failed',error=? WHERE user_id=? AND day=? AND shift=? AND kind=?",[$e->getMessage(),$person['id'],$r['day'],$r['shift'],$r['kind']]);$this->a->meta('telegram_error',$e->getMessage());}$sent++;}}}catch(Throwable $e){$this->a->meta('telegram_error',$e->getMessage());}}
}
function due_reminders(array $schedule,array $events,int $now): array {$day=local_day($now);$due=[];if($schedule['enabled'])foreach($schedule['shifts'] as $shift){$w=bounds($day,$shift);$own=array_filter($events,fn($e)=>$e['shift']===$shift&&$e['work_date']===$day&&$e['time']<=$now);if(!$own&&$now>=($schedule['starts'][$shift]??$w['start'])+600000&&$now<$w['end'])$due[]=['day'=>$day,'shift'=>$shift,'kind'=>'in','expected_at'=>$schedule['starts'][$shift]??$w['start']];}foreach(pairs(array_values(array_filter($events,fn($e)=>$e['time']<=$now)))['pending'] as $e)if(in_array($e['shift'],['day_first','day_second'],true)){$w=bounds($e['work_date'],$e['shift']);if($now>=$w['end']+300000&&$now<$w['end']+86400000)$due[]=['day'=>$e['work_date'],'shift'=>$e['shift'],'kind'=>'out'];}return $due;}
function telegram_routes(App $a,string $path,array $b,string $method): ?array {if(!str_starts_with($path,'/telegram/'))return null;$tg=new Telegram($a);$info=json_decode($a->meta('telegram_identity')??'null',true);$linked=(bool)$a->one('SELECT user_id FROM {{telegram_links}} WHERE user_id=?',[$a->user['id']]);if($path==='/telegram/me'&&$method==='GET')return [200,['configured'=>$tg->configured(),'linked'=>$linked,'ready'=>$tg->configured(),'bot'=>$info['username']??null]];if($path==='/telegram/link'&&$method==='POST'){$bot=$tg->identity();$code=rtrim(strtr(base64_encode(random_bytes(32)),'+/','-_'),'=');$a->run('DELETE FROM {{telegram_link_codes}} WHERE user_id=? OR expires<?',[$a->user['id'],now_ms()]);$a->run('INSERT INTO {{telegram_link_codes}} VALUES(?,?,?)',[hash('sha256',$code),$a->user['id'],now_ms()+900000]);return [201,['url'=>'https://t.me/'.$bot['username'].'?start='.$code,'expires'=>now_ms()+900000]];}if($path==='/telegram/unlink'&&$method==='POST'){$a->run('DELETE FROM {{telegram_links}} WHERE user_id=?',[$a->user['id']]);$a->run('DELETE FROM {{telegram_link_codes}} WHERE user_id=?',[$a->user['id']]);return [200,['ok'=>true]];}if($path==='/telegram/test'&&$method==='POST'){$link=$a->one('SELECT chat_id FROM {{telegram_links}} WHERE user_id=?',[$a->user['id']]);if(!$link)fail('Cần liên kết Telegram trước.');$key='telegram_test_'.$a->user['id'];if(now_ms()-(int)($a->meta($key)??0)<60000)fail('Đợi một phút trước khi gửi thử lại.',429);$a->meta($key,(string)now_ms());$tg->message($link['chat_id'],"Anh Gấu Studio · Tin nhắn kiểm thử\nKết nối nhắc chấm công đã hoạt động.");return [200,['ok'=>true]];}$a->requireManager();if($path==='/telegram/status'&&$method==='GET')return [200,['configured'=>$tg->configured(),'ready'=>$tg->configured(),'bot'=>$info['username']??null,'lastPoll'=>($v=$a->meta('telegram_poll'))?(int)$v:null,'lastError'=>$a->meta('telegram_error')??'','group'=>tg_group_chat($a)?['title'=>(string)($a->meta('telegram_group_title')??''),'sent'=>(int)$a->scalar("SELECT COUNT(*) FROM {{telegram_group_outbox}} WHERE state='sent'"),'failed'=>(int)$a->scalar("SELECT COUNT(*) FROM {{telegram_group_outbox}} WHERE state='failed' AND created_at>?",[now_ms()-86400000])]:null,'webUrl'=>$a->config['app_url'],'deliveries'=>$a->all('SELECT d.*,u.name FROM {{telegram_deliveries}} d JOIN {{users}} u ON u.id=d.user_id ORDER BY d.attempted_at DESC LIMIT 50')]];if($path==='/telegram/schedules')fail('Lịch nhắc checkin đã đồng bộ với mục Phân ca. Hãy sửa ca tại đó.',409);fail('Không tìm thấy chức năng Telegram.',404);}
function location_routes(App $a,string $path,string $method): ?array {if(!str_starts_with($path,'/location/')||$method!=='GET')return null;$a->requireManager();if($path==='/location/provinces')$url='https://provinces.open-api.vn/api/v2/?depth=1';elseif($path==='/location/wards'){$province=$_GET['province']??'';if(!preg_match('/^\d{1,2}$/D',$province))fail('Mã tỉnh/thành không hợp lệ.');$url='https://provinces.open-api.vn/api/v2/p/'.(int)$province.'?depth=2';}elseif($path==='/location/search'){$query=$_GET['q']??'';if(!is_string($query)||strlen(trim($query))<5||strlen($query)>750)fail('Nhập địa chỉ studio từ 5–250 ký tự.');$url='https://nominatim.openstreetmap.org/search?'.http_build_query(['q'=>trim($query),'format'=>'jsonv2','countrycodes'=>'vn','limit'=>5,'accept-language'=>'vi']);}else fail('Không tìm thấy dịch vụ địa chỉ.',404);$file=$a->storage.'/cache/location-'.hash('sha256',$url).'.json';if(is_file($file)&&time()-filemtime($file)<($path==='/location/search'?3600:86400))return [200,['items'=>json_decode(file_get_contents($file),true,512,JSON_THROW_ON_ERROR)]];if($path==='/location/search'){if(now_ms()-(int)($a->meta('location_search_time')??0)<1100)fail('Vui lòng đợi một chút trước khi tìm lại.');$a->meta('location_search_time',(string)now_ms());}try{$data=remote_json($url);$result=[];if($path==='/location/search'){foreach($data as $r)if(isset($r['lat'],$r['lon'],$r['display_name']))$result[]=['label'=>(string)$r['display_name'],'latitude'=>(float)$r['lat'],'longitude'=>(float)$r['lon']];}else{foreach($path==='/location/wards'?($data['wards']??[]):$data as $r)$result[]=['code'=>(string)$r['code'],'name'=>(string)$r['name']];}file_put_contents($file,json_encode($result,JSON_THROW_ON_ERROR),LOCK_EX);return [200,['items'=>$result]];}catch(Throwable){fail('Chưa kết nối được API địa chỉ. Bạn vẫn có thể chọn ghim trên bản đồ hoặc lấy vị trí hiện tại.',502);}}
// ===== Box chat nhóm (chủ dự án yêu cầu 14/11/2026) =====
// Admin thêm bot vào nhóm rồi gõ /nhom trong nhóm (Telegram đã liên kết tài khoản Admin) → lưu meta telegram_group_chat.
// Giao Task / Nhắc nhở mới (task_create, không gồm việc hằng ngày) và ghi Event log mới (POST /event-log, cả /log)
// thì xếp vào telegram_group_outbox; cron gửi vào nhóm (hiện đủ nội dung, điểm KPI). Tin nhắn riêng cho người nhận giữ như cũ.
// /bonhom hoặc bot bị xóa khỏi nhóm thì ngừng gửi. Nhóm nâng cấp supergroup (đổi mã chat) thì tự đổi theo.
const TG_GROUP_MAX_AGE=86400000;
function tg_group_chat(App $a): ?string {$c=$a->meta('telegram_group_chat');return $c!==null&&$c!==''?$c:null;}
function tg_group_queue(App $a,string $kind,string $ref): void {
 if(!tg_group_chat($a))return;
 $a->run("INSERT IGNORE INTO {{telegram_group_outbox}}(kind,ref_id,state,created_at) VALUES(?,?,'pending',?)",[$kind,$ref,now_ms()]);
}
function tg_group_set(App $a,?string $chat,string $title=''): void {$a->meta('telegram_group_chat',$chat??'');$a->meta('telegram_group_title',$chat===null?'':$title);}
function tg_group_command(App $a,Telegram $tg,array $m): bool {
 $chat=(string)($m['chat']['id']??'');$current=tg_group_chat($a);
 if(isset($m['migrate_to_chat_id'])){if($current===$chat)tg_group_set($a,(string)$m['migrate_to_chat_id'],(string)($a->meta('telegram_group_title')??''));return true;}
 if(isset($m['migrate_from_chat_id'])){if($current===(string)$m['migrate_from_chat_id'])tg_group_set($a,$chat,(string)($m['chat']['title']??''));return true;}
 if(isset($m['left_chat_member'])){if($current===$chat&&(string)($m['left_chat_member']['id']??'')===(string)$a->meta('telegram_bot_id'))tg_group_set($a,null);return true;}
 if(!preg_match('~^/(nhom|bonhom)(?:@\w+)?\s*$~u',trim($m['text']??''),$cmd))return false;
 if($m['from']['is_bot']??false)return true;
 $reply=fn(string $msg)=>$tg->call('sendMessage',['chat_id'=>$chat,'text'=>$msg,'reply_to_message_id'=>$m['message_id']??null,'disable_web_page_preview'=>true]);
 try{
  $group=in_array($m['chat']['type']??'',['group','supergroup'],true);
  if($cmd[1]==='nhom'&&!$group)fail('Thêm bot vào box chat nhóm rồi gõ /nhom trong nhóm đó.');
  $sender=$a->one("SELECT u.id,u.name,u.role FROM {{telegram_links}} l JOIN {{users}} u ON u.id=l.user_id WHERE l.chat_id=? AND u.archived=0 AND u.status!='inactive'",[(string)($m['from']['id']??'')]);
  if(!$sender)fail('Telegram của bạn chưa liên kết với tài khoản trên web. Vào web → Chấm công → Liên kết Telegram.');
  if($sender['role']!=='admin')fail('Chỉ Admin được chọn box chat nhận thông báo.');
  if($cmd[1]==='bonhom'){
   if(!$current)fail('Chưa có box chat nào nhận thông báo.');
   if($group&&$current!==$chat)fail('Box chat này không phải nhóm đang nhận thông báo.');
   tg_group_set($a,null);$a->run("UPDATE {{telegram_group_outbox}} SET state='skipped' WHERE state='pending'");
   $reply('Đã ngừng gửi thông báo vào box chat'.($group?' này':' nhóm').'. Gõ /nhom trong nhóm để bật lại.');return true;
  }
  tg_group_set($a,$chat,(string)($m['chat']['title']??''));
  $reply("✅ Box chat này sẽ nhận thông báo khi giao Task, Nhắc nhở và ghi Event log mới trên web.\nNgười nhận vẫn được nhắn riêng như cũ. Gõ /bonhom để ngừng.");
 }catch(HttpError $e){$reply('⚠️ '.$e->getMessage());}
 return true;
}
function tg_group_day(string $day): string {return implode('/',array_reverse(explode('-',$day)));}
function tg_group_text(App $a,array $q): ?array {
 $cut=fn(string $s,int $n)=>text_length($s)>$n?mb_substr($s,0,$n).'…':$s;
 if($q['kind']==='task'){
  $t=$a->one('SELECT t.*,(SELECT name FROM {{users}} WHERE id=t.created_by) creator FROM {{tasks}} t WHERE t.id=?',[$q['ref_id']]);
  if(!$t||$t['status']!=='open'||$t['template_id']!==null)return null;
  $names=array_column($a->all('SELECT u.name FROM {{task_recipients}} r JOIN {{users}} u ON u.id=r.user_id WHERE r.task_id=? ORDER BY u.name',[$t['id']]),'name');
  $to=$t['audience']==='all'?'Tất cả nhân viên ('.count($names).' người)':($names?implode(', ',$names):'Chưa có người nhận');
  $text=($t['kind']==='task'?'📌 Task mới':'🔔 Nhắc nhở mới').' · từ '.$t['creator']."\nGiao cho: ".$to."\n\n".$t['title']
   .($t['body']!==''?"\n".$cut($t['body'],1500):'')
   .($t['due_at']?"\n\n⏰ Hạn: ".date('H:i',intdiv((int)$t['due_at'],1000)).' '.tg_group_day(date('Y-m-d',intdiv((int)$t['due_at'],1000))):'')
   .($t['require_photo']?"\n📷 Cần chụp ".((int)$t['require_photo']>1?$t['require_photo'].' ':'').'ảnh khi báo xong':'')
   .($t['require_review']?"\n✔ Task cần duyệt":'');
  return [$text,'Mở mục Việc','/#tasks'];
 }
 if($q['kind']==='log'){
  $r=$a->one('SELECT e.*,(SELECT name FROM {{users}} WHERE id=e.user_id) person,(SELECT name FROM {{users}} WHERE id=e.created_by) creator FROM {{event_log}} e WHERE e.id=?',[$q['ref_id']]);
  if(!$r||in_array($r['status'],['deleted','void'],true))return null;
  $pts=-(int)$r['points'];$n=$r['offense']===null?null:(int)$r['offense'];$fixed=$r['rule_code']!==''&&$pts>=LOG_FIXED_POINTS&&$n===null;
  $kpi=$pts>0?'➖ Trừ '.$pts.' điểm KPI ('.number_format($pts*1000,0,',','.').'đ)'.($n?' · Lần '.$n.' trong tháng ('.LOG_TIER_NAMES[$n].')':($fixed?' · lỗi nặng, điểm cố định':'')):($r['user_id']===null?'Nhật ký chung · không trừ điểm':'Nhắc nhở · không trừ điểm');
  $text='📝 Event log mới · '.tg_group_day($r['event_date'])."\nNgười liên quan: ".($r['person']??'Nhật ký chung studio')
   ."\nLoại: ".(LOG_CATEGORY_LABELS[$r['category']]??$r['category'])
   .($r['rule_code']!==''?"\nLuật: ".$r['rule_code'].' · '.$r['rule_name']:'')
   ."\n".$kpi
   .($r['status']==='pending'?"\nTrạng thái: Chờ xác nhận":'')
   ."\n\n".$cut($r['description'],2000)
   ."\n\nGhi bởi: ".$r['creator'];
  return [$text,'Mở Event log','/#eventlog'];
 }
 return null;
}
// Cron mỗi phút: gửi tối đa 20 tin; tin quá 1 ngày (cron dừng lâu) thì bỏ, không gửi dồn.
function tg_group_tick(App $a,?Telegram $tg=null,?int $clock=null): void {
 $now=$clock??now_ms();$tg??=new Telegram($a);
 $a->run("UPDATE {{telegram_group_outbox}} SET state='skipped' WHERE state='pending' AND created_at<?",[$now-TG_GROUP_MAX_AGE]);
 $a->run("DELETE FROM {{telegram_group_outbox}} WHERE created_at<?",[$now-30*86400000]);
 $chat=tg_group_chat($a);if(!$chat||!$tg->configured())return;
 foreach($a->all("SELECT * FROM {{telegram_group_outbox}} WHERE state='pending' ORDER BY id LIMIT 20") as $q){
  if(!$a->run("UPDATE {{telegram_group_outbox}} SET state='sending' WHERE id=? AND state='pending'",[$q['id']])->rowCount())continue;
  $msg=tg_group_text($a,$q);
  if(!$msg){$a->run("UPDATE {{telegram_group_outbox}} SET state='skipped' WHERE id=?",[$q['id']]);continue;}
  try{
   $tg->call('sendMessage',['chat_id'=>$chat,'text'=>$msg[0],'disable_web_page_preview'=>true]+$tg->button($msg[1],$a->config['app_url'].$msg[2]));
   $a->run("UPDATE {{telegram_group_outbox}} SET state='sent',sent_at=?,error=NULL WHERE id=?",[$now,$q['id']]);
  }catch(Throwable $e){$a->run("UPDATE {{telegram_group_outbox}} SET state='failed',error=? WHERE id=?",[$e->getMessage(),$q['id']]);$a->meta('telegram_error','Box chat nhóm: '.$e->getMessage());}
 }
}
