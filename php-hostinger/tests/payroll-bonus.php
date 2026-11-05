<?php
// Phiếu thưởng + quyền Nhập thưởng + Ứng lương báo Telegram (chủ dự án yêu cầu 02/11/2026).
// Chạy trong giao dịch rồi hoàn tác; Telegram giả lập, không gửi tin thật.
foreach(['bootstrap','telegram','bookings','people-finance'] as $f)require_once __DIR__.'/../site/nhansu-private/lib/'.$f.'.php';
$a=new App('/var/nhansu-private');$n=0;function verify($v,$message){global $n;if(!$v)throw new RuntimeException('FAIL: '.$message);$n++;}
function denied(callable $fn,int $status,string $message){try{$fn();}catch(HttpError $e){verify($e->status===$status,$message.' (mã '.$e->status.': '.$e->getMessage().')');return $e->getMessage();}verify(false,$message.' (không bị chặn)');}
try{$a->tx(function()use($a){
 $a->run('DELETE FROM {{telegram_links}}');$a->config['telegram_token']='';$month=substr(local_day(now_ms()),0,7);$today=local_day(now_ms());
 $admin=$a->one("SELECT * FROM {{users}} WHERE role='admin' AND archived=0 ORDER BY id LIMIT 1");
 $make=function(string $key,string $role)use($a){$a->run("INSERT INTO {{users}}(name,email,password,role,employee_code,notes) VALUES(?,?,'x',?,?,'')",['TB '.$key,'tb.'.$key.'@local.test',$role,'TB-'.strtoupper($key)]);return $a->one('SELECT * FROM {{users}} WHERE id=LAST_INSERT_ID()');};
 $tin=$make('tin','employee');$huy=$make('huy','employee');$m=$make('quanly','manager');$mk=$make('makeup','makeup');
 $as=function(array $u)use($a){$a->user=$u;};$route=fn(string $path,array $b=[],string $method='POST')=>payroll_bonus_routes($a,$path,$b,$method);
 $add=fn(array $u,string $kind,int $amount,string $day='',string $id='')=>$route('/payroll/bonuses/add',['id'=>$id?:uuid_v4(),'day'=>$day?:local_day(now_ms()),'user_id'=>(int)$u['id'],'kind'=>$kind,'amount'=>$amount,'note'=>'']);
 $total=fn(array $u,string $kind)=>(int)($a->scalar('SELECT amount FROM {{payroll_bonuses}} WHERE month=? AND user_id=? AND kind=?',[$month,$u['id'],$kind])?:0);

 // Quyền Nhập thưởng: chỉ Admin tick cho Nhân viên; Makeup không được cấp.
 $as($admin);save_user_grants($a,(int)$mk['id'],'makeup',['bonus'=>true]);verify(!$a->one('SELECT 1 FROM {{payroll_bonus_editors}} WHERE user_id=?',[$mk['id']]),'Makeup không được cấp quyền Nhập thưởng');
 $as($tin);verify(bonus_can_enter($a)===false,'Nhân viên chưa tick không nhập thưởng');denied(fn()=>$add($huy,'referral',50000),403,'Chưa cấp quyền thì bị chặn');
 $as($admin);save_user_grants($a,(int)$tin['id'],'employee',['event_log'=>false,'bookings'=>false,'meetings'=>false,'bonus'=>true]);
 $as($tin);verify(bonus_can_enter($a)===true,'Tick Nhập thưởng thì nhân viên nhập được');
 $as($admin);save_user_grants($a,(int)$tin['id'],'employee',['event_log'=>false,'bookings'=>false,'meetings'=>false]);verify((bool)$a->one('SELECT 1 FROM {{payroll_bonus_editors}} WHERE user_id=?',[$tin['id']]),'Giao diện cũ (không có ô) giữ quyền Nhập thưởng');
 $as($m);verify(bonus_can_enter($a)===true,'Quản lý luôn nhập được');

 // Tín cộng / trừ thưởng cho Huy theo ngày; không tự nhập cho mình.
 $as($tin);denied(fn()=>$add($tin,'referral',50000),403,'Không tự nhập thưởng cho chính mình');
 $id=uuid_v4();$r=$add($huy,'referral',100000,'',$id);verify($r[0]===201&&$total($huy,'referral')===100000,'Cộng 100k Giới thiệu khách');
 $r=$add($huy,'referral',100000,'',$id);verify(($r[1]['already_saved']??false)&&$total($huy,'referral')===100000,'Gửi lại cùng mã không cộng 2 lần');
 $add($huy,'referral',50000);verify($total($huy,'referral')===150000,'Cộng dồn trong tháng');
 denied(fn()=>$add($huy,'referral',-200000),400,'Không trừ bớt quá tổng');
 $add($huy,'referral',-150000);verify($total($huy,'referral')===0&&!$a->one('SELECT 1 FROM {{payroll_bonuses}} WHERE month=? AND user_id=? AND kind=?',[$month,$huy['id'],'referral']),'Trừ về 0 thì xóa dòng tổng');
 denied(fn()=>$add($huy,'ot_rate',1000),400,'Đơn giá OT không nhập theo ngày');
 denied(fn()=>$add($huy,'referral',0),400,'Số tiền 0 bị chặn');
 denied(fn()=>$add($huy,'referral',1000,local_day(now_ms()+86400000)),400,'Ngày tương lai bị chặn');
 denied(fn()=>$add($m,'referral',1000),400,'Chỉ nhập cho tài khoản Nhân viên');
 denied(fn()=>$route('/payroll/bonuses',['month'=>$month,'user_id'=>(int)$huy['id'],'items'=>['referral'=>1]]),403,'Nhân viên không sửa tổng trên phiếu lương');
 $add($huy,'praise',30000);

 // Quản lý sửa tổng trên phiếu lương: ghi chênh lệch vào lịch sử để nhân viên xem.
 $as($m);$route('/payroll/bonuses',['month'=>$month,'user_id'=>(int)$huy['id'],'items'=>['praise'=>80000,'ot_rate'=>20000]]);
 $last=$a->one("SELECT * FROM {{payroll_bonus_entries}} WHERE user_id=? AND kind='praise' ORDER BY created_at DESC,amount DESC LIMIT 1",[$huy['id']]);
 verify($total($huy,'praise')===80000&&(int)$last['amount']===50000&&str_contains($last['note'],'30.000đ → 80.000đ'),'Sửa tổng ghi chênh lệch +50k');
 verify(!$a->one("SELECT 1 FROM {{payroll_bonus_entries}} WHERE user_id=? AND kind='ot_rate'",[$huy['id']]),'Đơn giá OT không vào lịch sử thưởng');

 // Danh sách: Tín thấy người khác (không có mình), Huy chỉ thấy lịch sử của mình.
 $_GET['month']=$month;
 $as($tin);$r=$route('/payroll/bonus-entries',[],'GET')[1];$ids=array_column($r['people'],'id');
 verify($r['can_enter']&&in_array((int)$huy['id'],$ids,true)&&!in_array((int)$tin['id'],$ids,true)&&!in_array((int)$m['id'],$ids,true),'Tín thấy nhân viên khác, không có mình / Quản lý');
 $as($huy);$r=$route('/payroll/bonus-entries',[],'GET')[1];
 verify(!$r['can_enter']&&!$r['people']&&$r['entries']&&!array_filter($r['entries'],fn($e)=>(int)$e['user_id']!==(int)$huy['id']),'Huy chỉ thấy thưởng của mình');
 verify(count(array_filter($r['entries'],fn($e)=>$e['kind']==='referral'))===3,'Lịch sử giữ cả lượt cộng và trừ');

 // Phiếu thưởng: nhân viên thấy khoản thưởng, tổng thưởng, KPI trừ; không thấy lương cứng / lương cuối.
 $row=visible_summary(['role'=>'employee'],['bonus_items'=>['praise'=>80000],'bonus_total'=>500000,'kpi_applied'=>20000,'ot_bonus'=>0,'commission'=>0,'booking_rate'=>15000,'salary'=>1,'base_salary'=>2,'monthly_salary'=>3,'gross_salary'=>4]);
 verify(isset($row['bonus_items'],$row['bonus_total'],$row['kpi_applied'],$row['booking_rate'])&&!array_intersect_key($row,array_flip(['salary','base_salary','monthly_salary','gross_salary'])),'Nhân viên thấy phiếu thưởng, không thấy lương');

 // Ứng lương: hôm nay vẫn tạo được; Telegram báo ngay Quản lý + Admin (không báo người gửi), duyệt trong 24 giờ.
 $as($tin);$r=advance_routes($a,'/advances',['id'=>uuid_v4(),'requested_date'=>$today,'amount'=>500000,'reason'=>'Đóng tiền nhà'],'POST');verify($r[0]===201&&$r[1]['notified']===0,'Tạo phiếu ứng ngày hôm nay được (chưa cấu hình bot thì không gửi)');
 foreach([[$tin,'ct'],[$huy,'ch'],[$m,'cm'],[$admin,'ca']] as [$u,$chat])$a->run('INSERT INTO {{telegram_links}} VALUES(?,?,?)',[$u['id'],$chat,now_ms()]);
 $a->config['telegram_token']='12345:'.str_repeat('x',30);$a->config['app_url']='https://example.test/nhansu';$sent=[];
 $tg=new Telegram($a,function($method,$b)use(&$sent){$sent[]=$b;return ['ok'=>true,'result'=>['message_id'=>1]];});
 $text=advance_request_text('TB tin',$today,500000,'Đóng tiền nhà',now_ms());$count=advance_notify_managers($a,$text,$tg);$chats=array_column($sent,'chat_id');sort($chats);
 verify($count===2&&$chats===['ca','cm'],'Báo đúng Admin + Quản lý, không báo nhân viên');
 verify(str_contains($text,'TB tin xin ứng 500.000đ')&&str_contains($text,'Lý do: Đóng tiền nhà')&&str_contains($text,'trong 24 giờ tới'),'Tin có số tiền, lý do, hạn 24 giờ: '.$text);
 verify(($sent[0]['reply_markup']['inline_keyboard'][0][0]['url']??'')==='https://example.test/nhansu/#advances','Nút mở mục Ứng lương');
 $as($m);$sent=[];advance_notify_managers($a,$text,$tg);verify(array_column($sent,'chat_id')===['ca'],'Quản lý tự gửi thì chỉ báo Admin');
 throw new RuntimeException('ROLLBACK');
});}catch(RuntimeException $e){if($e->getMessage()!=='ROLLBACK')throw $e;}
echo "PASS $n payroll bonus checks: grant, daily add/subtract, no self entry, slip edit history, staff slip visibility, advance Telegram\n";
