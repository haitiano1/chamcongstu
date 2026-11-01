<?php
// Thẻ lịch hiện đúng lựa chọn Make up (Ko MK / Nam MK / Nữ MK / Làm tóc): ô Makeup trên Sheet, hoặc "Make up: …" trong Ghi chú khi Sheet không có cột / lịch web chưa đồng bộ.
foreach(['bootstrap','telegram','bookings','tasks'] as $f)require_once __DIR__.'/../site/nhansu-private/lib/'.$f.'.php';
$cases=[
 ['Sheet Makeup Nữ',['sheet_fields_json'=>json_encode([['label'=>'Makeup','value'=>'Nữ']]),'note'=>''],'Nữ MK'],
 ['Sheet Make up Làm Tóc',['sheet_fields_json'=>json_encode([['label'=>'Make up?','value'=>'Làm Tóc']]),'note'=>''],'Làm tóc'],
 ['Sheet Makeup trống (không gửi)',['sheet_fields_json'=>json_encode([['label'=>'Tên khách','value'=>'Lan']]),'note'=>''],'Ko MK'],
 ['Sheet Makeup "Không"',['sheet_fields_json'=>json_encode([['label'=>'Makeup','value'=>'Không']]),'note'=>''],'Ko MK'],
 ['Web chưa đồng bộ: ghi chú Make up: Nam',['sheet_fields_json'=>null,'note'=>'Đặc biệt: Ô TÔ · Make up: Nam · Giữ 2 tiếng'],'Nam MK'],
 ['Web chọn Không',['sheet_fields_json'=>null,'note'=>'Đặc biệt: Ô TÔ'],'Ko MK'],
 ['Sheet không có cột Makeup, ghi chú có Make up: Nữ',['sheet_fields_json'=>json_encode([['label'=>'Ghi chú','value'=>'Make up: Nữ · chụp áo dài']]),'note'=>'Make up: Nữ · chụp áo dài'],'Nữ MK'],
 ['Sheet chọn nhiều: Nữ, Làm Tóc',['sheet_fields_json'=>json_encode([['label'=>'Makeup','value'=>'Nữ, Làm Tóc']]),'note'=>''],'Nữ MK, Làm tóc'],
 ['Sheet ghi chữ khác',['sheet_fields_json'=>json_encode([['label'=>'Makeup','value'=>'Cô dâu']]),'note'=>''],'Cô dâu'],
 ['Ghi chú nhắc "makeup" không phải mục',['sheet_fields_json'=>null,'note'=>'khách tự makeup ở nhà'],'Ko MK'],
];
$bad=0;foreach($cases as [$name,$row,$want]){$got=booking_makeup_label($row);if($got!==$want)$bad++;echo ($got===$want?'ok  ':'FAIL').' '.$name.' → '.$got."\n";}
if($bad)exit(1);echo "PASS ".count($cases)." makeup label checks\n";
