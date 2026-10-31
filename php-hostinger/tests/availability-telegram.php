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
 verify(count($to('c1'))===1&&str_contains($to('c1')[0]['text'],'(09/11 – 15/11)')&&str_contains($to('c1')[0]['text'],'Đã chọn 3/10 ca'),'Nhắc nhân viên chưa chốt, đúng tuần tới và số ca: '.json_encode($to('c1'),JSON_UNESCAPED_UNICODE));
 verify(($to('c1')[0]['reply_markup']['inline_keyboard'][0][0]['url']??'')==='https://example.test/nhansu/#availability','Nút mở thẳng Đăng ký ca');
 verify(!$to('c2'),'Người đã chốt không bị nhắc');verify(count($to('cm'))===1,'Quản lý chưa chốt cũng được nhắc như popup');verify(!$to('ca'),'Thứ 7 không gửi danh sách cho Admin');
 $count=count($sent);availability_telegram_tick($a,$at('2026-11-07','09:40'),$tg);verify(count($sent)===$count,'Cùng khung 9h không gửi lặp');
 availability_telegram_tick($a,$at('2026-11-07','12:30'),$tg);availability_telegram_tick($a,$at('2026-11-07','19:59'),$tg);verify(count($sent)===$count,'Ngoài khung 9h–12h, 20h–23h không gửi');
 availability_telegram_tick($a,$at('2026-11-06','09:05'),$tg);verify(count($sent)===$count,'Thứ 6 không nhắc');
 availability_telegram_tick($a,$at('2026-11-07','22:30'),$tg);verify(count($to('c1'))===2,'Cron lỡ 20h thì gửi bù trước 23h');
 // Chủ nhật 20h: nhắc riêng + danh sách người chưa chốt cho Admin và Quản lý.
 $sent=[];availability_telegram_tick($a,$at('2026-11-08','20:01'),$tg);
 verify(count($to('c1'))===1&&!$to('c2'),'Chủ nhật 20h vẫn nhắc riêng');
 $list=array_values(array_filter($sent,fn($x)=>str_contains($x['text'],'Chưa chốt ca tuần')));
 $ids=array_column($list,'chat_id');sort($ids);verify($ids===['ca','cm'],'Danh sách gửi cả Admin và Quản lý: '.json_encode($ids));
 $text=$list[0]['text'];verify(str_contains($text,'• DK mot: 3/10 ca')&&str_contains($text,'• DK ba: 0/10 ca · chưa nối Telegram')&&!str_contains($text,'DK hai'),'Danh sách đúng người chưa chốt, ghi người chưa nối Telegram: '.$text);
 // Đã chốt thì tin 9h hôm sau không nhắc nữa (CN 9h đã qua; thử bằng tuần sau khi chốt).
 $a->run('INSERT INTO {{availability_weeks}} VALUES(?,?,?)',[$e1['id'],$week,now_ms()]);$sent=[];
 $a->run("DELETE FROM {{telegram_deliveries}} WHERE day='2026-11-08'");availability_telegram_tick($a,$at('2026-11-08','20:30'),$tg);verify(!$to('c1'),'Chốt rồi thì không nhắc nữa');
 // Báo Admin/Quản lý khi nhân viên chốt: danh sách ca từng ngày, nút mở Đăng ký ca; không gửi cho chính người chốt.
 verify(avail_shift_lines($a,(int)$e1['id'],$week)==="T2 09/11: Ca 1, Ca 2\nT3 10/11: Ca 2",'Liệt kê ca theo ngày: '.avail_shift_lines($a,(int)$e1['id'],$week));
 $sent=[];$a->user=$m;avail_notify_managers($a,'x',$tg);verify(array_column($sent,'chat_id')===['ca'],'Báo Admin, không gửi lại cho chính người gửi');
 verify(($sent[0]['reply_markup']['inline_keyboard'][0][0]['url']??'')==='https://example.test/nhansu/#availability','Tin báo quản lý mở Đăng ký ca');
 // Chốt qua API vẫn chạy khi Telegram chưa cấu hình.
 $a->config['telegram_token']='';$a->user=$e3;for($i=0;$i<5;$i++)foreach(['day_first','day_second'] as $s)$a->run('INSERT INTO {{shift_availability}} VALUES(?,?,?,?)',[$e3['id'],date('Y-m-d',strtotime($week.'T00:00:00Z')+$i*86400),$s,now_ms()]);
 verify(availability_routes($a,'/availability/commit',['week'=>$week],'POST')[1]['ok']===true&&$a->one('SELECT 1 FROM {{availability_weeks}} WHERE user_id=? AND week_start=?',[$e3['id'],$week]),'Chốt tuần thành công');
 // Lịch của tôi kèm ca đã đăng ký và ca nghỉ gấp của chính mình; Toàn studio thì không.
 $a->run('INSERT INTO {{shift_cancellations}}(user_id,day,shift,cancelled_at,reason) VALUES(?,?,?,?,?)',[$e1['id'],'2026-11-11','day_first',now_ms(),'Test nghỉ gấp']);
 $a->user=$e1;$_GET=['month'=>'2026-11','scope'=>'mine'];$r=booking_routes($a,'/bookings',[],'GET')[1];
 verify(count($r['registered'])===3&&$r['urgent_cancels']==[['day'=>'2026-11-11','shift'=>'day_first']],'Lịch của tôi có 3 ca đăng ký và 1 ca nghỉ gấp: '.json_encode([$r['registered'],$r['urgent_cancels']]));
 $_GET=['month'=>'2026-11','scope'=>'mine','page'=>'2'];$r=booking_routes($a,'/bookings',[],'GET')[1];verify(!isset($r['registered']),'Chỉ trang 1 gửi ca đăng ký');
 $_GET=['month'=>'2026-11'];$r=booking_routes($a,'/bookings',[],'GET')[1];verify(!isset($r['registered']),'Toàn studio không kèm ca đăng ký');
 throw new RuntimeException('ROLLBACK');
});}catch(RuntimeException $e){if($e->getMessage()!=='ROLLBACK')throw $e;}
echo "PASS $n availability checks: Telegram reminders, manager list, commit notice, My calendar shifts\n";
