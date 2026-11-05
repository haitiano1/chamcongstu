<?php
// Không hiện lịch trùng (chủ dự án yêu cầu 02/11/2026): script gửi mã mọi dòng còn trên các tab vừa gửi đủ;
// lịch trên web không còn dòng trên Sheet thì ẩn (status missing), dòng xuất hiện lại thì hiện lại. Giao dịch rồi hoàn tác.
foreach(['bootstrap','telegram','bookings','people-finance','workflows','tasks','availability'] as $f)require_once __DIR__.'/../site/nhansu-private/lib/'.$f.'.php';
$a=new App('/var/nhansu-private');$n=0;function verify($v,$message){global $n;if(!$v)throw new RuntimeException('FAIL: '.$message);$n++;}
try{$a->tx(function()use($a){
 $admin=$a->one("SELECT * FROM {{users}} WHERE role='admin' AND archived=0 ORDER BY id LIMIT 1");$a->user=$admin;
 $sheet=$a->meta('sheet_sync_id')?:'sheet-test-id';$a->meta('sheet_sync_id',$sheet);
 $day='2026-12-14';$tab='Tháng 12';
 [$A,$B,$C,$D]=[uuid_v4(),uuid_v4(),uuid_v4(),uuid_v4()];
 $row=fn(string $id,string $time,string $name)=>['id'=>$id,'day'=>$day,'time'=>$time,'customer_name'=>$name,'package_label'=>'','customer_phone'=>'','main_alias'=>'','support_alias'=>'','note'=>'','status'=>'active'];
 import_bookings($a,['tab'=>$tab,'rows'=>[$row($A,'09:00','Khách A'),$row($B,'11:00','Hoai Lam ZALO'),$row($C,'11:00','Hoai Lam ZALO')]],$sheet,null);
 $status=fn(string $id)=>$a->scalar('SELECT status FROM {{shoot_bookings}} WHERE id=?',[$id]);
 verify($status($A)==='active'&&$status($B)==='active'&&$status($C)==='active','Nhận 3 lịch từ Sheet');
 // Lịch web đang chờ ghi xuống Sheet (chưa có dòng): không bị ẩn.
 $a->run("INSERT INTO {{shoot_bookings}}(id,source_sheet,source_tab,source_row,shoot_day,minutes,package_label,customer_name,customer_phone,main_alias,support_alias,note,status,created_at,updated_at) VALUES(?,?,?,0,?,780,'','Khách web','','','','','active',?,?)",[$D,$sheet,$tab,$day,now_ms(),now_ms()]);
 booking_enqueue($a,$D,'create',$tab,[]);
 // Dòng B đã xóa trên Sheet (hoặc cắt dán mất mã): tab gửi đủ chỉ còn A, C.
 $hidden=booking_hide_missing($a,$sheet,['tabs'=>[$tab],'ids'=>[$A,strtoupper($C)]]);
 verify($hidden===1&&$status($B)==='missing'&&$status($A)==='active'&&$status($C)==='active','Lịch không còn dòng trên Sheet thì ẩn, giữ lịch còn dòng');
 verify($status($D)==='active','Lịch web đang chờ ghi Sheet không bị ẩn');
 verify((bool)$a->one("SELECT 1 FROM {{booking_audit}} WHERE booking_id=? AND action='missing'",[$B]),'Lịch sử ghi lại lịch bị ẩn');
 $_GET=['month'=>'2026-12'];$ids=array_column(booking_routes($a,'/bookings',[],'GET')[1]['rows'],'id');
 verify(!in_array($B,$ids,true)&&in_array($C,$ids,true),'Lịch chụp không còn thẻ trùng');
 verify(!in_array($B,array_column(booking_busy($a,$day),'id'),true),'Lịch đã ẩn không chặn giờ khi thêm lịch, không tính công / hoa hồng');
 // Tab khác không gửi trong lần này: không đụng.
 verify(booking_hide_missing($a,$sheet,['tabs'=>['Tháng 1'],'ids'=>[]])===0&&$status($A)==='active','Tab không gửi thì không ẩn lịch');
 // Dòng có mã B xuất hiện lại (VD hoàn tác xóa dòng): tự hiện lại.
 import_bookings($a,['tab'=>$tab,'rows'=>[$row($A,'09:00','Khách A'),$row($B,'11:00','Hoai Lam ZALO')]],$sheet,null);
 verify($status($B)==='active','Dòng xuất hiện lại thì lịch hiện lại');
 // Dữ liệu sai thì báo lỗi, không ẩn gì.
 try{booking_hide_missing($a,$sheet,['tabs'=>[$tab],'ids'=>['khong-phai-ma']]);verify(false,'Mã sai phải bị chặn');}catch(HttpError){verify($status($A)==='active','Mã sai bị chặn, không ẩn lịch');}
 throw new RuntimeException('ROLLBACK');
});}catch(RuntimeException $e){if($e->getMessage()!=='ROLLBACK')throw $e;}
echo "PASS $n missing-row checks: hide rows deleted on Sheet, keep pending web bookings, restore when row returns\n";
