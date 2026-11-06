<?php
// Chủ dự án yêu cầu 02/11/2026:
// 1) Event log: ô Lần mắc lỗi trong tháng (Lần 1 −20, Lần 2 −100, Lần 3 −300); luật nặng (từ 100 điểm) giữ điểm riêng.
// 2) Task / Nhắc nhở có hạn: chỉ khóa chấm ra ca 2 từ đúng ngày đến hạn; hạn ngày khác vẫn chấm ra bình thường.
// 3) Mọi danh sách người (Việc, Event log, Chấm hộ, Lịch làm việc…) chỉ là tài khoản Nhân viên.
// Chạy trong giao dịch rồi hoàn tác.
foreach(['bootstrap','telegram','bookings','people-finance','workflows','studio-rules','tasks','availability','api'] as $f)require_once __DIR__.'/../site/nhansu-private/lib/'.$f.'.php';
$a=new App('/var/nhansu-private');$n=0;function verify($v,$message){global $n;if(!$v)throw new RuntimeException('FAIL: '.$message);$n++;}
function denied(callable $fn,int $status,string $message){try{$fn();}catch(HttpError $e){verify($e->status===$status,$message.' (mã '.$e->status.': '.$e->getMessage().')');return $e->getMessage();}verify(false,$message.' (không bị chặn)');}
try{$a->tx(function()use($a){
 $a->config['telegram_token']='';
 $admin=$a->one("SELECT * FROM {{users}} WHERE role='admin' AND archived=0 ORDER BY id LIMIT 1");
 $make=function(string $key,string $role)use($a){$a->run("INSERT INTO {{users}}(name,email,password,role,employee_code,notes) VALUES(?,?,'x',?,?,'')",['KD '.$key,'kd.'.$key.'@local.test',$role,'KD-'.strtoupper($key)]);return $a->one('SELECT * FROM {{users}} WHERE id=LAST_INSERT_ID()');};
 $e=$make('mot','employee');$e2=$make('hai','employee');$m=$make('quanly','manager');
 $as=function(array $u)use($a){$a->user=$u;};$today=local_day(now_ms());
 $rule=function(string $code,int $points)use($a,$admin){$id=uuid_v4();$a->run("INSERT INTO {{studio_rules}}(id,code,name,category,content,points,status,created_by,created_at,updated_by,updated_at) VALUES(?,?,?,?,?,?,'active',?,?,?,?)",[$id,$code,'Luật thử '.$code,'Thử','',$points,$admin['id'],now_ms(),$admin['id'],now_ms()]);return $id;};
 $thuong=$rule('KD-01',20);$nang=$rule('KD-NANG',300);$canh=$rule('KD-100',100);
 $log=function(array $b)use($a){$id=uuid_v4();log_routes($a,'/event-log',$b+['id'=>$id,'event_date'=>local_day(now_ms()),'category'=>'other','description'=>'Sự việc thử '.substr($id,0,8)],'POST');return $a->one('SELECT * FROM {{event_log}} WHERE id=?',[$id]);};

 // 1) Event log: Lần mắc lỗi.
 $as($admin);
 $r=$log(['user_id'=>(int)$e['id'],'offense'=>2]);verify((int)$r['points']===-100&&(int)$r['offense']===2,'Tick Lần 2 thì trừ 100 (Cảnh cáo)');
 $r=$log(['user_id'=>(int)$e['id'],'offense'=>3,'rule_id'=>$thuong]);verify((int)$r['points']===-300&&(int)$r['offense']===3&&$r['rule_code']==='KD-01','Luật thường tick Lần 3 thì trừ 300, không theo điểm luật');
 $r=$log(['user_id'=>(int)$e['id'],'offense'=>0,'rule_id'=>$thuong]);verify((int)$r['points']===0&&$r['offense']===null,'Nhắc nhở: 0 điểm');
 $r=$log(['user_id'=>(int)$e['id'],'offense'=>1,'rule_id'=>$nang]);verify((int)$r['points']===-300&&$r['offense']===null,'Luật nặng giữ điểm riêng −300 dù tick Lần 1');
 $r=$log(['user_id'=>(int)$e['id'],'offense'=>3,'rule_id'=>$canh]);verify((int)$r['points']===-100,'Luật 100 điểm là luật nặng, trừ đúng 100');
 verify(str_contains((string)$a->scalar("SELECT note FROM {{event_log_audit}} WHERE event_log_id=? AND action='created'",[$r['id']]),'Luật nặng KD-100'),'Lịch sử ghi rõ luật nặng');
 // Giao diện cũ (không gửi offense, deduction 0 khi chọn luật): luật thường tự đếm số lần trong tháng.
 $r=$log(['user_id'=>(int)$e2['id'],'deduction'=>0,'rule_id'=>$thuong]);verify((int)$r['points']===-20&&(int)$r['offense']===1,'Giao diện cũ chọn luật thường: tự tính Lần 1');
 $r=$log(['user_id'=>(int)$e2['id'],'deduction'=>0,'rule_id'=>$thuong]);verify((int)$r['points']===-100&&(int)$r['offense']===2,'Lần sau tự tính Lần 2');
 verify(str_contains((string)$a->scalar("SELECT note FROM {{event_log_audit}} WHERE event_log_id=? AND action='created'",[$r['id']]),'Lần 2 trong tháng (Cảnh cáo)'),'Lịch sử ghi lần mắc lỗi');
 $r=$log(['user_id'=>(int)$e2['id'],'deduction'=>100]);verify((int)$r['points']===-100&&$r['offense']===null,'Không chọn luật, gửi điểm 100 như cũ');
 // Hộp ghi nhanh (nghỉ gấp, đối soát): offense=null giữ đúng điểm đã chọn.
 verify(log_points($a,['id'=>$thuong,'code'=>'KD-01','points'=>20],['deduction'=>0,'offense'=>null],(int)$e2['id'],$today)===[0,null],'Ghi nhanh chọn 0 điểm thì giữ 0');
 verify(log_points($a,['id'=>$thuong,'code'=>'KD-01','points'=>20],['deduction'=>300,'offense'=>null],(int)$e2['id'],$today)===[300,null],'Ghi nhanh chọn 300 thì giữ 300');
 denied(fn()=>$log(['user_id'=>null,'offense'=>1]),400,'Nhật ký chung không trừ điểm');
 $r=$log(['user_id'=>null,'offense'=>0,'rule_id'=>$thuong]);verify((int)$r['points']===0,'Nhật ký chung kèm luật thường: chỉ ghi nhận');
 denied(fn()=>$log(['user_id'=>null,'rule_id'=>$nang]),400,'Nhật ký chung không áp luật nặng');
 denied(fn()=>$log(['user_id'=>(int)$e['id'],'offense'=>4]),400,'Lần mắc lỗi chỉ 1–3');
 denied(fn()=>$log(['user_id'=>(int)$m['id'],'offense'=>1]),400,'Không ghi Event log cho Quản lý');
 // Gợi ý Lần: đếm log cùng luật trong tháng, bỏ log đã hủy.
 $_GET=['user_id'=>(string)$e2['id'],'rule_id'=>$thuong,'day'=>$today];$c=log_routes($a,'/event-log/offenses',[],'GET')[1];verify($c['count']===2&&$c['month']===substr($today,0,7)&&$c['fixed']===false,'Gợi ý: Huy đã bị ghi 2 lần → lần này Lần 3');
 $a->run("UPDATE {{event_log}} SET status='void' WHERE user_id=? AND rule_id=? AND offense=2",[$e2['id'],$thuong]);$c=log_routes($a,'/event-log/offenses',[],'GET')[1];verify($c['count']===1,'Log đã hủy không tính');
 $_GET=['user_id'=>(string)$e2['id'],'rule_id'=>$nang,'day'=>$today];verify(log_routes($a,'/event-log/offenses',[],'GET')[1]['fixed']===true,'Gợi ý báo luật nặng');
 $as($e);$_GET=['user_id'=>(string)$e2['id'],'rule_id'=>$thuong,'day'=>$today];denied(fn()=>log_routes($a,'/event-log/offenses',[],'GET'),403,'Nhân viên chưa được cấp quyền không xem gợi ý');
 // Xác nhận log chờ (log cũ): chọn Lần thì đổi điểm theo Lần.
 $as($admin);$pid=uuid_v4();$a->run("INSERT INTO {{event_log}}(id,user_id,event_date,category,description,points,status,created_by,created_at,updated_by,updated_at,resolution) VALUES(?,?,?,'other','Log chờ xác nhận thử',0,'pending',?,?,?,?,'')",[$pid,$e['id'],$today,$e['id'],now_ms(),$e['id'],now_ms()]);
 log_routes($a,"/event-log/$pid/resolve",['status'=>'confirmed','note'=>'Xác nhận lần 2','offense'=>2,'rule_id'=>$thuong,'version'=>1],'POST');$p=$a->one('SELECT * FROM {{event_log}} WHERE id=?',[$pid]);
 verify($p['status']==='confirmed'&&(int)$p['points']===-100&&(int)$p['offense']===2&&$p['rule_code']==='KD-01','Xác nhận log chờ: Lần 2 trừ 100');

 // 2) Khóa chấm ra ca 2 theo ngày đến hạn.
 $task=function(string $kind,int $due,string $title)use($a,$admin,$e){$id=task_create($a,(int)$admin['id'],['kind'=>$kind,'title'=>$title,'audience'=>'people','user_ids'=>[(int)$e['id']],'due_at'=>now_ms()+3600000])['id'];$a->run('UPDATE {{tasks}} SET due_at=? WHERE id=?',[$due,$id]);return $id;};
 $at=fn(string $day,int $hour)=>day_time($day,$hour);$plus=fn(int $days)=>gmdate('Y-m-d',strtotime($today.'T00:00:00Z')+$days*86400);
 $block=function()use($a,$e){$a->user=$e;try{daily_tasks_block_checkout($a,['type'=>'out','shift'=>'day_second']);return '';}catch(HttpError $x){return $x->getMessage();}};
 $titles=fn(?int $now=null)=>array_column(task_due_today($a,(int)$e['id'],$now),'title');
 $t48=$task('task',$at($plus(2),10),'Chỉnh 50 ảnh khách A');
 verify($block()===''&&!in_array('Chỉnh 50 ảnh khách A',$titles(),true),'Hạn 48h: hôm nay vẫn chấm ra ca 2 bình thường');
 verify(!in_array('Chỉnh 50 ảnh khách A',$titles($at($plus(1),18)),true),'Ngày mai vẫn chấm ra bình thường');
 verify(in_array('Chỉnh 50 ảnh khách A',$titles($at($plus(2),18)),true),'Đúng ngày đến hạn thì khóa chấm ra');
 verify(in_array('Chỉnh 50 ảnh khách A',$titles($at($plus(3),18)),true),'Quá hạn mà chưa xong thì ngày sau vẫn khóa');
 $tToday=$task('task',$at($today,18),'Dọn phòng chụp 2');
 $msg=$block();verify(str_contains($msg,'Dọn phòng chụp 2')&&!str_contains($msg,'Chỉnh 50 ảnh'),'Hạn trong hôm nay: chưa xong thì không chấm ra ca 2 · '.$msg);
 $a->user=$e;try{daily_tasks_block_checkout($a,['type'=>'out','shift'=>'day_first']);$ok=true;}catch(HttpError){$ok=false;}verify($ok,'Chấm ra ca 1 không bị khóa');
 $a->run('UPDATE {{task_recipients}} SET done_at=? WHERE task_id=? AND user_id=?',[now_ms(),$tToday,$e['id']]);verify($block()==='','Báo xong thì chấm ra được');
 $nToday=$task('notice',$at($today,17),'Mai họp 8h30');verify(str_contains($block(),'Nhắc nhở: Mai họp 8h30'),'Nhắc nhở hạn hôm nay chưa xem thì khóa');
 $a->run('UPDATE {{task_recipients}} SET seen_at=? WHERE task_id=? AND user_id=?',[now_ms(),$nToday,$e['id']]);verify($block()==='','Bấm Đã xem thì mở khóa');
 $tDecline=$task('task',$at($today,19),'Việc xin từ chối');$a->run("INSERT INTO {{task_declines}}(id,task_id,user_id,reason,status,requested_at,review_note) VALUES(?,?,?,?,'pending',?,'')",[uuid_v4(),$tDecline,$e['id'],'Em không làm được',now_ms()]);
 verify($block()==='','Đang xin từ chối chờ duyệt thì không khóa');
 $tOld=$task('task',$at('2026-10-30',10),'Việc cũ trước khi áp dụng');verify(!in_array('Việc cũ trước khi áp dụng',$titles(),true),'Việc hạn trước ngày áp dụng không khóa');
 $a->user=$e;$me=main_routes($a,'/me',[],'GET')[1];verify(array_key_exists('dueTasks',$me)&&$me['dueTasks']===[],'/me trả dueTasks');

 // Xóa việc đã giao (02/11/2026): Admin / Quản lý xóa mềm; ẩn khỏi danh sách, không tính việc chờ, không khóa chấm ra; nhân viên không xóa được.
 $tDel=$task('task',$at($today,18),'Việc sẽ xóa');verify(str_contains($block(),'Việc sẽ xóa'),'Trước khi xóa: việc hạn hôm nay khóa chấm ra');
 $a->user=$e;$pendingBefore=task_pending($a);denied(fn()=>task_routes($a,"/tasks/$tDel/delete",['version'=>1],'POST'),403,'Nhân viên không xóa được việc');
 $a->user=$m;$v=(int)$a->scalar('SELECT version FROM {{tasks}} WHERE id=?',[$tDel]);task_routes($a,"/tasks/$tDel/delete",['version'=>$v,'reason'=>'Giao nhầm'],'POST');
 verify($a->scalar('SELECT status FROM {{tasks}} WHERE id=?',[$tDel])==='deleted'&&(bool)$a->one("SELECT 1 FROM {{task_audit}} WHERE task_id=? AND action='deleted' AND note='Giao nhầm'",[$tDel]),'Quản lý xóa việc, lưu lịch sử kèm lý do');
 verify(!in_array($tDel,array_column(task_routes($a,'/tasks',[],'GET')[1]['rows'],'id'),true),'Việc đã xóa không còn trong danh sách');
 $a->user=$e;verify(task_pending($a)===$pendingBefore-1&&!str_contains($block(),'Việc sẽ xóa'),'Không tính việc chờ, không khóa chấm ra');
 $a->user=$m;$v=(int)$a->scalar('SELECT version FROM {{tasks}} WHERE id=?',[$tDel]);denied(fn()=>task_routes($a,"/tasks/$tDel/delete",['version'=>$v],'POST'),409,'Xóa lại báo đã xóa');
 $tCancel=$task('notice',$at($plus(1),9),'Việc đã hủy cần dọn');$v=(int)$a->scalar('SELECT version FROM {{tasks}} WHERE id=?',[$tCancel]);task_routes($a,"/tasks/$tCancel/cancel",['version'=>$v,'reason'=>'Không cần nữa'],'POST');
 $v=(int)$a->scalar('SELECT version FROM {{tasks}} WHERE id=?',[$tCancel]);task_routes($a,"/tasks/$tCancel/delete",['version'=>$v],'POST');verify($a->scalar('SELECT status FROM {{tasks}} WHERE id=?',[$tCancel])==='deleted','Xóa được cả việc đã hủy để dọn danh sách');
 // 3) Chỉ tài khoản Nhân viên.
 $as($admin);$all=task_create($a,(int)$admin['id'],['kind'=>'notice','title'=>'Thông báo cả studio','audience'=>'all','user_ids'=>[]])['id'];
 $roles=array_unique(array_column($a->all('SELECT u.role FROM {{task_recipients}} r JOIN {{users}} u ON u.id=r.user_id WHERE r.task_id=?',[$all]),'role'));verify($roles===['employee'],'Giao @all chỉ tới Nhân viên: '.json_encode($roles));
 verify(!array_diff(array_unique(array_column(finance_people($a,true),'role')),['employee']),'Danh sách chọn người (Event log, Việc) chỉ có Nhân viên');
 $users=management_routes($a,'/users',[],'GET')[1]['users'];verify($users&&!array_diff(array_unique(array_column($users,'role')),['employee']),'Chấm hộ, Hoa hồng chỉ chọn Nhân viên');
 denied(fn()=>management_routes($a,'/attendance/manual-shift',['user_id'=>(int)$m['id']],'POST'),400,'Không chấm hộ cho Quản lý');
 $dash=main_routes($a,'/dashboard',[],'GET');if(!$dash)$dash=management_routes($a,'/dashboard',[],'GET');verify((int)$dash[1]['people']===(int)$a->scalar("SELECT COUNT(*) FROM {{users}} WHERE archived=0 AND status!='inactive' AND role='employee'"),'Tổng quan chỉ đếm Nhân viên');
 $_GET=['start'=>$today,'end'=>$today];$roster=roster_routes($a,'/roster',[],'GET')[1];verify(!array_diff(array_unique(array_column($roster['people'],'role')),['employee']),'Lịch làm việc chỉ có Nhân viên');
 denied(fn()=>daily_template_routes($a,'/tasks/templates',['user_id'=>(int)$m['id'],'title'=>'Việc hằng ngày cho QL','require_photo'=>0],'POST'),400,'Không cài việc hằng ngày cho Quản lý');
 throw new RuntimeException('ROLLBACK');
});}catch(RuntimeException $e){if($e->getMessage()!=='ROLLBACK')throw $e;}
echo "PASS $n checks: offense tiers + heavy rules, due-day checkout lock, task delete, employees-only lists\n";
