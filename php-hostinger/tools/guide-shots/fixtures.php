<?php
// Dữ liệu thử cho ảnh Tìm hiểu web (khách demo, tài khoản Nhân viên test) trên bản dev. php fixtures.php setup|checkin|clean
// Gắn nhãn [HDTEST] / tab ui-guide để xóa sạch sau khi chụp. Không chạy trên hosting.
require_once '/var/nhansu-private/lib/bootstrap.php';foreach(['workflows','telegram','people-finance','bookings','tasks','availability','studio-rules','meetings'] as $f)require_once "/var/nhansu-private/lib/$f.php";
$a=new App('/var/nhansu-private');$admin=$a->one("SELECT * FROM {{users}} WHERE email='test@gmail.com'");$emp=$a->one("SELECT * FROM {{users}} WHERE email='nhanvien.test@local.test'");$a->user=$admin;
support_telegram(new Telegram($a,fn($m,$b)=>['ok'=>true,'result'=>['message_id'=>1]]));
$mode=$argv[1]??'';$day=local_day(now_ms());$tag='[HDTEST]';
if($mode==='clean'){
 foreach($a->all("SELECT id FROM {{shoot_bookings}} WHERE source_tab='ui-guide'") as $r){foreach(['booking_staff','booking_team_counts','booking_audit','booking_sheet_queue'] as $t)$a->run("DELETE FROM {{{$t}}} WHERE booking_id=?",[$r['id']]);$a->run('DELETE FROM {{shoot_bookings}} WHERE id=?',[$r['id']]);}
 $a->run("DELETE FROM {{booking_aliases}} WHERE alias_key=?",[alias_key('NVTEST')]);
 foreach($a->all("SELECT id FROM {{tasks}} WHERE body LIKE ?",['%'.$tag.'%']) as $t){foreach(['task_recipients','task_audit','task_proofs','task_declines'] as $x)$a->run("DELETE FROM {{{$x}}} WHERE task_id=?",[$t['id']]);$a->run('DELETE FROM {{tasks}} WHERE id=?',[$t['id']]);}
 foreach($a->all("SELECT id FROM {{meetings}} WHERE purpose LIKE ?",['%'.$tag.'%']) as $m){foreach(['meeting_attendees','meeting_audit'] as $x)$a->run("DELETE FROM {{{$x}}} WHERE meeting_id=?",[$m['id']]);$a->run('DELETE FROM {{meetings}} WHERE id=?',[$m['id']]);}
 $a->run("DELETE FROM {{events}} WHERE user_id=? AND work_date=? AND reason=?",[$emp['id'],$day,$tag]);
 $a->run("DELETE FROM {{guide_checks}} WHERE user_id=?",[$emp['id']]);
 $a->run("DELETE FROM {{shift_availability}} WHERE user_id=? AND day>=?",[$emp['id'],$day]);
 echo "cleaned\n";exit;
}
if($mode==='checkin'){$a->run("INSERT INTO {{events}}(user_id,type,time,shift,work_date,source,reason) VALUES(?,'in',?,'day_first',?,'self',?)",[$emp['id'],day_time($day)+(9*60+2)*60000,$day,$tag]);echo "checked in\n";exit;}
// Lịch khách giả hôm nay, thợ chính là Nhân viên test.
$a->run('INSERT IGNORE INTO {{booking_aliases}} VALUES(?,?,?,?,?)',[alias_key('NVTEST'),'NVTEST',$emp['id'],$admin['id'],now_ms()]);
$rows=[];foreach([['09:30','Team 349','Team lớp 12A (demo)','Giữ 2 tiếng'],['11:30','699','Chị Lan (demo)',''],['15:00','Couple 999','Anh Minh & chị Hoa (demo)',''],['17:00','399','Bé Na (demo)','']] as [$t,$p,$n,$note])$rows[]=['id'=>uuid_v4(),'day'=>$day,'time'=>$t,'status'=>'active','main_alias'=>'NVTEST','support_alias'=>'','package_label'=>$p,'note'=>$note,'customer_name'=>$n,'customer_phone'=>'0900 000 00'.count($rows),'makeup'=>count($rows)===1?'Nữ':''];
import_bookings($a,['tab'=>'ui-guide','rows'=>$rows],'ui-guide',null);
// Việc thử
$mk=fn($b)=>task_create($a,(int)$admin['id'],$b+['body'=>$tag,'audience'=>'people','user_ids'=>[(int)$emp['id']]]);
$mk(['kind'=>'task','title'=>'Lau kính phòng chụp 1','due_at'=>day_time($day)+18*3600000,'require_photo'=>1]);
$mk(['kind'=>'notice','title'=>'Thứ 6 họp toàn studio lúc 9h']);
$r=$mk(['kind'=>'task','title'=>'Sắp xếp đạo cụ kệ 2','require_review'=>true]);
$a->user=$emp;task_routes($a,'/tasks/'.$r['id'].'/done',['note'=>'Em xếp xong rồi ạ'],'POST');
$a->user=$admin;task_routes($a,'/tasks/'.$r['id'].'/review',['user_id'=>(int)$emp['id'],'decision'=>'reject','note'=>'Kệ dưới còn lộn xộn, xếp theo màu giúp anh'],'POST');
// Biên bản họp chờ xác nhận
meeting_routes($a,'/meetings',['publish'=>true,'title'=>'Họp đầu tuần (demo)','meeting_day'=>$day,'start'=>'08:30','end'=>'09:00','purpose'=>'Phân công tuần này '.$tag,'opinions'=>'','conclusion'=>'Mỗi người kiểm tra đạo cụ trước ca.','actions'=>[['text'=>'Kiểm tra đèn phòng 2','user_id'=>(int)$emp['id'],'due'=>$day]],'attendees'=>[['user_id'=>(int)$emp['id'],'present'=>true]]],'POST');
echo "setup ok $day\n";
