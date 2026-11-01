<?php
// Lệnh Telegram /log: đúng thứ tự (nội dung | tên) và gõ ngược (tên | nội dung) đều ghi được. Giao dịch rồi hoàn tác, Telegram giả lập.
foreach(['bootstrap','telegram','bookings','people-finance','workflows','studio-rules','tasks'] as $f)require_once __DIR__.'/../site/nhansu-private/lib/'.$f.'.php';
$a=new App('/var/nhansu-private');$n=0;function verify($v,$message){global $n;if(!$v)throw new RuntimeException('FAIL: '.$message);$n++;}
class FakeTg extends Telegram {public array $sent=[];public function __construct(){}public function call(string $method,array $b=[]): mixed {$this->sent[]=$b['text']??'';return [];}}
class Rollback extends Exception {}
try{$a->tx(function()use($a){
 $admin=$a->one("SELECT * FROM {{users}} WHERE role='admin' AND archived=0 ORDER BY id LIMIT 1");
 $a->run("INSERT INTO {{users}}(name,email,password,role,employee_code,notes) VALUES('Trần Văn Zuy','tl.zuy@local.test','x','employee','TL-ZUY','')");$zuy=(int)$a->scalar('SELECT LAST_INSERT_ID()');
 $a->run('DELETE FROM {{telegram_links}} WHERE chat_id=?',['999000111']);$a->run('DELETE FROM {{telegram_links}} WHERE user_id=?',[$admin['id']]);
 $a->run('INSERT INTO {{telegram_links}}(user_id,chat_id,linked_at) VALUES(?,?,?)',[$admin['id'],'999000111',now_ms()]);
 $send=function(string $args)use($a){$tg=new FakeTg();static $mid=1;log_telegram_command($a,$tg,['chat'=>['id'=>999000111],'from'=>['id'=>999000111],'message_id'=>$mid++],$args);return $tg->sent[0]??'';};
 $count=fn()=>(int)$a->scalar("SELECT COUNT(*) FROM {{event_log}} WHERE user_id=? AND status!='deleted'",[$zuy]);
 $r=$send('Đã họp nhắc lo thiết bị studio | Zuy');verify(str_starts_with($r,'✅')&&$count()===1,'Đúng thứ tự: nội dung | tên');
 $r=$send('Zuy | Đã họp và Zuy nói lí do phân bì làm ít việc');verify(str_starts_with($r,'✅')&&str_contains($r,'Trần Văn Zuy')&&str_contains($r,'Đã họp và Zuy')&&$count()===2,'Gõ ngược: tên | nội dung vẫn ghi đúng');
 verify($a->scalar("SELECT description FROM {{event_log}} WHERE user_id=? ORDER BY created_at DESC LIMIT 1",[$zuy])!=='Zuy','Nội dung không bị ghi thành tên');
 $r=$send('Zuy | Quên checkout ca 2 | 20');verify(str_starts_with($r,'✅')&&str_contains($r,'Trừ 20')&&$count()===3,'Gõ ngược kèm điểm');
 $r=$send('Abcxyz | Khong co ai ten nay');verify(str_contains($r,'Không tìm thấy nhân viên "Khong co ai ten nay"')&&$count()===3,'Cả hai phần không phải tên: báo lỗi như cũ');
 $r=$send('all | Họp rút kinh nghiệm cả studio');verify(str_starts_with($r,'✅')&&str_contains($r,'Nhật ký chung'),'all đặt trước cũng được');
 throw new Rollback();
});}catch(Rollback){}
echo "PASS $n telegram /log checks\n";
