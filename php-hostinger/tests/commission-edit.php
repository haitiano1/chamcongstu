<?php
// Sửa / xóa hoa hồng nhập tay (chủ dự án yêu cầu 12/11/2026); chạy trong giao dịch rồi hoàn tác.
require_once __DIR__.'/../site/nhansu-private/lib/bootstrap.php';require_once __DIR__.'/../site/nhansu-private/lib/api.php';
function cid(){$h=bin2hex(random_bytes(16));return substr($h,0,8)."-".substr($h,8,4)."-".substr($h,12,4)."-".substr($h,16,4)."-".substr($h,20);}
$a=new App('/var/nhansu-private');$n=0;function verify($v,$message){global $n;if(!$v)throw new RuntimeException('FAIL: '.$message);$n++;}
try{$a->tx(function()use($a){
 $admin=$a->one("SELECT * FROM {{users}} WHERE role='admin' AND archived=0 LIMIT 1");$a->user=$admin;
 $mk=function(string $name)use($a){$a->run("INSERT INTO {{users}}(name,email,password,role,employee_code,notes) VALUES(?,?,'x','employee',?,'')",[$name,strtolower(str_replace(' ','.',$name)).'@commission.test',strtoupper(substr(md5($name),0,8))]);return $a->one('SELECT * FROM {{users}} WHERE id=LAST_INSERT_ID()');};
 $s1=$mk('Comm One');$s2=$mk('Comm Two');$call=fn(string $p,array $b=[],string $m='POST')=>management_routes($a,$p,$b,$m);
 $fail=function(callable $fn,string $msg){try{$fn();verify(false,$msg.' phải bị chặn');}catch(HttpError $e){verify(true,$msg);}};
 $t=day_time(local_day(now_ms()))+10*3600000; // 10:00 hôm nay
 if($t>now_ms())$t-=86400000;
 if($a->closure(substr(local_day($t),0,7))){echo "skip: tháng đã chốt\n";return;}
 $id=cid();$call('/commissions',['id'=>$id,'user_id'=>(int)$s1['id'],'customers'=>3,'served_at'=>$t]);
 verify((int)$a->scalar('SELECT customers FROM {{commissions}} WHERE id=?',[$id])===3,'tạo');
 $r=$call('/commissions/'.$id,['user_id'=>(int)$s2['id'],'customers'=>2,'served_at'=>$t+3600000]);verify($r[1]['rate']===15000,'sửa giữ mức thường');
 $row=$a->one('SELECT * FROM {{commissions}} WHERE id=?',[$id]);verify((int)$row['user_id']===(int)$s2['id']&&(int)$row['customers']===2&&(int)$row['served_at']===$t+3600000,'sửa đổi người, số khách, giờ');
 verify((int)$a->scalar("SELECT COUNT(*) FROM {{employee_audit}} WHERE action LIKE 'commission_%' AND user_id IN (?,?)",[$s1['id'],$s2['id']])===2,'ghi audit sửa + chuyển người');
 $fail(fn()=>$call('/commissions/'.$id,['user_id'=>(int)$s2['id'],'customers'=>0,'served_at'=>$t]),'số khách 0');
 $fail(fn()=>$call('/commissions/'.$id,['user_id'=>(int)$s2['id'],'customers'=>1,'served_at'=>day_time(local_day($t))+20*3600000]),'giờ OT không có lượt OT');
 $fail(fn()=>$call('/commissions/'.$id,['user_id'=>(int)$admin['id'],'customers'=>1,'served_at'=>$t]),'gán cho Admin');
 $fail(fn()=>$call('/commissions/'.cid().'/delete'),'xóa mã không có');
 $call('/commissions/'.$id.'/delete');verify(!$a->one('SELECT id FROM {{commissions}} WHERE id=?',[$id]),'xóa');
 $emp=$a->user;$a->user=$s1;$fail(fn()=>management_routes($a,'/commissions/'.$id.'/delete',[],'POST'),'nhân viên không xóa được');$a->user=$emp;
 throw new RuntimeException('ROLLBACK');});}catch(RuntimeException $e){if($e->getMessage()!=='ROLLBACK'){echo $e->getMessage(),"\n";exit(1);}}
echo "OK $n kiểm tra\n";
