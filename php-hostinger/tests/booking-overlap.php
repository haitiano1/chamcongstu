<?php
// Vướng lịch (05/11/2026): thêm/sửa lịch vào giờ đã có khách không bị chặn hẳn. Lần đầu trả 409 kèm các lịch vướng, gửi lại allow_overlap thì đặt được bình thường.
// Chạy trong giao dịch rồi hoàn tác.
foreach(['bootstrap','telegram','bookings','people-finance','tasks'] as $f)require_once __DIR__.'/../site/nhansu-private/lib/'.$f.'.php';
$a=new App('/var/nhansu-private');$n=0;function verify($v,$message){global $n;if(!$v)throw new RuntimeException('FAIL: '.$message);$n++;}
try{$a->tx(function()use($a){
 $a->user=$a->one("SELECT * FROM {{users}} WHERE role='admin' AND archived=0 ORDER BY id LIMIT 1");
 $day=date('Y-m-d',strtotime(local_day(now_ms()).' +20 days'));$a->run("DELETE FROM {{shoot_bookings}} WHERE shoot_day=?",[$day]);
 $form=['day'=>$day,'package'=>null,'customer_phone'=>'','makeup'=>'','special'=>'','note'=>''];
 $create=fn(array $x)=>booking_routes($a,'/bookings/create',$x+$form,'POST');
 $queue=fn(string $id,string $action)=>(int)$a->scalar("SELECT COUNT(*) FROM {{booking_sheet_queue}} WHERE booking_id=? AND action=? AND status='pending'",[$id,$action]);
 $count=fn()=>(int)$a->scalar("SELECT COUNT(*) FROM {{shoot_bookings}} WHERE shoot_day=? AND status='active'",[$day]);
 // Giờ trống: đặt ngay, không hỏi.
 [$s,$r]=$create(['time'=>'10:00','customer_name'=>'Chị Lan']);verify($s===200&&isset($r['id']),'Giờ trống đặt được ngay');$first=$r['id'];
 [$s,$r]=$create(['time'=>'11:00','customer_name'=>'Anh Nam']);verify($s===200,'Khách sát giờ (hết 11:00, vào 11:00) không vướng');$second=$r['id'];
 // Giờ vướng: trả 409 + đúng các lịch vướng, chưa ghi gì.
 [$s,$r]=$create(['time'=>'10:30','customer_name'=>'Chị Mai']);
 verify($s===409&&array_column($r['conflicts'],'id')===[$first,$second],'Khung 10:30–11:30 vướng cả 2 lịch: '.json_encode($r,JSON_UNESCAPED_UNICODE));
 verify($r['conflicts'][0]['start']===600&&$r['conflicts'][0]['end']===660&&$r['conflicts'][0]['customer_name']==='Chị Lan','Có giờ, tên khách của lịch vướng');
 verify(str_contains($r['error'],'Chọn giờ khác'),'Bản web cũ trên điện thoại vẫn đọc được lỗi');verify($count()===2,'Chưa bấm Vẫn đặt lịch thì chưa ghi lịch');
 // Chỉ true mới được qua (bản cũ không gửi ô này).
 verify($create(['time'=>'10:30','customer_name'=>'Chị Mai','allow_overlap'=>'1'])[0]===409,'allow_overlap phải đúng true');
 // Vẫn đặt lịch: lịch vào web và hàng chờ ghi Sheet như bình thường.
 [$s,$r]=$create(['time'=>'10:30','customer_name'=>'Chị Mai','allow_overlap'=>true]);$third=$r['id']??'';verify($s===200&&$third!==''&&$count()===3,'Vẫn đặt lịch thì đặt được');
 verify($queue($third,'create')===1,'Lịch vướng vẫn ghi xuống Sheet');verify((int)$a->scalar('SELECT COUNT(*) FROM {{booking_staff}} WHERE booking_id=?',[$third])===(int)$a->scalar('SELECT COUNT(*) FROM {{booking_staff}} WHERE booking_id=?',[$first]),'Lịch vướng có thợ như lịch thường');
 // Trùng hẳn giờ bắt đầu cũng đặt được.
 [$s,$r]=$create(['time'=>'10:00','customer_name'=>'Chị Hoa']);verify($s===409,'Trùng hẳn giờ bắt đầu vẫn hỏi');
 verify($create(['time'=>'10:00','customer_name'=>'Chị Hoa','allow_overlap'=>true])[0]===200&&$count()===4,'Trùng hẳn giờ bắt đầu vẫn đặt được');
 // Ngoài giờ mở cửa vẫn chặn dù đã xác nhận vướng lịch.
 try{$create(['time'=>'21:30','customer_name'=>'Khuya','allow_overlap'=>true]);verify(false,'21:30–22:30 phải bị chặn');}catch(HttpError $e){verify(str_contains($e->getMessage(),'07:00–22:00'),'Giới hạn 07:00–22:00 giữ nguyên');}
 // Sửa lịch: đổi giờ sang chỗ vướng thì hỏi; không tính chính lịch đang sửa.
 $row=fn(string $id)=>$a->one('SELECT * FROM {{shoot_bookings}} WHERE id=?',[$id]);
 $update=function(string $id,array $x)use($a,$row){$e=booking_form($row($id));return booking_routes($a,'/bookings/update',$x+['id'=>$id,'day'=>$e['day'],'time'=>$e['time'],'package'=>$e['package'],'customer_name'=>$e['customer_name'],'customer_phone'=>$e['customer_phone'],'makeup'=>'','special'=>'','note'=>$e['note']],'POST');};
 [$s,$r]=$create(['time'=>'15:00','customer_name'=>'Chị Thu']);$free=$r['id'];
 verify($update($free,['time'=>'15:30'])[0]===200,'Dời giờ không vướng ai (không tính chính nó)');
 [$s,$r]=$update($free,['time'=>'11:30']);verify($s===409&&array_column($r['conflicts'],'id')===[$second],'Dời sang giờ vướng thì hỏi');verify($row($free)['minutes']==='930'||(int)$row($free)['minutes']===930,'Chưa xác nhận thì giờ cũ giữ nguyên');
 [$s,$r]=$update($free,['time'=>'11:30','allow_overlap'=>true]);verify($s===200&&(int)$row($free)['minutes']===690,'Vẫn đặt lịch thì đổi được giờ');
 // Lịch đang vướng: sửa thứ không đổi khung giờ thì không hỏi lại.
 [$s,$r]=$update($third,['customer_name'=>'Chị Mai cọc 200k']);verify($s===200&&$r['changed']==['customer_name'],'Sửa tên lịch đang vướng không hỏi lại');
 [$s,$r]=$update($third,['package'=>999]);verify($s===409,'Đổi gói làm dài khung giờ thì hỏi lại');
 throw new RuntimeException('rollback');
});}catch(RuntimeException $e){if($e->getMessage()!=='rollback')throw $e;}
echo "OK booking-overlap: $n kiểm tra\n";
