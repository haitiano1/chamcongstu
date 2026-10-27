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
function log_permissions(App $a): array {
 $admin=$a->user['role']==='admin';$delegated=(bool)$a->one('SELECT user_id FROM {{event_log_recorders}} WHERE user_id=?',[$a->user['id']]);
 return ['can_record'=>$admin||$delegated,'can_manage'=>$admin||$delegated,'can_grant'=>$admin,'delegated'=>$delegated];
}
function log_routes(App $a,string $path,array $b,string $method): ?array {
 if($path!=='/event-log'&&!str_starts_with($path,'/event-log/'))return null;
 $perms=log_permissions($a);$manage=$perms['can_manage'];
 if($path==='/event-log/source'&&$method==='GET'){$file=__DIR__.'/legacy-event-log.pdf';if(!is_file($file))fail('Chưa có PDF gốc.',404);header('Content-Type: application/pdf');header('Content-Disposition: inline; filename="EVENT-LOG-STUDIO.pdf"');readfile($file);exit;}
 if(preg_match('~^/event-log/([a-f0-9-]{36})/classify$~D',$path,$m)&&$method==='POST'){
  if(!$manage)fail('Chỉ Admin hoặc người được Admin cấp quyền mới được chỉnh sửa Event log.',403);$row=$a->one('SELECT * FROM {{event_log}} WHERE id=?',[$m[1]]);if(!$row)fail('Không tìm thấy log.',404);entry_version($b,$row);if(!in_array($row['status'],['pending','historical'],true))fail('Chỉ bổ sung thông tin cho log chưa xác nhận hoặc log lưu trữ.',409);
  $subject=$b['user_id']??null;if($subject!==null){if(!is_int($subject))fail('Nhân viên không hợp lệ.');if(!$a->one('SELECT id FROM {{users}} WHERE id=?',[$subject]))fail('Nhân viên không tồn tại.');}
  $precision=$b['date_precision']??'';if(!in_array($precision,['day','month','unknown'],true))fail('Độ chính xác ngày không hợp lệ.');
  $day=$precision==='day'?valid_day($b['event_date']??null):($precision==='month'?valid_month($b['event_date']??null):'');if($day&&$day>local_day(now_ms()))fail('Ngày không được ở tương lai.');
  $note=input_text($b['note']??null,'Lý do bổ sung',5,1000);
  $a->run("UPDATE {{event_log}} SET user_id=?,event_date=?,date_precision=?,end_month='',updated_by=?,updated_at=?,version=version+1 WHERE id=?",[$subject,$day,$precision,$a->user['id'],now_ms(),$row['id']]);
  $beforeName=$row['user_id']?$a->scalar('SELECT name FROM {{users}} WHERE id=?',[$row['user_id']]):'Chưa gắn nhân viên';$afterName=$subject?$a->scalar('SELECT name FROM {{users}} WHERE id=?',[$subject]):'Chưa gắn nhân viên';finance_audit($a,'log',$row['id'],'classified','Ngày: '.($row['event_date']?:'Chưa xác định').' → '.($day?:'Chưa xác định').' | Nhân viên: '.$beforeName.' → '.$afterName.' | '.$note);return [200,['ok'=>true]];
 }
 if($path==='/event-log/recorders'&&$method==='POST'){
  $a->requireAdmin();if(!is_int($b['user_id']??null)||!is_bool($b['enabled']??null))fail('Quyền ghi Event log không hợp lệ.');
  $target=$a->target($b['user_id']);if($target['role']==='admin')fail('Admin luôn có quyền chỉnh sửa Event log.');
  if($b['enabled'])$a->run('INSERT INTO {{event_log_recorders}} VALUES(?,?,?) ON DUPLICATE KEY UPDATE granted_by=VALUES(granted_by),granted_at=VALUES(granted_at)',[$target['id'],$a->user['id'],now_ms()]);else $a->run('DELETE FROM {{event_log_recorders}} WHERE user_id=?',[$target['id']]);
  $a->audit($target['id'],$b['enabled']?'grant_event_log':'revoke_event_log');return [200,['ok'=>true]];
 }
 if($path==='/event-log'&&$method==='POST'){
  if(!$perms['can_record'])fail('Bạn chưa được cấp quyền ghi Event log.',403);
  $id=entry_uuid($b['id']??null);$day=valid_day($b['event_date']??null);if($day<'2020-01-01'||$day>local_day(now_ms()))fail('Ngày sự việc không được ở tương lai.');
  $subject=$b['user_id']??null;if($subject!==null){if(!is_int($subject))fail('Nhân viên không hợp lệ.');$target=$a->one("SELECT id,role FROM {{users}} WHERE id=? AND archived=0 AND status!='inactive'",[$subject]);if(!$target)fail('Nhân viên không tồn tại hoặc đã nghỉ.');}
  $deduction=$b['deduction']??0;if(!is_int($deduction)||!in_array($deduction,[0,20,100,300],true)||($subject===null&&$deduction!==0))fail('Điểm KPI cần là 0, 20, 100 hoặc 300; nhật ký chung không trừ điểm.');
  $category=$b['category']??'';if(!in_array($category,LOG_CATEGORIES,true))fail('Loại sự việc không hợp lệ.');
  $description=input_text($b['description']??null,'Nội dung sự việc',5,2000);$existing=$a->one('SELECT * FROM {{event_log}} WHERE id=?',[$id]);
  if($existing){if($existing['created_by']===$a->user['id']&&$existing['user_id']===$subject&&$existing['event_date']===$day&&$existing['category']===$category&&$existing['points']===-$deduction&&$existing['description']===$description)return [200,['id'=>$id,'already_saved'=>true]];fail('Mã sự việc đã được sử dụng.',409);}
  $status=$manage?'confirmed':'pending';$a->run("INSERT INTO {{event_log}}(id,user_id,event_date,category,description,points,status,created_by,created_at,updated_by,updated_at,resolution) VALUES(?,?,?,?,?,?,?,?,?,?,?,'')",[$id,$subject,$day,$category,$description,-$deduction,$status,$a->user['id'],now_ms(),$a->user['id'],now_ms()]);
  finance_audit($a,'log',$id,'created','Ghi nhận bởi người được cấp quyền chỉnh sửa Event log.');return [201,['id'=>$id,'status'=>$status]];
 }
 if($path==='/event-log'&&$method==='GET'){
  [$start,$end]=finance_range();$period=$_GET['period']??'month';if(!in_array($period,['all','month','unknown'],true))fail('Khoảng lịch sử không hợp lệ.');$params=[];$where='1=1';if($period==='unknown')$where="r.date_precision='unknown'";elseif($period==='month'){if(!empty($_GET['date'])){$where="((r.date_precision='day' AND r.event_date=?) OR (r.date_precision='day_range' AND r.event_date<=? AND r.end_date>=?))";$params=[$start,$start,$start];}else{$month=substr($start,0,7);$where="((r.date_precision='day' AND r.event_date>=? AND r.event_date<=?) OR (r.date_precision='day_range' AND r.event_date<=? AND r.end_date>=?) OR (r.date_precision IN ('month','range') AND r.event_date<=? AND (CASE WHEN r.end_month='' THEN r.event_date ELSE r.end_month END)>=?))";$params=[$start,$end,$end,$start,$month,$month];}}
  if(!empty($_GET['user_id'])){if(!ctype_digit((string)$_GET['user_id']))fail('Nhân viên không hợp lệ.');$where.=' AND r.user_id=?';$params[]=(int)$_GET['user_id'];}
  $status=$_GET['status']??'';if($status!==''){if(!in_array($status,['pending','confirmed','resolved','void','historical'],true))fail('Trạng thái không hợp lệ.');$where.=' AND r.status=?';$params[]=$status;}
  $q=input_text($_GET['q']??'','Từ khóa',0,200);if($q!==''){$where.=' AND (r.description LIKE ? OR r.related_names LIKE ? OR r.source_label LIKE ? OR EXISTS(SELECT 1 FROM {{users}} person WHERE person.id=r.user_id AND person.name LIKE ?))';$params=[...$params,'%'.$q.'%','%'.$q.'%','%'.$q.'%','%'.$q.'%'];}
  $page=finance_page();$per=(int)($_GET['per_page']??100);if(!in_array($per,[10,20,50,100],true))fail('Số dòng mỗi trang không hợp lệ.');$summary=$a->one("SELECT COUNT(*) total,COALESCE(SUM(CASE WHEN r.status='pending' THEN 1 ELSE 0 END),0) pending,COALESCE(SUM(CASE WHEN r.status IN ('confirmed','resolved') THEN -r.points ELSE 0 END),0) deduction_points FROM {{event_log}} r WHERE ".$where,$params);
  $rows=$a->all('SELECT r.*,u.name,u.employee_code,u.role,(SELECT name FROM {{users}} WHERE id=r.created_by) creator_name,(SELECT name FROM {{users}} WHERE id=r.updated_by) updater_name FROM {{event_log}} r LEFT JOIN {{users}} u ON u.id=r.user_id WHERE '.$where.' ORDER BY r.event_date DESC,r.created_at DESC,r.id LIMIT '.$per.' OFFSET '.(($page-1)*$per),$params);
  $byPerson=$a->all("SELECT r.user_id,u.name,COUNT(*) total,COALESCE(SUM(CASE WHEN r.status IN ('confirmed','resolved') THEN -r.points ELSE 0 END),0) deduction_points FROM {{event_log}} r LEFT JOIN {{users}} u ON u.id=r.user_id WHERE ".$where.' GROUP BY r.user_id,u.name ORDER BY u.name',$params);
  $people=finance_people($a,true);
  $recorders=$perms['can_grant']?array_column($a->all('SELECT user_id FROM {{event_log_recorders}}'),'user_id'):[];
  return [200,['rows'=>$rows,'summary'=>$summary,'by_person'=>$byPerson,'people'=>$people,'recorders'=>$recorders,'permissions'=>$perms,'page'=>$page,'per_page'=>$per,'total'=>(int)$summary['total'],'start'=>$start,'end'=>$end,'period'=>$period]];
 }
 if(preg_match('~^/event-log/([a-f0-9-]{36})/edit$~D',$path,$m)&&$method==='POST'){
  if(!$manage)fail('Chỉ Admin hoặc người được Admin cấp quyền mới được chỉnh sửa Event log.',403);
  $row=$a->one('SELECT * FROM {{event_log}} WHERE id=?',[$m[1]]);if(!$row)fail('Không tìm thấy sự việc.',404);entry_version($b,$row);
  $description=input_text($b['description']??null,'Nội dung sự việc',5,2000);$note=input_text($b['note']??null,'Lý do chỉnh sửa',5,1000);$category=$b['category']??'';if(!in_array($category,LOG_CATEGORIES,true))fail('Loại sự việc không hợp lệ.');
  $a->run('UPDATE {{event_log}} SET description=?,category=?,updated_by=?,updated_at=?,version=version+1 WHERE id=?',[$description,$category,$a->user['id'],now_ms(),$row['id']]);
  finance_audit($a,'log',$row['id'],'edited',$note.' | Loại: '.$row['category'].' → '.$category.' | Nội dung trước: '.$row['description'].' | Nội dung sau: '.$description);return [200,['ok'=>true]];
 }
 if(preg_match('~^/event-log/([a-f0-9-]{36})/(resolve|audit)$~D',$path,$m)){
  $id=entry_uuid($m[1]);$row=$a->one('SELECT * FROM {{event_log}} WHERE id=?',[$id]);if(!$row)fail('Không tìm thấy sự việc.',404);
  if($m[2]==='audit'&&$method==='GET')return [200,['rows'=>$a->all('SELECT h.*,u.name actor_name FROM {{event_log_audit}} h JOIN {{users}} u ON u.id=h.actor_id WHERE event_log_id=? ORDER BY h.time,h.id',[$id])]];
  if($m[2]==='resolve'&&$method==='POST'){
   if(!$manage)fail('Chỉ Admin hoặc người được Admin cấp quyền mới được chỉnh sửa Event log.',403);entry_version($b,$row);
   $status=$b['status']??'';$allowed=match($row['status']){'pending'=>['confirmed','void'],'historical'=>['confirmed','void'],'confirmed'=>['resolved','void'],'resolved'=>['void'],default=>[]};
   if(!in_array($status,$allowed,true))fail('Trạng thái sự việc đã thay đổi hoặc thao tác không hợp lệ.',409);
   if($row['created_by']===$a->user['id']&&$row['status']==='pending')fail('Không được tự xác nhận nhật ký đã gửi của mình.',403);
   $note=input_text($b['note']??null,'Ghi chú xử lý',5,2000);
   $points=$row['points'];if($status==='confirmed'&&array_key_exists('deduction',$b)){if(!is_int($b['deduction'])||!in_array($b['deduction'],[0,20,100,300],true)||($row['user_id']===null&&$b['deduction']!==0))fail('Điểm xác nhận không hợp lệ.');$points=-$b['deduction'];} $a->run('UPDATE {{event_log}} SET status=?,points=?,resolution=?,updated_by=?,updated_at=?,version=version+1 WHERE id=?',[$status,$points,$note,$a->user['id'],now_ms(),$id]);finance_audit($a,'log',$id,$status,$note.($points!==$row['points']?' | Điểm: '.$row['points'].' → '.$points:''));return [200,['ok'=>true]];
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
