<?php
// Ứng lương: nút trạng thái có số phiếu, Sửa ngày chuyển phiếu về tháng trước (chủ dự án yêu cầu 04/11/2026); chạy trong giao dịch rồi hoàn tác.
require_once __DIR__.'/../site/nhansu-private/lib/bootstrap.php';require_once __DIR__.'/../site/nhansu-private/lib/people-finance.php';require_once __DIR__.'/../site/nhansu-private/lib/bookings.php';require_once __DIR__.'/../site/nhansu-private/lib/telegram.php';
$a=new App('/var/nhansu-private');$n=0;function verify($v,$message){global $n;if(!$v)throw new RuntimeException('FAIL: '.$message);$n++;}
try{$a->tx(function()use($a){
 $a->run('DELETE FROM {{telegram_links}}');$a->run('DELETE FROM {{salary_advances}}');
 $admin=$a->one("SELECT * FROM {{users}} WHERE role='admin' AND archived=0 LIMIT 1");
 $mk=function(string $name,string $role)use($a){$a->run("INSERT INTO {{users}}(name,email,password,role,employee_code,notes) VALUES(?,?,'x',?,?,'')",[$name,strtolower(str_replace(' ','.',$name)).'@advance.test',$role,strtoupper(substr(md5($name),0,8))]);return $a->one('SELECT * FROM {{users}} WHERE id=LAST_INSERT_ID()');};
 $staff=$mk('Advance Staff','employee');$manager=$mk('Advance Manager','manager');
 $as=function(array $u)use($a){$a->user=$u;};$call=function(string $path,array $b=[],string $m='POST',array $get=[])use($a){$_GET=$get;return advance_routes($a,$path,$b,$m);};
 $fail=function(callable $fn,string $msg){try{$fn();verify(false,$msg.' phải bị chặn');}catch(HttpError $e){verify(true,$msg);return $e->getMessage();}return '';};
 $today=local_day(now_ms());$month=substr($today,0,7);$prev=date('Y-m',strtotime($month.'-01 -1 month'));$prevEnd=date('Y-m-t',strtotime($prev.'-01'));
 // 3 phiếu tháng này: chờ duyệt, đã duyệt, đã chi.
 $as($staff);$ids=[];foreach([200000,300000,500000] as $amt){$id=uuid_v4();$call('/advances',['id'=>$id,'requested_date'=>$month.'-01','amount'=>$amt,'reason'=>'Ứng thử '.$amt]);$ids[]=$id;}
 $as($manager);$v=fn($id)=>(int)$a->scalar('SELECT version FROM {{salary_advances}} WHERE id=?',[$id]);
 $call("/advances/{$ids[1]}/review",['status'=>'approved','note'=>'','version'=>$v($ids[1])]);
 $call("/advances/{$ids[2]}/review",['status'=>'approved','note'=>'','version'=>$v($ids[2])]);
 $call("/advances/{$ids[2]}/pay",['paid_date'=>$today,'note'=>'','confirmed'=>true,'version'=>$v($ids[2])]);
 $list=$call('/advances',[],'GET',['month'=>$month])[1];$c=$list['status_counts'];
 verify($c['all']===3&&$c['pending']===1&&$c['approved']===1&&$c['paid']===1,'Đếm theo trạng thái');
 $only=$call('/advances',[],'GET',['month'=>$month,'status'=>'paid'])[1];verify($only['total']===1&&$only['status_counts']['all']===3,'Lọc trạng thái vẫn giữ số đếm đủ');
 // Nhân viên không sửa ngày; sai phiên bản bị chặn; ngày không đổi bị chặn; đã chi phải có ngày chi ≤ hôm nay.
 $as($staff);$fail(fn()=>$call("/advances/{$ids[0]}/date",['requested_date'=>$prevEnd,'version'=>$v($ids[0])]),'Nhân viên không sửa ngày');
 $as($manager);$fail(fn()=>$call("/advances/{$ids[0]}/date",['requested_date'=>$prevEnd,'version'=>$v($ids[0])+3]),'Sai phiên bản');
 $fail(fn()=>$call("/advances/{$ids[0]}/date",['requested_date'=>$month.'-01','version'=>$v($ids[0])]),'Ngày không đổi');
 $fail(fn()=>$call("/advances/{$ids[2]}/date",['requested_date'=>$prevEnd,'paid_date'=>date('Y-m-d',strtotime($today.' +2 day')),'version'=>$v($ids[2])]),'Ngày chi ở tương lai');
 // Chuyển về cuối tháng trước: phiếu chờ duyệt (chỉ ngày đề nghị) và phiếu đã chi (cả ngày chi).
 $call("/advances/{$ids[0]}/date",['requested_date'=>$prevEnd,'note'=>'Tính vào lương tháng trước','version'=>$v($ids[0])]);
 $res=$call("/advances/{$ids[2]}/date",['requested_date'=>$prevEnd,'paid_date'=>$prevEnd,'note'=>'','version'=>$v($ids[2])])[1];verify($res['paid_date']===$prevEnd,'Đổi cả ngày chi');
 $now=$call('/advances',[],'GET',['month'=>$month])[1];verify($now['total']===1&&$now['status_counts']['all']===1&&$now['rows'][0]['id']===$ids[1],'Tháng này chỉ còn phiếu chưa chuyển');
 $old=$call('/advances',[],'GET',['month'=>$prev])[1];verify($old['total']===2&&(int)$old['summary']['paid_total']===500000&&(int)$old['summary']['pending_total']===200000,'Tháng trước nhận 2 phiếu, tổng đúng');
 verify(count($old['by_person'])===1&&(int)$old['by_person'][0]['requests']===2&&$old['by_day'][0]['day']===$prevEnd,'Thống kê theo người / ngày theo ngày mới');
 $paidBasis=$call('/advances',[],'GET',['month'=>$prev,'basis'=>'paid'])[1];verify($paidBasis['total']===1&&(int)$paidBasis['summary']['paid_total']===500000,'Tính theo ngày chi cũng về tháng trước');
 $audit=$call("/advances/{$ids[0]}/audit",[],'GET')[1]['rows'];$last=end($audit);verify($last['action']==='date_changed'&&str_contains($last['note'],'→ '.implode('/',array_reverse(explode('-',$prevEnd))))&&str_contains($last['note'],'Tính vào lương'),'Lịch sử lưu ngày cũ → mới');
 $audit2=$call("/advances/{$ids[2]}/audit",[],'GET')[1]['rows'];verify(str_contains(end($audit2)['note'],'Ngày đã chi'),'Lịch sử ghi đổi ngày chi');
 // Phiếu lương: phiếu Đã chi trong tháng trừ vào Tổng tiền cuối cùng; phiếu chưa chi chỉ nhắc; nhân viên không thấy.
 $row=fn($m)=>current(array_filter($a->monthly($m)['rows'],fn($r)=>(int)$r['id']===(int)$staff['id']));
 $o=$row($prev);verify($o&&$o['advance_total']===500000&&count($o['advance_items'])===1&&$o['payout']===(int)$o['salary']-500000,'Tháng trước trừ phiếu đã chi');
 verify($o['advance_open_count']===1&&$o['advance_open_total']===200000,'Tháng trước nhắc phiếu chờ duyệt chưa trừ');
 $c=$row($month);verify($c&&$c['advance_total']===0&&$c['advance_open_count']===1&&$c['advance_open_total']===300000&&$c['payout']===(int)$c['salary'],'Tháng này không trừ, nhắc phiếu đã duyệt');
 $as($staff);$own=current($a->monthly($prev)['rows']);verify($own&&!isset($own['advance_total'])&&!isset($own['payout'])&&!isset($own['salary']),'Nhân viên không thấy ứng / lương cuối trên bảng công');$as($manager);
 // Phiếu chưa chi gửi paid_date thì bỏ qua; không tự sửa phiếu của mình.
 $call("/advances/{$ids[1]}/date",['requested_date'=>$prevEnd,'paid_date'=>$prevEnd,'version'=>$v($ids[1])]);verify($a->scalar('SELECT paid_date FROM {{salary_advances}} WHERE id=?',[$ids[1]])===null,'Chưa chi không ghi ngày chi');
 $own=uuid_v4();$call('/advances',['id'=>$own,'requested_date'=>$today,'amount'=>100000,'reason'=>'Quản lý tự ứng']);$fail(fn()=>$call("/advances/$own/date",['requested_date'=>$prevEnd,'version'=>$v($own)]),'Không tự sửa ngày phiếu của mình');
 throw new RuntimeException('ROLLBACK');
});}catch(RuntimeException $e){if($e->getMessage()!=='ROLLBACK'){fwrite(STDERR,$e->getMessage()."\n");exit(1);}}
echo "advance-date: $n kiểm tra đạt\n";
