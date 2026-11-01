<?php
// Về khi còn khách / Làm tiếp: chấm vào lại cùng ca sau khi đã chấm ra (trong giờ ca, vẫn tính 1 ca, hoa hồng theo lúc có mặt),
// Yêu cầu bổ sung công cho lần vào lại, làm cả ngày vẫn tự chuyển ca, khách còn lại, Telegram báo Admin/Quản lý.
// Chạy trong giao dịch rồi hoàn tác; Telegram giả lập, không gửi tin thật.
foreach(['bootstrap','telegram','workflows','bookings','people-finance','tasks'] as $f)require_once __DIR__.'/../site/nhansu-private/lib/'.$f.'.php';
$a=new App('/var/nhansu-private');$n=0;function verify($v,$message){global $n;if(!$v)throw new RuntimeException('FAIL: '.$message);$n++;}
function denied(callable $fn,string $want,string $message){try{$fn();}catch(HttpError $e){verify(str_contains($e->getMessage(),$want),$message.' ('.$e->getMessage().')');return;}verify(false,$message.' (không bị chặn)');}
try{$a->tx(function()use($a){
 foreach(['telegram_links','telegram_deliveries'] as $t)$a->run('DELETE FROM {{'.$t.'}}');
 $admin=$a->one("SELECT * FROM {{users}} WHERE role='admin' AND archived=0 ORDER BY id LIMIT 1");$a->user=$admin;
 $make=function(string $key,string $role)use($a){$a->run("INSERT INTO {{users}}(name,email,password,role,employee_code,notes) VALUES(?,?,'x',?,?,'')",['LT '.$key,'lt.'.$key.'@local.test',$role,'LT-'.strtoupper($key)]);return $a->one('SELECT * FROM {{users}} WHERE id=LAST_INSERT_ID()');};
 $e=$make('cuong','employee');$m=$make('quanly','manager');
 $at=fn(string $day,string $hm)=>day_time($day)+((int)substr($hm,0,2)*60+(int)substr($hm,3,2))*60000;
 $ev=function(array $u,string $type,string $shift,string $day,string $hm,string $source='self')use($a,$at){$a->run('INSERT INTO {{events}}(user_id,type,time,shift,work_date,source,reason) VALUES(?,?,?,?,?,?,?)',[$u['id'],$type,$at($day,$hm),$shift,$day,$source,'']);return ['id'=>(int)$a->scalar('SELECT LAST_INSERT_ID()'),'user_id'=>$u['id'],'type'=>$type,'time'=>$at($day,$hm),'shift'=>$shift,'work_date'=>$day];};
 $book=function(array $u,string $day,string $hm,string $name,string $package)use($a){$id=uuid_v4();$min=(int)substr($hm,0,2)*60+(int)substr($hm,3,2);$a->run("INSERT INTO {{shoot_bookings}}(id,source_sheet,source_tab,source_row,shoot_day,minutes,package_label,customer_name,customer_phone,main_alias,support_alias,note,status,created_at,updated_at) VALUES(?,'test','test',1,?,?,?,?,'','','','','active',?,?)",[$id,$day,$min,$package,$name,now_ms(),now_ms()]);$a->run("INSERT INTO {{booking_staff}} VALUES(?,?,'support')",[$id,$u['id']]);};

 // 1. Quy tắc vào/ra: đã chấm ra thì phải bấm Làm tiếp mới vào lại được, chỉ trong giờ ca; vào lại xong thì lượt kế tiếp là chấm ra.
 $d='2026-10-20';$evs=[['id'=>1,'type'=>'in','time'=>$at($d,'14:00'),'shift'=>'day_second','work_date'=>$d],['id'=>2,'type'=>'out','time'=>$at($d,'17:02'),'shift'=>'day_second','work_date'=>$d]];
 denied(fn()=>attendance_decision($evs,'day_second',$at($d,'18:00')),'Bạn đã hoàn tất ca này hôm nay','Không bấm Làm tiếp thì vẫn chặn như cũ');
 verify(attendance_decision($evs,'day_second',$at($d,'18:00'),false,true)['type']==='in','Bấm Làm tiếp thì chấm vào lại được');
 denied(fn()=>attendance_decision($evs,'day_second',$at($d,'19:05'),false,true),'Sau 19:00 hãy chấm OT','Hết giờ Ca 2 thì không làm tiếp, chỉ OT');
 denied(fn()=>attendance_decision($evs,'day_first',$at($d,'14:10'),false,true),'Chỉ được chấm vào trong khung giờ','Ca khác vẫn theo khung giờ của ca');
 $evs[]=['id'=>3,'type'=>'in','time'=>$at($d,'18:00'),'shift'=>'day_second','work_date'=>$d];verify(attendance_decision($evs,'day_second',$at($d,'19:20'))['type']==='out','Vào lại xong thì lượt kế tiếp là chấm ra');
 $ca1=[['id'=>1,'type'=>'in','time'=>$at($d,'09:00'),'shift'=>'day_first','work_date'=>$d],['id'=>2,'type'=>'out','time'=>$at($d,'11:00'),'shift'=>'day_first','work_date'=>$d]];
 denied(fn()=>attendance_decision($ca1,'day_first',$at($d,'14:05'),false,true),'Đã hết giờ Ca 1 (14:00)','Hết giờ Ca 1 thì báo chấm Ca 2');

 // 2. Công và hoa hồng: 2 đoạn trong cùng ca vẫn tính 1 ca (0,5 công), giờ làm cộng 2 đoạn, khách 18:00 có mặt (đoạn sau) được tính.
 $d='2026-10-21';foreach([['in','14:00'],['out','17:02'],['in','17:55'],['out','19:20']] as [$t,$hm])$ev($e,$t,'day_second',$d,$hm);
 $sum=summary(array_values(array_filter($a->events((int)$e['id']),fn($x)=>$x['work_date']===$d)),'2026-10',8000000,[],null);verify($sum['completedShifts']===1&&$sum['workdays']==0.5&&$sum['hours']==4.12,'Hai đoạn trong ca vẫn tính 1 ca (0,5 công): '.json_encode(array_intersect_key($sum,array_flip(['completedShifts','workdays','hours']))));
 $pairs=pairs($a->events((int)$e['id']))['pairs'];verify(count($pairs)===2,'Hai đoạn trong cùng ca');
 $presence=attendance_presence($a,[(int)$e['id']],$d,$d,now_ms())[(int)$e['id']]??[];$covers=fn(int $t)=>(bool)array_filter($presence,fn($p)=>$p[0]<=$t&&$p[1]>=$t);
 verify($covers($at($d,'18:00'))&&$covers($at($d,'19:00'))&&!$covers($at($d,'17:30')),'Có mặt lúc 18:00, 19:00; vắng 17:02–17:55');

 // 3. Chấm hộ đoạn thứ hai (Quản lý) theo thứ tự giờ.
 $d='2026-10-22';$ev($e,'in','day_second',$d,'14:00');$ev($e,'out','day_second',$d,'16:00');
 $a->insertEvent(['type'=>'in','shift'=>'day_second','time'=>$at($d,'18:10'),'reason'=>'Quay lại làm khách 19h'],(int)$e['id'],true);
 $a->insertEvent(['type'=>'out','shift'=>'day_second','time'=>$at($d,'19:25'),'reason'=>'Về sau khách 19h'],(int)$e['id'],true);
 verify(count(array_filter($a->events((int)$e['id']),fn($x)=>$x['work_date']===$d))===4,'Chấm hộ thêm đoạn thứ hai trong ca');

 // 4. Yêu cầu bổ sung công: quên bấm Làm tiếp → gửi vào lại + ra; duyệt ra trước thì báo sai thứ tự; duyệt vào rồi ra thì ghi đủ.
 $d='2026-10-23';$ev($e,'in','day_second',$d,'14:00');$ev($e,'out','day_second',$d,'17:00');
 $req=function(string $type,string $hm)use($a,$e,$d,$at){$a->run("INSERT INTO {{attendance_requests}}(user_id,shift,type,time,reason,created_at) VALUES(?,'day_second',?,?,?,?)",[$e['id'],$type,$at($d,$hm),'Quay lại làm khách 19h quên bấm làm tiếp',now_ms()]);return (int)$a->scalar('SELECT LAST_INSERT_ID()');};
 $rin=$req('in','18:00');$rout=$req('out','19:30');$a->user=$m;
 denied(fn()=>workflow_routes($a,"/attendance-requests/$rout/review",['status'=>'approved','note'=>'Đã kiểm tra camera'],'POST'),'Sai thứ tự vào/ra','Duyệt lượt ra trước lượt vào lại thì báo sai thứ tự');
 workflow_routes($a,"/attendance-requests/$rin/review",['status'=>'approved','note'=>'Đã kiểm tra camera'],'POST');workflow_routes($a,"/attendance-requests/$rout/review",['status'=>'approved','note'=>'Đã kiểm tra camera'],'POST');
 $day=array_values(array_filter($a->events((int)$e['id']),fn($x)=>$x['work_date']===$d));verify(array_column($day,'type')===['in','out','in','out']&&$day[2]['source']==='manual','Duyệt bổ sung vào lại + ra: '.json_encode(array_column($day,'type')));
 // Bổ sung trùng lượt vào trong lúc đang làm vẫn bị chặn.
 $d2='2026-10-24';$ev($e,'in','day_second',$d2,'14:00');$a->run("INSERT INTO {{attendance_requests}}(user_id,shift,type,time,reason,created_at) VALUES(?,'day_second','in',?,?,?)",[$e['id'],$at($d2,'14:30'),'Gửi trùng lượt vào trong ca',now_ms()]);$dup=(int)$a->scalar('SELECT LAST_INSERT_ID()');
 denied(fn()=>workflow_routes($a,"/attendance-requests/$dup/review",['status'=>'approved','note'=>'Thử duyệt trùng'],'POST'),'Sai thứ tự vào/ra','Không ghi trùng lượt vào khi đang làm');
 // Bổ sung chen vào giữa lịch sử vẫn bị chặn.
 $a->run("INSERT INTO {{attendance_requests}}(user_id,shift,type,time,reason,created_at) VALUES(?,'day_second','in',?,?,?)",[$e['id'],$at($d,'16:00'),'Chen giữa các lượt đã có',now_ms()]);$mid=(int)$a->scalar('SELECT LAST_INSERT_ID()');
 denied(fn()=>workflow_routes($a,"/attendance-requests/$mid/review",['status'=>'approved','note'=>'Thử duyệt chen giữa'],'POST'),'Sai thứ tự','Không chen giữa lịch sử');
 $a->user=$admin;

 // 5. Làm cả ngày: ra Ca 1 rồi Làm tiếp Ca 1, đúng 14:00 vẫn tự chuyển sang Ca 2; ra Ca 1 mà không làm tiếp thì không chuyển.
 if(!$a->closure('2026-10')){
  $d='2026-10-26';$first=$ev($e,'in','day_first',$d,'09:30');$ev($e,'out','day_first',$d,'11:00');$ev($e,'in','day_first',$d,'11:30');$a->run('INSERT INTO {{full_days}}(user_id,work_date,in_event_id,created_at) VALUES(?,?,?,?)',[$e['id'],$d,$first['id'],now_ms()]);
  $d2='2026-10-27';$first2=$ev($e,'in','day_first',$d2,'09:30');$ev($e,'out','day_first',$d2,'12:00');$a->run('INSERT INTO {{full_days}}(user_id,work_date,in_event_id,created_at) VALUES(?,?,?,?)',[$e['id'],$d2,$first2['id'],now_ms()]);
  $a->rollFullDays((int)$e['id']);
  $types=fn(string $day)=>array_map(fn($x)=>$x['shift'].':'.$x['type'],array_values(array_filter($a->events((int)$e['id']),fn($x)=>$x['work_date']===$day)));
  verify($types($d)===['day_first:in','day_first:out','day_first:in','day_first:out','day_second:in'],'Làm tiếp Ca 1 thì 14:00 vẫn tự sang Ca 2: '.json_encode($types($d)));
  verify($types($d2)===['day_first:in','day_first:out'],'Ra Ca 1 không làm tiếp thì không tự sang Ca 2');
 }

 // 6. Khách còn lại sau giờ chấm ra.
 $d='2026-11-03';$book($e,$d,'12:00','Khách trưa','699');$book($e,$d,'18:00','Trương ZALO','');$book($e,$d,'19:00','Will ZALO','1499');
 verify(array_map('booking_line',remaining_bookings($a,(int)$e['id'],$d,'day_second',$at($d,'17:02')))===['18:00 · Trương ZALO','19:00 · Will ZALO · Gói 1499'],'Ra Ca 2 lúc 17:02 còn khách 18:00, 19:00');
 verify(array_map('booking_line',remaining_bookings($a,(int)$e['id'],$d,'day_first',$at($d,'11:00')))===['12:00 · Khách trưa · Gói 699'],'Ra Ca 1 chỉ xét khách trước 14:00');
 verify(remaining_bookings($a,(int)$e['id'],$d,'day_second',$at($d,'19:00'))===[],'Khách đã tới giờ thì không còn là khách còn lại');

 // 7. Telegram báo Admin/Quản lý: về khi còn khách, rồi quay lại làm tiếp; không báo chính người đó, không gửi lặp; lượt chấm hộ không báo.
 foreach([[$e,'ce'],[$m,'cm'],[$admin,'ca']] as [$u,$chat])$a->run('INSERT INTO {{telegram_links}} VALUES(?,?,?)',[$u['id'],$chat,now_ms()]);
 $a->config['telegram_token']='12345:'.str_repeat('x',30);$a->config['app_url']='https://example.test/nhansu';$sent=[];
 $tg=new Telegram($a,function($method,$b)use(&$sent){$sent[]=$b;return ['ok'=>true,'result'=>['message_id'=>1]];});
 $ev($e,'in','day_second',$d,'14:00');$ev($e,'out','day_second',$d,'17:02');attendance_alert_tick($a,$at($d,'17:03'),$tg);
 $ids=array_column($sent,'chat_id');sort($ids);verify($ids===['ca','cm'],'Báo về sớm cho Admin và Quản lý, không báo nhân viên: '.json_encode($ids));
 $text=$sent[0]['text'];foreach(['LT cuong chấm ra Ca 2 lúc 17:02 · Thứ 3 03/11/2026','Còn 2 khách được giao sau giờ này:','• 18:00 · Trương ZALO','• 19:00 · Will ZALO · Gói 1499','không tính hoa hồng cho LT cuong nếu không quay lại làm tiếp'] as $want)verify(str_contains($text,$want),'Tin về sớm có "'.$want.'": '.$text);
 verify(($sent[0]['reply_markup']['inline_keyboard'][0][0]['url']??'')==='https://example.test/nhansu/#bookings','Nút mở Lịch chụp');
 $sent=[];attendance_alert_tick($a,$at($d,'17:10'),$tg);verify(!$sent,'Không gửi lặp');
 $ev($e,'in','day_second',$d,'17:55');attendance_alert_tick($a,$at($d,'17:56'),$tg);
 verify(count($sent)===2&&str_contains($sent[0]['text'],'LT cuong đã chấm ra Ca 2 lúc 17:02, nay chấm vào lại lúc 17:55')&&str_contains($sent[0]['text'],'Ca 2 vẫn tính 1 ca, không cộng thêm công'),'Báo quay lại làm tiếp: '.json_encode(array_column($sent,'text'),JSON_UNESCAPED_UNICODE));
 // Ra sớm rồi vào lại ngay trong 1 phút: chỉ báo làm tiếp, không báo về sớm. Lượt cũ hơn 3 tiếng hoặc chấm hộ không báo.
 $d='2026-11-04';$book($e,$d,'18:00','Khách tối','699');$sent=[];$ev($e,'in','day_second',$d,'14:00');$ev($e,'out','day_second',$d,'17:00');$ev($e,'in','day_second',$d,'17:00');attendance_alert_tick($a,$at($d,'17:01'),$tg);
 verify(count($sent)===2&&!array_filter($sent,fn($x)=>str_contains($x['text'],'Về khi còn khách')),'Đã vào lại thì không báo về sớm');
 $d='2026-11-05';$book($e,$d,'18:00','Khách tối','699');$sent=[];$ev($e,'in','day_second',$d,'14:00');$ev($e,'out','day_second',$d,'15:00');attendance_alert_tick($a,$at($d,'18:30'),$tg);verify(!$sent,'Lượt cũ hơn 3 tiếng không báo');
 $d='2026-11-06';$book($e,$d,'18:00','Khách tối','699');$sent=[];$ev($e,'in','day_second',$d,'14:00','manual');$ev($e,'out','day_second',$d,'17:00','manual');attendance_alert_tick($a,$at($d,'17:01'),$tg);verify(!$sent,'Lượt chấm hộ không báo');
 // Ra đúng hết ca, không còn khách: không báo.
 $d='2026-11-07';$book($e,$d,'15:00','Khách chiều','699');$sent=[];$ev($e,'in','day_second',$d,'14:00');$ev($e,'out','day_second',$d,'19:05');attendance_alert_tick($a,$at($d,'19:06'),$tg);verify(!$sent,'Không còn khách thì không báo');
 throw new RuntimeException('ROLLBACK');
});}catch(RuntimeException $e){if($e->getMessage()!=='ROLLBACK')throw $e;}
echo "PASS $n resume/early-out checks: resume in shift window, 1 credit, presence, manual & supplement re-entry, full-day roll, remaining bookings, Telegram\n";
