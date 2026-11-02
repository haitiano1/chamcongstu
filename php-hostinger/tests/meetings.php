<?php
// Biên bản họp (02/11/2026): quyền lập/sửa (Admin, Quản lý, Nhân viên được tick), nháp chỉ người có quyền thấy, Xác nhận & gửi báo Telegram người có mặt,
// người có mặt bấm "Đã đọc và xác nhận" (đúng bản, lưu giờ), đủ người thì tự khóa, khóa rồi chỉ Admin sửa; sửa nội dung thì xác nhận lại;
// thêm người có mặt chỉ báo người mới; nhắc lại cách 10 phút; xóa. Role Makeup: chỉ các API xem Lịch chụp.
// Chạy trong giao dịch rồi hoàn tác; Telegram giả lập, không gửi tin thật.
foreach(['bootstrap','telegram','bookings','people-finance','tasks','meetings'] as $f)require_once __DIR__.'/../site/nhansu-private/lib/'.$f.'.php';
$a=new App('/var/nhansu-private');$n=0;function verify($v,$message){global $n;if(!$v)throw new RuntimeException('FAIL: '.$message);$n++;}
function denied(callable $fn,int $status,string $message){try{$fn();}catch(HttpError $e){verify($e->status===$status,$message.' (mã '.$e->status.': '.$e->getMessage().')');return $e->getMessage();}verify(false,$message.' (không bị chặn)');}
try{$a->tx(function()use($a){
 foreach(['telegram_links','meeting_attendees','meetings','meeting_editors','meeting_audit'] as $t)$a->run('DELETE FROM {{'.$t.'}}');
 $a->config['telegram_token']='12345:'.str_repeat('x',30);$a->config['app_url']='https://example.test/nhansu';
 $sent=[];$tg=new Telegram($a,function($method,$b)use(&$sent){if($method!=='sendMessage')throw new RuntimeException('Unexpected '.$method);$sent[]=$b;return ['ok'=>true,'result'=>['message_id'=>1]];});
 $admin=$a->one("SELECT * FROM {{users}} WHERE role='admin' AND archived=0 ORDER BY id LIMIT 1");
 $make=function(string $key,string $role)use($a){$a->run("INSERT INTO {{users}}(name,email,password,role,employee_code,notes) VALUES(?,?,'x',?,?,'')",['BB '.$key,'bb.'.$key.'@local.test',$role,'BB-'.strtoupper($key)]);return $a->one('SELECT * FROM {{users}} WHERE id=LAST_INSERT_ID()');};
 $e=$make('mot','employee');$e2=$make('hai','employee');$m=$make('quanly','manager');$mk=$make('makeup','makeup');
 foreach([$e,$e2,$m] as $i=>$u)$a->run('INSERT INTO {{telegram_links}} VALUES(?,?,?)',[$u['id'],'chat-'.$u['id'],now_ms()]);
 $as=function(array $u)use($a){$a->user=$u;};$route=fn(string $path,array $b=[],string $method='POST')=>meeting_routes($a,$path,$b,$method);
 $row=fn(string $id)=>$a->one('SELECT * FROM {{meetings}} WHERE id=?',[$id]);$att=fn(string $id,array $u)=>$a->one('SELECT * FROM {{meeting_attendees}} WHERE meeting_id=? AND user_id=?',[$id,$u['id']]);
 $base=['title'=>'Họp rút kinh nghiệm','meeting_day'=>'2026-11-02','start'=>'09:00','end'=>'10:00','purpose'=>'Khách phàn nàn chờ lâu','opinions'=>'Huy: cần thêm người đón khách','conclusion'=>'Thêm 1 người trực quầy','actions'=>[['text'=>'Trực quầy ca 1','user_id'=>(int)$e['id'],'due'=>'2026-11-05']],
  'attendees'=>[['user_id'=>(int)$m['id'],'present'=>true],['user_id'=>(int)$e['id'],'present'=>true],['user_id'=>(int)$e2['id'],'present'=>false]]];

 // Quyền: Nhân viên chưa tick không lập được; Makeup không có trong danh sách người họp và không được cấp quyền.
 $as($e);verify(meeting_permissions($a)['can_edit']===false,'Nhân viên chưa tick không có quyền sửa');denied(fn()=>$route('/meetings',$base),403,'Nhân viên chưa tick không lập được');
 $as($admin);verify(!in_array((int)$mk['id'],array_map('intval',array_column(meeting_people($a),'id')),true),'Makeup không có trong thành phần tham gia');
 save_user_grants($a,(int)$mk['id'],'makeup',['event_log'=>true,'bookings'=>true,'meetings'=>true]);verify(!$a->one('SELECT 1 FROM {{meeting_editors}} WHERE user_id=?',[$mk['id']]),'Makeup không được cấp quyền');
 save_user_grants($a,(int)$e['id'],'employee',['event_log'=>false,'bookings'=>false,'meetings'=>true]);$as($e);verify(meeting_permissions($a)['can_edit']===true,'Nhân viên được tick có quyền sửa');
 save_user_grants($a,(int)$e['id'],'employee',['event_log'=>false,'bookings'=>false]);verify((bool)$a->one('SELECT 1 FROM {{meeting_editors}} WHERE user_id=?',[$e['id']]),'Giao diện cũ (2 ô) không xóa quyền Biên bản họp');
 save_user_grants($a,(int)$e['id'],'employee',['event_log'=>false,'bookings'=>false,'meetings'=>false]);$as($e);verify(meeting_permissions($a)['can_edit']===false,'Bỏ tick thì mất quyền');

 // Nháp: chỉ người có quyền thấy; không gửi Telegram.
 $as($m);denied(fn()=>$route('/meetings',array_merge($base,['end'=>'08:00'])),400,'Giờ kết thúc phải sau giờ bắt đầu');
 $r=$route('/meetings',array_merge($base,['purpose'=>'','conclusion'=>'']));verify($r[0]===201&&$row($r[1]['id'])['status']==='draft','Lưu nháp không cần đủ nội dung');$id=$r[1]['id'];
 $as($e);verify(!in_array($id,array_column($route('/meetings',[],'GET')[1]['rows'],'id'),true),'Nhân viên không thấy nháp');
 meeting_telegram_tick($a,null,$tg);verify($sent===[],'Nháp không gửi Telegram');
 // Xác nhận & gửi: thiếu kết luận bị chặn; gửi thì người lập có mặt tự xác nhận, Telegram báo người có mặt còn lại (không báo người vắng).
 $as($m);$v=(int)$row($id)['version'];denied(fn()=>$route("/meetings/$id/edit",array_merge($base,['conclusion'=>'','publish'=>true,'version'=>$v])),400,'Gửi cần Kết luận');
 $r=$route("/meetings/$id/edit",array_merge($base,['publish'=>true,'version'=>$v]));verify($r[1]['queued']===1&&$row($id)['status']==='published'&&(int)$row($id)['revision']===1,'Gửi xác nhận: 1 người cần báo');
 verify($att($id,$m)['ack_at']!==null&&$att($id,$e)['ack_at']===null&&$att($id,$e2)['notify_state']==='none','Người lập tự xác nhận; người vắng không bị báo');
 meeting_telegram_tick($a,null,$tg);verify(count($sent)===1&&$sent[0]['chat_id']==='chat-'.$e['id']&&str_contains($sent[0]['text'],'Họp rút kinh nghiệm')&&str_contains($sent[0]['text'],'Trực quầy ca 1 (hạn 05/11/2026)')&&str_contains($sent[0]['text'],'Đã đọc và xác nhận hiểu rõ nội dung'),'Telegram báo người có mặt kèm việc phụ trách');
 verify(($sent[0]['reply_markup']['inline_keyboard'][0][0]['url']??'')==='https://example.test/nhansu/#meetings','Nút mở Biên bản họp');
 $as($e);verify(count(meeting_pending_for($a,(int)$e['id']))===1,'Chấm công hiện biên bản chờ xác nhận');
 // Xác nhận: phải đúng bản; người vắng không xác nhận được; đủ người thì tự khóa.
 denied(fn()=>$route("/meetings/$id/ack",['revision'=>0]),409,'Xác nhận bản cũ bị chặn');
 $as($e2);denied(fn()=>$route("/meetings/$id/ack",['revision'=>1]),403,'Người vắng không xác nhận');
 $as($e);$r=$route("/meetings/$id/ack",['revision'=>1]);verify($r[1]['locked']===true&&$row($id)['status']==='locked'&&$row($id)['locked_by']===null,'Đủ người xác nhận thì tự khóa');
 verify($att($id,$e)['ack_at']!==null&&(int)$att($id,$e)['ack_revision']===1&&meeting_pending_for($a,(int)$e['id'])===[],'Lưu giờ xác nhận');
 verify((bool)$a->one("SELECT 1 FROM {{meeting_audit}} WHERE meeting_id=? AND action='ack' AND actor_id=?",[$id,$e['id']]),'Nhật ký lưu lượt xác nhận');
 // Đã khóa: Quản lý không sửa; Admin sửa nội dung thì mọi người có mặt xác nhận lại (Telegram báo đã sửa).
 $as($m);denied(fn()=>$route("/meetings/$id/edit",array_merge($base,['version'=>(int)$row($id)['version']])),403,'Khóa rồi Quản lý không sửa');
 $as($admin);denied(fn()=>$route("/meetings/$id/edit",array_merge($base,['version'=>(int)$row($id)['version']])),400,'Không đổi gì thì báo chưa có thay đổi');
 $sent=[];$r=$route("/meetings/$id/edit",array_merge($base,['conclusion'=>'Thêm 1 người trực quầy từ 9h','version'=>(int)$row($id)['version'],'note'=>'Ghi rõ giờ']));
 verify($r[1]['reset']===true&&$r[1]['queued']===2&&(int)$row($id)['revision']===2&&$row($id)['status']==='locked'&&$att($id,$e)['ack_at']===null&&$att($id,$m)['ack_at']===null,'Admin sửa: mọi người có mặt xác nhận lại');
 meeting_telegram_tick($a,null,$tg);verify(count($sent)===2&&str_contains($sent[0]['text'],'vừa được '.$admin['name'].' sửa'),'Telegram báo biên bản đã sửa');
 // Nhắc: chỉ người chưa xác nhận, cách nhau 10 phút.
 $a->run('UPDATE {{meeting_attendees}} SET queued_at=? WHERE meeting_id=?',[now_ms()-700000,$id]);$as($m);$e1=$route("/meetings/$id/remind")[1];$e2r=$route("/meetings/$id/remind")[1];verify($e1['queued']===2&&$e2r['queued']===0,'Nhắc lại cách 10 phút');
 $sent=[];meeting_telegram_tick($a,null,$tg);verify(count($sent)===2&&str_contains($sent[0]['text'],'Nhắc lại'),'Tin nhắc lại');
 // Mở khóa (Admin), thêm người có mặt: chỉ báo người mới, giữ xác nhận cũ.
 $as($e);$route("/meetings/$id/ack",['revision'=>2]);$as($m);$route("/meetings/$id/ack",['revision'=>2]);
 $as($m);denied(fn()=>$route("/meetings/$id/unlock",['version'=>(int)$row($id)['version']]),403,'Quản lý không mở khóa');
 $as($admin);$route("/meetings/$id/unlock",['version'=>(int)$row($id)['version'],'note'=>'Thêm người']);verify($row($id)['status']==='published','Admin mở khóa');
 $as($m);$more=array_merge($base,['conclusion'=>'Thêm 1 người trực quầy từ 9h','version'=>(int)$row($id)['version']]);$more['attendees'][2]['present']=true;$r=$route("/meetings/$id/edit",$more);
 verify($r[1]['reset']===false&&$r[1]['queued']===1&&$att($id,$e)['ack_at']!==null&&$att($id,$e2)['notify_state']==='pending'&&$row($id)['status']==='published','Thêm người có mặt chỉ báo người mới');
 // Xóa: biên bản đã gửi chỉ Admin, cần lý do; ẩn khỏi danh sách, bỏ tin chờ gửi.
 denied(fn()=>$route("/meetings/$id/delete",['version'=>(int)$row($id)['version'],'reason'=>'Họp nhầm']),403,'Quản lý không xóa biên bản đã gửi');
 $as($admin);denied(fn()=>$route("/meetings/$id/delete",['version'=>(int)$row($id)['version'],'reason'=>'']),400,'Xóa cần lý do');
 $route("/meetings/$id/delete",['version'=>(int)$row($id)['version'],'reason'=>'Họp nhầm ngày']);$sent=[];meeting_telegram_tick($a,null,$tg);
 verify($row($id)['status']==='deleted'&&$sent===[]&&!in_array($id,array_column($route('/meetings',[],'GET')[1]['rows'],'id'),true),'Xóa mềm, không gửi tin');
 // Role Makeup: chỉ xem Lịch chụp.
 foreach(['GET /bookings'=>true,'GET /bookings/days'=>true,'GET /me'=>true,'POST /logout'=>true,'GET /meetings'=>false,'POST /attendance'=>false,'GET /tasks'=>false,'POST /bookings/create'=>false,'GET /monthly'=>false] as $k=>$ok)verify(in_array($k,MAKEUP_ROUTES,true)===$ok,'Makeup '.$k.($ok?' được':' bị chặn'));
 $as($mk);verify(booking_permissions($a)['can_create']===false&&log_permissions($a)['can_record']===false,'Makeup không thêm lịch, không ghi log');
 verify(validate_employee(['name'=>'MK','email'=>'mk@local.test','role'=>'makeup','status'=>'active','monthly_salary'=>0])['role']==='makeup','Tạo được tài khoản Makeup');
 throw new RuntimeException('ROLLBACK');
});}catch(RuntimeException $e){if($e->getMessage()!=='ROLLBACK')throw $e;}
echo "PASS $n meeting checks: grants, draft, publish + Telegram, ack + autolock, admin edit resets, remind, add attendee, delete, Makeup routes\n";
