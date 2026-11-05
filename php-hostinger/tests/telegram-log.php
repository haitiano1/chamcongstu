<?php
// Lệnh Telegram /log: tên trước giống /task (tên | nội dung, chủ dự án yêu cầu 02/11/2026); gõ kiểu cũ (nội dung | tên) vẫn ghi được.
// Luật thường tự tính theo số lần trong tháng (20 → 100 → 300), luật nặng trừ đúng điểm riêng; chỉ ghi cho tài khoản Nhân viên.
// Giao dịch rồi hoàn tác, Telegram giả lập.
foreach(['bootstrap','telegram','bookings','people-finance','workflows','studio-rules','tasks'] as $f)require_once __DIR__.'/../site/nhansu-private/lib/'.$f.'.php';
$a=new App('/var/nhansu-private');$n=0;function verify($v,$message){global $n;if(!$v)throw new RuntimeException('FAIL: '.$message);$n++;}
class FakeTg extends Telegram {public array $sent=[];public function __construct(){}public function call(string $method,array $b=[]): mixed {$this->sent[]=$b['text']??'';return [];}}
class Rollback extends Exception {}
try{$a->tx(function()use($a){
 $admin=$a->one("SELECT * FROM {{users}} WHERE role='admin' AND archived=0 ORDER BY id LIMIT 1");
 $a->run("INSERT INTO {{users}}(name,email,password,role,employee_code,notes) VALUES('Trần Văn Zuy','tl.zuy@local.test','x','employee','TL-ZUY','')");$zuy=(int)$a->scalar('SELECT LAST_INSERT_ID()');
 $a->run("INSERT INTO {{users}}(name,email,password,role,employee_code,notes) VALUES('Lê Văn Xoan','tl.xoan@local.test','x','employee','TL-XOAN','')");$xoan=(int)$a->scalar('SELECT LAST_INSERT_ID()');
 $a->run("INSERT INTO {{users}}(name,email,password,role,employee_code,notes) VALUES('Quản Lý Yêm','tl.yem@local.test','x','manager','TL-YEM','')");
 $a->run('DELETE FROM {{telegram_links}} WHERE chat_id=?',['999000111']);$a->run('DELETE FROM {{telegram_links}} WHERE user_id=?',[$admin['id']]);
 $a->run('INSERT INTO {{telegram_links}}(user_id,chat_id,linked_at) VALUES(?,?,?)',[$admin['id'],'999000111',now_ms()]);
 $send=function(string $args)use($a){$tg=new FakeTg();static $mid=1;log_telegram_command($a,$tg,['chat'=>['id'=>999000111],'from'=>['id'=>999000111],'message_id'=>$mid++],$args);return $tg->sent[0]??'';};
 $count=fn()=>(int)$a->scalar("SELECT COUNT(*) FROM {{event_log}} WHERE user_id=? AND status!='deleted'",[$zuy]);
 $log=fn(int $u,string $text)=>$a->one('SELECT * FROM {{event_log}} WHERE user_id=? AND description=?',[$u,$text]);
 $r=$send('Zuy | Đã họp nhắc lo thiết bị studio');verify(str_starts_with($r,'✅')&&str_contains($r,'Trần Văn Zuy')&&$count()===1,'Tên trước: tên | nội dung');
 verify((bool)$log($zuy,'Đã họp nhắc lo thiết bị studio'),'Nội dung không bị ghi thành tên');
 $r=$send('Đã họp và Zuy nói lí do phân bì làm ít việc | Zuy');verify(str_starts_with($r,'✅')&&str_contains($r,'Đã họp và Zuy')&&$count()===2,'Gõ kiểu cũ: nội dung | tên vẫn ghi đúng');
 $r=$send('Zuy | Quên checkout ca 2 | 20');verify(str_starts_with($r,'✅')&&str_contains($r,'Trừ 20')&&$count()===3,'Kèm điểm 20');
 $r=$send('Abcxyz | Khong co ai ten nay');verify(str_contains($r,'Không tìm thấy nhân viên "Abcxyz"')&&$count()===3,'Cả hai phần không phải tên: báo lỗi theo phần tên');
 $r=$send('Yêm | Quản lý quên khóa cửa');verify(str_contains($r,'Không tìm thấy nhân viên')&&!$a->one("SELECT 1 FROM {{event_log}} e JOIN {{users}} u ON u.id=e.user_id WHERE u.role='manager' AND e.description='Quản lý quên khóa cửa'"),'Không ghi log cho Quản lý');
 $r=$send('all | Họp rút kinh nghiệm cả studio');verify(str_starts_with($r,'✅')&&str_contains($r,'Nhật ký chung'),'all = nhật ký chung');
 // Luật thường (dưới 100 điểm): lần 1 −20, lần 2 −100, lần 3 trở đi −300, đếm riêng từng người trong tháng.
 $mk=function(string $code,int $points)use($a,$admin){$id=uuid_v4();$a->run("INSERT INTO {{studio_rules}}(id,code,name,category,content,points,status,created_by,created_at,updated_by,updated_at) VALUES(?,?,?,?,?,?,'active',?,?,?,?)",[$id,$code,'Luật thử '.$code,'Thử','',$points,$admin['id'],now_ms(),$admin['id'],now_ms()]);return $id;};
 $mk('TL-01',20);$mk('TL-02',0);$mk('TL-NANG',300);
 $r=$send('Zuy | Quên checklist khách 1 | TL-01');$l=$log($zuy,'Quên checklist khách 1');verify((int)$l['points']===-20&&(int)$l['offense']===1&&str_contains($r,'Lần 1 trong tháng (Nhắc nhở)'),'Luật thường lần 1: −20 Nhắc nhở · '.$r);
 $r=$send('Zuy | Quên checklist khách 2 | TL-01');$l=$log($zuy,'Quên checklist khách 2');verify((int)$l['points']===-100&&(int)$l['offense']===2&&str_contains($r,'Cảnh cáo'),'Lần 2: −100 Cảnh cáo');
 $r=$send('Zuy, Xoan | Quên checklist khách 3 | TL-01');verify((int)$log($zuy,'Quên checklist khách 3')['points']===-300&&(int)$log($zuy,'Quên checklist khách 3')['offense']===3&&(int)$log($xoan,'Quên checklist khách 3')['points']===-20&&str_contains($r,'Trần Văn Zuy: Lần 3')&&str_contains($r,'Lê Văn Xoan: Lần 1'),'Đếm riêng từng người: Zuy lần 3 −300, Xoan lần 1 −20 · '.$r);
 $send('Zuy | Quên checklist khách 4 | TL-01');verify((int)$log($zuy,'Quên checklist khách 4')['points']===-300&&(int)$log($zuy,'Quên checklist khách 4')['offense']===3,'Lần 4 vẫn Kỷ luật −300');
 $send('Xoan | Quên báo ca | TL-02');verify((int)$log($xoan,'Quên báo ca')['points']===-20&&(int)$log($xoan,'Quên báo ca')['offense']===1,'Luật 0 điểm cũng tính theo số lần, đếm riêng từng luật');
 $a->run("UPDATE {{event_log}} SET status='void' WHERE user_id=? AND rule_code='TL-02'",[$xoan]);$send('Xoan | Quên báo ca lần nữa | TL-02');verify((int)$log($xoan,'Quên báo ca lần nữa')['offense']===1,'Log đã hủy không tính vào số lần');
 // Luật nặng (từ 100 điểm): luôn trừ đúng điểm của luật.
 $r=$send('Xoan | Chấm công hộ người khác | TL-NANG');$l=$log($xoan,'Chấm công hộ người khác');verify((int)$l['points']===-300&&$l['offense']===null&&str_contains($r,'lỗi nặng'),'Luật nặng trừ cố định −300 ngay lần đầu');
 verify(str_contains((string)$a->scalar("SELECT note FROM {{event_log_audit}} WHERE event_log_id=? AND action='created'",[$l['id']]),'Luật nặng TL-NANG'),'Lịch sử ghi rõ luật nặng');
 $r=$send('all | Studio bừa bộn | TL-NANG');verify(str_contains($r,'không trừ điểm'),'Nhật ký chung không áp luật nặng');
 throw new Rollback();
});}catch(Rollback){}
echo "PASS $n telegram /log checks: name first, old order, offense tiers, heavy rules, employees only\n";
