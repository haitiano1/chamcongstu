<?php
declare(strict_types=1);

const LOG_CATEGORIES=['missing_punch','late','early_out','absence','customer','task','other'];
function input_text(mixed $value,string $label,int $min=0,int $max=1000): string {
 if(!is_string($value)||!preg_match('//u',$value))fail($label.' không hợp lệ.');
 $value=trim($value);$length=text_length($value);
 if($length<$min||$length>$max)fail($label.' cần từ '.$min.'–'.$max.' ký tự.');
 return $value;
}
function entry_uuid(mixed $id): string {
 if(!is_string($id)||!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/iD',$id))fail('Mã yêu cầu không hợp lệ.');
 return strtolower($id);
}
function finance_range(): array {
 $month=valid_month($_GET['month']??substr(local_day(now_ms()),0,7));
 $start=$month.'-01';$end=date('Y-m-t',strtotime($start));
 if(!empty($_GET['date']))$start=$end=valid_day($_GET['date']);
 return [$start,$end];
}
// Điểm KPI: mỗi nhân viên có 2000 điểm mỗi tháng, trừ theo Event log đã xác nhận/đã xử lý trong tháng.
// Lọc tháng giống hệt trang Event log để hai nơi cùng một con số.
// Tiền: 1 điểm bị trừ = 1.000đ, trừ vào Tổng thưởng (hiện là hoa hồng mỗi khách hệ thống tính), tối đa bằng Tổng thưởng,
// không trừ vào lương theo công. Lương cuối = lương theo công + Tổng thưởng − trừ KPI.
const KPI_MONTHLY_POINTS=2000;
const KPI_POINT_VND=1000;
function kpi_month_deductions(App $a,string $month): array {
 $start=valid_month($month).'-01';$end=date('Y-m-t',strtotime($start));
 $rows=$a->all("SELECT r.user_id,COALESCE(SUM(-r.points),0) deducted FROM {{event_log}} r WHERE r.user_id IS NOT NULL AND r.status IN ('confirmed','resolved') AND ((r.date_precision='day' AND r.event_date>=? AND r.event_date<=?) OR (r.date_precision='day_range' AND r.event_date<=? AND r.end_date>=?) OR (r.date_precision IN ('month','range') AND r.event_date<=? AND (CASE WHEN r.end_month='' THEN r.event_date ELSE r.end_month END)>=?)) GROUP BY r.user_id",[$start,$end,$end,$start,$month,$month]);
 $out=[];foreach($rows as $r)$out[(int)$r['user_id']]=(int)$r['deducted'];return $out;
}
// Thưởng tiến độ (chủ dự án chốt 29/10/2026), áp dụng từ tháng 11/2026:
// - Chuyên cần: Nhân viên đi làm đủ 28 ngày/tháng (tháng 2: 26) được cộng 500.000đ vào thưởng.
// - Hoa hồng lịch khách: mỗi khách đã chụp (thợ chính hoặc phụ) = mức của người đó × hệ số gói.
const PROGRESS_BONUS_FROM='2026-11';
const ATTENDANCE_BONUS_VND=500000;
// Team 349 tính như 399 (mỗi dòng/người), Couple 999 ×2 (chủ dự án chốt 29/10/2026).
const BOOKING_PACKAGE_UNITS=[349=>1,399=>1,699=>1,999=>2,1499=>4,2999=>8];
const BOOKING_RATE_SEED=[['huy',17500],['cuong',15000]];
function attendance_target(string $month): int {return substr($month,5,2)==='02'?26:28;}
// "399", "1.499", "1499k", "1.499.000", "Gói 699 + ..." → 399 / 1499 / 699; chỉ ghi chữ "Couple" → 999, "Team" → 349.
function booking_package_price(string $label): ?int {
 if(!preg_match('/\d{1,3}(?:[.,]\d{3})+|\d+/',$label,$m)){$text=mb_strtolower($label,'UTF-8');return str_contains($text,'couple')?999:(str_contains($text,'team')?349:null);}
 $n=(int)preg_replace('/\D/','',$m[0]);return $n>=10000?intdiv($n,1000):$n;
}
function booking_rates_seed(App $a): void {
 if($a->meta('booking_rates_seeded'))return;$a->meta('booking_rates_seeded','1');
 $admin=$a->scalar("SELECT id FROM {{users}} WHERE role='admin' AND archived=0 ORDER BY id LIMIT 1");if(!$admin||!function_exists('task_fold'))return;
 $people=$a->all("SELECT id,name FROM {{users}} WHERE archived=0 AND status!='inactive'");
 foreach(BOOKING_RATE_SEED as [$key,$rate]){
  // Chỉ gán khi tên gọi khớp đúng một người; trùng hoặc không có thì Admin tự nhập trong hồ sơ nhân viên.
  $hits=array_values(array_filter($people,fn($p)=>(explode(' ',task_fold($p['name']))[substr_count(task_fold($p['name']),' ')]??'')===$key));
  if(count($hits)===1)$a->run('INSERT IGNORE INTO {{booking_rates}} VALUES(?,?,?,?)',[$hits[0]['id'],$rate,$admin,now_ms()]);
 }
}
function booking_rate_save(App $a,int $user,int $rate): void {
 $old=(int)($a->scalar('SELECT rate FROM {{booking_rates}} WHERE user_id=?',[$user])?:0);if($old===$rate)return;
 $a->run('INSERT INTO {{booking_rates}} VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE rate=VALUES(rate),updated_by=VALUES(updated_by),updated_at=VALUES(updated_at)',[$user,$rate,$a->user['id'],now_ms()]);$a->audit($user,'booking_rate');
}
function booking_rates(App $a): array {
 booking_rates_seed($a);$out=[];foreach($a->all('SELECT user_id,rate FROM {{booking_rates}} WHERE rate>0') as $r)$out[(int)$r['user_id']]=(int)$r['rate'];return $out;
}
// Khách đã chụp trong tháng (lịch đang hoạt động, đã qua giờ chụp) của người có mức hoa hồng.
function booking_month_commissions(App $a,string $month,int $now): array {
 $rates=booking_rates($a);if(!$rates)return [];
 $start=valid_month($month).'-01';$end=date('Y-m-t',strtotime($start));$today=local_day($now);$minute=intdiv($now-day_time($today),60000);$out=[];
 foreach($a->all("SELECT DISTINCT s.user_id,b.id,b.shoot_day,b.minutes,b.package_label FROM {{shoot_bookings}} b JOIN {{booking_staff}} s ON s.booking_id=b.id WHERE b.status='active' AND b.shoot_day>=? AND b.shoot_day<=?",[$start,$end]) as $r){
  $id=(int)$r['user_id'];if(!isset($rates[$id])||$r['shoot_day']>$today||($r['shoot_day']===$today&&(int)$r['minutes']>$minute))continue;
  $units=BOOKING_PACKAGE_UNITS[booking_package_price($r['package_label'])??0]??0;
  $out[$id]??=['customers'=>0,'units'=>0,'unpriced'=>0,'rate'=>$rates[$id]];$out[$id]['customers']++;$out[$id]['units']+=$units;if(!$units)$out[$id]['unpriced']++;
 }
 foreach($rates as $id=>$rate)$out[$id]??=['customers'=>0,'units'=>0,'unpriced'=>0,'rate'=>$rate];
 return $out;
}
// Gắn KPI vào từng dòng bảng lương. $money=false: tháng đã chốt từ trước khi có KPI, chỉ thêm điểm, giữ nguyên tiền đã chốt.
function payroll_apply(App $a,string $month,array $rows,bool $money=true): array {
 $kpi=kpi_month_deductions($a,$month);
 $progress=$month>=PROGRESS_BONUS_FROM;$bookings=$progress?booking_month_commissions($a,$month,now_ms()):[];
 foreach($rows as &$p){$used=$kpi[$p['id']]??0;$p['kpi_quota']=KPI_MONTHLY_POINTS;$p['kpi_deducted']=$used;$p['kpi_left']=KPI_MONTHLY_POINTS-$used;
  if($progress&&$p['role']==='employee'){$p['attendance_target']=attendance_target($month);$p['attendance_bonus_vnd']=ATTENDANCE_BONUS_VND;$p['attendance_bonus']=$p['days']>=$p['attendance_target']?ATTENDANCE_BONUS_VND:0;}
  if($b=$bookings[$p['id']]??null){$p['booking_customers']=$b['customers'];$p['booking_unpriced']=$b['unpriced'];$p['booking_commission']=$b['rate']*$b['units'];}
  if(!$money)continue;$p['bonus_total']=(int)$p['commission']+($p['booking_commission']??0)+($p['attendance_bonus']??0);$p['kpi_money']=$used*KPI_POINT_VND;$p['kpi_applied']=max(0,min($p['kpi_money'],$p['bonus_total']));
  $p['gross_salary']=(int)$p['base_salary']+$p['bonus_total'];$p['salary']=$p['gross_salary']-$p['kpi_applied'];}
 unset($p);return $rows;
}
function entry_version(array $body,array $row): void {
 if(!is_int($body['version']??null)||$body['version']!==$row['version'])fail('Dữ liệu đã thay đổi. Tải lại trước khi xử lý.',409);
}
function finance_audit(App $a,string $kind,string $id,string $action,string $note): void {
 $table=$kind==='advance'?'advance_audit':'event_log_audit';$column=$kind==='advance'?'advance_id':'event_log_id';
 $a->run('INSERT INTO {{'.$table.'}}('.$column.',actor_id,action,note,time) VALUES(?,?,?,?,?)',[$id,$a->user['id'],$action,$note,now_ms()]);
}
function finance_people(App $a,bool $all=false): array {
 return $a->all("SELECT id,name,employee_code,role,archived,status FROM {{users}}".($all?'':" WHERE archived=0 AND status!='inactive'").' ORDER BY name');
}
function finance_page(): int {
 $p=filter_var($_GET['page']??1,FILTER_VALIDATE_INT);if(!$p||$p<1||$p>100000)fail('Trang không hợp lệ.');return $p;
}
function advance_routes(App $a,string $path,array $b,string $method): ?array {
 if($path!=='/advances'&&!str_starts_with($path,'/advances/'))return null;
 $manager=$a->user['role']!=='employee';
 if($path==='/advances'&&$method==='POST'){
  $id=entry_uuid($b['id']??null);$day=valid_day($b['requested_date']??null);
  if($day<'2020-01-01'||day_time($day)>now_ms()+366*86400000)fail('Ngày ứng không hợp lệ.');
  if(!is_int($b['amount']??null)||$b['amount']<1||$b['amount']>100000000)fail('Số tiền ứng cần là số nguyên từ 1 đến 100.000.000đ.');
  $reason=input_text($b['reason']??null,'Lý do ứng',5,1000);
  $existing=$a->one('SELECT * FROM {{salary_advances}} WHERE id=?',[$id]);
  if($existing){if($existing['user_id']===$a->user['id']&&$existing['requested_date']===$day&&$existing['amount']===$b['amount']&&$existing['reason']===$reason)return [200,['id'=>$id,'already_saved'=>true]];fail('Mã yêu cầu đã được sử dụng.',409);}
  $a->run("INSERT INTO {{salary_advances}}(id,user_id,requested_date,amount,reason,created_at,review_note,payment_note) VALUES(?,?,?,?,?,?,'','')",[$id,$a->user['id'],$day,$b['amount'],$reason,now_ms()]);
  finance_audit($a,'advance',$id,'created',$reason);return [201,['id'=>$id]];
 }
 if($path==='/advances'&&$method==='GET'){
  [$start,$end]=finance_range();$basis=$_GET['basis']??'requested';if(!in_array($basis,['requested','paid'],true))fail('Loại ngày không hợp lệ.');
  $column=$basis==='paid'?'r.paid_date':'r.requested_date';$where=$column.'>=? AND '.$column.'<=?';$params=[$start,$end];
  $scope=$_GET['scope']??'all';if(!in_array($scope,['all','mine'],true))fail('Phạm vi không hợp lệ.');
  if(!$manager||$scope==='mine'){$where.=' AND r.user_id=?';$params[]=$a->user['id'];}
  if(!empty($_GET['user_id'])){if(!ctype_digit((string)$_GET['user_id']))fail('Nhân viên không hợp lệ.');$where.=' AND r.user_id=?';$params[]=(int)$_GET['user_id'];}
  $status=$_GET['status']??'';if($status!==''){if(!in_array($status,['pending','approved','paid','rejected','cancelled'],true))fail('Trạng thái không hợp lệ.');$where.=' AND r.status=?';$params[]=$status;}
  $sum="COUNT(*) requests,COALESCE(SUM(r.amount),0) requested_total,COALESCE(SUM(CASE WHEN r.status='pending' THEN r.amount ELSE 0 END),0) pending_total,COALESCE(SUM(CASE WHEN r.status='approved' THEN r.amount ELSE 0 END),0) approved_total,COALESCE(SUM(CASE WHEN r.status='paid' THEN r.amount ELSE 0 END),0) paid_total";
  $summary=$a->one('SELECT '.$sum.' FROM {{salary_advances}} r WHERE '.$where,$params);
  $people=$manager?finance_people($a,true):[];$page=finance_page();
  $rows=$a->all('SELECT r.*,u.name,u.employee_code,u.role,(SELECT name FROM {{users}} WHERE id=r.reviewed_by) reviewer_name,(SELECT name FROM {{users}} WHERE id=r.paid_by) payer_name FROM {{salary_advances}} r JOIN {{users}} u ON u.id=r.user_id WHERE '.$where.' ORDER BY '.$column.' DESC,r.created_at DESC,r.id LIMIT 100 OFFSET '.(($page-1)*100),$params);
  $byPerson=$a->all('SELECT r.user_id,u.name,'.$sum.' FROM {{salary_advances}} r JOIN {{users}} u ON u.id=r.user_id WHERE '.$where.' GROUP BY r.user_id,u.name ORDER BY u.name',$params);
  $byDay=$a->all('SELECT '.$column.' day,'.$sum.' FROM {{salary_advances}} r WHERE '.$where.' GROUP BY '.$column.' ORDER BY '.$column.' DESC',$params);
  return [200,['rows'=>$rows,'summary'=>$summary,'by_person'=>$byPerson,'by_day'=>$byDay,'people'=>$people,'page'=>$page,'total'=>(int)$summary['requests'],'start'=>$start,'end'=>$end,'basis'=>$basis]];
 }
 if(preg_match('~^/advances/([a-f0-9-]{36})/(review|pay|cancel|audit)$~D',$path,$m)){
  $id=entry_uuid($m[1]);$row=$a->one('SELECT * FROM {{salary_advances}} WHERE id=?',[$id]);if(!$row)fail('Không tìm thấy yêu cầu ứng lương.',404);
  if(!$manager&&$row['user_id']!==$a->user['id'])fail('Bạn không có quyền xem yêu cầu này.',403);
  if($m[2]==='audit'&&$method==='GET')return [200,['rows'=>$a->all('SELECT h.*,u.name actor_name FROM {{advance_audit}} h JOIN {{users}} u ON u.id=h.actor_id WHERE advance_id=? ORDER BY h.time,h.id',[$id])]];
  if($method!=='POST')fail('Không tìm thấy chức năng.',404);
  if($m[2]==='cancel'){
   if($row['user_id']!==$a->user['id'])fail('Chỉ được hủy yêu cầu của chính mình.',403);
   entry_version($b,$row);if($row['status']!=='pending')fail('Chỉ hủy được yêu cầu đang chờ duyệt.',409);
   $a->run("UPDATE {{salary_advances}} SET status='cancelled',version=version+1 WHERE id=?",[$id]);finance_audit($a,'advance',$id,'cancelled','Nhân viên hủy yêu cầu.');return [200,['ok'=>true]];
  }
  $a->requireManager();if($row['user_id']===$a->user['id'])fail('Không được tự duyệt hoặc xác nhận chi yêu cầu của mình.',403);$a->target($row['user_id'],true);entry_version($b,$row);
  if($m[2]==='review'){
   if($row['status']!=='pending')fail('Yêu cầu đã được xử lý.',409);
   $status=$b['status']??'';if(!in_array($status,['approved','rejected'],true))fail('Kết quả duyệt không hợp lệ.');
   $note=input_text($b['note']??'','Ghi chú',$status==='rejected'?5:0,1000);
   $a->run('UPDATE {{salary_advances}} SET status=?,reviewed_by=?,reviewed_at=?,review_note=?,version=version+1 WHERE id=?',[$status,$a->user['id'],now_ms(),$note,$id]);finance_audit($a,'advance',$id,$status,$note);return [200,['ok'=>true]];
  }
  if($m[2]==='pay'){
   if($row['status']!=='approved')fail('Chỉ xác nhận chi yêu cầu đã được duyệt và chưa chi.',409);
   if(($b['confirmed']??false)!==true)fail('Cần xác nhận đã thực sự giao/chuyển tiền.');
   $day=valid_day($b['paid_date']??null);if($day<'2020-01-01'||$day>local_day(now_ms())||$day<local_day($row['created_at']))fail('Ngày đã chi phải từ ngày tạo yêu cầu đến hôm nay.');
   $note=input_text($b['note']??'','Ghi chú chi tiền',0,1000);
   $a->run("UPDATE {{salary_advances}} SET status='paid',paid_date=?,paid_by=?,paid_at=?,payment_note=?,version=version+1 WHERE id=?",[$day,$a->user['id'],now_ms(),$note,$id]);finance_audit($a,'advance',$id,'paid',$day.' · '.$note);return [200,['ok'=>true]];
  }
 }
 fail('Không tìm thấy chức năng ứng lương.',404);
}
// Admin và Quản lý luôn được ghi, sửa, xử lý Event log; Nhân viên cần Admin tick "Viết Event Log" trong hồ sơ (chủ dự án chốt 30/10/2026).
function log_permissions(App $a): array {
 $full=in_array($a->user['role'],['admin','manager'],true);$delegated=(bool)$a->one('SELECT user_id FROM {{event_log_recorders}} WHERE user_id=?',[$a->user['id']]);
 return ['can_record'=>$full||$delegated,'can_manage'=>$full||$delegated,'can_grant'=>$a->user['role']==='admin','delegated'=>$delegated];
}
// Cấp quyền trong hồ sơ nhân viên (chủ dự án yêu cầu 30/10/2026): Viết Event Log, Lên lịch chụp. Chỉ Admin tick;
// Admin và Quản lý luôn có đủ 2 quyền nên không giữ dòng cấp quyền cho họ.
const USER_GRANTS=['event_log'=>['event_log_recorders','grant_event_log','revoke_event_log'],'bookings'=>['booking_creators','grant_booking_create','revoke_booking_create']];
function user_grants(App $a): array {$out=[];foreach(USER_GRANTS as $k=>[$table])$out[$k]=array_fill_keys(array_map('intval',array_column($a->all('SELECT user_id FROM {{'.$table.'}}'),'user_id')),true);return $out;}
function save_user_grants(App $a,int $id,string $role,mixed $grants): void {
 if(!is_array($grants))fail('Cấp quyền không hợp lệ.');
 foreach(USER_GRANTS as $k=>[$table,$grant,$revoke]){
  if(!is_bool($grants[$k]??null))fail('Cấp quyền không hợp lệ.');
  $had=(bool)$a->one('SELECT user_id FROM {{'.$table.'}} WHERE user_id=?',[$id]);$want=$role==='employee'&&$grants[$k];if($want===$had)continue;
  if($want)$a->run('INSERT INTO {{'.$table.'}} VALUES(?,?,?)',[$id,$a->user['id'],now_ms()]);else $a->run('DELETE FROM {{'.$table.'}} WHERE user_id=?',[$id]);
  if($role==='employee')$a->audit($id,$want?$grant:$revoke);
 }
}
// Người được cấp quyền (không phải Admin) sửa, bổ sung, xử lý hoặc hủy log ghi về chính mình: không áp dụng ngay,
// tạo yêu cầu kèm nội dung trước/sau; Quản lý hoặc Admin khác duyệt mới áp dụng (chủ dự án chốt 29/10/2026).
const LOG_STATUS_LABELS=['pending'=>'Chờ xác nhận','confirmed'=>'Đã xác nhận','resolved'=>'Đã xử lý','void'=>'Đã hủy','historical'=>'Log lưu trữ'];
const LOG_CATEGORY_LABELS=['missing_punch'=>'Quên chấm công','late'=>'Đi trễ','early_out'=>'Về sớm','absence'=>'Vắng ca','customer'=>'Khách hàng','task'=>'Công việc','other'=>'Khác'];
const LOG_CHANGE_LABELS=['edit'=>'Chỉnh sửa nội dung','classify'=>'Bổ sung ngày / nhân viên','resolve'=>'Xử lý / hủy log'];
function log_needs_approval(App $a,array $row): bool {return $a->user['role']!=='admin'&&$row['user_id']!==null&&(int)$row['user_id']===(int)$a->user['id'];}
function log_person(App $a,?int $id): string {return $id?(string)$a->scalar('SELECT name FROM {{users}} WHERE id=?',[$id]):'Chưa gắn nhân viên';}
function log_change_text(array $fields): string {return implode('; ',array_map(fn($k,$v)=>$k.': '.$v,array_keys($fields),$fields));}
function log_request_change(App $a,array $row,string $action,array $b,array $before,array $after): array {
 if($a->one("SELECT id FROM {{event_log_change_requests}} WHERE event_log_id=? AND status='pending'",[$row['id']]))fail('Log này đã có yêu cầu đang chờ Quản lý duyệt.',409);
 $id=uuid_v4();unset($b['version']);
 $a->run("INSERT INTO {{event_log_change_requests}}(id,event_log_id,action,payload_json,before_json,after_json,base_version,requested_by,requested_at,status,review_note) VALUES(?,?,?,?,?,?,?,?,?,'pending','')",[$id,$row['id'],$action,json_encode($b,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),json_encode($before,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),json_encode($after,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),$row['version'],$a->user['id'],now_ms()]);
 finance_audit($a,'log',$row['id'],'change_requested',LOG_CHANGE_LABELS[$action].' · chờ Quản lý duyệt | Trước: '.log_change_text($before).' | Sau: '.log_change_text($after));
 return [202,['pending_approval'=>true,'request_id'=>$id]];
}
function log_change_routes(App $a,string $path,array $b,string $method): ?array {
 if(!preg_match('~^/event-log/requests/([a-f0-9-]{36})/(approve|reject|cancel)$~D',$path,$m)||$method!=='POST')return null;
 $req=$a->one('SELECT * FROM {{event_log_change_requests}} WHERE id=?',[entry_uuid($m[1])]);if(!$req)fail('Không tìm thấy yêu cầu.',404);if($req['status']!=='pending')fail('Yêu cầu đã được xử lý.',409);
 $note=input_text($b['note']??'','Ghi chú',$m[2]==='reject'?5:0,1000);$label=LOG_CHANGE_LABELS[$req['action']]??$req['action'];
 $close=fn(string $status)=>$a->run('UPDATE {{event_log_change_requests}} SET status=?,reviewed_by=?,reviewed_at=?,review_note=? WHERE id=?',[$status,$a->user['id'],now_ms(),$note,$req['id']]);
 if($m[2]==='cancel'){if($req['requested_by']!==$a->user['id'])fail('Chỉ người gửi được rút yêu cầu.',403);$close('cancelled');finance_audit($a,'log',$req['event_log_id'],'change_cancelled','Rút yêu cầu '.$label.($note!==''?' | '.$note:''));return [200,['ok'=>true]];}
 if(!in_array($a->user['role'],['admin','manager'],true))fail('Chỉ Quản lý hoặc Admin được duyệt yêu cầu sửa/hủy log.',403);
 if($req['requested_by']===$a->user['id'])fail('Không được tự duyệt yêu cầu của mình.',403);
 if($m[2]==='reject'){$close('rejected');finance_audit($a,'log',$req['event_log_id'],'change_rejected','Từ chối '.$label.' | '.$note);return [200,['ok'=>true]];}
 $row=$a->one('SELECT * FROM {{event_log}} WHERE id=?',[$req['event_log_id']]);if(!$row)fail('Không tìm thấy sự việc.',404);
 if($row['version']!==$req['base_version'])fail('Log đã thay đổi sau khi gửi yêu cầu. Hãy từ chối để người gửi tạo lại.',409);
 $requester=$a->one("SELECT * FROM {{users}} WHERE id=? AND archived=0 AND status!='inactive'",[$req['requested_by']]);if(!$requester)fail('Người gửi đã nghỉ. Hãy từ chối yêu cầu.',409);
 // Áp dụng đúng thao tác người gửi đã yêu cầu (vẫn kiểm tra quyền và dữ liệu của họ), rồi ghi người duyệt.
 $approver=$a->user;$a->user=$requester;
 try{log_routes($a,'/event-log/'.$row['id'].'/'.$req['action'],json_decode($req['payload_json'],true,32,JSON_THROW_ON_ERROR)+['version'=>$req['base_version']],'POST',true);}finally{$a->user=$approver;}
 $close('approved');finance_audit($a,'log',$req['event_log_id'],'change_approved','Duyệt '.$label.' của '.$requester['name'].($note!==''?' | '.$note:''));
 return [200,['ok'=>true]];
}
function log_change_requests(App $a): array {
 $approver=in_array($a->user['role'],['admin','manager'],true);
 $rows=$a->all("SELECT c.id,c.event_log_id,c.action,c.before_json,c.after_json,c.requested_by,c.requested_at,u.name requester_name,l.description,l.event_date,(SELECT name FROM {{users}} WHERE id=l.user_id) subject_name FROM {{event_log_change_requests}} c JOIN {{users}} u ON u.id=c.requested_by JOIN {{event_log}} l ON l.id=c.event_log_id WHERE c.status='pending'".($approver?'':' AND c.requested_by=?').' ORDER BY c.requested_at',$approver?[]:[$a->user['id']]);
 foreach($rows as &$r){$r['before']=json_decode($r['before_json'],true);$r['after']=json_decode($r['after_json'],true);unset($r['before_json'],$r['after_json']);$r['can_review']=$approver&&$r['requested_by']!==$a->user['id'];}unset($r);
 return $rows;
}
function log_routes(App $a,string $path,array $b,string $method,bool $approved=false): ?array {
 if($path!=='/event-log'&&!str_starts_with($path,'/event-log/'))return null;
 if($r=log_change_routes($a,$path,$b,$method))return $r;
 $perms=log_permissions($a);$manage=$perms['can_manage'];
 if($path==='/event-log/source'&&$method==='GET'){$file=__DIR__.'/legacy-event-log.pdf';if(!is_file($file))fail('Chưa có PDF gốc.',404);header('Content-Type: application/pdf');header('Content-Disposition: inline; filename="EVENT-LOG-STUDIO.pdf"');readfile($file);exit;}
 if(preg_match('~^/event-log/([a-f0-9-]{36})/classify$~D',$path,$m)&&$method==='POST'){
  if(!$manage)fail('Chỉ Admin, Quản lý hoặc nhân viên được Admin cấp quyền mới được chỉnh sửa Event log.',403);$row=$a->one('SELECT * FROM {{event_log}} WHERE id=?',[$m[1]]);if(!$row)fail('Không tìm thấy log.',404);entry_version($b,$row);if(!in_array($row['status'],['pending','historical'],true))fail('Chỉ bổ sung thông tin cho log chưa xác nhận hoặc log lưu trữ.',409);
  $subject=$b['user_id']??null;if($subject!==null){if(!is_int($subject))fail('Nhân viên không hợp lệ.');if(!$a->one('SELECT id FROM {{users}} WHERE id=?',[$subject]))fail('Nhân viên không tồn tại.');}
  $precision=$b['date_precision']??'';if(!in_array($precision,['day','month','unknown'],true))fail('Độ chính xác ngày không hợp lệ.');
  $day=$precision==='day'?valid_day($b['event_date']??null):($precision==='month'?valid_month($b['event_date']??null):'');if($day&&$day>local_day(now_ms()))fail('Ngày không được ở tương lai.');
  $note=input_text($b['note']??null,'Lý do bổ sung',5,1000);
  if(!$approved&&log_needs_approval($a,$row))return log_request_change($a,$row,'classify',$b,['Ngày'=>$row['event_date']?:'Chưa xác định','Nhân viên'=>log_person($a,$row['user_id'])],['Ngày'=>$day?:'Chưa xác định','Nhân viên'=>log_person($a,$subject),'Lý do'=>$note]);
  $a->run("UPDATE {{event_log}} SET user_id=?,event_date=?,date_precision=?,end_month='',updated_by=?,updated_at=?,version=version+1 WHERE id=?",[$subject,$day,$precision,$a->user['id'],now_ms(),$row['id']]);
  $beforeName=$row['user_id']?$a->scalar('SELECT name FROM {{users}} WHERE id=?',[$row['user_id']]):'Chưa gắn nhân viên';$afterName=$subject?$a->scalar('SELECT name FROM {{users}} WHERE id=?',[$subject]):'Chưa gắn nhân viên';finance_audit($a,'log',$row['id'],'classified','Ngày: '.($row['event_date']?:'Chưa xác định').' → '.($day?:'Chưa xác định').' | Nhân viên: '.$beforeName.' → '.$afterName.' | '.$note);return [200,['ok'=>true]];
 }
 if($path==='/event-log/recorders'&&$method==='POST'){
  $a->requireAdmin();if(!is_int($b['user_id']??null)||!is_bool($b['enabled']??null))fail('Quyền ghi Event log không hợp lệ.');
  $target=$a->target($b['user_id']);if($target['role']!=='employee')fail('Admin và Quản lý luôn có quyền chỉnh sửa Event log.');
  if($b['enabled'])$a->run('INSERT INTO {{event_log_recorders}} VALUES(?,?,?) ON DUPLICATE KEY UPDATE granted_by=VALUES(granted_by),granted_at=VALUES(granted_at)',[$target['id'],$a->user['id'],now_ms()]);else $a->run('DELETE FROM {{event_log_recorders}} WHERE user_id=?',[$target['id']]);
  $a->audit($target['id'],$b['enabled']?'grant_event_log':'revoke_event_log');return [200,['ok'=>true]];
 }
 if($path==='/event-log'&&$method==='POST'){
  if(!$perms['can_record'])fail('Bạn chưa được cấp quyền ghi Event log.',403);
  $id=entry_uuid($b['id']??null);$day=valid_day($b['event_date']??null);if($day<'2020-01-01'||$day>local_day(now_ms()))fail('Ngày sự việc không được ở tương lai.');
  $subject=$b['user_id']??null;if($subject!==null){if(!is_int($subject))fail('Nhân viên không hợp lệ.');$target=$a->one("SELECT id,role FROM {{users}} WHERE id=? AND archived=0 AND status!='inactive'",[$subject]);if(!$target)fail('Nhân viên không tồn tại hoặc đã nghỉ.');}
  $rule=function_exists('studio_rule_pick')?studio_rule_pick($a,$b):null;
  $deduction=$rule?$rule['points']:($b['deduction']??0);if($rule?($subject===null&&$deduction!==0):(!is_int($deduction)||!in_array($deduction,[0,20,100,300],true)||($subject===null&&$deduction!==0)))fail($rule?'Nhật ký chung studio không trừ điểm. Chọn nhân viên liên quan để áp luật '.$rule['code'].'.':'Điểm KPI cần là 0, 20, 100 hoặc 300; nhật ký chung không trừ điểm.');
  $category=$b['category']??'';if(!in_array($category,LOG_CATEGORIES,true))fail('Loại sự việc không hợp lệ.');
  $description=input_text($b['description']??null,'Nội dung sự việc',5,2000);$existing=$a->one('SELECT * FROM {{event_log}} WHERE id=?',[$id]);
  if($existing){if($existing['created_by']===$a->user['id']&&$existing['user_id']===$subject&&$existing['event_date']===$day&&$existing['category']===$category&&$existing['points']===-$deduction&&$existing['description']===$description)return [200,['id'=>$id,'already_saved'=>true]];fail('Mã sự việc đã được sử dụng.',409);}
  $status=$manage?'confirmed':'pending';$a->run("INSERT INTO {{event_log}}(id,user_id,event_date,category,description,rule_id,rule_code,rule_name,points,status,created_by,created_at,updated_by,updated_at,resolution) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,'')",[$id,$subject,$day,$category,$description,$rule['id']??null,$rule['code']??'',$rule['name']??'',-$deduction,$status,$a->user['id'],now_ms(),$a->user['id'],now_ms()]);
  finance_audit($a,'log',$id,'created','Ghi nhận bởi người được cấp quyền chỉnh sửa Event log.');return [201,['id'=>$id,'status'=>$status]];
 }
 if($path==='/event-log'&&$method==='GET'){
  [$start,$end]=finance_range();$period=$_GET['period']??'month';if(!in_array($period,['all','month','unknown'],true))fail('Khoảng lịch sử không hợp lệ.');$params=[];$where='1=1';if($period==='unknown')$where="r.date_precision='unknown'";elseif($period==='month'){if(!empty($_GET['date'])){$where="((r.date_precision='day' AND r.event_date=?) OR (r.date_precision='day_range' AND r.event_date<=? AND r.end_date>=?))";$params=[$start,$start,$start];}else{$month=substr($start,0,7);$where="((r.date_precision='day' AND r.event_date>=? AND r.event_date<=?) OR (r.date_precision='day_range' AND r.event_date<=? AND r.end_date>=?) OR (r.date_precision IN ('month','range') AND r.event_date<=? AND (CASE WHEN r.end_month='' THEN r.event_date ELSE r.end_month END)>=?))";$params=[$start,$end,$end,$start,$month,$month];}}
  if(!empty($_GET['user_id'])){if(!ctype_digit((string)$_GET['user_id']))fail('Nhân viên không hợp lệ.');$where.=' AND r.user_id=?';$params[]=(int)$_GET['user_id'];}
  $status=$_GET['status']??'';if($status!==''){if(!in_array($status,['pending','confirmed','resolved','void','historical'],true))fail('Trạng thái không hợp lệ.');$where.=' AND r.status=?';$params[]=$status;}
  $q=input_text($_GET['q']??'','Từ khóa',0,200);if($q!==''){$where.=' AND (r.description LIKE ? OR r.related_names LIKE ? OR r.source_label LIKE ? OR EXISTS(SELECT 1 FROM {{users}} person WHERE person.id=r.user_id AND person.name LIKE ?))';$params=[...$params,'%'.$q.'%','%'.$q.'%','%'.$q.'%','%'.$q.'%'];}
  $page=finance_page();$per=(int)($_GET['per_page']??100);if(!in_array($per,[10,20,50,100],true))fail('Số dòng mỗi trang không hợp lệ.');$summary=$a->one("SELECT COUNT(*) total,COALESCE(SUM(CASE WHEN r.status='pending' THEN 1 ELSE 0 END),0) pending,COALESCE(SUM(CASE WHEN r.status IN ('confirmed','resolved') THEN -r.points ELSE 0 END),0) deduction_points FROM {{event_log}} r WHERE ".$where,$params);
  $rows=$a->all('SELECT r.*,u.name,u.employee_code,u.role,(SELECT name FROM {{users}} WHERE id=r.created_by) creator_name,(SELECT name FROM {{users}} WHERE id=r.updated_by) updater_name,(SELECT c.action FROM {{event_log_change_requests}} c WHERE c.event_log_id=r.id AND c.status=\'pending\' LIMIT 1) pending_change FROM {{event_log}} r LEFT JOIN {{users}} u ON u.id=r.user_id WHERE '.$where.' ORDER BY r.event_date DESC,r.created_at DESC,r.id LIMIT '.$per.' OFFSET '.(($page-1)*$per),$params);
  $byPerson=$a->all("SELECT r.user_id,u.name,COUNT(*) total,COALESCE(SUM(CASE WHEN r.status IN ('confirmed','resolved') THEN -r.points ELSE 0 END),0) deduction_points FROM {{event_log}} r LEFT JOIN {{users}} u ON u.id=r.user_id WHERE ".$where.' GROUP BY r.user_id,u.name ORDER BY u.name',$params);
  $people=finance_people($a,true);
  $recorders=$perms['can_grant']?array_column($a->all('SELECT user_id FROM {{event_log_recorders}}'),'user_id'):[];
  return [200,['rows'=>$rows,'summary'=>$summary,'by_person'=>$byPerson,'people'=>$people,'recorders'=>$recorders,'change_requests'=>log_change_requests($a),'permissions'=>$perms+['can_approve'=>in_array($a->user['role'],['admin','manager'],true)],'page'=>$page,'per_page'=>$per,'total'=>(int)$summary['total'],'start'=>$start,'end'=>$end,'period'=>$period]];
 }
 if(preg_match('~^/event-log/([a-f0-9-]{36})/edit$~D',$path,$m)&&$method==='POST'){
  if(!$manage)fail('Chỉ Admin, Quản lý hoặc nhân viên được Admin cấp quyền mới được chỉnh sửa Event log.',403);
  $row=$a->one('SELECT * FROM {{event_log}} WHERE id=?',[$m[1]]);if(!$row)fail('Không tìm thấy sự việc.',404);entry_version($b,$row);
  $description=input_text($b['description']??null,'Nội dung sự việc',5,2000);$note=input_text($b['note']??null,'Lý do chỉnh sửa',5,1000);$category=$b['category']??'';if(!in_array($category,LOG_CATEGORIES,true))fail('Loại sự việc không hợp lệ.');
  if(!$approved&&log_needs_approval($a,$row))return log_request_change($a,$row,'edit',$b,['Loại'=>LOG_CATEGORY_LABELS[$row['category']]??$row['category'],'Nội dung'=>$row['description']],['Loại'=>LOG_CATEGORY_LABELS[$category],'Nội dung'=>$description,'Lý do'=>$note]);
  $a->run('UPDATE {{event_log}} SET description=?,category=?,updated_by=?,updated_at=?,version=version+1 WHERE id=?',[$description,$category,$a->user['id'],now_ms(),$row['id']]);
  finance_audit($a,'log',$row['id'],'edited',$note.' | Loại: '.$row['category'].' → '.$category.' | Nội dung trước: '.$row['description'].' | Nội dung sau: '.$description);return [200,['ok'=>true]];
 }
 if(preg_match('~^/event-log/([a-f0-9-]{36})/(resolve|audit)$~D',$path,$m)){
  $id=entry_uuid($m[1]);$row=$a->one('SELECT * FROM {{event_log}} WHERE id=?',[$id]);if(!$row)fail('Không tìm thấy sự việc.',404);
  if($m[2]==='audit'&&$method==='GET')return [200,['rows'=>$a->all('SELECT h.*,u.name actor_name FROM {{event_log_audit}} h JOIN {{users}} u ON u.id=h.actor_id WHERE event_log_id=? ORDER BY h.time,h.id',[$id])]];
  if($m[2]==='resolve'&&$method==='POST'){
   if(!$manage)fail('Chỉ Admin, Quản lý hoặc nhân viên được Admin cấp quyền mới được chỉnh sửa Event log.',403);entry_version($b,$row);
   $status=$b['status']??'';$allowed=match($row['status']){'pending'=>['confirmed','void'],'historical'=>['confirmed','void'],'confirmed'=>['resolved','void'],'resolved'=>['void'],default=>[]};
   if(!in_array($status,$allowed,true))fail('Trạng thái sự việc đã thay đổi hoặc thao tác không hợp lệ.',409);
   if($row['created_by']===$a->user['id']&&$row['status']==='pending')fail('Không được tự xác nhận nhật ký đã gửi của mình.',403);
   $note=input_text($b['note']??null,'Ghi chú xử lý',5,2000);
   $points=$row['points'];$rule=null;if($status==='confirmed'&&function_exists('studio_rule_pick'))$rule=studio_rule_pick($a,$b);
   if($rule){if($row['user_id']===null&&$rule['points']!==0)fail('Nhật ký chung studio không trừ điểm.');$points=-$rule['points'];}
   elseif($status==='confirmed'&&array_key_exists('deduction',$b)){if(!is_int($b['deduction'])||!in_array($b['deduction'],[0,20,100,300],true)||($row['user_id']===null&&$b['deduction']!==0))fail('Điểm xác nhận không hợp lệ.');$points=-$b['deduction'];}
   if(!$approved&&log_needs_approval($a,$row))return log_request_change($a,$row,'resolve',$b,['Trạng thái'=>LOG_STATUS_LABELS[$row['status']]??$row['status'],'Điểm KPI'=>abs($row['points']),'Luật'=>$row['rule_code']?:'—'],['Trạng thái'=>LOG_STATUS_LABELS[$status],'Điểm KPI'=>abs($points),'Luật'=>$rule['code']??($row['rule_code']?:'—'),'Giải trình'=>$note]);
   $a->run('UPDATE {{event_log}} SET status=?,points=?,resolution=?,rule_id=COALESCE(?,rule_id),rule_code=COALESCE(?,rule_code),rule_name=COALESCE(?,rule_name),updated_by=?,updated_at=?,version=version+1 WHERE id=?',[$status,$points,$note,$rule['id']??null,$rule['code']??null,$rule['name']??null,$a->user['id'],now_ms(),$id]);finance_audit($a,'log',$id,$status,$note.($rule?' | Luật: '.$rule['code'].' · '.$rule['name']:'').($points!==$row['points']?' | Điểm: '.$row['points'].' → '.$points:''));return [200,['ok'=>true]];
  }
 }
 fail('Không tìm thấy chức năng Event log.',404);
}
function people_finance_routes(App $a,string $path,array $b,string $method): ?array {
 return advance_routes($a,$path,$b,$method)??log_routes($a,$path,$b,$method);
}

function load_legacy_event_log(App $a): void {
 $file=__DIR__.'/legacy-event-log.json';if(!is_file($file))return;
 $meta='legacy_log_import_202510_v3';if($a->meta($meta)==='done')return;
 $admin=$a->one("SELECT id FROM {{users}} WHERE role='admin' AND archived=0 ORDER BY id LIMIT 1");if(!$admin)return;
 $data=json_decode(file_get_contents($file),true,64,JSON_THROW_ON_ERROR);if(($data['format']??'')!=='anhgau-legacy-log-v1')return;
 $a->tx(function()use($a,$data,$admin,$meta){
  if($a->meta($meta)==='done')return;
  foreach($data['entries'] as $r){$old=$a->one('SELECT * FROM {{event_log}} WHERE source_ref=?',[$r['source_ref']]);if($old){
   if(!$a->one("SELECT id FROM {{event_log_audit}} WHERE event_log_id=? AND action='classified'",[$old['id']])){
    $a->run('UPDATE {{event_log}} SET event_date=?,date_precision=?,original_date=?,end_month=?,end_date=?,version=version+1 WHERE id=?',[$r['event_date'],$r['date_precision'],$r['original_date'],$r['end_month'],$r['end_date'],$old['id']]);
    finance_audit_actor($a,$old['id'],$admin['id'],'source_date_refined',$r['date_note']);
    if($old['status']==='pending'&&$old['points']===0&&!$a->one("SELECT id FROM {{event_log_audit}} WHERE event_log_id=? AND action IN ('confirmed','resolved','void')",[$old['id']]))$a->run("UPDATE {{event_log}} SET status='historical' WHERE id=?",[$old['id']]);
   }continue;}
   $id=$r['id'];$a->run("INSERT INTO {{event_log}}(id,user_id,event_date,category,description,source_ref,source_label,date_precision,original_date,end_month,end_date,related_names,points,status,created_by,created_at,updated_by,updated_at,resolution) VALUES(?,NULL,?,'other',?,?,?,?,?,?,?,?,0,'historical',?,?,?,?,?)",[$id,$r['event_date'],$r['description'],$r['source_ref'],$r['source_label'],$r['date_precision'],$r['original_date'],$r['end_month'],$r['end_date'],$r['related_names'],$admin['id'],now_ms(),$admin['id'],now_ms(),'Nhập từ tài liệu lịch sử; chưa xác minh sự việc, danh tính viết tắt hoặc điểm KPI.']);
   finance_audit_actor($a,$id,$admin['id'],'legacy_import','Nhập nguyên nội dung mục '.$r['number'].' từ PDF. '.$r['date_note']);
  }
  $a->meta($meta,'done');
 });
}
function finance_audit_actor(App $a,string $id,int $actor,string $action,string $note): void {
 $a->run('INSERT INTO {{event_log_audit}}(event_log_id,actor_id,action,note,time) VALUES(?,?,?,?,?)',[$id,$actor,$action,$note,now_ms()]);
}
