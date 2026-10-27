<?php
declare(strict_types=1);
// Đăng ký ca theo tuần (thứ Hai – Chủ nhật): nhân viên tick ca chắc chắn đi làm, tối thiểu 5 ca rồi chốt.
// Sau khi chốt (hoặc khi tuần đã bắt đầu) bỏ tick là "nghỉ gấp": bắt buộc lý do, lưu lại, báo quản lý,
// hệ thống chỉ ĐỀ XUẤT điểm KPI 100 (tái phạm trong tháng 300); người có quyền Event log mới ghi nhận.
const AVAIL_MIN_SHIFTS=5;
const AVAIL_SHIFTS=['day_first','day_second'];
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
function avail_shift_start(string $day,string $shift): int {return bounds($day,$shift)['start'];}
function avail_audit(App $a,int $user,string $day,string $shift,string $action,string $note=''): void {
 $a->run('INSERT INTO {{availability_audit}}(user_id,day,shift,action,note,actor_id,time) VALUES(?,?,?,?,?,?,?)',[$user,$day,$shift,$action,$note,$a->user['id'],now_ms()]);
}
// Điểm đề xuất: lần nghỉ gấp đầu trong tháng 100, từ lần thứ hai 300.
function avail_cancel_rows(App $a,string $from,string $to,?int $user=null): array {
 $rows=$a->all("SELECT c.id,c.user_id,c.day,c.shift,c.cancelled_at,c.reason,u.name,u.employee_code,(SELECT r.id FROM {{attendance_issue_links}} l JOIN {{event_log}} r ON r.id=l.event_log_id WHERE l.source_key=CONCAT('cancel_',c.id)) event_log_id FROM {{shift_cancellations}} c JOIN {{users}} u ON u.id=c.user_id WHERE c.day>=? AND c.day<=?".($user?' AND c.user_id=?':'').' ORDER BY c.cancelled_at',$user?[$from,$to,$user]:[$from,$to]);
 foreach($rows as &$r){$prior=(int)$a->scalar('SELECT COUNT(*) FROM {{shift_cancellations}} WHERE user_id=? AND cancelled_at<? AND cancelled_at>=?',[$r['user_id'],$r['cancelled_at'],day_time(substr(local_day($r['cancelled_at']),0,7).'-01')]);$r['ordinal']=$prior+1;$r['suggested_deduction']=$prior?300:100;}unset($r);
 return $rows;
}
function avail_notify_managers(App $a,string $text): void {
 if(!class_exists('Telegram'))return;$tg=new Telegram($a);if(!$tg->configured())return;
 foreach($a->all("SELECT l.chat_id FROM {{telegram_links}} l JOIN {{users}} u ON u.id=l.user_id WHERE u.role IN ('admin','manager') AND u.archived=0 AND u.status!='inactive' AND u.id!=?",[$a->user['id']]) as $m){try{$tg->message($m['chat_id'],$text);}catch(Throwable){}}
}
function availability_routes(App $a,string $path,array $b,string $method): ?array {
 if($path!=='/availability'&&!str_starts_with($path,'/availability/'))return null;
 $me=$a->user['id'];$manager=$a->user['role']!=='employee';$now=now_ms();$thisWeek=avail_this_week($now);
 if($path==='/availability/summary'&&$method==='GET'){
  $next=date('Y-m-d',strtotime($thisWeek.'T00:00:00Z')+7*86400);$state=avail_committed($a,$me,$next,$now);
  $count=(int)$a->scalar('SELECT COUNT(*) FROM {{shift_availability}} WHERE user_id=? AND day>=? AND day<=?',[$me,$next,avail_days($next)[6]]);
  return [200,['next_week'=>$next,'next_committed'=>$state['committed'],'next_count'=>$count,'min'=>AVAIL_MIN_SHIFTS,'needs_registration'=>$a->user['role']!=='admin']];
 }
 if($path==='/availability'&&$method==='GET'){
  $week=avail_week($_GET['week']??$thisWeek);$days=avail_days($week);
  $mine=$a->all('SELECT day,shift,created_at FROM {{shift_availability}} WHERE user_id=? AND day>=? AND day<=?',[$me,$days[0],$days[6]]);
  $myCancels=$a->all('SELECT day,shift,cancelled_at,reason FROM {{shift_cancellations}} WHERE user_id=? AND day>=? AND day<=? ORDER BY cancelled_at',[$me,$days[0],$days[6]]);
  $out=['week_start'=>$week,'days'=>$days,'this_week'=>$thisWeek,'now'=>$now,'min'=>AVAIL_MIN_SHIFTS,'mine'=>['shifts'=>$mine,'cancels'=>$myCancels]+avail_committed($a,$me,$week,$now),'can_manage'=>$manager];
  if($manager){
   $people=$a->all("SELECT id,name,employee_code,role FROM {{users}} WHERE archived=0 AND status!='inactive' AND role!='admin' ORDER BY name");
   $all=$a->all('SELECT user_id,day,shift FROM {{shift_availability}} WHERE day>=? AND day<=?',[$days[0],$days[6]]);$cancels=avail_cancel_rows($a,$days[0],$days[6]);
   foreach($people as &$p){$p['shifts']=array_values(array_filter($all,fn($s)=>$s['user_id']===$p['id']));$p['active']=count($p['shifts']);$p['cancels']=array_values(array_filter($cancels,fn($c)=>$c['user_id']===$p['id']));$p+=avail_committed($a,$p['id'],$week,$now);}unset($p);
   $out['people']=$people;$out['cancels']=$cancels;$out['can_record_log']=log_permissions($a)['can_manage'];
  }
  return [200,$out];
 }
 if($path==='/availability/toggle'&&$method==='POST'){
  $day=valid_day($b['day']??null);$shift=$b['shift']??'';if(!in_array($shift,AVAIL_SHIFTS,true))fail('Ca không hợp lệ.');$on=$b['on']??null;if(!is_bool($on))fail('Thiếu trạng thái chọn ca.');
  $week=avail_week_of($day);if($week<$thisWeek||$week>date('Y-m-d',strtotime($thisWeek.'T00:00:00Z')+28*86400))fail('Chỉ đăng ký được tuần này và 4 tuần tới.');
  if(avail_shift_start($day,$shift)<=$now)fail('Ca này đã bắt đầu hoặc đã qua, không thay đổi được.',409);
  $active=(bool)$a->one('SELECT 1 FROM {{shift_availability}} WHERE user_id=? AND day=? AND shift=?',[$me,$day,$shift]);
  if($on){
   if($active)return [200,['ok'=>true]];
   // Tick lại ca đã nghỉ gấp vẫn giữ lượt nghỉ gấp cũ trong shift_cancellations.
   $a->run('INSERT INTO {{shift_availability}}(user_id,day,shift,created_at) VALUES(?,?,?,?)',[$me,$day,$shift,$now]);
   avail_audit($a,$me,$day,$shift,'added');return [200,['ok'=>true]];
  }
  if(!$active)return [200,['ok'=>true]];
  if(!avail_committed($a,$me,$week,$now)['committed']){$a->run('DELETE FROM {{shift_availability}} WHERE user_id=? AND day=? AND shift=?',[$me,$day,$shift]);avail_audit($a,$me,$day,$shift,'removed_draft');return [200,['ok'=>true]];}
  if(($b['confirm_urgent']??false)!==true)fail('Ca đã chốt. Bỏ ca lúc này là nghỉ gấp, cần xác nhận.',409);
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
  $count=(int)$a->scalar('SELECT COUNT(*) FROM {{shift_availability}} WHERE user_id=? AND day>=? AND day<=?',[$me,$days[0],$days[6]]);
  if($count<AVAIL_MIN_SHIFTS)fail('Cần chọn tối thiểu '.AVAIL_MIN_SHIFTS.' ca trước khi chốt (đang có '.$count.' ca).');
  $a->run('INSERT INTO {{availability_weeks}}(user_id,week_start,committed_at) VALUES(?,?,?)',[$me,$week,$now]);
  avail_audit($a,$me,$week,'week','committed',$count.' ca');return [200,['ok'=>true]];
 }
 if($path==='/availability/cancel-log'&&$method==='POST'){
  $a->requireManager();if(!log_permissions($a)['can_manage'])fail('Bạn chưa được Admin cấp quyền chỉnh sửa Event log.',403);
  $cid=$b['id']??null;if(!is_int($cid))fail('Dữ liệu không hợp lệ.');$c=$a->one('SELECT user_id,day FROM {{shift_cancellations}} WHERE id=?',[$cid]);if(!$c)fail('Không còn lượt nghỉ gấp này. Tải lại.',409);
  $user=$c['user_id'];$key='cancel_'.$cid;$row=null;foreach(avail_cancel_rows($a,$c['day'],$c['day'],$user) as $r)if($r['id']===$cid)$row=$r;
  if(!$row)fail('Không còn lượt nghỉ gấp này. Tải lại.',409);if($row['event_log_id'])return [200,['id'=>$row['event_log_id'],'already_saved'=>true]];
  $id=uuid_v4();$result=log_routes($a,'/event-log',['id'=>$id,'user_id'=>$user,'event_date'=>local_day($row['cancelled_at']),'category'=>'absence','description'=>input_text($b['description']??null,'Nội dung ghi nhận',5,2000),'deduction'=>$b['deduction']??0],'POST');
  $a->run('INSERT INTO {{attendance_issue_links}} VALUES(?,?)',[$key,$id]);return $result;
 }
 fail('Không tìm thấy chức năng Đăng ký ca.',404);
}
