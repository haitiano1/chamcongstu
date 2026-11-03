<?php
// Lịch Team: chụp xong thợ nhập số khách theo gói; tính vào Số khách đã chụp (thống kê Lịch chụp theo tháng) và phiếu lương.
// Chạy trong giao dịch rồi hoàn tác.
require_once __DIR__.'/../site/nhansu-private/lib/bootstrap.php';require_once __DIR__.'/../site/nhansu-private/lib/bookings.php';require_once __DIR__.'/../site/nhansu-private/lib/people-finance.php';require_once __DIR__.'/../site/nhansu-private/lib/tasks.php';
$a=new App('/var/nhansu-private');$n=0;function verify($v,$message){global $n;if(!$v)throw new RuntimeException('FAIL: '.$message);$n++;}
try{$a->tx(function()use($a){
 $admin=$a->one("SELECT * FROM {{users}} WHERE role='admin' AND archived=0 LIMIT 1");$a->user=$admin;
 $a->run("INSERT INTO {{users}}(name,email,password,role,employee_code,notes) VALUES('Team Test','team.test@local.test','x','employee','TEAM-TEST','')");$id=(int)$a->scalar('SELECT LAST_INSERT_ID()');
 $a->run("INSERT INTO {{users}}(name,email,password,role,employee_code,notes) VALUES('Team Other','team.other@local.test','x','employee','TEAM-OTHER','')");$other=(int)$a->scalar('SELECT LAST_INSERT_ID()');
 $alias='TEAM_TEST';$a->run('INSERT INTO {{booking_aliases}} VALUES(?,?,?,?,?)',[alias_key($alias),$alias,$id,$admin['id'],now_ms()]);
 $a->run('INSERT INTO {{booking_rates}} VALUES(?,?,?,?)',[$id,10000,$admin['id'],now_ms()]);
 $at=fn(string $day,string $hm)=>day_time($day)+((int)substr($hm,0,2)*60+(int)substr($hm,3,2))*60000;
 $ev=function(string $type,string $day,string $hm,string $shift)use($a,$id,$at){$a->run("INSERT INTO {{events}}(user_id,type,time,shift,work_date,source,reason) VALUES(?,?,?,?,?,'self','')",[$id,$type,$at($day,$hm),$shift,$day]);};
 $ev('in','2025-03-05','14:00','day_second');$ev('out','2025-03-05','19:00','day_second');
 // Tháng 03/2025 (không có lịch thật): 1 khách 699, nhóm Team 15:00 có 2 dòng trên Sheet, 1 khách Couple 999 lúc 18:00.
 $rows=[];foreach([['14:30','699'],['15:00','Team 349'],['15:00','Team 349'],['18:00','Couple 999']] as [$t,$pkg])$rows[]=['id'=>uuid_v4(),'day'=>'2025-03-05','time'=>$t,'status'=>'active','main_alias'=>$alias,'support_alias'=>'','package_label'=>$pkg,'note'=>'','customer_name'=>'Team fixture'];
 import_bookings($a,['tab'=>'team-test','rows'=>$rows],'team-test',null);
 $ids=array_column($a->all("SELECT id FROM {{shoot_bookings}} WHERE source_tab='team-test' ORDER BY minutes,id"),'id');$team=$ids[1];
 $now=$at('2025-03-31','23:00');$r=booking_month_commissions($a,'2025-03',$now)[$id];
 verify($r['customers']===4&&$r['units']===5,'Chưa nhập: mỗi dòng Team 1 khách → 4 khách, 5 suất; được '.json_encode($r));
 $_GET=['month'=>'2025-03'];$s=booking_routes($a,'/bookings/summary',[],'GET')[1];
 verify($s['active']===4&&$s['shot']===4&&$s['upcoming']===0&&$s['today']===0&&$s['month']==='2025-03','Thống kê tháng 03/2025: 4 lịch, 4 khách đã chụp; được '.json_encode($s));
 // Nhập số khách Team: 6 × Team 349, 3 × 699, 1 × Couple 999 → 10 khách, 6+3+2 = 11 suất; dòng Team còn lại cùng giờ không đếm thêm.
 $res=booking_routes($a,'/bookings/team',['id'=>$team,'counts'=>['349'=>6,'699'=>3,'999'=>1,'1499'=>0]],'POST')[1];
 verify($res['changed']&&$res['customers']===10,'Lưu 10 khách Team');
 $r=booking_month_commissions($a,'2025-03',$now)[$id];
 verify($r['customers']===12&&$r['units']===14,'Phiếu lương: 1 + 10 (Team) + 1 (Couple) = 12 khách, 1 + 11 + 2 = 14 suất; được '.json_encode($r));
 $s=booking_routes($a,'/bookings/summary',[],'GET')[1];verify($s['shot']===12&&$s['active']===4,'Thống kê: Số khách đã chụp 12, Tổng lịch tháng vẫn 4 lịch; được '.json_encode($s));
 verify(booking_routes($a,'/bookings/team',['id'=>$team,'counts'=>['349'=>6,'699'=>3,'999'=>1]],'POST')[1]['changed']===false,'Lưu lại không đổi');
 $_GET=['month'=>'2025-03'];$list=booking_routes($a,'/bookings',[],'GET')[1]['rows'];$row=array_values(array_filter($list,fn($x)=>$x['id']===$team))[0];
 verify((array)$row['team_counts']===[349=>6,699=>3,999=>1]&&$row['team_edit']===true,'Danh sách lịch trả số khách Team, Admin sửa được');
 verify(!isset(array_values(array_filter($list,fn($x)=>$x['id']===$ids[0]))[0]['team_counts']),'Lịch thường không có ô số khách Team');
 // Thợ của lịch được nhập; nhân viên khác không; lịch thường, gói lạ, số âm bị chặn.
 $a->user=$a->one('SELECT * FROM {{users}} WHERE id=?',[$id]);
 verify(booking_routes($a,'/bookings/team',['id'=>$team,'counts'=>['349'=>8]],'POST')[1]['customers']===8,'Thợ của lịch nhập được');
 $a->user=$a->one('SELECT * FROM {{users}} WHERE id=?',[$other]);
 try{booking_routes($a,'/bookings/team',['id'=>$team,'counts'=>['349'=>9]],'POST');verify(false,'Nhân viên khác phải bị chặn');}catch(HttpError $e){verify($e->getCode()===403||str_contains($e->getMessage(),'thợ'),'Nhân viên không phải thợ của lịch bị chặn');}
 $a->user=$admin;
 foreach([[['id'=>$ids[0],'counts'=>['699'=>2]],'Chỉ lịch Team'],[['id'=>$team,'counts'=>['500'=>2]],'Gói không hợp lệ'],[['id'=>$team,'counts'=>['349'=>-1]],'Số khách']] as [$body,$msg]){try{booking_routes($a,'/bookings/team',$body,'POST');verify(false,$msg.' phải bị chặn');}catch(HttpError $e){verify(str_contains($e->getMessage(),$msg),$msg.': '.$e->getMessage());}}
 // Lịch Team chưa tới giờ chụp không nhập được.
 $future=local_day(now_ms()+3*86400000);import_bookings($a,['tab'=>'team-test-2','rows'=>[['id'=>uuid_v4(),'day'=>$future,'time'=>'15:00','status'=>'active','main_alias'=>$alias,'support_alias'=>'','package_label'=>'Team 349','note'=>'','customer_name'=>'Team future']]],'team-test-2',null);
 $fid=$a->scalar("SELECT id FROM {{shoot_bookings}} WHERE source_tab='team-test-2'");
 try{booking_routes($a,'/bookings/team',['id'=>$fid,'counts'=>['349'=>3]],'POST');verify(false,'Chưa chụp phải bị chặn');}catch(HttpError $e){verify(str_contains($e->getMessage(),'Chụp xong'),'Chưa tới giờ chụp không nhập');}
 // Xóa hết số khách thì quay lại đếm mỗi dòng Team 1 khách.
 booking_routes($a,'/bookings/team',['id'=>$team,'counts'=>['349'=>0]],'POST');$r=booking_month_commissions($a,'2025-03',$now)[$id];verify($r['customers']===4,'Xóa số khách Team thì đếm lại theo dòng');
 throw new RuntimeException('ROLLBACK');
});}catch(RuntimeException $e){if($e->getMessage()!=='ROLLBACK')throw $e;}
echo "PASS $n booking team checks: Team headcount by package, monthly stats, payroll count\n";
