<?php
// Thẻ lịch "Có MK" / "Không MK": ô Makeup trên Sheet, hoặc "Make up: …" trong Ghi chú khi Sheet không có cột / lịch web chưa đồng bộ.
foreach(['bootstrap','telegram','bookings','tasks'] as $f)require_once __DIR__.'/../site/nhansu-private/lib/'.$f.'.php';
$cases=[
 ['Sheet Makeup Nữ',['sheet_fields_json'=>json_encode([['label'=>'Makeup','value'=>'Nữ']]),'note'=>''],true],
 ['Sheet Make up Làm Tóc',['sheet_fields_json'=>json_encode([['label'=>'Make up?','value'=>'Làm Tóc']]),'note'=>''],true],
 ['Sheet Makeup trống (không gửi)',['sheet_fields_json'=>json_encode([['label'=>'Tên khách','value'=>'Lan']]),'note'=>''],false],
 ['Sheet Makeup "Không"',['sheet_fields_json'=>json_encode([['label'=>'Makeup','value'=>'Không']]),'note'=>''],false],
 ['Web chưa đồng bộ: ghi chú Make up: Nam',['sheet_fields_json'=>null,'note'=>'Đặc biệt: Ô TÔ · Make up: Nam · Giữ 2 tiếng'],true],
 ['Web chọn Không',['sheet_fields_json'=>null,'note'=>'Đặc biệt: Ô TÔ'],false],
 ['Sheet không có cột Makeup, ghi chú có Make up: Nữ',['sheet_fields_json'=>json_encode([['label'=>'Ghi chú','value'=>'Make up: Nữ · chụp áo dài']]),'note'=>'Make up: Nữ · chụp áo dài'],true],
 ['Ghi chú nhắc "makeup" không phải mục',['sheet_fields_json'=>null,'note'=>'khách tự makeup ở nhà'],false],
];
$bad=0;foreach($cases as [$name,$row,$want]){$got=booking_has_makeup($row);if($got!==$want)$bad++;echo ($got===$want?'ok  ':'FAIL').' '.$name.' → '.($got?'Có MK':'Không MK')."\n";}
if($bad)exit(1);echo "PASS ".count($cases)." makeup card checks\n";
