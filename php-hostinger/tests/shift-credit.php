<?php
// Bổ sung công (chủ dự án yêu cầu 06/11/2026): khách cuối 13:30 chụp lấn sang Ca 2 nên Ca 2 bị coi là ca không khách; Admin/Quản lý tự bổ sung cho từng nhân viên.
// Chạy trong giao dịch rồi hoàn tác.
foreach(['bootstrap','telegram','bookings','people-finance','tasks','availability'] as $f)require_once __DIR__.'/../site/nhansu-private/lib/'.$f.'.php';
$a=new App('/var/nhansu-private');$n=0;function verify($v,$message){global $n;if(!$v)throw new RuntimeException('FAIL: '.$message);$n++;}
try{$a->tx(function()use($a){
 $day='2026-11-27';$month='2026-11';$key=$day.':day_second';$a->run("UPDATE {{shoot_bookings}} SET status='cancelled' WHERE shoot_day=?",[$day]);
 $a->user=$a->one("SELECT * FROM {{users}} WHERE role='admin' AND archived=0 LIMIT 1");
 $make=function(string $name)use($a){$a->run("INSERT INTO {{users}}(name,email,password,role,employee_code,notes) VALUES(?,?,'x','employee',?,'')",[$name,strtolower(str_replace(' ','',$name)).'@shift-credit.test','ZZ'.substr(md5($name),0,6)]);$id=(int)$a->one('SELECT id FROM {{users}} WHERE employee_code=?',['ZZ'.substr(md5($name),0,6)])['id'];$a->run('INSERT INTO {{salaries}}(user_id,monthly_salary) VALUES(?,3000000) ON DUPLICATE KEY UPDATE monthly_salary=3000000',[$id]);return $id;};
 $one=$make('ZZ Bo Sung Mot');$two=$make('ZZ Bo Sung Hai');
 $ev=function(int $id,string $type,int $h,string $shift)use($a,$day){$a->run("INSERT INTO {{events}}(user_id,type,time,shift,work_date,source,reason) VALUES(?,?,?,?,?,'self','')",[$id,$type,day_time($day)+$h*3600000,$shift,$day]);};
 foreach([$one,$two] as $id){$ev($id,'in',9,'day_first');$ev($id,'out',14,'day_first');$ev($id,'in',14,'day_second');$ev($id,'out',19,'day_second');}
 // Khách cuối 13:30: Ca 1 có khách, Ca 2 không có lịch nào bắt đầu trong ca.
 $a->run("INSERT INTO {{shoot_bookings}}(id,source_sheet,source_tab,source_row,shoot_day,minutes,package_label,customer_name,customer_phone,main_alias,support_alias,note,status,created_at,updated_at) VALUES(?,'test','test',1,?,810,'699','Khach A','','','','','active',?,?)",[uuid_v4(),$day,now_ms(),now_ms()]);
 $row=fn($id)=>array_values(array_filter($a->monthly($month)['rows'],fn($r)=>(int)$r['id']===$id))[0];
 $post=fn(array $b)=>payroll_bonus_routes($a,'/payroll/shift-credit',$b,'POST');
 $r=$row($one);verify($r['workdays']===0.5&&$r['unbookedShifts']===1&&$r['unbookedDays']===[$key]&&$r['shiftCredits']===[],'Trước khi bổ sung: 0,5 công, 1 ca không khách');$before=$r['salary'];$bonus=$r['bonus_items']['no_customer'];verify($bonus===50000,'Khoản ca không khách 1/2 ngày');
 verify($post(['user_id'=>$one,'day'=>$day,'shift'=>'day_second','on'=>true,'note'=>'Khách 13:30 lấn sang Ca 2'])[1]['ok']===true,'Bổ sung công');
 $r=$row($one);verify($r['workdays']==1&&$r['unbookedShifts']===0&&$r['bonus_items']['no_customer']===0&&$r['completedShifts']===2,'Sau khi bổ sung: 1 công, hết ca không khách: '.json_encode([$r['workdays'],$r['unbookedShifts']]));
 verify($r['salary']===$before,'Tổng lương không đổi: ½ ngày chuyển từ thưởng sang công');
 verify($r['shiftCredits'][0]['key']===$key&&$r['shiftCredits'][0]['note']==='Khách 13:30 lấn sang Ca 2'&&$r['shiftCredits'][0]['by']===$a->user['name'],'Lưu ca, ghi chú, người bổ sung');
 $r2=$row($two);verify($r2['workdays']===0.5&&$r2['unbookedShifts']===1,'Chỉ tính cho đúng nhân viên được bổ sung');
 foreach([[$one,'day_second',true,409,'Bổ sung lần 2 bị chặn'],[$two,'day_first',true,409,'Ca có khách không cần bổ sung'],[$two,'day_second',false,409,'Bỏ ca chưa bổ sung bị chặn']] as [$id,$shift,$on,$status,$message]){try{$post(['user_id'=>$id,'day'=>$day,'shift'=>$shift,'on'=>$on]);verify(false,$message);}catch(HttpError $x){verify($x->status===$status,$message);}}
 // Nhân viên chỉ xem: không gọi được, thấy ca đã bổ sung trên bảng công.
 $admin=$a->user;$a->user=$a->one('SELECT * FROM {{users}} WHERE id=?',[$one]);
 try{$post(['user_id'=>$one,'day'=>$day,'shift'=>'day_second','on'=>false]);verify(false,'Nhân viên phải bị chặn');}catch(HttpError $x){verify($x->status===403,'Nhân viên không tự bổ sung công');}
 $mine=$a->monthly($month)['rows'];verify(count($mine)===1&&$mine[0]['shiftCredits'][0]['key']===$key&&!isset($mine[0]['monthly_salary']),'Nhân viên thấy ca đã bổ sung, không thấy lương');$a->user=$admin;
 verify($post(['user_id'=>$one,'day'=>$day,'shift'=>'day_second','on'=>false])[1]['ok']===true,'Bỏ bổ sung công');$r=$row($one);verify($r['workdays']===0.5&&$r['unbookedShifts']===1&&$r['salary']===$before,'Bỏ thì về như cũ');
 // Tháng đã chốt: khóa.
 $a->run('INSERT INTO {{payroll_closures}}(month,rows_json,commissions_json,closed_by,closed_at) VALUES(?,?,?,?,?)',[$month,'[]','[]',$a->user['id'],now_ms()]);
 try{$post(['user_id'=>$two,'day'=>$day,'shift'=>'day_second','on'=>true]);verify(false,'Tháng chốt phải khóa');}catch(HttpError $x){verify($x->status===409,'Tháng đã chốt không bổ sung được');}
 throw new RuntimeException('ROLLBACK');
});}catch(RuntimeException $e){if($e->getMessage()!=='ROLLBACK')throw $e;}
echo "PASS $n shift credit checks\n";
