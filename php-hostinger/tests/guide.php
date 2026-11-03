<?php
// Tìm hiểu web: nhân viên tick xác nhận từng mục (không bỏ được, lưu phiên bản), Admin/Quản lý xem tiến độ, nhắc Telegram.
require_once __DIR__.'/../site/nhansu-private/lib/bootstrap.php';foreach(['workflows','telegram','people-finance','bookings','tasks','studio-rules'] as $f)require_once __DIR__."/../site/nhansu-private/lib/$f.php";
$a=new App('/var/nhansu-private');$n=0;function verify($v,$message){global $n;if(!$v)throw new RuntimeException('FAIL: '.$message);$n++;}
try{$a->tx(function()use($a){
 $a->run('DELETE FROM {{telegram_links}}');$a->config['telegram_token']='12345:'.str_repeat('x',30);$sent=[];support_telegram(new Telegram($a,function($m,$b)use(&$sent){$sent[]=$b;return ['ok'=>true,'result'=>['message_id'=>1]];}));
 $mk=function(string $name,string $role)use($a){$a->run("INSERT INTO {{users}}(name,email,password,role,employee_code,notes) VALUES(?,?,'x',?,?,'')",[$name,strtolower(str_replace(' ','.',$name)).'@guide.test',$role,strtoupper(substr(md5($name),0,8))]);return $a->one('SELECT * FROM {{users}} WHERE id=LAST_INSERT_ID()');};
 $staff=$mk('Guide Staff','employee');$manager=$mk('Guide Manager','manager');
 foreach([(int)$staff['id'],(int)$manager['id']] as $uid)$a->run('INSERT INTO {{telegram_links}}(user_id,chat_id,linked_at) VALUES(?,?,?)',[$uid,'chat-'.$uid,now_ms()]);
 $call=fn(string $p,array $b=[],string $m='POST')=>studio_rule_routes($a,$p,$b,$m);$total=count(GUIDE_ITEMS);
 $a->user=$staff;$g=$call('/guide',[],'GET')[1];verify($g['total']===$total&&$g['done']===0&&$g['can_check']&&count($g['items'])===$total&&!$g['people'],'Nhân viên thấy '.$total.' mục, chưa xác nhận');
 try{$call('/guide/check',['key'=>'app']);verify(false,'Thiếu xác nhận');}catch(HttpError $e){verify(true,'Phải tick ô xác nhận');}
 try{$call('/guide/check',['key'=>'xyz','confirm'=>true]);verify(false,'Mục sai');}catch(HttpError $e){verify(true,'Mục không hợp lệ bị chặn');}
 $r=$call('/guide/check',['key'=>'app','confirm'=>true])[1];verify($r['done']===1&&isset(((array)$r['mine'])['app']),'Xác nhận mục 1');
 $at=((array)$r['mine'])['app'];$r=$call('/guide/check',['key'=>'app','confirm'=>true])[1];verify($r['done']===1&&((array)$r['mine'])['app']===$at,'Tick lại không đổi giờ xác nhận');
 $row=$a->one('SELECT * FROM {{guide_checks}} WHERE user_id=? AND item=?',[$staff['id'],'app']);verify((int)$row['version']===GUIDE_ITEMS['app']['v']&&$row['title']===GUIDE_ITEMS['app']['title'],'Lưu phiên bản và tiêu đề làm bằng chứng');
 $a->user=$manager;try{$call('/guide/check',['key'=>'app','confirm'=>true]);verify(false,'Quản lý tick');}catch(HttpError $e){verify(true,'Quản lý không tick thay');}
 $g=$call('/guide',[],'GET')[1];$p=array_values(array_filter($g['people'],fn($x)=>$x['id']===(int)$staff['id']))[0];verify(isset(((array)$p['checks'])['app'])&&count((array)$p['checks'])===1,'Quản lý thấy tiến độ 1 mục');
 $sent=[];$r=$call('/guide/remind')[1];verify(in_array('Guide Staff',$r['people'],true)&&in_array('chat-'.$staff['id'],array_column($sent,'chat_id'),true)&&str_contains($sent[0]['text'],'chưa xác nhận'),'Nhắc qua Telegram người chưa xong');
 $a->user=$staff;try{$call('/guide/remind');verify(false,'NV nhắc');}catch(HttpError $e){verify(true,'Nhân viên không gửi nhắc');}
 // Phiên bản cũ không tính: giả lập nội dung đổi bằng cách hạ version đã lưu.
 $a->run('UPDATE {{guide_checks}} SET version=0 WHERE user_id=? AND item=?',[$staff['id'],'app']);verify($call('/guide',[],'GET')[1]['done']===0,'Nội dung đổi phiên bản thì phải xác nhận lại');
 // Đủ mục → Telegram báo Quản lý.
 $sent=[];foreach(array_keys(GUIDE_ITEMS) as $k)$r=$call('/guide/check',['key'=>$k,'confirm'=>true])[1];
 verify($r['done']===$total&&$r['notified']>=1&&in_array('chat-'.$manager['id'],array_column($sent,'chat_id'),true)&&str_contains(end($sent)['text'],'đã xác nhận đủ'),'Xác nhận đủ: Telegram báo Admin/Quản lý');
 throw new RuntimeException('ROLLBACK');
});}catch(RuntimeException $e){if($e->getMessage()!=='ROLLBACK')throw $e;}
echo "PASS $n guide checks: confirm once, version, manager progress, remind, completion Telegram\n";
