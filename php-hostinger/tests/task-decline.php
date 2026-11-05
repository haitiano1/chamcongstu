<?php
// Xin từ chối Task / Nhắc nhở: người nhận ghi lý do, Admin/Quản lý khác duyệt; duyệt thì gỡ người đó (không còn ai nhận thì việc vẫn mở, chờ giao người khác),
// không duyệt thì việc vẫn treo (Nhắc nhở quay lại Chưa xem); rút yêu cầu; báo xong tự rút; Telegram báo hai chiều.
// Chạy trong giao dịch rồi hoàn tác; Telegram giả lập, không gửi tin thật.
foreach(['bootstrap','telegram','bookings','people-finance','tasks'] as $f)require_once __DIR__.'/../site/nhansu-private/lib/'.$f.'.php';
$a=new App('/var/nhansu-private');$n=0;function verify($v,$message){global $n;if(!$v)throw new RuntimeException('FAIL: '.$message);$n++;}
function denied(callable $fn,int $status,string $message){try{$fn();}catch(HttpError $e){verify($e->status===$status,$message.' (mã '.$e->status.': '.$e->getMessage().')');return $e->getMessage();}verify(false,$message.' (không bị chặn)');}
try{$a->tx(function()use($a){
 foreach(['telegram_links','task_declines'] as $t)$a->run('DELETE FROM {{'.$t.'}}');$a->config['telegram_token']='';
 $admin=$a->one("SELECT * FROM {{users}} WHERE role='admin' AND archived=0 ORDER BY id LIMIT 1");
 $make=function(string $key,string $role)use($a){$a->run("INSERT INTO {{users}}(name,email,password,role,employee_code,notes) VALUES(?,?,'x',?,?,'')",['TC '.$key,'tc.'.$key.'@local.test',$role,'TC-'.strtoupper($key)]);return $a->one('SELECT * FROM {{users}} WHERE id=LAST_INSERT_ID()');};
 $e=$make('mot','employee');$e2=$make('hai','employee');$m=$make('quanly','manager');
 $as=function(array $u)use($a){$a->user=$u;};$route=fn(string $path,array $b=[],string $method='POST')=>task_routes($a,$path,$b,$method);
 $task=fn(array $ids,string $kind='task',string $title='Dọn phòng chụp 2')=>task_create($a,(int)$admin['id'],['kind'=>$kind,'title'=>$title,'audience'=>'people','user_ids'=>$ids])['id'];
 $state=fn(string $id)=>$a->scalar('SELECT status FROM {{task_declines}} WHERE id=?',[$id]);
 $recipient=fn(string $task,array $u)=>$a->one('SELECT * FROM {{task_recipients}} WHERE task_id=? AND user_id=?',[$task,$u['id']]);

 // Gửi yêu cầu: cần lý do từ 5 ký tự, mỗi việc một yêu cầu đang chờ; người không nhận việc không gửi được.
 $t1=$task([(int)$e['id']]);$as($e);$pending=task_pending($a);
 denied(fn()=>$route("/tasks/$t1/decline",['reason'=>'ko']),400,'Lý do quá ngắn bị chặn');
 $r=$route("/tasks/$t1/decline",['reason'=>'Hôm nay em nghỉ ca 2 đã báo trước']);verify($r[0]===201,'Gửi yêu cầu từ chối');$d1=$r[1]['id'];
 denied(fn()=>$route("/tasks/$t1/decline",['reason'=>'Gửi lại lần nữa']),409,'Không gửi trùng khi đang chờ');
 verify(task_pending($a)===$pending,'Đang chờ duyệt thì việc vẫn tính là cần làm');
 $as($e2);denied(fn()=>$route("/tasks/$t1/decline",['reason'=>'Không phải việc của em']),403,'Người không nhận việc không từ chối được');
 // Danh sách: Quản lý thấy yêu cầu chờ duyệt; người gửi thấy yêu cầu của mình.
 $as($m);$list=$route('/tasks',[],'GET')[1];verify(count($list['decline_requests'])===1&&$list['decline_requests'][0]['name']==='TC mot'&&$list['decline_requests'][0]['title']==='Dọn phòng chụp 2','Quản lý thấy yêu cầu chờ duyệt');
 $as($e);$mine=$route('/tasks',[],'GET')[1];verify($mine['decline_requests']===[]&&count($mine['my_declines'])===1&&$mine['my_declines'][0]['status']==='pending','Nhân viên thấy yêu cầu của mình, không thấy danh sách duyệt');
 // Nhân viên không tự duyệt; Quản lý không duyệt khi thiếu lý do không duyệt.
 denied(fn()=>$route("/tasks/declines/$d1/approve"),403,'Nhân viên không duyệt được');
 $as($m);denied(fn()=>$route("/tasks/declines/$d1/reject",['note'=>'']),400,'Không duyệt cần ghi lý do');
 // Không duyệt: việc vẫn treo, người gửi thấy lý do; ghi lịch sử.
 $route("/tasks/declines/$d1/reject",['note'=>'Ca 2 hôm nay em vẫn đi làm, làm giúp anh']);verify($state($d1)==='rejected'&&$recipient($t1,$e)!==null,'Không duyệt thì việc vẫn giữ cho nhân viên');
 $as($e);verify(task_pending($a)===$pending,'Việc vẫn trong danh sách cần làm');$mine=$route('/tasks',[],'GET')[1];verify($mine['my_declines'][0]['status']==='rejected'&&$mine['my_declines'][0]['review_note']==='Ca 2 hôm nay em vẫn đi làm, làm giúp anh'&&$mine['my_declines'][0]['reviewer_name']==='TC quanly','Người gửi thấy lý do và người không duyệt');
 verify($a->one("SELECT 1 FROM {{task_audit}} WHERE task_id=? AND action='decline_rejected'",[$t1])!==null&&$a->one("SELECT 1 FROM {{task_audit}} WHERE task_id=? AND action='decline_requested'",[$t1])!==null,'Lịch sử việc ghi xin từ chối và không duyệt');
 denied(fn()=>$route("/tasks/declines/$d1/approve"),409,'Yêu cầu đã xử lý thì không duyệt lại');
 // Gửi lại và được duyệt: gỡ khỏi việc; việc chỉ có một người nhận thì vẫn mở, không còn ai nhận.
 $d2=$route("/tasks/$t1/decline",['reason'=>'Em xin đổi người vì đang chụp khách'])[1]['id'];$as($m);$route("/tasks/declines/$d2/approve",['note'=>'']);
 verify($state($d2)==='approved'&&$recipient($t1,$e)===null,'Duyệt thì gỡ người nhận');verify($a->one('SELECT status FROM {{tasks}} WHERE id=?',[$t1])['status']==='open'&&!(int)$a->scalar('SELECT COUNT(*) FROM {{task_recipients}} WHERE task_id=?',[$t1]),'Việc không còn ai nhận vẫn mở, chờ giao người khác');
 $as($e);verify(task_pending($a)===$pending-1,'Được duyệt thì bớt việc cần làm');$mine=$route('/tasks',[],'GET')[1];verify($mine['my_declines'][0]['status']==='approved','Người gửi thấy đã duyệt');
 // Việc nhiều người nhận: duyệt chỉ gỡ người xin, việc vẫn mở cho người khác.
 $t2=$task([(int)$e['id'],(int)$e2['id']]);$as($e);$d3=$route("/tasks/$t2/decline",['reason'=>'Em không biết làm phần này'])[1]['id'];$as($admin);$route("/tasks/declines/$d3/approve",['note'=>'Để Hai làm']);
 verify($recipient($t2,$e)===null&&$recipient($t2,$e2)!==null&&$a->scalar('SELECT status FROM {{tasks}} WHERE id=?',[$t2])==='open','Chỉ gỡ người xin, việc vẫn mở cho người khác');
 // Nhắc nhở đã xem mà xin từ chối, không duyệt thì quay lại Chưa xem.
 $t3=$task([(int)$e['id']],'notice','Mai họp 8h30');$as($e);$route("/tasks/$t3/seen");$d4=$route("/tasks/$t3/decline",['reason'=>'Mai em có lịch khám bệnh'])[1]['id'];
 $as($m);$route("/tasks/declines/$d4/reject",['note'=>'Họp bắt buộc, dời lịch khám']);verify($recipient($t3,$e)['seen_at']===null,'Nhắc nhở không duyệt thì quay lại Chưa xem');
 // Rút yêu cầu: chỉ người gửi; báo xong trong lúc chờ thì tự rút.
 $t4=$task([(int)$e['id']]);$as($e);$d5=$route("/tasks/$t4/decline",['reason'=>'Em bận chụp khách'])[1]['id'];
 $as($e2);denied(fn()=>$route("/tasks/declines/$d5/withdraw"),403,'Người khác không rút được');$as($e);$route("/tasks/declines/$d5/withdraw");verify($state($d5)==='withdrawn','Người gửi rút được yêu cầu');
 $d6=$route("/tasks/$t4/decline",['reason'=>'Em bận chụp khách lần nữa'])[1]['id'];$route("/tasks/$t4/done",['note'=>'','photos'=>[]]);verify($state($d6)==='withdrawn','Báo xong trong lúc chờ thì yêu cầu tự rút');
 denied(fn()=>$route("/tasks/$t4/decline",['reason'=>'Đã xong rồi mới xin']),409,'Đã báo xong thì không xin từ chối');
 // Chỉ giao việc cho Nhân viên (chủ dự án yêu cầu 02/11/2026). Việc cũ đã giao cho Quản lý: không tự duyệt, Admin duyệt được.
 denied(fn()=>$task([(int)$m['id']]),400,'Không giao việc cho Quản lý');
 $t5=$task([(int)$e2['id']]);$a->run("INSERT INTO {{task_recipients}}(task_id,user_id,done_note,notify_state) VALUES(?,?,'','skipped')",[$t5,$m['id']]);$as($m);$d7=$route("/tasks/$t5/decline",['reason'=>'Việc này của bộ phận khác'])[1]['id'];
 denied(fn()=>$route("/tasks/declines/$d7/approve"),403,'Quản lý không tự duyệt yêu cầu của mình');$as($admin);$route("/tasks/declines/$d7/approve");verify($state($d7)==='approved','Admin duyệt yêu cầu của Quản lý');
 // Việc bị hủy thì không duyệt nữa và không còn trong danh sách chờ.
 $t6=$task([(int)$e['id']]);$as($e);$d8=$route("/tasks/$t6/decline",['reason'=>'Em xin không làm việc này'])[1]['id'];$as($admin);$v=(int)$a->scalar('SELECT version FROM {{tasks}} WHERE id=?',[$t6]);$route("/tasks/$t6/cancel",['version'=>$v,'reason'=>'Không cần nữa']);
 verify(!array_filter($route('/tasks',[],'GET')[1]['decline_requests'],fn($x)=>$x['id']===$d8),'Việc đã hủy không còn trong danh sách chờ duyệt');denied(fn()=>$route("/tasks/declines/$d8/approve"),409,'Việc đã hủy thì không duyệt');

 // Telegram: báo Admin/Quản lý (trừ người gửi) khi xin từ chối; báo người gửi khi có kết quả.
 foreach([[$e,'ce'],[$m,'cm'],[$admin,'ca']] as [$u,$chat])$a->run('INSERT INTO {{telegram_links}} VALUES(?,?,?)',[$u['id'],$chat,now_ms()]);
 $a->config['telegram_token']='12345:'.str_repeat('x',30);$a->config['app_url']='https://example.test/nhansu';$sent=[];
 $tg=new Telegram($a,function($method,$b)use(&$sent){$sent[]=$b;return ['ok'=>true,'result'=>['message_id'=>1]];});
 verify(task_decline_manager_chats($a,(int)$m['id'])===['ca'],'Không báo lại cho chính Quản lý gửi yêu cầu');
 $text=task_decline_request_text('TC mot',['kind'=>'task','title'=>'Dọn phòng chụp 2'],'Em bận chụp khách');task_decline_notify($a,task_decline_manager_chats($a,(int)$e['id']),$text,$tg);
 $ids=array_column($sent,'chat_id');sort($ids);verify($ids===['ca','cm']&&str_contains($sent[0]['text'],'TC mot xin từ chối: «Dọn phòng chụp 2»')&&str_contains($sent[0]['text'],'Lý do / ý kiến: Em bận chụp khách')&&($sent[0]['reply_markup']['inline_keyboard'][0][0]['url']??'')==='https://example.test/nhansu/#tasks','Báo Quản lý và Admin kèm lý do, nút mở mục Việc');
 $no=task_decline_result_text(['kind'=>'task','title'=>'Dọn phòng chụp 2'],false,'TC quanly','Hôm nay em vẫn đi làm');verify(str_contains($no,'TC quanly không duyệt. Lý do: Hôm nay em vẫn đi làm')&&str_contains($no,'Việc vẫn giữ cho bạn, hãy hoàn thành rồi bấm Báo đã xong.'),'Tin không duyệt: '.$no);
 verify(str_contains(task_decline_result_text(['kind'=>'notice','title'=>'Mai họp'],true,'Alex',''),'Alex đã duyệt, bạn không cần làm việc này nữa.'),'Tin đã duyệt');
 throw new RuntimeException('ROLLBACK');
});}catch(RuntimeException $e){if($e->getMessage()!=='ROLLBACK')throw $e;}
echo "PASS $n task decline checks: request, review, reject keeps task, approve removes (kept open), notice reset, withdraw, Telegram\n";
