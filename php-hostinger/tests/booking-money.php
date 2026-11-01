<?php
// Sửa lịch → Phụ thu, Tip: lưu trên web, ghi xuống Sheet (việc update chỉ các ô đổi), Sheet có cột thì đọc về, không có cột / script cũ thì giữ số web.
// Chạy trong giao dịch rồi hoàn tác.
foreach(['bootstrap','telegram','bookings','people-finance','tasks'] as $f)require_once __DIR__.'/../site/nhansu-private/lib/'.$f.'.php';
$a=new App('/var/nhansu-private');$n=0;function verify($v,$message){global $n;if(!$v)throw new RuntimeException('FAIL: '.$message);$n++;}
function denied(callable $fn,string $want,string $message){try{$fn();}catch(HttpError $e){verify(str_contains($e->getMessage(),$want),$message.' ('.$e->getMessage().')');return;}verify(false,$message.' (không bị chặn)');}
try{$a->tx(function()use($a){
 $a->user=$a->one("SELECT * FROM {{users}} WHERE role='admin' AND archived=0 ORDER BY id LIMIT 1");$sheet='SHEETTEST';
 $add=function(string $day,int $min,?string $fields)use($a,$sheet){$id=uuid_v4();$a->run("INSERT INTO {{shoot_bookings}}(id,source_sheet,source_tab,source_row,shoot_day,minutes,package_label,customer_name,customer_phone,main_alias,support_alias,note,sheet_fields_json,status,created_at,updated_at) VALUES(?,?,'Tháng 10',2,?,?,'699','Chị Lan','0901234567','HUY','CƯỜNG','',?,'active',?,?)",[$id,$sheet,$day,$min,$fields,now_ms(),now_ms()]);return $id;};
 $form=fn(string $id)=>booking_form($a->one('SELECT * FROM {{shoot_bookings}} WHERE id=?',[$id]));
 $save=function(string $id,array $extra)use($a,$form){$e=$form($id);return booking_routes($a,'/bookings/update',['id'=>$id,'day'=>$e['day'],'time'=>$e['time'],'package'=>$e['package'],'customer_name'=>$e['customer_name'],'customer_phone'=>$e['customer_phone'],'makeup'=>'','special'=>'','note'=>$e['note']]+$extra,'POST')[1];};
 $queue=fn(string $id)=>$a->one("SELECT * FROM {{booking_sheet_queue}} WHERE booking_id=? AND action='update' AND status='pending' ORDER BY created_at DESC LIMIT 1",[$id]);
 // Lịch đã qua (đã chụp), Sheet có cột Tip: nhập Phụ thu + Tip.
 $id=$add('2026-10-20',600,json_encode([['label'=>'Tên khách','value'=>'Chị Lan'],['label'=>'Tip','value'=>'']],JSON_UNESCAPED_UNICODE));
 verify($form($id)['surcharge']===''&&$form($id)['tip']==='','Form sửa lịch có Phụ thu, Tip (trống)');
 $r=$save($id,['surcharge'=>'100k','tip'=>'50.000']);verify($r['changed']==['surcharge','tip'],'Chỉ đổi Phụ thu, Tip: '.json_encode($r['changed']));
 $row=$a->one('SELECT * FROM {{shoot_bookings}} WHERE id=?',[$id]);verify($row['surcharge_label']==='100k'&&$row['tip_label']==='50.000','Lưu trên web');
 $fields=array_column(json_decode($row['sheet_fields_json'],true),'value','label');verify(($fields['Tip']??'')==='50.000'&&($fields['Phụ thu']??'')==='100k','Popup hiện ngay Phụ thu, Tip: '.$row['sheet_fields_json']);
 $q=$queue($id);$payload=json_decode($q['payload_json'],true);verify($q&&$payload['changed']==['surcharge','tip'],'Việc ghi Sheet chỉ gồm 2 ô');
 $poll=array_values(array_filter(booking_queue_poll($a)['queue'],fn($x)=>$x['booking_id']===$id))[0];verify($poll['values']['surcharge']==='100k'&&$poll['values']['tip']==='50.000'&&$poll['changed']==['surcharge','tip'],'Gửi script số mới: '.json_encode($poll['values'],JSON_UNESCAPED_UNICODE));
 // Không đổi gì thì không tạo việc; sửa lại đúng số cũ cũng vậy.
 verify($save($id,['surcharge'=>'100k','tip'=>'50.000'])['changed']===[],'Không đổi thì không ghi Sheet');
 // Bản web cũ (không gửi 2 ô này) giữ nguyên số.
 $save($id,['customer_phone'=>'0909111222']);$row=$a->one('SELECT surcharge_label,tip_label FROM {{shoot_bookings}} WHERE id=?',[$id]);verify($row['surcharge_label']==='100k'&&$row['tip_label']==='50.000','Không gửi 2 ô thì giữ nguyên');
 // Số tiền không hợp lệ bị chặn; xóa (để trống) được.
 denied(fn()=>$save($id,['tip'=>'năm chục']),'Tip ghi số tiền','Tip phải là số tiền');
 $r=$save($id,['tip'=>'']);verify($r['changed']==['tip']&&$a->scalar('SELECT tip_label FROM {{shoot_bookings}} WHERE id=?',[$id])==='','Xóa Tip');
 // Chỉ sửa Phụ thu/Tip thì không kiểm tra trùng giờ (Sheet có lịch chồng giờ vẫn sửa được).
 $other=$add('2026-10-20',630,null);$r=$save($id,['surcharge'=>'200000']);verify($r['changed']==['surcharge'],'Lịch chồng giờ vẫn nhập Phụ thu được');
 // Lịch chưa có dữ liệu Sheet (web chưa đồng bộ): vẫn lưu được.
 $r=$save($other,['tip'=>'30k']);verify($r['changed']==['tip']&&$a->scalar('SELECT tip_label FROM {{shoot_bookings}} WHERE id=?',[$other])==='30k','Lịch chưa đồng bộ vẫn lưu Tip');
 // Đồng bộ từ Sheet: đang chờ ghi thì Sheet cũ không đè; ghi xong (ack) thì Sheet có cột → đọc về; không có cột (script cũ) → giữ số web.
 $send=fn(array $extra)=>import_bookings($a,['tab'=>'Tháng 10','rows'=>[['id'=>$id,'day'=>'2026-10-20','time'=>'10:00','package_label'=>'699','customer_name'=>'Chị Lan','customer_phone'=>'0909111222','main_alias'=>'HUY','support_alias'=>'CƯỜNG','note'=>'']+$extra]],$sheet,null);
 $send(['surcharge_label'=>'','tip_label'=>'']);verify($a->scalar('SELECT surcharge_label FROM {{shoot_bookings}} WHERE id=?',[$id])==='200000','Đang chờ ghi Sheet thì Sheet cũ không đè');
 $a->run("UPDATE {{booking_sheet_queue}} SET status='done' WHERE booking_id=?",[$id]);
 $send([]);verify($a->scalar('SELECT surcharge_label FROM {{shoot_bookings}} WHERE id=?',[$id])==='200000','Sheet không có cột / script cũ: giữ số web');
 $send(['surcharge_label'=>'250,000','tip_label'=>'40,000']);$row=$a->one('SELECT surcharge_label,tip_label FROM {{shoot_bookings}} WHERE id=?',[$id]);verify($row['surcharge_label']==='250,000'&&$row['tip_label']==='40,000','Sửa trên Sheet thì web cập nhật theo');
 verify($form($id)['surcharge']==='250,000'&&$form($id)['tip']==='40,000','Form sửa lịch điền sẵn số mới');
 // Gói khách chụp sau đó không làm mất số.
 booking_routes($a,'/bookings/package',['id'=>$id,'package'=>1499],'POST');$row=$a->one('SELECT surcharge_label,tip_label FROM {{shoot_bookings}} WHERE id=?',[$id]);verify($row['surcharge_label']==='250,000'&&$row['tip_label']==='40,000','Đổi gói giữ Phụ thu, Tip');
 throw new RuntimeException('ROLLBACK');
});}catch(RuntimeException $e){if($e->getMessage()!=='ROLLBACK')throw $e;}
echo "PASS $n booking money checks: surcharge/tip edit, Sheet queue, import keep/update, validation\n";
