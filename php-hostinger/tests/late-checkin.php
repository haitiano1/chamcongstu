<?php
// Đi muộn: checkin quá 30 phút sau khách đầu tiên của ca. Lần 1 miễn, lần 2 tự ghi Event log 20, lần 3 100, từ lần 4 300 điểm KPI;
// log bị hủy/xóa không đếm và không ghi lại; Telegram báo người đi muộn + Admin/Quản lý một lần; nhãn muộn gắn vào lượt checkin.
// Chạy trong giao dịch rồi hoàn tác; Telegram giả lập, không gửi tin thật.
foreach(['bootstrap','telegram','bookings','people-finance','tasks','availability','studio-rules'] as $f)require_once __DIR__.'/../site/nhansu-private/lib/'.$f.'.php';
$a=new App('/var/nhansu-private');$n=0;function verify($v,$message){global $n;if(!$v)throw new RuntimeException('FAIL: '.$message);$n++;}
try{$a->tx(function()use($a){
 foreach(['telegram_links','telegram_deliveries','late_checkins'] as $t)$a->run('DELETE FROM {{'.$t.'}}');$a->run("DELETE FROM {{meta}} WHERE `key`='late_rule_seeded'");$a->run("DELETE FROM {{studio_rules}} WHERE code='DM-01'");
 $admin=$a->one("SELECT * FROM {{users}} WHERE role='admin' AND archived=0 ORDER BY id LIMIT 1");$a->user=$admin;
 $make=function(string $key,string $role)use($a){$a->run("INSERT INTO {{users}}(name,email,password,role,employee_code,notes) VALUES(?,?,'x',?,?,'')",['DM '.$key,'dm.'.$key.'@local.test',$role,'DM-'.strtoupper($key)]);return $a->one('SELECT * FROM {{users}} WHERE id=LAST_INSERT_ID()');};
 $e=$make('cuong','employee');$other=$make('huy','employee');$m=$make('quanly','manager');
 foreach([[$e,'ce'],[$other,'co'],[$m,'cm'],[$admin,'ca']] as [$u,$chat])$a->run('INSERT INTO {{telegram_links}} VALUES(?,?,?)',[$u['id'],$chat,now_ms()]);
 $at=fn(string $day,string $hm)=>day_time($day)+((int)substr($hm,0,2)*60+(int)substr($hm,3,2))*60000;
 // Mốc đến giữ lúc checkin (snapshot); lượt vào ghi thẳng vào bảng events.
 $checkin=function(array $u,string $day,string $shift,?string $expected,string $in)use($a,$at){if($expected!==null)$a->run("INSERT INTO {{booking_shift_snapshots}} VALUES(?,?,?,?,'booking','[]',?)",[$u['id'],$day,$shift,$at($day,$expected),now_ms()]);else $a->run("INSERT INTO {{booking_shift_snapshots}} VALUES(?,?,?,?,'default','[]',?)",[$u['id'],$day,$shift,$at($day,$shift==='day_first'?'09:00':'14:00'),now_ms()]);
  $a->run("INSERT INTO {{events}}(user_id,type,time,shift,work_date,source,reason) VALUES(?,'in',?,?,?,'self','')",[$u['id'],$at($day,$in),$shift,$day]);return (int)$a->scalar('SELECT LAST_INSERT_ID()');};
 $a->config['telegram_token']='12345:'.str_repeat('x',30);$a->config['app_url']='https://example.test/nhansu';
 $sent=[];$tg=new Telegram($a,function($method,$b)use(&$sent){if($method!=='sendMessage')throw new RuntimeException('Unexpected '.$method);$sent[]=$b;return ['ok'=>true,'result'=>['message_id'=>1]];});
 $to=function(string $chat)use(&$sent){return array_values(array_filter($sent,fn($x)=>$x['chat_id']===$chat));};
 $logs=fn()=>$a->all("SELECT * FROM {{event_log}} WHERE user_id=? AND status!='deleted' ORDER BY event_date",[$e['id']]);

 // Ngày 1: khách 9:00, vào 9:56 → muộn 56 phút, lần 1, miễn: không ghi Event log, Telegram báo cả 3 người. Ca 2 vào đúng 14:30 không muộn.
 $first=$checkin($e,'2026-11-02','day_first','09:00','09:56');$checkin($e,'2026-11-02','day_second','14:00','14:30');
 late_telegram_tick($a,$at('2026-11-02','09:57'),$tg);
 $rule=$a->one("SELECT * FROM {{studio_rules}} WHERE code='DM-01'");verify($rule&&$rule['status']==='active'&&str_contains($rule['content'],'Lần 2: Lỗi, trừ 20 điểm KPI'),'Cài sẵn luật DM-01 trong Luật Studio');
 verify(!$logs(),'Lần 1 trong tháng không ghi Event log');
 $row=$a->one('SELECT * FROM {{late_checkins}} WHERE user_id=?',[$e['id']]);verify($row&&$row['late_minutes']===56&&$row['ordinal']===1&&$row['points']===0&&$row['shift']==='day_first','Ghi nhận lượt muộn 56 phút, lần 1: '.json_encode($row));
 verify((int)$a->scalar('SELECT COUNT(*) FROM {{late_checkins}} WHERE user_id=?',[$e['id']])===1,'Vào 14:30 với khách 14:00 chưa tính muộn');
 $self=$to('ce');verify(count($self)===1,'Người đi muộn nhận 1 tin');$text=$self[0]['text'];
 foreach(['Bạn checkin muộn 56 phút','Ca 1 (9:00–14:00) · Thứ 2 02/11/2026','Khách đầu tiên 09:00 · checkin sau 09:30 là muộn · bạn checkin lúc 09:56','lần đi muộn thứ 1 trong tháng 11/2026: nhắc nhở, miễn trừ điểm','Lần tới (lần 2): Lỗi, trừ 20 điểm KPI','• Lần 3: Cảnh cáo, trừ 100 điểm KPI','• Từ lần 4 trở đi: Kỷ luật, trừ 300 điểm KPI mỗi lần'] as $want)verify(str_contains($text,$want),'Tin nhân viên có "'.$want.'": '.$text);
 verify(($self[0]['reply_markup']['inline_keyboard'][0][0]['url']??'')==='https://example.test/nhansu/#attendance','Nút mở Chấm công cho nhân viên');
 verify(count($to('cm'))===1&&count($to('ca'))===1&&!$to('co'),'Báo Quản lý và Admin, không báo nhân viên khác');
 verify(str_contains($to('cm')[0]['text'],'DM cuong checkin muộn 56 phút')&&($to('cm')[0]['reply_markup']['inline_keyboard'][0][0]['url']??'')==='https://example.test/nhansu/#eventlog','Tin quản lý ghi tên, nút mở Event log');
 $count=count($sent);late_telegram_tick($a,$at('2026-11-02','10:30'),$tg);verify(count($sent)===$count&&count($logs())===0,'Chạy lại cron không gửi lặp');

 // Ngày 2: khách 10:00, vào 10:31 → lần 2: tự ghi Event log 20 điểm, đã xác nhận, gắn luật DM-01 và liên kết đối soát.
 $checkin($e,'2026-11-03','day_first','10:00','10:31');$sent=[];late_telegram_tick($a,$at('2026-11-03','10:32'),$tg);
 $l=$logs();verify(count($l)===1&&$l[0]['points']===-20&&$l[0]['status']==='confirmed'&&$l[0]['category']==='late'&&$l[0]['rule_code']==='DM-01'&&$l[0]['source_label']===LATE_AUTO_LABEL,'Lần 2 tự ghi Event log trừ 20: '.json_encode($l));
 verify(str_contains($l[0]['description'],'Đi muộn 31 phút · Ca 1 · Thứ 3 03/11/2026 · Khách đầu tiên 10:00, checkin 10:31 · Lần 2 trong tháng: Lỗi, trừ 20 điểm KPI.'),'Nội dung log đủ thông tin: '.$l[0]['description']);
 verify($a->one('SELECT 1 FROM {{attendance_issue_links}} WHERE source_key=? AND event_log_id=?',[$e['id'].'_2026-11-03_day_first_late',$l[0]['id']])!==null,'Liên kết với Đối soát chấm công');
 verify(str_contains($to('ce')[0]['text'],'lần đi muộn thứ 2 trong tháng 11/2026: Lỗi, trừ 20 điểm KPI')&&str_contains($to('ce')[0]['text'],'Đã ghi vào Event log, trừ 20 điểm KPI.')&&str_contains($to('ce')[0]['text'],'Lần tới (lần 3): Cảnh cáo, trừ 100 điểm KPI'),'Tin lần 2: '.$to('ce')[0]['text']);

 // Không có khách được giao (snapshot default) thì vào muộn mấy cũng không tính.
 $checkin($e,'2026-11-04','day_first',null,'11:30');late_telegram_tick($a,$at('2026-11-04','11:31'),$tg);verify(count($logs())===1&&!$a->one("SELECT 1 FROM {{late_checkins}} WHERE day='2026-11-04'"),'Ca không có khách không tính muộn');

 // Hủy log lần 2 (có lý do chính đáng): lần đó không đếm; lần muộn kế tiếp lại là lần 2.
 $a->run("UPDATE {{event_log}} SET status='void' WHERE id=?",[$l[0]['id']]);
 $checkin($e,'2026-11-05','day_second','15:00','15:45');late_telegram_tick($a,$at('2026-11-05','15:46'),$tg);
 $row=$a->one("SELECT * FROM {{late_checkins}} WHERE user_id=? AND day='2026-11-05'",[$e['id']]);verify($row['ordinal']===2&&$row['points']===20,'Log bị hủy thì lần sau vẫn là lần 2: '.json_encode($row));
 // Lần 3 = 100, lần 4 và 5 = 300.
 foreach([['2026-11-06',3,100],['2026-11-07',4,300],['2026-11-09',5,300]] as [$d,$ord,$pts]){$checkin($e,$d,'day_first','09:00','09:45');late_telegram_tick($a,$at($d,'09:50'),$tg);$row=$a->one('SELECT * FROM {{late_checkins}} WHERE user_id=? AND day=?',[$e['id'],$d]);$log=$a->one('SELECT points FROM {{event_log}} WHERE id=?',[$row['event_log_id']]);verify($row['ordinal']===$ord&&$log['points']===-$pts,"Lần $ord trừ $pts: ".json_encode($row));}
 verify(-(int)$a->scalar("SELECT SUM(points) FROM {{event_log}} WHERE user_id=? AND status IN ('confirmed','resolved')",[$e['id']])===720,'Tổng KPI bị trừ 20+100+300+300 (log hủy không tính)');

 // Xóa log (Admin xóa vì có lý do): không ghi lại log mới, lần đó không đếm.
 $row=$a->one("SELECT * FROM {{late_checkins}} WHERE user_id=? AND day='2026-11-09'",[$e['id']]);$a->user=$admin;
 log_routes($a,'/event-log/'.$row['event_log_id'].'/delete',['version'=>1,'note'=>'Khách báo dời giờ'],'POST');
 late_telegram_tick($a,$at('2026-11-09','10:30'),$tg);verify((int)$a->scalar("SELECT COUNT(*) FROM {{event_log}} WHERE user_id=? AND event_date='2026-11-09' AND status!='deleted'",[$e['id']])===0,'Log đã xóa không bị tạo lại');
 $marks=late_checkins($a,(int)$e['id'],'2026-11');verify($marks['2026-11-09_day_first']['excused']===true&&$marks['2026-11-09_day_first']['ordinal']===null,'Lần có log bị xóa không đếm');

 // Nhãn trên danh sách chấm công: gắn vào đúng lượt vào.
 $events=with_late_marks($a,$a->events((int)$e['id']));$byId=array_column($events,null,'id');
 verify(($byId[$first]['late']??null)==['minutes'=>56,'expected_at'=>$at('2026-11-02','09:00'),'deadline'=>$at('2026-11-02','09:30'),'ordinal'=>1,'points'=>0,'level'=>'nhắc nhở, miễn trừ điểm','excused'=>false,'logged'=>false],'Nhãn muộn lượt đầu: '.json_encode($byId[$first]['late']??null,JSON_UNESCAPED_UNICODE));
 verify(count(array_filter($events,fn($x)=>isset($x['late'])))===6,'Chỉ các lượt muộn có nhãn (6 lượt)');
 verify(count(array_filter(with_late_marks($a,$a->events((int)$e['id']),'2026-12'),fn($x)=>isset($x['late'])))===0,'Lọc theo tháng khác thì không có nhãn');

 // Đối soát chấm công (chỉ xét tới hôm nay) dùng cùng cách đếm: thử ở tháng đã qua với người khác.
 $checkin($other,'2026-10-05','day_first','09:00','09:40');$checkin($other,'2026-10-06','day_second','14:00','14:20');$checkin($other,'2026-10-07','day_second','16:00','16:45');$checkin($other,'2026-10-08','day_first','12:00','12:31');
 $issues=array_values(array_filter(attendance_issues($a,'2026-10',(int)$other['id']),fn($r)=>$r['kind']==='late'));$by=array_column($issues,null,'day');
 verify(count($issues)===3&&$by['2026-10-05']['late_ordinal']===1&&$by['2026-10-05']['suggested_deduction']===0&&$by['2026-10-05']['late_minutes']===40&&$by['2026-10-07']['late_ordinal']===2&&$by['2026-10-07']['suggested_deduction']===20&&$by['2026-10-08']['late_ordinal']===3&&$by['2026-10-08']['suggested_deduction']===100,'Đối soát: lần, điểm đề xuất: '.json_encode(array_map(fn($r)=>[$r['day'],$r['late_ordinal'],$r['suggested_deduction']],$issues)));
 $void=uuid_v4();$a->run("INSERT INTO {{event_log}}(id,user_id,event_date,category,description,points,status,created_by,created_at,updated_by,updated_at,resolution) VALUES(?,?,'2026-10-07','late','x',-20,'void',?,?,?,?,'')",[$void,$other['id'],$admin['id'],now_ms(),$admin['id'],now_ms()]);$a->run('INSERT INTO {{attendance_issue_links}} VALUES(?,?)',[$other['id'].'_2026-10-07_day_second_late',$void]);
 $by=array_column(array_filter(attendance_issues($a,'2026-10',(int)$other['id']),fn($r)=>$r['kind']==='late'),null,'day');verify($by['2026-10-07']['late_ordinal']===null&&$by['2026-10-08']['late_ordinal']===2&&$by['2026-10-08']['suggested_deduction']===20,'Đối soát: log đã hủy không đếm');

 // Telegram chưa cấu hình: vẫn tự ghi Event log, không gửi.
 $a->config['telegram_token']='';$sent=[];$checkin($e,'2026-11-10','day_first','09:00','10:00');late_telegram_tick($a,$at('2026-11-10','10:01'));
 verify(!$sent&&$a->one("SELECT event_log_id FROM {{late_checkins}} WHERE user_id=? AND day='2026-11-10'",[$e['id']])['event_log_id']!==null,'Không có Telegram vẫn tự ghi Event log');
 // Luật DM-01 đã bị Admin xóa thì không tạo lại.
 $a->run("UPDATE {{studio_rules}} SET status='deleted' WHERE code='DM-01'");late_rule_seed($a);verify($a->scalar("SELECT status FROM {{studio_rules}} WHERE code='DM-01'")==='deleted','Không tạo lại luật đã xóa');
 throw new RuntimeException('ROLLBACK');
});}catch(RuntimeException $e){if($e->getMessage()!=='ROLLBACK')throw $e;}
echo "PASS $n late checks: grace 30 min, monthly count, auto Event log from 2nd, void/delete excused, Telegram, marks, reconciliation\n";
