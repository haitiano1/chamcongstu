<?php
// Box chat nhóm (chủ dự án yêu cầu 14/11/2026): Admin gõ /nhom trong nhóm; giao Task / Nhắc nhở và ghi Event log mới thì cron gửi vào nhóm.
// Giao dịch rồi hoàn tác, Telegram giả lập.
foreach(['bootstrap','telegram','bookings','people-finance','workflows','studio-rules','tasks'] as $f)require_once __DIR__.'/../site/nhansu-private/lib/'.$f.'.php';
$a=new App('/var/nhansu-private');$n=0;function verify($v,$message){global $n;if(!$v)throw new RuntimeException('FAIL: '.$message);$n++;}
class FakeTg extends Telegram {public array $sent=[];public function __construct(public App $a){}public function configured(): bool {return true;}public function call(string $method,array $b=[]): mixed {$this->sent[]=$b;return [];}}
class Rollback extends Exception {}
try{$a->tx(function()use($a){
 $tg=new FakeTg($a);$a->config['app_url']='https://example.test/nhansu';
 $admin=$a->one("SELECT * FROM {{users}} WHERE role='admin' AND archived=0 ORDER BY id LIMIT 1");
 $a->run("INSERT INTO {{users}}(name,email,password,role,employee_code,notes) VALUES('Trần Văn Zuy','tg.zuy@local.test','x','employee','TG-ZUY','')");$zuy=(int)$a->scalar('SELECT LAST_INSERT_ID()');
 $a->run('DELETE FROM {{telegram_links}} WHERE chat_id IN (?,?) OR user_id IN (?,?)',['999000111','999000222',$admin['id'],$zuy]);
 $a->run('INSERT INTO {{telegram_links}}(user_id,chat_id,linked_at) VALUES(?,?,?),(?,?,?)',[$admin['id'],'999000111',now_ms(),$zuy,'999000222',now_ms()]);
 $a->run('DELETE FROM {{telegram_group_outbox}}');tg_group_set($a,null);$a->meta('telegram_bot_id','777');
 $mid=1;$msg=function(string $text,string $from,string $type='supergroup',string $chat='-100555')use(&$mid){return ['message_id'=>$mid++,'chat'=>['id'=>$type==='private'?(int)$from:(int)$chat,'type'=>$type,'title'=>'AGS team'],'from'=>['id'=>(int)$from,'is_bot'=>false],'text'=>$text];};
 $last=function()use($tg){return end($tg->sent)['text']??'';};
 $outbox=fn()=>(int)$a->scalar('SELECT COUNT(*) FROM {{telegram_group_outbox}}');
 $task=fn(string $title,string $kind='task')=>task_create($a,$admin['id'],['kind'=>$kind,'title'=>$title,'body'=>'Chi tiết việc','audience'=>'people','user_ids'=>[$zuy],'require_photo'=>2,'due_at'=>$kind==='task'?now_ms()+86400000:null]);
 $task('Chưa có nhóm nên không xếp');verify($outbox()===0,'Chưa chọn nhóm thì không xếp tin');
 $tg->update(['message'=>$msg('/nhom','999000111','private')]);verify(str_contains($last(),'gõ /nhom trong nhóm'),'Chat riêng gõ /nhom thì hướng dẫn');
 $tg->update(['message'=>$msg('/nhom','999000222')]);verify(str_contains($last(),'Chỉ Admin')&&!tg_group_chat($a),'Nhân viên không chọn được nhóm');
 $tg->update(['message'=>$msg('/nhom@testagsbot','999000333')]);verify(str_contains($last(),'chưa liên kết')&&!tg_group_chat($a),'Người lạ không chọn được nhóm');
 $tg->update(['message'=>$msg('/nhom','999000111')]);verify(str_starts_with($last(),'✅')&&tg_group_chat($a)==='-100555'&&$a->meta('telegram_group_title')==='AGS team','Admin gõ /nhom trong nhóm thì lưu nhóm');
 $tg->sent=[];
 $t=$task('Dọn phòng chụp 2');$task('Mai họp 8h30','notice');$gone=$task('Việc sẽ bị xóa');
 $a->run("UPDATE {{tasks}} SET status='deleted' WHERE id=?",[$gone['id']]);
 daily_tasks_ensure($a,local_day(now_ms()));
 verify($outbox()===3,'Task, Nhắc nhở mới được xếp; việc hằng ngày không xếp');
 $a->user=$admin;$lid=uuid_v4();log_routes($a,'/event-log',['id'=>$lid,'user_id'=>$zuy,'event_date'=>local_day(now_ms()),'category'=>'missing_punch','description'=>'Quên chấm ra ca 2','offense'=>1],'POST');
 $gid=uuid_v4();log_routes($a,'/event-log',['id'=>$gid,'user_id'=>null,'event_date'=>local_day(now_ms()),'category'=>'other','description'=>'Họp rút kinh nghiệm cả studio'],'POST');
 verify($outbox()===5,'Ghi Event log mới được xếp');
 tg_group_tick($a,$tg);
 $texts=array_column($tg->sent,'text');$chats=array_unique(array_column($tg->sent,'chat_id'));
 verify(count($texts)===4&&$chats===['-100555'],'Gửi 4 tin vào nhóm, bỏ việc đã xóa');
 verify(str_contains($texts[0],'📌 Task mới')&&str_contains($texts[0],'Dọn phòng chụp 2')&&str_contains($texts[0],'Trần Văn Zuy')&&str_contains($texts[0],'Chi tiết việc')&&str_contains($texts[0],'⏰ Hạn')&&str_contains($texts[0],'Cần chụp 2 ảnh'),'Tin Task đủ nội dung');
 verify(str_contains($texts[1],'🔔 Nhắc nhở mới')&&str_contains($texts[1],'Mai họp 8h30'),'Tin Nhắc nhở');
 verify(str_contains($texts[2],'📝 Event log mới')&&str_contains($texts[2],'Quên chấm công')&&str_contains($texts[2],'Trừ 20 điểm KPI (20.000đ)')&&str_contains($texts[2],'Lần 1')&&str_contains($texts[2],'Quên chấm ra ca 2'),'Tin Event log hiện đủ điểm KPI');
 verify(str_contains($texts[3],'Nhật ký chung studio')&&str_contains($texts[3],'không trừ điểm'),'Nhật ký chung');
 verify(($tg->sent[0]['reply_markup']['inline_keyboard'][0][0]['url']??'')==='https://example.test/nhansu/#tasks'&&($tg->sent[2]['reply_markup']['inline_keyboard'][0][0]['url']??'')==='https://example.test/nhansu/#eventlog','Nút mở đúng trang');
 verify((int)$a->scalar("SELECT COUNT(*) FROM {{telegram_group_outbox}} WHERE state='sent'")===4&&(int)$a->scalar("SELECT COUNT(*) FROM {{telegram_group_outbox}} WHERE state='skipped'")===1,'Trạng thái hàng đợi');
 verify((int)$a->scalar("SELECT COUNT(*) FROM {{task_recipients}} WHERE task_id=? AND notify_state='pending'",[$t['id']])===1,'Tin nhắn riêng cho người nhận vẫn xếp như cũ');
 $tg->sent=[];tg_group_tick($a,$tg);verify(!$tg->sent,'Không gửi lặp');
 $a->run("UPDATE {{telegram_group_outbox}} SET created_at=created_at-? WHERE 1",[0]);$task('Việc cũ');$a->run("UPDATE {{telegram_group_outbox}} SET created_at=? WHERE state='pending'",[now_ms()-TG_GROUP_MAX_AGE-1000]);tg_group_tick($a,$tg);verify(!$tg->sent,'Tin quá 1 ngày không gửi dồn');
 $tg->update(['message'=>['message_id'=>99,'chat'=>['id'=>-100555,'type'=>'group'],'from'=>['id'=>1,'is_bot'=>false],'migrate_to_chat_id'=>-100999]]);verify(tg_group_chat($a)==='-100999','Nhóm nâng cấp supergroup thì đổi mã');
 $tg->update(['message'=>$msg('/bonhom','999000222','supergroup','-100999')]);verify(tg_group_chat($a)==='-100999','Nhân viên không tắt được');
 $tg->update(['message'=>$msg('/bonhom','999000111','supergroup','-100999')]);verify(!tg_group_chat($a)&&str_contains($last(),'Đã ngừng'),'Admin /bonhom thì ngừng');
 $before=$outbox();$task('Sau khi tắt');verify($outbox()===$before,'Tắt nhóm thì không xếp');
 $tg->update(['message'=>$msg('/nhom','999000111','group','-100444')]);verify(tg_group_chat($a)==='-100444','Bật lại');
 $tg->update(['message'=>['message_id'=>100,'chat'=>['id'=>-100444,'type'=>'group'],'from'=>['id'=>1,'is_bot'=>false],'left_chat_member'=>['id'=>777,'is_bot'=>true]]]);verify(!tg_group_chat($a),'Bot bị xóa khỏi nhóm thì ngừng');
 $tg->sent=[];$tg->update(['message'=>$msg('/task','999000111','supergroup','-100999')]);verify(str_contains($last(),'Cách giao việc'),'Lệnh /task trong nhóm vẫn chạy');
 throw new Rollback();
});}catch(Rollback){}
echo "OK $n telegram-group\n";
