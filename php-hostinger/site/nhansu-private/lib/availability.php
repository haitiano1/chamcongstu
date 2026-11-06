<?php
declare(strict_types=1);
// Đăng ký ca theo tuần (thứ Hai – Chủ nhật): nhân viên tick ca chắc chắn đi làm, tối thiểu 10 ca rồi chốt (chủ dự án đổi từ 5 lên 10 ngày 28/10/2026).
// Sau khi chốt (hoặc khi tuần đã bắt đầu) bỏ tick là "nghỉ gấp": bắt buộc lý do, lưu lại, báo quản lý,
// hệ thống chỉ ĐỀ XUẤT điểm KPI 100 (tái phạm trong tháng 300); người có quyền Event log mới ghi nhận.
const AVAIL_MIN_SHIFTS=10;
// Bỏ ca đã chốt khi còn ≤ 2 ngày tới ngày của ca là nghỉ gấp; từ 3 ngày trở lên là báo trước, không tính.
const AVAIL_URGENT_DAYS=2;
const AVAIL_SHIFTS=['day_first','day_second'];
// Thứ 7, Chủ nhật bắt buộc đi làm (chủ dự án yêu cầu 06/11/2026), từ tuần 09/11/2026: cả 2 ca tự tính là đã đăng ký, không bỏ được.
// Ca bắt buộc không lưu vào shift_availability; mọi chỗ đọc ca đăng ký đi qua avail_shifts() để luôn có đủ, kể cả nhân viên mới.
const AVAIL_WEEKEND_FROM='2026-11-09';
function avail_forced(string $day): bool {return $day>=AVAIL_WEEKEND_FROM&&(int)date('N',strtotime($day.'T00:00:00Z'))>=6;}
function avail_shifts(App $a,string $from,string $to,array $users): array {
 if(!$users)return [];$in=implode(',',array_fill(0,count($users),'?'));$out=[];
 foreach($a->all("SELECT user_id,day,shift,created_at FROM {{shift_availability}} WHERE day>=? AND day<=? AND user_id IN ($in)",[$from,$to,...$users]) as $r)$out[$r['day'].'|'.$r['shift'].'|'.$r['user_id']]=$r;
 $staff=array_map('intval',array_column($a->all("SELECT id FROM {{users}} WHERE role='employee' AND id IN ($in)",$users),'id'));
 for($d=$from;$d<=$to;$d=date('Y-m-d',strtotime($d.'T00:00:00Z')+86400))if(avail_forced($d))foreach($staff as $u)foreach(AVAIL_SHIFTS as $s)$out[$d.'|'.$s.'|'.$u]??=['user_id'=>$u,'day'=>$d,'shift'=>$s,'created_at'=>0];
 ksort($out);return array_values($out);
}
function avail_week(mixed $value): string {
 $day=valid_day($value);if(date('N',strtotime($day.'T00:00:00Z'))!=='1')fail('Tuần cần bắt đầu từ thứ Hai.');return $day;
}
function avail_this_week(int $now): string {$day=local_day($now);return date('Y-m-d',strtotime($day.'T00:00:00Z')-(((int)date('N',strtotime($day.'T00:00:00Z')))-1)*86400);}
function avail_days(string $week): array {$out=[];for($i=0;$i<7;$i++)$out[]=date('Y-m-d',strtotime($week.'T00:00:00Z')+$i*86400);return $out;}
function avail_week_of(string $day): string {return date('Y-m-d',strtotime($day.'T00:00:00Z')-(((int)date('N',strtotime($day.'T00:00:00Z')))-1)*86400);}
// Tuần đã chốt khi nhân viên bấm chốt, hoặc tự chốt khi tuần đã bắt đầu.
function avail_committed(App $a,int $user,string $week,int $now): array {
 $row=$a->one('SELECT committed_at FROM {{availability_weeks}} WHERE user_id=? AND week_start=?',[$user,$week]);
 return ['committed'=>$row!==null||$now>=day_time($week),'committed_at'=>$row['committed_at']??null,'auto'=>$row===null&&$now>=day_time($week)];
}
function avail_days_ahead(string $day,int $now): int {return intdiv(strtotime($day.'T00:00:00Z')-strtotime(local_day($now).'T00:00:00Z'),86400);}
// Ca đã đăng ký hiện trong Lịch làm việc (chỉ để xem; đối soát chấm công vẫn theo lịch khách).
function avail_roster(App $a,string $start,string $end,?int $user,array $existing): array {
 $have=[];foreach($existing as $e)$have[$e['user_id'].'_'.$e['day'].'_'.$e['shift']]=true;
 $people=[];foreach($a->all("SELECT id user_id,name,role,status,archived,employee_code FROM {{users}} WHERE archived=0 AND status!='inactive' AND role='employee'".($user?' AND id=?':''),$user?[$user]:[]) as $p)$people[(int)$p['user_id']]=$p;
 $out=[];foreach(avail_shifts($a,$start,$end,array_keys($people)) as $s){$k=$s['user_id'].'_'.$s['day'].'_'.$s['shift'];if(isset($have[$k]))continue;$out[]=['day'=>$s['day'],'shift'=>$s['shift']]+$people[(int)$s['user_id']]+['id'=>'reg_'.$k,'source'=>'registered','from_registration'=>true];}
 usort($out,fn($x,$y)=>[$x['day'],$x['shift'],$x['name']]<=>[$y['day'],$y['shift'],$y['name']]);return $out;
}
function avail_shift_start(string $day,string $shift): int {return bounds($day,$shift)['start'];}
function avail_audit(App $a,int $user,string $day,string $shift,string $action,string $note=''): void {
 $a->run('INSERT INTO {{availability_audit}}(user_id,day,shift,action,note,actor_id,time) VALUES(?,?,?,?,?,?,?)',[$user,$day,$shift,$action,$note,$a->user['id'],now_ms()]);
}
// Điểm đề xuất: lần nghỉ gấp đầu trong tháng 100, từ lần thứ hai 300.
function avail_cancel_rows(App $a,string $from,string $to,?int $user=null): array {
 $rows=$a->all("SELECT c.id,c.user_id,c.day,c.shift,c.cancelled_at,c.reason,u.name,u.employee_code,(SELECT r.id FROM {{attendance_issue_links}} l JOIN {{event_log}} r ON r.id=l.event_log_id WHERE l.source_key=CONCAT('cancel_',c.id)) event_log_id FROM {{shift_cancellations}} c JOIN {{users}} u ON u.id=c.user_id WHERE c.day>=? AND c.day<=? AND u.role='employee'".($user?' AND c.user_id=?':'').' ORDER BY c.cancelled_at',$user?[$from,$to,$user]:[$from,$to]);
 foreach($rows as &$r){$prior=(int)$a->scalar('SELECT COUNT(*) FROM {{shift_cancellations}} WHERE user_id=? AND cancelled_at<? AND cancelled_at>=?',[$r['user_id'],$r['cancelled_at'],day_time(substr(local_day($r['cancelled_at']),0,7).'-01')]);$r['ordinal']=$prior+1;$r['suggested_deduction']=$prior?300:100;}unset($r);
 return $rows;
}
function avail_notify_managers(App $a,string $text,?Telegram $tg=null): void {
 if(!class_exists('Telegram'))return;$tg??=new Telegram($a);if(!$tg->configured())return;
 foreach($a->all("SELECT l.chat_id FROM {{telegram_links}} l JOIN {{users}} u ON u.id=l.user_id WHERE u.role IN ('admin','manager') AND u.archived=0 AND u.status!='inactive' AND u.id!=?",[$a->user['id']]) as $m){try{$tg->call('sendMessage',['chat_id'=>$m['chat_id'],'text'=>$text,'disable_web_page_preview'=>true]+$tg->button('Mở Đăng ký ca',$a->config['app_url'].'/#availability'));}catch(Throwable){}}
}
function avail_week_label(string $week): string {return date('d/m',strtotime($week.'T00:00:00Z')).' – '.date('d/m',strtotime($week.'T00:00:00Z')+6*86400);}
// Các ca đã chọn trong tuần, mỗi ngày một dòng: "T2 02/11: Ca 1, Ca 2".
function avail_shift_lines(App $a,int $user,string $week): string {
 $days=avail_days($week);$names=['day_first'=>'Ca 1','day_second'=>'Ca 2'];$short=['T2','T3','T4','T5','T6','T7','CN'];$by=[];
 foreach(avail_shifts($a,$days[0],$days[6],[$user]) as $r)$by[$r['day']][]=$names[$r['shift']]??$r['shift'];
 $out=[];foreach($days as $i=>$d)if(isset($by[$d]))$out[]=$short[$i].' '.date('d/m',strtotime($d.'T00:00:00Z')).': '.implode(', ',$by[$d]);return implode("\n",$out);
}
// Telegram nhắc Đăng ký ca (chủ dự án yêu cầu 31/10/2026). Cron mỗi phút: thứ 7 và Chủ nhật lúc 9h và 20h nhắn riêng từng người chưa chốt
// tuần tới (Nhân viên và Quản lý, như popup trên web); 20h Chủ nhật gửi Admin/Quản lý danh sách người chưa chốt. Cron lỡ giờ thì gửi bù
// trong 3 tiếng. Mỗi tin giữ chỗ trong telegram_deliveries (kind 'avail', shift avail_9 / avail_20 / avail_list) nên không gửi lặp.
const AVAIL_REMIND_HOURS=[9,20];
function avail_telegram_send(App $a,Telegram $tg,int $user,string $chat,string $day,string $slot,string $text,int $now): bool {
 if(!$a->run("INSERT IGNORE INTO {{telegram_deliveries}}(user_id,day,shift,kind,state,attempted_at) VALUES(?,?,?,'avail','pending',?)",[$user,$day,$slot,$now])->rowCount())return false;
 try{$tg->call('sendMessage',['chat_id'=>$chat,'text'=>$text,'disable_web_page_preview'=>true]+$tg->button('Mở Đăng ký ca',$a->config['app_url'].'/#availability'));
  $a->run("UPDATE {{telegram_deliveries}} SET state='sent',sent_at=?,error=NULL WHERE user_id=? AND day=? AND shift=? AND kind='avail'",[$now,$user,$day,$slot]);}
 catch(Throwable $e){$a->run("UPDATE {{telegram_deliveries}} SET state='failed',error=? WHERE user_id=? AND day=? AND shift=? AND kind='avail'",[mb_substr($e->getMessage(),0,500),$user,$day,$slot]);$a->meta('telegram_error',$e->getMessage());}
 return true;
}
function availability_telegram_tick(App $a,?int $clock=null,?Telegram $tg=null): void {
 if(!class_exists('Telegram'))return;$tg??=new Telegram($a);if(!$tg->configured())return;
 $now=$clock??now_ms();$day=local_day($now);$dow=(int)date('N',strtotime($day.'T00:00:00Z'));if($dow<6)return;
 $hour=intdiv($now-day_time($day),3600000);$slot=null;foreach(AVAIL_REMIND_HOURS as $h)if($hour>=$h&&$hour<$h+3)$slot=$h;if($slot===null)return;
 $next=date('Y-m-d',strtotime(avail_this_week($now).'T00:00:00Z')+7*86400);$days=avail_days($next);$label=avail_week_label($next);$pending=[];
 foreach($a->all("SELECT u.id,u.name,l.chat_id FROM {{users}} u LEFT JOIN {{telegram_links}} l ON l.user_id=u.id WHERE u.role='employee' AND u.archived=0 AND u.status!='inactive' ORDER BY u.name") as $p){
  if(avail_committed($a,(int)$p['id'],$next,$now)['committed'])continue;
  $p['count']=count(avail_shifts($a,$days[0],$days[6],[(int)$p['id']]));$pending[]=$p;
 }
 $sent=0;
 foreach($pending as $p){if(!$p['chat_id'])continue;if($sent>=25)return;
  $text="📅 Anh Gấu Studio · Đăng ký ca\nBạn chưa chốt ca làm tuần tới ($label). Đã chọn {$p['count']}/".AVAIL_MIN_SHIFTS." ca.\nThứ 7, Chủ nhật bắt buộc đi làm, đã tự tính. Vào tick thêm những ca chắc chắn đi làm cho đủ tối thiểu ".AVAIL_MIN_SHIFTS." ca, rồi bấm Chốt.";
  if(avail_telegram_send($a,$tg,(int)$p['id'],(string)$p['chat_id'],$day,'avail_'.$slot,$text,$now))$sent++;}
 if($dow!==7||$slot!==20||!$pending)return;
 $text="📋 Anh Gấu Studio · Chưa chốt ca tuần $label (".count($pending)." người)\n".implode("\n",array_map(fn($p)=>'• '.$p['name'].': '.$p['count'].'/'.AVAIL_MIN_SHIFTS.' ca'.($p['chat_id']?'':' · chưa nối Telegram'),$pending));
 foreach($a->all("SELECT u.id,l.chat_id FROM {{users}} u JOIN {{telegram_links}} l ON l.user_id=u.id WHERE u.role IN ('admin','manager') AND u.archived=0 AND u.status!='inactive' ORDER BY u.id") as $m){if($sent>=25)return;if(avail_telegram_send($a,$tg,(int)$m['id'],(string)$m['chat_id'],$day,'avail_list',$text,$now))$sent++;}
}
function availability_routes(App $a,string $path,array $b,string $method): ?array {
 if($path!=='/availability'&&!str_starts_with($path,'/availability/'))return null;
 $me=$a->user['id'];$manager=$a->user['role']!=='employee';$now=now_ms();$thisWeek=avail_this_week($now);
 if($path==='/availability/summary'&&$method==='GET'){
  $next=date('Y-m-d',strtotime($thisWeek.'T00:00:00Z')+7*86400);$state=avail_committed($a,$me,$next,$now);
  $count=count(avail_shifts($a,$next,avail_days($next)[6],[$me]));
  return [200,['next_week'=>$next,'next_committed'=>$state['committed'],'next_count'=>$count,'min'=>AVAIL_MIN_SHIFTS,'needs_registration'=>$a->user['role']==='employee']];
 }
 if($path==='/availability'&&$method==='GET'){
  $week=avail_week($_GET['week']??$thisWeek);$days=avail_days($week);
  $mine=avail_shifts($a,$days[0],$days[6],[$me]);
  $myCancels=$a->all('SELECT day,shift,cancelled_at,reason FROM {{shift_cancellations}} WHERE user_id=? AND day>=? AND day<=? ORDER BY cancelled_at',[$me,$days[0],$days[6]]);
  $out=['week_start'=>$week,'days'=>$days,'this_week'=>$thisWeek,'now'=>$now,'min'=>AVAIL_MIN_SHIFTS,'urgent_days'=>AVAIL_URGENT_DAYS,'forced_days'=>array_values(array_filter($days,'avail_forced')),'mine'=>['shifts'=>$mine,'cancels'=>$myCancels]+avail_committed($a,$me,$week,$now),'can_manage'=>$manager];
  if($manager){
   $people=$a->all("SELECT id,name,employee_code,role FROM {{users}} WHERE archived=0 AND status!='inactive' AND role='employee' ORDER BY name");
   $all=avail_shifts($a,$days[0],$days[6],array_map('intval',array_column($people,'id')));$cancels=avail_cancel_rows($a,$days[0],$days[6]);
   foreach($people as &$p){$p['shifts']=array_values(array_filter($all,fn($s)=>$s['user_id']===$p['id']));$p['active']=count($p['shifts']);$p['cancels']=array_values(array_filter($cancels,fn($c)=>$c['user_id']===$p['id']));$p+=avail_committed($a,$p['id'],$week,$now);}unset($p);
   $out['people']=$people;$out['cancels']=$cancels;$out['can_record_log']=log_permissions($a)['can_manage'];
  }
  return [200,$out];
 }
 if(in_array($path,['/availability/toggle','/availability/commit'],true)&&$method==='POST'&&$a->user['role']!=='employee')fail('Đăng ký ca chỉ dành cho tài khoản Nhân viên.',403);
 if($path==='/availability/toggle'&&$method==='POST'){
  $day=valid_day($b['day']??null);$shift=$b['shift']??'';if(!in_array($shift,AVAIL_SHIFTS,true))fail('Ca không hợp lệ.');$on=$b['on']??null;if(!is_bool($on))fail('Thiếu trạng thái chọn ca.');
  $week=avail_week_of($day);if($week<$thisWeek||$week>date('Y-m-d',strtotime($thisWeek.'T00:00:00Z')+28*86400))fail('Chỉ đăng ký được tuần này và 4 tuần tới.');
  if(avail_shift_start($day,$shift)<=$now)fail('Ca này đã bắt đầu hoặc đã qua, không thay đổi được.',409);
  if(avail_forced($day)){if($on)return [200,['ok'=>true]];fail('Thứ 7, Chủ nhật bắt buộc đi làm, không bỏ được ca.',409);}
  $active=(bool)$a->one('SELECT 1 FROM {{shift_availability}} WHERE user_id=? AND day=? AND shift=?',[$me,$day,$shift]);
  if($on){
   if($active)return [200,['ok'=>true]];
   // Tick lại ca đã nghỉ gấp vẫn giữ lượt nghỉ gấp cũ trong shift_cancellations.
   $a->run('INSERT INTO {{shift_availability}}(user_id,day,shift,created_at) VALUES(?,?,?,?)',[$me,$day,$shift,$now]);
   avail_audit($a,$me,$day,$shift,'added');return [200,['ok'=>true]];
  }
  if(!$active)return [200,['ok'=>true]];
  if(!avail_committed($a,$me,$week,$now)['committed']){$a->run('DELETE FROM {{shift_availability}} WHERE user_id=? AND day=? AND shift=?',[$me,$day,$shift]);avail_audit($a,$me,$day,$shift,'removed_draft');return [200,['ok'=>true]];}
  if(avail_days_ahead($day,$now)>AVAIL_URGENT_DAYS){
   $count=count(avail_shifts($a,$week,avail_days($week)[6],[$me]));
   if($count<=AVAIL_MIN_SHIFTS)fail('Tuần đã chốt cần giữ tối thiểu '.AVAIL_MIN_SHIFTS.' ca. Hãy chọn ca khác thay thế trước rồi mới bỏ ca này.',409);
   $a->run('DELETE FROM {{shift_availability}} WHERE user_id=? AND day=? AND shift=?',[$me,$day,$shift]);avail_audit($a,$me,$day,$shift,'removed_notice');return [200,['ok'=>true]];
  }
  if(($b['confirm_urgent']??false)!==true)fail('Ca còn trong vòng '.AVAIL_URGENT_DAYS.' ngày. Bỏ ca lúc này là nghỉ gấp, cần xác nhận.',409);
  $reason=input_text($b['reason']??'','Lý do nghỉ gấp',5,500);
  $a->run('DELETE FROM {{shift_availability}} WHERE user_id=? AND day=? AND shift=?',[$me,$day,$shift]);
  $a->run('INSERT INTO {{shift_cancellations}}(user_id,day,shift,cancelled_at,reason) VALUES(?,?,?,?,?)',[$me,$day,$shift,$now,$reason]);
  avail_audit($a,$me,$day,$shift,'urgent_cancel',$reason);
  $label=($shift==='day_first'?'Ca 1 (9h–14h)':'Ca 2 (14h–19h)').' ngày '.date('d/m',strtotime($day.'T00:00:00Z'));
  avail_notify_managers($a,"⚠️ Anh Gấu Studio · Nghỉ ca gấp\n".$a->user['name'].' nghỉ '.$label."\nLý do: ".$reason);
  return [200,['ok'=>true,'urgent'=>true]];
 }
 if($path==='/availability/commit'&&$method==='POST'){
  $week=avail_week($b['week']??null);$days=avail_days($week);
  if($week<$thisWeek)fail('Tuần đã qua.',409);
  if(avail_committed($a,$me,$week,$now)['committed'])fail('Tuần này đã chốt.',409);
  $count=count(avail_shifts($a,$days[0],$days[6],[$me]));
  if($count<AVAIL_MIN_SHIFTS)fail('Cần chọn tối thiểu '.AVAIL_MIN_SHIFTS.' ca trước khi chốt (đang có '.$count.' ca).');
  $a->run('INSERT INTO {{availability_weeks}}(user_id,week_start,committed_at) VALUES(?,?,?)',[$me,$week,$now]);
  avail_audit($a,$me,$week,'week','committed',$count.' ca');
  avail_notify_managers($a,"✅ Anh Gấu Studio · Chốt đăng ký ca\n".$a->user['name'].' đã chốt '.$count.' ca tuần '.avail_week_label($week)."\n".avail_shift_lines($a,$me,$week));
  return [200,['ok'=>true]];
 }
 if($path==='/availability/cancel-log'&&$method==='POST'){
  $a->requireManager();if(!log_permissions($a)['can_manage'])fail('Bạn chưa được Admin cấp quyền chỉnh sửa Event log.',403);
  $cid=$b['id']??null;if(!is_int($cid))fail('Dữ liệu không hợp lệ.');$c=$a->one('SELECT user_id,day FROM {{shift_cancellations}} WHERE id=?',[$cid]);if(!$c)fail('Không còn lượt nghỉ gấp này. Tải lại.',409);
  $user=$c['user_id'];$key='cancel_'.$cid;$row=null;foreach(avail_cancel_rows($a,$c['day'],$c['day'],$user) as $r)if($r['id']===$cid)$row=$r;
  if(!$row)fail('Không còn lượt nghỉ gấp này. Tải lại.',409);if($row['event_log_id'])return [200,['id'=>$row['event_log_id'],'already_saved'=>true]];
  $id=uuid_v4();$result=log_routes($a,'/event-log',['id'=>$id,'user_id'=>$user,'event_date'=>local_day($row['cancelled_at']),'category'=>'absence','description'=>input_text($b['description']??null,'Nội dung ghi nhận',5,2000),'deduction'=>$b['deduction']??0,'rule_id'=>$b['rule_id']??null,'offense'=>null],'POST');
  $a->run('INSERT INTO {{attendance_issue_links}} VALUES(?,?)',[$key,$id]);return $result;
 }
 fail('Không tìm thấy chức năng Đăng ký ca.',404);
}
