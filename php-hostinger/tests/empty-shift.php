<?php
// Ca không có khách (chủ dự án yêu cầu 17/11/2026): chấm vào phải chọn Xin off / Ở lại tối thiểu 2 tiếng, chấm ra chưa đủ 2 tiếng thì chặn
// (ngày thường được Về luôn, không tính ca), đủ thì bắt ghi công việc đã làm. Thứ 7, Chủ nhật bắt buộc đi làm.
foreach(['bootstrap','telegram','bookings','people-finance','tasks','availability','workflows','api'] as $f)require_once __DIR__.'/../site/nhansu-private/lib/'.$f.'.php';
$a=new App('/var/nhansu-private');$n=0;function verify($v,$message){global $n;if(!$v)throw new RuntimeException('FAIL: '.$message);$n++;}
try{$a->tx(function()use($a){
 $thu='2026-11-19';$fri='2026-11-20';$sat='2026-11-21';$month='2026-11';
 $a->run("UPDATE {{shoot_bookings}} SET status='cancelled' WHERE shoot_day IN (?,?,?)",[$thu,$fri,$sat]);
 $a->run("INSERT INTO {{users}}(name,email,password,role,employee_code,notes) VALUES('ES Nhan Vien','es@empty.test','x','employee','ES-1','')");$emp=$a->one('SELECT * FROM {{users}} WHERE id=LAST_INSERT_ID()');$id=(int)$emp['id'];
 $a->run('INSERT INTO {{salaries}}(user_id,monthly_salary) VALUES(?,3000000) ON DUPLICATE KEY UPDATE monthly_salary=3000000',[$id]);
 $at=fn($d,$hm)=>day_time($d)+((int)substr($hm,0,2)*60+(int)substr($hm,3,2))*60000;
 $ev=function($d,$type,$hm,$shift,$source='self')use($a,$id,$at){$a->run("INSERT INTO {{events}}(user_id,type,time,shift,work_date,source,reason) VALUES(?,?,?,?,?,?,'')",[$id,$type,$at($d,$hm),$shift,$d,$source]);};
 $a->user=$emp;$g=fn($b,$shift,$d,$hm)=>empty_shift_guard($a,$b+['shift'=>$shift],$shift,$at($d,$hm));
 // 1. Thứ 5, Ca 1 không khách: chấm vào phải chọn; ngày thường có Xin off.
 $r=$g(['type'=>'in'],'day_first',$thu,'09:10');verify($r&&$r[0]===409&&$r[1]['empty_shift']['weekend']===false,'Chấm vào ca không khách: hỏi Xin off / Ở lại');
 verify($g(['type'=>'in','empty_choice'=>'stay'],'day_first',$thu,'09:10')===null&&empty_shift_row($a,$id,$thu,'day_first')['choice']==='stay','Ở lại: cho chấm, ghi lựa chọn');
 $ev($thu,'in','09:10','day_first');
 verify($g(['type'=>'in','resume'=>true],'day_first',$thu,'12:00')===null,'Làm tiếp / đã có lượt trong ca: không hỏi lại');
 // 2. Chấm ra 10:00 (chưa đủ 2 tiếng): chặn, ngày thường được Về luôn.
 $r=$g(['type'=>'out'],'day_first',$thu,'10:00');verify($r&&$r[1]['empty_checkout']['until']===$at($thu,'11:10')&&$r[1]['empty_checkout']['can_leave']===true,'Chưa đủ 2 tiếng: chặn, được ra từ 11:10');
 verify($g(['type'=>'out','empty_leave'=>true],'day_first',$thu,'10:00')===null&&empty_shift_row($a,$id,$thu,'day_first')['choice']==='early','Về luôn: cho chấm ra, ca ghi không tính');
 $ev($thu,'out','10:00','day_first');
 // 3. Ca 2 không khách: đủ 2 tiếng thì bắt ghi công việc đã làm (Ca 1 về trước thì không hỏi).
 verify($g(['type'=>'in','empty_choice'=>'stay'],'day_second',$thu,'14:05')===null,'Vào Ca 2 không khách');$ev($thu,'in','14:05','day_second');
 $r=$g(['type'=>'out'],'day_second',$thu,'16:30');verify($r&&$r[1]['empty_note']['shifts']===['day_second'],'Đủ 2 tiếng: bắt ghi việc, chỉ Ca 2 (Ca 1 về trước không cần)');
 $r=$g(['type'=>'out','work_note'=>'dọn'],'day_second',$thu,'16:30');verify($r&&isset($r[1]['empty_note']),'Ghi quá ngắn: chặn');
 verify($g(['type'=>'out','work_note'=>'Dọn kho đạo cụ, lau gương'],'day_second',$thu,'16:30')===null&&empty_shift_row($a,$id,$thu,'day_second')['note']==='Dọn kho đạo cụ, lau gương','Ghi đủ: cho chấm ra, lưu việc');
 $ev($thu,'out','16:30','day_second');
 // 4. Thứ 6 làm cả ngày, cả 2 ca không khách: 14:00 tự chuyển; chấm ra Ca 2 đủ 2 tiếng từ 14:00, ghi việc cho cả Ca 1 và Ca 2.
 $ev($fri,'in','09:05','day_first');$ev($fri,'out','14:00','day_first','auto');$ev($fri,'in','14:00','day_second','auto');
 $r=$g(['type'=>'out'],'day_second',$fri,'15:00');verify($r&&$r[1]['empty_checkout']['until']===$at($fri,'16:00'),'Ca 2 tự vào 14:00: được ra từ 16:00');
 $r=$g(['type'=>'out'],'day_second',$fri,'16:30');verify($r&&$r[1]['empty_note']['shifts']===['day_first','day_second'],'Ghi việc cho cả Ca 1 (tự chấm ra) và Ca 2');
 verify($g(['type'=>'out','work_note'=>'Chỉnh 40 ảnh khách, trực fanpage'],'day_second',$fri,'16:30')===null&&empty_shift_row($a,$id,$fri,'day_first')['note']!==null,'Lưu việc cho cả 2 ca');$ev($fri,'out','16:30','day_second');
 // 5. Thứ 7: không Xin off, không Về trước.
 $r=$g(['type'=>'in'],'day_first',$sat,'09:00');verify($r[1]['empty_shift']['weekend']===true,'Thứ 7: chỉ Ở lại');
 $ev($sat,'in','09:00','day_first');$r=$g(['type'=>'out','empty_leave'=>true],'day_first',$sat,'10:00');verify($r&&$r[1]['empty_checkout']['can_leave']===false,'Thứ 7: chưa đủ 2 tiếng thì không cho về');
 // 6. Ca có khách (lịch thêm sau): không hỏi gì, ca thường.
 $a->run("INSERT INTO {{shoot_bookings}}(id,source_sheet,source_tab,source_row,shoot_day,minutes,package_label,customer_name,customer_phone,main_alias,support_alias,note,status,created_at,updated_at) VALUES(?,'test','test',1,?,?,'699','Khach','','','','','active',?,?)",[uuid_v4(),$sat,600,now_ms(),now_ms()]);
 verify($g(['type'=>'out'],'day_first',$sat,'09:30')===null,'Có khách thêm vào ca: chấm ra bình thường, không bắt 2 tiếng / ghi việc');
 $ev($sat,'out','09:30','day_first');
 // 7. Admin/Quản lý không áp.
 $admin=$a->one("SELECT * FROM {{users}} WHERE role='admin' AND archived=0 LIMIT 1");$a->user=$admin;verify(empty_shift_guard($a,['type'=>'in','shift'=>'day_first'],'day_first',$at($thu,'09:10'))===null,'Admin không áp');$a->user=$emp;
 // 8. Phiếu lương: ca về trước 2 tiếng không tính; ca xin off và công việc hiện trên phiếu.
 empty_shift_save($a,$id,'2026-11-18','day_first',['choice'=>'off'],true);
 $rows=array_column($a->summaries($month),null,'id');$r=$rows[$id];
 verify($r['emptyEarly']===[$thu.':day_first'],'Danh sách về trước: '.json_encode($r['emptyEarly']));
 verify(!in_array($thu.':day_first',$r['unbookedDays'],true)&&in_array($thu.':day_second',$r['unbookedDays'],true)&&in_array($fri.':day_first',$r['unbookedDays'],true),'Ca về trước không vào Ca làm không có khách: '.json_encode($r['unbookedDays']));
 verify($r['emptyOff']===['2026-11-18:day_first']&&count((array)$r['emptyNotes'])===3,'Phiếu lương có ca xin off và 3 ghi chép công việc');
 verify(isset(visible_summary(['role'=>'employee'],$r)['emptyNotes']),'Nhân viên thấy ghi chép của mình');
 // 9. Telegram: báo xin off, về trước, công việc đã làm (không báo lượt ở lại chưa ghi việc).
 $sent=[];$tg=new Telegram($a,function($method,$p)use(&$sent){$sent[]=$p['text'];return ['ok'=>true,'result'=>[]];});
 $a->run("DELETE FROM {{telegram_links}}");$a->run("INSERT INTO {{telegram_links}}(user_id,chat_id,linked_at) VALUES(?,?,?)",[$admin['id'],'999',now_ms()]);
 $a->run('UPDATE {{empty_shifts}} SET updated_at=? WHERE user_id=?',[now_ms(),$id]);
 $a->config['telegram_token']='12345:'.str_repeat('x',30);empty_shift_telegram_tick($a,$tg);
 verify(count(array_filter($sent,fn($t)=>str_contains($t,'Xin off')))===1&&count(array_filter($sent,fn($t)=>str_contains($t,'Về trước')))===1&&count(array_filter($sent,fn($t)=>str_contains($t,'Công việc đã làm')))===3,'Telegram: '.count($sent).' tin');
 $before=count($sent);empty_shift_telegram_tick($a,$tg);verify(count($sent)===$before,'Không gửi lặp');
 throw new RuntimeException('ROLLBACK');});}catch(RuntimeException $e){if($e->getMessage()!=='ROLLBACK'){echo $e->getMessage(),"\n";exit(1);}}
echo "OK $n kiểm tra\n";
