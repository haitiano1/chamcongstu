<?php
// Vào OT khi Ca 2 còn mở (chủ dự án yêu cầu 09/11/2026): mở chấm OT từ 18:50, cùng lượt đó tự chấm ra Ca 2 (cùng ảnh + GPS, không xét hạn 19:30,
// không khóa việc hằng ngày); khóa việc hằng ngày chuyển sang lúc kết thúc OT; không báo "về khi còn khách". Chạy trong giao dịch rồi hoàn tác.
foreach(['bootstrap','telegram','workflows','bookings','people-finance','tasks'] as $f)require_once __DIR__.'/../site/nhansu-private/lib/'.$f.'.php';
$a=new App('/var/nhansu-private');$n=0;function verify($v,$message){global $n;if(!$v)throw new RuntimeException('FAIL: '.$message);$n++;}
function denied(callable $fn,string $want,string $message){try{$fn();}catch(HttpError $e){verify(str_contains($e->getMessage(),$want),$message.' ('.$e->getMessage().')');return;}verify(false,$message.' (không bị chặn)');}
try{$a->tx(function()use($a){
 $day=local_day(now_ms());$at=fn($hm)=>day_time($day)+((int)substr($hm,0,2)*60+(int)substr($hm,3,2))*60000;
 $a->run("INSERT INTO {{users}}(name,email,password,role,employee_code,notes) VALUES('OTS','ots@local.test','x','employee','OTS','')");$e=$a->one('SELECT * FROM {{users}} WHERE id=LAST_INSERT_ID()');$id=(int)$e['id'];$a->user=$e;
 $studio=$a->one('SELECT * FROM {{settings}} WHERE id=1');if(!$studio||$studio['latitude']===null){$a->run('UPDATE {{settings}} SET latitude=10.77,longitude=106.7,radius=100 WHERE id=1');$studio=$a->one('SELECT * FROM {{settings}} WHERE id=1');}
 $img=imagecreatetruecolor(320,240);ob_start();imagejpeg($img);$photo='data:image/jpeg;base64,'.base64_encode(ob_get_clean());
 $pos=fn()=>['latitude'=>(float)$studio['latitude'],'longitude'=>(float)$studio['longitude'],'accuracy'=>10,'timestamp'=>now_ms()];
 $self=fn($b,$sw=false)=>$a->insertEvent($b+['photo'=>$photo,'position'=>$pos()],$id,false,$sw);
 $a->run("INSERT INTO {{events}}(user_id,type,time,shift,work_date,source,reason) VALUES(?,'in',?,'day_second',?,'self','')",[$id,$at('14:00'),$day]);
 // Giờ mở OT.
 denied(fn()=>attendance_decision($a->events($id),'ot',$at('18:49')),'18:50','18:49 chưa mở OT');
 $d=attendance_decision($a->events($id),'ot',$at('18:50'));verify($d['type']==='in'&&$d['close']===['day_second'],'18:50 vào OT, Ca 2 đang mở thì tự chấm ra');
 // Chấm hộ / bổ sung (manual) vẫn bắt chấm ra ca chính trước.
 denied(fn()=>$a->insertEvent(['type'=>'in','shift'=>'ot','time'=>$at('18:55'),'reason'=>'Bổ sung OT abcdef','ot_confirmed'=>true],$id,true),'chấm ra ca chính','Lượt manual không tự đóng Ca 2');
 // Không cờ chuyển OT thì vẫn chặn.
 denied(fn()=>$self(['type'=>'in','shift'=>'ot','time'=>$at('19:45'),'ot_confirmed'=>true]),'chấm ra ca chính','Không cờ chuyển OT thì chặn');
 // Có việc hằng ngày chưa xong, 19:45 (quá hạn 19:30) vẫn vào OT được, Ca 2 được chấm ra cùng giờ.
 $tid=uuid_v4();$a->run("INSERT INTO {{tasks}}(id,kind,title,body,due_at,require_photo,audience,status,cancel_reason,created_by,created_at,updated_by,updated_at,template_id,task_day) VALUES(?,'task','Lau kính OTS','',NULL,0,'people','open','',?,?,?,?,NULL,?)",[$tid,$id,now_ms(),$id,now_ms(),$day]);
 $a->run("INSERT INTO {{task_recipients}}(task_id,user_id,done_note,notify_state) VALUES(?,?,'','skipped')",[$tid,$id]);
 $otIn=$self(['type'=>'in','shift'=>'ot','time'=>$at('19:45'),'ot_confirmed'=>true],true);
 $ev=$a->all('SELECT type,shift,time,reason,photo_jpeg IS NOT NULL p,distance FROM {{events}} WHERE user_id=? ORDER BY time,id',[$id]);
 verify(count($ev)===3&&$ev[1]['shift']==='day_second'&&$ev[1]['type']==='out'&&(int)$ev[1]['time']===$at('19:45')&&$ev[1]['p']&&$ev[1]['reason']===OT_SWITCH_REASON,'Ghi ra Ca 2 lúc 19:45 kèm ảnh');
 verify($ev[2]['shift']==='ot'&&$ev[2]['type']==='in','Rồi vào OT');
 $s=summary($a->events($id),substr($day,0,7),3000000,[]);verify($s['incomplete']===0&&$s['otIncomplete']===1,'Ca 2 hoàn tất, OT đang mở');
 // Kết thúc OT: khóa việc hằng ngày (nếu còn trong ngày).
 $now=now_ms();$sameDay=local_day($now)===$day;
 if($sameDay){denied(fn()=>daily_tasks_block_checkout($a,['type'=>'out','shift'=>'ot']),'chấm ra OT','Kết thúc OT bị khóa khi còn việc hằng ngày');}
 $a->run('UPDATE {{task_recipients}} SET done_at=? WHERE task_id=?',[now_ms(),$tid]);daily_tasks_block_checkout($a,['type'=>'out','shift'=>'ot']);verify(true,'Xong việc thì kết thúc OT được');
 daily_tasks_block_checkout($a,['type'=>'out','shift'=>'day_first']);verify(true,'Ca 1 không khóa');
 // Không báo "về khi còn khách" cho lượt ra Ca 2 để vào OT.
 $bid=uuid_v4();$a->run("INSERT INTO {{shoot_bookings}}(id,source_sheet,source_tab,source_row,shoot_day,minutes,package_label,customer_name,customer_phone,main_alias,support_alias,note,status,created_at,updated_at) VALUES(?,'test','test',1,?,1230,'699','Khach OTS','','','','','active',?,?)",[$bid,$day,now_ms(),now_ms()]);$a->run("INSERT INTO {{booking_staff}} VALUES(?,?,'support')",[$bid,$id]);
 foreach(['telegram_links','telegram_deliveries'] as $t)$a->run('DELETE FROM {{'.$t.'}}');$admin=$a->one("SELECT id FROM {{users}} WHERE role='admin' AND archived=0 ORDER BY id LIMIT 1");$a->run('INSERT INTO {{telegram_links}} VALUES(?,?,?)',[$admin['id'],'ca',now_ms()]);
 $a->config['telegram_token']='12345:'.str_repeat('x',30);$a->config['app_url']='https://example.test/nhansu';
 $sent=[];$tg=new Telegram($a,function($m,$p)use(&$sent){$sent[]=$p['text']??'';return ['ok'=>true,'result'=>['message_id'=>1]];});$early=function()use(&$sent){return array_filter($sent,fn($t)=>str_contains($t,'OTS')&&str_contains($t,'Về khi còn khách'));};
 attendance_alert_tick($a,$at('19:46'),$tg);verify(!$early(),'Không báo về sớm khi chuyển sang OT');
 $a->run("UPDATE {{events}} SET reason='' WHERE user_id=? AND reason=?",[$id,OT_SWITCH_REASON]);attendance_alert_tick($a,$at("19:47"),$tg);verify((bool)$early(),'Đối chứng: lượt ra thường thì có báo về sớm');
 throw new RuntimeException('ROLLBACK');});}catch(RuntimeException $e){if($e->getMessage()!=='ROLLBACK'){echo $e->getMessage(),"\n";exit(1);}}
echo "PASS $n OT switch checks\n";
