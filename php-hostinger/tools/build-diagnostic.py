from pathlib import Path
import json,hashlib
root=Path(__file__).resolve().parents[1]/'site'; public=root/'public_html/nhansu'; private=root/'nhansu-private'
files=[]
for f in sorted((private/'lib').iterdir()):
 if f.is_file() and f.suffix in ['.php','.json','.pdf']:files.append(('private','lib/'+f.name,hashlib.sha256(f.read_bytes()).hexdigest()))
files.append(('private','bin/cron.php',hashlib.sha256((private/'bin/cron.php').read_bytes()).hexdigest()))
for name in ['api.php','index.php','assets/app.js','assets/api-response.js','assets/people-finance.js','assets/bookings.js','assets/studio-journal.css','assets/roster.js','assets/tasks.js','assets/tasks.css','assets/camera.js','assets/mobile.css','assets/availability.js','assets/availability.css','assets/roster.js']:
 files.append(('public',name,hashlib.sha256((public/name).read_bytes()).hexdigest()))
data=json.dumps(files,ensure_ascii=True,separators=(',',':'))
code=r'''<?php
// Standalone read-only check. Never loads config.php, connects to MySQL or edits data.
ini_set('display_errors','0');
header('Content-Type: text/html; charset=utf-8');header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');header('X-Frame-Options: DENY');
$private=getenv('APP_PRIVATE_DIR')?:dirname(__DIR__,2).'/nhansu-private';
$manifest=json_decode('MANIFEST_JSON',true);
$checks=array();
$checks[]=array('PHP từ 8.2',PHP_VERSION_ID>=80200?'Đạt — '.PHP_VERSION:'Chưa đạt — '.PHP_VERSION,PHP_VERSION_ID>=80200);
foreach(array('pdo_mysql'=>'PDO MySQL','gd'=>'GD xử lý ảnh','curl'=>'cURL','zlib'=>'Gzip') as $extension=>$label){$ok=extension_loaded($extension);$checks[]=array($label,$ok?'Đã bật':'Chưa bật',$ok);}
$checks[]=array('Private nằm ngoài thư mục web',is_dir($private)?'Đã tìm thấy thư mục nhansu-private':'Không tìm thấy nhansu-private ngang cấp public_html',is_dir($private));
$checks[]=array('config.php',is_readable($private.'/config.php')?'Có file và PHP đọc được (không đọc nội dung cấu hình)':'Thiếu file hoặc PHP không đọc được',is_readable($private.'/config.php'));
foreach($manifest as $entry){$file=($entry[0]==='private'?$private:__DIR__).'/'.$entry[1];$ok=false;if(!is_file($file))$status='THIẾU FILE — kiểm tra có giải nén lồng thư mục không';elseif(!is_readable($file))$status='KHÔNG ĐỌC ĐƯỢC — kiểm tra quyền file';elseif(hash_file('sha256',$file)!==$entry[2])$status='KHÁC GÓI SỬA — có thể chưa thay file hoặc file tải lên chưa đầy đủ';else{$status='Đúng gói sửa';$ok=true;}$checks[]=array(($entry[0]==='private'?'nhansu-private/':'nhansu/').$entry[1],$status,$ok);}
function nhansu_check_esc($text){return htmlspecialchars($text,ENT_QUOTES,'UTF-8');}
?><!doctype html><html lang="vi"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Kiểm tra cập nhật · Anh Gấu Studio</title><style>body{font:15px/1.6 system-ui;background:#f4f7fb;color:#172b46;padding:18px}main{max-width:1050px;margin:auto;background:white;padding:24px;border-radius:14px}table{width:100%;border-collapse:collapse}td,th{padding:12px;border-bottom:1px solid #e5eaf2;text-align:left;overflow-wrap:anywhere}.ok{color:#166534}.bad{color:#b42318}a{color:#155eef}@media(max-width:650px){body{padding:0}main{padding:16px}td{font-size:12px}}</style><main><h1>Kiểm tra gói sửa lỗi 500</h1><p>Trang này chỉ kiểm tra PHP và file, không thay đổi dữ liệu và không hiển thị mật khẩu/token.</p><table><thead><tr><th>Mục kiểm tra</th><th>Kết quả</th></tr></thead><tbody><?php foreach($checks as $row){echo '<tr><td>'.nhansu_check_esc($row[0]).'</td><td class="'.($row[2]?'ok':'bad').'">'.nhansu_check_esc($row[1]).'</td></tr>';}?></tbody></table><p>Nếu có dòng đỏ, upload lại file hoặc giải nén đúng cấp thư mục. Giữ nguyên config.php và storage. Không đổi PHP toàn website WordPress chỉ để thử.</p><p>Nếu tất cả đạt nhưng API vẫn lỗi, mở liên kết dưới để lấy thông báo và mã tham chiếu; kiểm tra PHP error log trong hosting. Kết quả xanh không chứng minh kết nối database thành công.</p><p><a href="./api.php?route=%2Fstatus" target="_blank" rel="noopener">Mở API kiểm tra</a> · <a href="./">Mở trang nhân sự</a></p><p>Có thể xóa riêng kiemtra-capnhat.php sau khi sửa xong.</p></main></html>
'''
code=code.replace('MANIFEST_JSON',data.replace('\\','\\\\').replace("'","\\'"))
(public/'kiemtra-capnhat.php').write_text(code);(public/'kiemtra-capnhat.php').chmod(0o644)
