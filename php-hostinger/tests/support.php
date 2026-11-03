<?php
// Hỗ trợ: nhân viên gửi yêu cầu, Admin/Quản lý trả lời, duyệt / từ chối / xong; chạy trong giao dịch rồi hoàn tác.
require_once __DIR__.'/../site/nhansu-private/lib/bootstrap.php';require_once __DIR__.'/../site/nhansu-private/lib/people-finance.php';require_once __DIR__.'/../site/nhansu-private/lib/bookings.php';require_once __DIR__.'/../site/nhansu-private/lib/tasks.php';
$a=new App('/var/nhansu-private');$n=0;function verify($v,$message){global $n;if(!$v)throw new RuntimeException('FAIL: '.$message);$n++;}
try{$a->tx(function()use($a){
 $a->run('DELETE FROM {{telegram_links}}');// không nhắn Telegram thật trong lúc test
 $admin=$a->one("SELECT * FROM {{users}} WHERE role='admin' AND archived=0 LIMIT 1");
 $mk=function(string $name,string $role)use($a){$a->run("INSERT INTO {{users}}(name,email,password,role,employee_code,notes) VALUES(?,?,'x',?,?,'')",[$name,strtolower(str_replace(' ','.',$name)).'@support.test',$role,strtoupper(substr(md5($name),0,8))]);return $a->one('SELECT * FROM {{users}} WHERE id=LAST_INSERT_ID()');};
 $staff=$mk('Support Staff','employee');$other=$mk('Support Other','employee');$manager=$mk('Support Manager','manager');
 $as=function(array $u)use($a){$a->user=$u;};$call=fn(string $path,array $b=[],string $m='POST')=>task_routes($a,$path,$b,$m);
 $fail=function(callable $fn,string $msg){try{$fn();verify(false,$msg.' phải bị chặn');}catch(HttpError $e){verify(true,$msg);return $e->getMessage();}return '';};
 $base=(function()use($a,$admin){$a->user=$admin;return support_pending($a);})();
 // Quản lý / Admin không gửi yêu cầu; nhân viên gửi được, gửi lại cùng id không tạo bản sao.
 $as($manager);$fail(fn()=>$call('/tasks/support',['id'=>uuid_v4(),'category'=>'other','title'=>'Test','body'=>'Nội dung test']),'Quản lý không gửi yêu cầu');
 $as($staff);$id=uuid_v4();
 $fail(fn()=>$call('/tasks/support',['id'=>uuid_v4(),'category'=>'xyz','title'=>'Đèn hỏng','body'=>'Đèn phòng 2 không lên']),'Loại yêu cầu sai');
 $res=$call('/tasks/support',['id'=>$id,'category'=>'equipment','title'=>'Đèn flash hỏng','body'=>'Đèn flash phòng 2 không lên từ sáng.','photos'=>[]]);verify($res[0]===201,'Nhân viên gửi yêu cầu');
 verify(($call('/tasks/support',['id'=>$id,'category'=>'equipment','title'=>'Đèn flash hỏng','body'=>'Đèn flash phòng 2 không lên từ sáng.'])[1]['already_saved']??false)===true,'Gửi lại cùng mã không tạo thêm');
 $list=$call('/tasks/support',[],'GET')[1];verify(count(array_filter($list['rows'],fn($r)=>$r['id']===$id))===1&&$list['can_create']&&!$list['can_manage'],'Nhân viên thấy yêu cầu của mình');
 $as($other);verify(!array_filter($call('/tasks/support',[],'GET')[1]['rows'],fn($r)=>$r['id']===$id),'Nhân viên khác không thấy');$fail(fn()=>$call('/tasks/support/'.$id,[],'GET'),'Nhân viên khác không mở được');
 $fail(fn()=>$call("/tasks/support/$id/reply",['body'=>'Chen vào']),'Nhân viên khác không trả lời được');
 // Quản lý thấy, số chờ tăng; trả lời → nhân viên có cập nhật mới.
 $as($manager);verify(support_pending($a)===$base+1,'Số chờ của Quản lý tăng 1');$row=$call('/tasks/support/'.$id,[],'GET')[1]['request'];verify($row['name']==='Support Staff'&&$row['status']==='pending','Quản lý mở được yêu cầu');
 $call("/tasks/support/$id/reply",['body'=>'Anh kiểm tra pin chưa?']);
 $as($staff);verify(support_pending($a)===1,'Nhân viên có 1 cập nhật chưa xem');$d=$call('/tasks/support/'.$id,[],'GET')[1];verify(count($d['messages'])===1&&!$d['messages'][0]['staff']&&support_pending($a)===0,'Mở yêu cầu thì hết cập nhật mới');
 $call("/tasks/support/$id/reply",['body'=>'Đã thay pin vẫn không lên.']);verify(support_pending($a)===0,'Tự trả lời không tính cập nhật mới');
 $fail(fn()=>$call("/tasks/support/$id/status",['status'=>'done','note'=>'','version'=>1]),'Nhân viên không tự duyệt');
 // Duyệt → Đã xong; từ chối cần lý do; sai phiên bản bị chặn; trạng thái đóng không chuyển nữa.
 $as($manager);$v=(int)$call('/tasks/support/'.$id,[],'GET')[1]['request']['version'];
 $fail(fn()=>$call("/tasks/support/$id/status",['status'=>'rejected','note'=>'','version'=>$v]),'Từ chối không lý do');
 $fail(fn()=>$call("/tasks/support/$id/status",['status'=>'approved','note'=>'','version'=>$v+5]),'Sai phiên bản');
 $call("/tasks/support/$id/status",['status'=>'approved','note'=>'Chiều nay mua đèn mới','version'=>$v]);
 $as($staff);verify(support_pending($a)===1,'Duyệt thì nhân viên có cập nhật mới');$fail(fn()=>$call("/tasks/support/$id/withdraw"),'Đã duyệt không rút được');
 $as($manager);verify(support_pending($a)===$base+1,'Đã duyệt vẫn tính chưa xong');$call("/tasks/support/$id/status",['status'=>'done','note'=>'','version'=>$v+1]);verify(support_pending($a)===$base,'Đã xong thì hết chờ');
 $fail(fn()=>$call("/tasks/support/$id/status",['status'=>'approved','note'=>'','version'=>$v+2]),'Đã xong không duyệt lại');
 $d=$call('/tasks/support/'.$id,[],'GET')[1];verify(array_column($d['messages'],'status')===[null,null,'approved','done']&&$d['request']['status']==='done','Lịch sử trao đổi + trạng thái đủ');
 // Rút yêu cầu khi còn Chờ xử lý; đã rút không trả lời được.
 $as($staff);$id2=uuid_v4();$call('/tasks/support',['id'=>$id2,'category'=>'shift','title'=>'Xin đổi ca thứ 7','body'=>'Em xin đổi ca 1 sang ca 2 thứ 7 này.']);
 $call("/tasks/support/$id2/withdraw");verify($a->scalar('SELECT status FROM {{support_requests}} WHERE id=?',[$id2])==='withdrawn','Rút yêu cầu');
 $fail(fn()=>$call("/tasks/support/$id2/reply",['body'=>'Thêm']),'Đã rút không trả lời');
 // Ảnh: JPEG hợp lệ lưu được và đọc lại; ảnh sai định dạng bị chặn.
 $img=imagecreatetruecolor(300,200);ob_start();imagejpeg($img,null,80);$jpg='data:image/jpeg;base64,'.base64_encode(ob_get_clean());
 $id3=uuid_v4();$call('/tasks/support',['id'=>$id3,'category'=>'pay','title'=>'Hỏi lương tháng 10','body'=>'Em thấy thiếu 1 ngày công.','photos'=>[$jpg,$jpg]]);
 verify((int)$a->scalar('SELECT COUNT(*) FROM {{support_photos}} WHERE request_id=?',[$id3])===2&&(int)$a->scalar('SELECT photos FROM {{support_requests}} WHERE id=?',[$id3])===2,'Lưu 2 ảnh');
 $fail(fn()=>$call('/tasks/support',['id'=>uuid_v4(),'category'=>'pay','title'=>'Ảnh lỗi','body'=>'Ảnh không hợp lệ','photos'=>['data:image/png;base64,AAAA']]),'Ảnh sai định dạng');
 $fail(fn()=>$call('/tasks/support',['id'=>uuid_v4(),'category'=>'pay','title'=>'Nhiều ảnh','body'=>'Quá nhiều ảnh','photos'=>[$jpg,$jpg,$jpg,$jpg]]),'Quá 3 ảnh');
 // Số trên nút Việc: /tasks/summary trả thêm support.
 $as($manager);verify(($call('/tasks/summary',[],'GET')[1]['support']??null)===support_pending($a),'tasks/summary có số Hỗ trợ');
 throw new RuntimeException('ROLLBACK');
});}catch(RuntimeException $e){if($e->getMessage()!=='ROLLBACK')throw $e;}
echo "PASS $n support checks: create, privacy, reply, approve/reject/done, withdraw, photos, counts\n";
