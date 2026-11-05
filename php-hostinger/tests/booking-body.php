<?php
// Body 999k (05/11/2026): cùng giá Couple 999 nhưng là gói riêng; 2 tiếng, ×2 suất như Couple. Chạy trong giao dịch rồi hoàn tác.
foreach(['bootstrap','telegram','bookings','people-finance','tasks'] as $f)require_once __DIR__.'/../site/nhansu-private/lib/'.$f.'.php';
$a=new App('/var/nhansu-private');$n=0;function verify($v,$message){global $n;if(!$v)throw new RuntimeException('FAIL: '.$message);$n++;}
verify(booking_package_price('Body 999')===999&&booking_package_price('Body')===999&&booking_package_price('body 999k')===999,'Nhãn Body có giá 999');
verify(booking_package_code('Body 999k')==='body'&&booking_package_code('Couple 999')===999&&booking_package_code('999')===999&&booking_package_code('1.499')===1499&&booking_package_code('')===null,'Mã gói');
verify(booking_length('Body 999')===120,'Body giữ 2 tiếng');verify(BOOKING_PACKAGE_UNITS[booking_package_price('Body 999')]===2,'Body ×2 suất');
try{$a->tx(function()use($a){
 $a->user=$a->one("SELECT * FROM {{users}} WHERE role='admin' AND archived=0 ORDER BY id LIMIT 1");
 $day=date('Y-m-d',strtotime(local_day(now_ms()).' +21 days'));$a->run("DELETE FROM {{shoot_bookings}} WHERE shoot_day=?",[$day]);
 $form=['day'=>$day,'customer_phone'=>'','makeup'=>'','special'=>'','note'=>''];
 $row=fn(string $id)=>$a->one('SELECT * FROM {{shoot_bookings}} WHERE id=?',[$id]);
 $create=fn(array $x)=>booking_routes($a,'/bookings/create',$x+$form,'POST');
 $pick=fn(string $id,mixed $p)=>booking_routes($a,'/bookings/package',['id'=>$id,'package'=>$p],'POST')[1];
 $update=function(string $id,array $x)use($a,$row){$e=booking_form($row($id));return booking_routes($a,'/bookings/update',$x+['id'=>$id,'day'=>$e['day'],'time'=>$e['time'],'package'=>$e['package'],'customer_name'=>$e['customer_name'],'customer_phone'=>$e['customer_phone'],'makeup'=>'','special'=>'','note'=>$e['note']],'POST');};
 // Thêm lịch: lưu đúng chữ, chiếm 2 tiếng, ghi Sheet đúng chữ.
 [$s,$r]=$create(['time'=>'10:00','package'=>'body','customer_name'=>'Khách Body']);verify($s===200,'Thêm lịch Body');$body=$r['id'];
 verify($row($body)['package_label']==='Body 999','Lưu "Body 999"');verify(booking_form($row($body))['package']==='body','Form Sửa lịch chọn sẵn Body');
 [$s,$r]=$create(['time'=>'11:30','package'=>null,'customer_name'=>'Vướng Body']);verify($s===409&&array_column($r['conflicts'],'id')===[$body],'Body chiếm đủ 2 tiếng 10:00–12:00');
 // Popup Gói khách chụp: Couple ↔ Body là đổi gói thật, không bị coi "không thay đổi" dù cùng giá.
 [$s,$r]=$create(['time'=>'14:00','package'=>999,'customer_name'=>'Khách Couple']);$couple=$r['id'];verify($row($couple)['package_label']==='999','Couple vẫn lưu 999');
 verify($pick($couple,'body')['changed']===['package_label']&&$row($couple)['package_label']==='Body 999','Couple → Body');
 verify($pick($couple,'body')['changed']===[],'Chọn lại Body: không đổi');
 $job=$a->one("SELECT * FROM {{booking_sheet_queue}} WHERE booking_id=? ORDER BY created_at DESC,id DESC LIMIT 1",[$couple]);verify((bool)$job,'Đổi gói có việc ghi Sheet');
 verify($pick($couple,999)['changed']===['package_label']&&$row($couple)['package_label']==='999','Body → Couple');
 // Sửa lịch giữ nguyên gói Body; đổi sang gói khác thì đổi.
 [$s,$r]=$update($body,['customer_name'=>'Khách Body cọc 200k']);verify($s===200&&$r['changed']==['customer_name']&&$row($body)['package_label']==='Body 999','Sửa tên không đụng ô Gói');
 [$s,$r]=$update($couple,['package'=>'body']);verify($s===200&&in_array('package_label',$r['changed'],true)&&$row($couple)['package_label']==='Body 999','Sửa lịch đổi Couple → Body');
 // Nhãn gõ tay trên Sheet: "Body 999k" giữ nguyên chữ, chọn lại Body không đổi.
 $a->run('UPDATE {{shoot_bookings}} SET package_label=? WHERE id=?',['Body 999k',$body]);verify($pick($body,'body')['changed']===[],'Sheet ghi "Body 999k" vẫn là gói Body');
 // Mã lạ vẫn bị chặn.
 foreach(['couple','body ',1000,'999'] as $bad){try{$create(['time'=>'17:00','package'=>$bad,'customer_name'=>'Sai']);verify(false,'Phải chặn '.json_encode($bad));}catch(HttpError $e){verify(str_contains($e->getMessage(),'Gói không hợp lệ'),'Chặn gói lạ');}}
 throw new RuntimeException('rollback');
});}catch(RuntimeException $e){if($e->getMessage()!=='rollback')throw $e;}
echo "OK booking-body: $n kiểm tra\n";
