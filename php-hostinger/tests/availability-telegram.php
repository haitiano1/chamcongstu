<?php
// Đăng ký ca: Telegram nhắc người chưa chốt (thứ 7, CN lúc 9h, 20h), danh sách cho Admin/Quản lý 20h CN, báo khi chốt; Lịch của tôi kèm ca đã đăng ký.
// Chạy trong giao dịch rồi hoàn tác; Telegram giả lập, không gửi tin thật.
foreach(['bootstrap','telegram','bookings','people-finance','tasks','availability'] as $f)require_once __DIR__.'/../site/nhansu-private/lib/'.$f.'.php';
$a=new App('/var/nhansu-private');$n=0;function verify($v,$message){global $n;if(!$v)throw new RuntimeException('FAIL: '.$message);$n++;}
try{$a->tx(function()use($a){
 foreach(['telegram_links','telegram_deliveries'] as $t)$a->run('DELETE FROM {{'.$t.'}}');
 $admin=$a->one("SELECT * FROM {{users}} WHERE role='admin' AND archived=0 LIMIT 1");$a->user=$admin;
 $make=function(string $key,string $role)use($a){$a->run("INSERT INTO {{users}}(name,email,password,role,employee_code,notes) VALUES(?,?,'x',?,?,'')",['DK '.$key,'dk.'.$key.'@local.test',$role,'DK-'.strtoupper($key)]);return $a->one('SELECT * FROM {{users}} WHERE id=LAST_INSERT_ID()');};
 $e1=$make('mot','employee');$e2=$make('hai','employee');$e3=$make('ba','employee');$m=$make('quanly','manager');
 foreach([[$e1,'c1'],[$e2,'c2'],[$m,'cm'],[$admin,'ca']] as [$u,$chat])$a->run('INSERT INTO {{telegram_links}} VALUES(?,?,?)',[$u['id'],$chat,now_ms()]);
 $week='2026-11-09';
 foreach([['2026-11-09','day_first'],['2026-11-09','day_second'],['2026-11-10','day_second']] as [$d,$s])$a->run('INSERT INTO {{shift_availability}} VALUES(?,?,?,?)',[$e1['id'],$d,$s,now_ms()]);
 $a->run('INSERT INTO {{availability_weeks}} VALUES(?,?,?)',[$e2['id'],$week,now_ms()]);
 $a->config['telegram_token']='12345:'.str_repeat('x',30);$a->config['app_url']='https://example.test/nhansu';
 $sent=[];$tg=new Telegram($a,function($method,$b)use(&$sent){if($method!=='sendMessage')throw new RuntimeException('Unexpected '.$method);$sent[]=$b;return ['ok'=>true,'result'=>['message_id'=>1]];});
 $at=fn(string $day,string $hm)=>day_time($day)+((int)substr($hm,0,2)*60+(int)substr($hm,3,2))*60000;
 $to=function(string $chat)use(&$sent){return array_values(array_filter($sent,fn($x)=>$x['chat_id']===$chat));};
 // Thứ 7 9:05: nhắc người chưa chốt có Telegram (nhân viên + quản lý), không nhắc người đã chốt, Admin không nhận danh sách.
 availability_telegram_tick($a,$at('2026-11-07','09:05'),$tg);
 verify(count($to('c1'))===1&&str_contains($to('c1')[0]['text'],'(09/11 – 15/11)')&&str_contains($to('c1')[0]['text'],'Đã chọn 7/10 ca'),'Nhắc nhân viên chưa chốt, đúng tuần tới và số ca: '.json_encode($to('c1'),JSON_UNESCAPED_UNICODE));
 verify(($to('c1')[0]['reply_markup']['inline_keyboard'][0][0]['url']??'')==='https://example.test/nhansu/#availability','Nút mở thẳng Đăng ký ca');
 verify(!$to('c2'),'Người đã chốt không bị nhắc');verify(!$to('cm'),'Quản lý không phải đăng ký ca, không bị nhắc (chỉ Nhân viên)');verify(!$to('ca'),'Thứ 7 không gửi danh sách cho Admin');
 $count=count($sent);availability_telegram_tick($a,$at('2026-11-07','09:40'),$tg);verify(count($sent)===$count,'Cùng khung 9h không gửi lặp');
 availability_telegram_tick($a,$at('2026-11-07','12:30'),$tg);availability_telegram_tick($a,$at('2026-11-07','19:59'),$tg);verify(count($sent)===$count,'Ngoài khung 9h–12h, 20h–23h không gửi');
 availability_telegram_tick($a,$at('2026-11-06','09:05'),$tg);verify(count($sent)===$count,'Thứ 6 không nhắc');
 availability_telegram_tick($a,$at('2026-11-07','22:30'),$tg);verify(count($to('c1'))===2,'Cron lỡ 20h thì gửi bù trước 23h');
 // Chủ nhật 20h: nhắc riêng + danh sách người chưa chốt cho Admin và Quản lý.
 $sent=[];availability_telegram_tick($a,$at('2026-11-08','20:01'),$tg);
 verify(count($to('c1'))===1&&!$to('c2'),'Chủ nhật 20h vẫn nhắc riêng');
 $list=array_values(array_filter($sent,fn($x)=>str_contains($x['text'],'Chưa chốt ca tuần')));
 $ids=array_column($list,'chat_id');sort($ids);verify($ids===['ca','cm'],'Danh sách gửi cả Admin và Quản lý: '.json_encode($ids));
 $text=$list[0]['text'];verify(str_contains($text,'• DK mot: 7/10 ca')&&str_contains($text,'• DK ba: 4/10 ca · chưa nối Telegram')&&!str_contains($text,'DK hai'),'Danh sách đúng người chưa chốt, ghi người chưa nối Telegram: '.$text);
 // Đã chốt thì tin 9h hôm sau không nhắc nữa (CN 9h đã qua; thử bằng tuần sau khi chốt).
 $a->run('INSERT INTO {{availability_weeks}} VALUES(?,?,?)',[$e1['id'],$week,now_ms()]);$sent=[];
 $a->run("DELETE FROM {{telegram_deliveries}} WHERE day='2026-11-08'");availability_telegram_tick($a,$at('2026-11-08','20:30'),$tg);verify(!$to('c1'),'Chốt rồi thì không nhắc nữa');
 // Báo Admin/Quản lý khi nhân viên chốt: danh sách ca từng ngày, nút mở Đăng ký ca; không gửi cho chính người chốt.
 verify(avail_shift_lines($a,(int)$e1['id'],$week)==="T2 09/11: Ca 1, Ca 2\nT3 10/11: Ca 2\nT7 14/11: Ca 1, Ca 2\nCN 15/11: Ca 1, Ca 2",'Liệt kê ca theo ngày: '.avail_shift_lines($a,(int)$e1['id'],$week));
 $sent=[];$a->user=$m;avail_notify_managers($a,'x',$tg);verify(array_column($sent,'chat_id')===['ca'],'Báo Admin, không gửi lại cho chính người gửi');
 verify(($sent[0]['reply_markup']['inline_keyboard'][0][0]['url']??'')==='https://example.test/nhansu/#availability','Tin báo quản lý mở Đăng ký ca');
 // Đăng ký ca chỉ dành cho Nhân viên (chủ dự án yêu cầu 02/11/2026): Quản lý gọi API bị chặn, danh sách quản lý xem không có Quản lý.
 $a->user=$m;try{availability_routes($a,'/availability/commit',['week'=>$week],'POST');verify(false,'Quản lý chốt ca phải bị chặn');}catch(HttpError $x){verify($x->status===403,'Quản lý không đăng ký ca');}
 $_GET=['week'=>$week];$r=availability_routes($a,'/availability',[],'GET')[1];verify(!in_array((int)$m['id'],array_map('intval',array_column($r['people'],'id')),true)&&in_array((int)$e1['id'],array_map('intval',array_column($r['people'],'id')),true),'Danh sách Đăng ký ca chỉ có Nhân viên');
 verify(availability_routes($a,'/availability/summary',[],'GET')[1]['needs_registration']===false,'Quản lý không bị popup nhắc đăng ký ca');
 // Chốt qua API vẫn chạy khi Telegram chưa cấu hình.
 $a->config['telegram_token']='';$a->user=$e3;for($i=0;$i<5;$i++)foreach(['day_first','day_second'] as $s)$a->run('INSERT INTO {{shift_availability}} VALUES(?,?,?,?)',[$e3['id'],date('Y-m-d',strtotime($week.'T00:00:00Z')+$i*86400),$s,now_ms()]);
 verify(availability_routes($a,'/availability/commit',['week'=>$week],'POST')[1]['ok']===true&&$a->one('SELECT 1 FROM {{availability_weeks}} WHERE user_id=? AND week_start=?',[$e3['id'],$week]),'Chốt tuần thành công');
 // Lịch của tôi kèm ca đã đăng ký và ca nghỉ gấp của chính mình; Toàn studio thì không.
 $a->run('INSERT INTO {{shift_cancellations}}(user_id,day,shift,cancelled_at,reason) VALUES(?,?,?,?,?)',[$e1['id'],'2026-11-11','day_first',now_ms(),'Test nghỉ gấp']);
 $a->user=$e1;$_GET=['month'=>'2026-11','scope'=>'mine'];$r=booking_routes($a,'/bookings',[],'GET')[1];
 verify(count($r['registered'])===15&&$r['urgent_cancels']==[['day'=>'2026-11-11','shift'=>'day_first']],'Lịch của tôi có 3 ca đăng ký, 12 ca cuối tuần bắt buộc từ 09/11 và 1 ca nghỉ gấp: '.json_encode([$r['registered'],$r['urgent_cancels']]));
 $_GET=['month'=>'2026-11','scope'=>'mine','page'=>'2'];$r=booking_routes($a,'/bookings',[],'GET')[1];verify(!isset($r['registered']),'Chỉ trang 1 gửi ca đăng ký');
 $_GET=['month'=>'2026-11'];$r=booking_routes($a,'/bookings',[],'GET')[1];verify(!isset($r['registered']),'Toàn studio không kèm ca đăng ký');
 // Thứ 7, Chủ nhật bắt buộc từ tuần 09/11/2026 (chủ dự án yêu cầu 06/11/2026): tự tính, không bỏ được, Quản lý/Admin không bị tính.
 verify(!avail_forced('2026-11-08')&&!avail_forced('2026-11-13')&&avail_forced('2026-11-14')&&avail_forced('2026-11-15'),'Bắt buộc từ T7 14/11, cuối tuần 07–08/11 giữ như cũ');
 $a->user=$e2;$_GET=['week'=>'2026-11-16'];$r=availability_routes($a,'/availability',[],'GET')[1];
 verify($r['forced_days']===['2026-11-21','2026-11-22']&&count($r['mine']['shifts'])===4,'Tuần mới tự có 4 ca cuối tuần: '.json_encode($r['mine']['shifts']));
 $_GET=['week'=>'2026-11-02'];verify(availability_routes($a,'/availability',[],'GET')[1]['forced_days']===[],'Tuần này chưa áp dụng');
 try{availability_routes($a,'/availability/toggle',['day'=>'2026-11-21','shift'=>'day_first','on'=>false],'POST');verify(false,'Bỏ ca cuối tuần phải bị chặn');}catch(HttpError $x){verify($x->status===409&&str_contains($x->getMessage(),'bắt buộc'),'Không bỏ được ca cuối tuần');}
 verify(availability_routes($a,'/availability/toggle',['day'=>'2026-11-22','shift'=>'day_second','on'=>true],'POST')[1]['ok']===true&&!$a->one('SELECT 1 FROM {{shift_availability}} WHERE user_id=? AND day=?',[$e2['id'],'2026-11-22']),'Tick ca cuối tuần không ghi trùng');
 foreach(['2026-11-16','2026-11-17','2026-11-18'] as $d)foreach(['day_first','day_second'] as $s)availability_routes($a,'/availability/toggle',['day'=>$d,'shift'=>$s,'on'=>true],'POST');
 verify(availability_routes($a,'/availability/commit',['week'=>'2026-11-16'],'POST')[1]['ok']===true,'6 ca trong tuần + 4 ca cuối tuần đủ 10 ca để chốt');
 $roster=array_values(array_filter(avail_roster($a,'2026-11-21','2026-11-22',(int)$e2['id'],[]),fn($x)=>$x['source']==='registered'));verify(count($roster)===4&&$roster[0]['name']==='DK hai'&&$roster[0]['role']==='employee','Lịch làm việc có ca cuối tuần bắt buộc');
 $a->user=$m;$_GET=['week'=>'2026-11-16'];$r=availability_routes($a,'/availability',[],'GET')[1];verify(!$r['mine']['shifts'],'Quản lý không bị tính ca bắt buộc');
 $row=array_values(array_filter($r['people'],fn($p)=>(int)$p['id']===(int)$e1['id']))[0];verify($row['active']===4,'Danh sách quản lý xem có ca cuối tuần của nhân viên');
 throw new RuntimeException('ROLLBACK');
});}catch(RuntimeException $e){if($e->getMessage()!=='ROLLBACK')throw $e;}
echo "PASS $n availability checks: Telegram reminders (employees only), manager list, commit notice, My calendar shifts, forced weekend\n";
