<?php
declare(strict_types=1);
// This boundary intentionally has no dependency on any private application file.
ini_set('display_errors','0');
$nhansuBaseBufferLevel=ob_get_level();
ob_start();
$nhansuEmergencyMemory=str_repeat('x',32768);
$nhansuStage='startup';
$nhansuLibrary='';
function nhansu_api_error($message,$status=500,$reference='') {
 global $nhansuBaseBufferLevel;
 while(ob_get_level()>$nhansuBaseBufferLevel)ob_end_clean();
 if(!headers_sent()) {
  http_response_code($status);
  header('Content-Type: application/json; charset=utf-8');
  header('Cache-Control: no-store');
  header('X-Content-Type-Options: nosniff');
  header('X-Frame-Options: DENY');
 }
 $data=array('error'=>$message);
 if($reference!=='')$data['reference']=$reference;
 echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
 exit;
}
function nhansu_api_reference($kind,$file,$line,$code='') {
 global $nhansuStage,$nhansuLibrary;
 $ref=substr(hash('sha256',microtime(true).':'.mt_rand()),0,12);
 // No exception messages, stack traces, config contents or absolute server paths.
 error_log('Nhansu API ref='.$ref.' stage='.$nhansuStage.' module='.$nhansuLibrary.' type='.$kind.' file='.basename($file).' line='.(int)$line.' code='.$code);
 return $ref;
}
register_shutdown_function(function(){
 global $nhansuEmergencyMemory,$nhansuStage,$nhansuLibrary;
 $last=error_get_last();
 if(!$last||!in_array($last['type'],array(E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR,E_USER_ERROR),true))return;
 $nhansuEmergencyMemory=null;
 $ref=nhansu_api_reference('fatal',$last['file'],$last['line'],(string)$last['type']);
 // Never append JSON to a photo, PDF or download that has already been sent.
 if(headers_sent())return;
 $message=$nhansuStage==='loading'?'PHP không tải được '.$nhansuLibrary.'. Hãy giải nén lại gói PRIVATE vào đúng thư mục nhansu-private.':'PHP dừng khi xử lý yêu cầu. Mở kiemtra-capnhat.php và kiểm tra PHP error log của hosting.';
 nhansu_api_error($message,500,$ref);
});
if(PHP_VERSION_ID<80200)nhansu_api_error('Ứng dụng cần PHP từ 8.2. Kiểm tra phiên bản PHP của riêng ứng dụng; không đổi cấu hình WordPress để thử.',503);
$private=getenv('APP_PRIVATE_DIR')?:dirname(__DIR__,2).'/nhansu-private';
try {
 $libraries=array('rules.php','schema.php','bootstrap.php','api.php','workflows.php','telegram.php','transfer.php','people-finance.php','bookings.php','tasks.php');
 foreach($libraries as $library) {
  if(!is_file($private.'/lib/'.$library)||!is_readable($private.'/lib/'.$library))nhansu_api_error('Thiếu hoặc không đọc được lib/'.$library.'. Giải nén lại gói PRIVATE bên trong nhansu-private, gộp thư mục lib và thay file trùng.',503);
 }
 foreach($libraries as $library) {
  $nhansuStage='loading';$nhansuLibrary='lib/'.$library;
  require_once $private.'/lib/'.$library;
 }
 $functions=array('rules.php'=>array('text_length','attendance_decision'),'schema.php'=>array('schema'),'bootstrap.php'=>array('headers_private','json_response'),'api.php'=>array('auth_routes','main_routes','management_routes'),'workflows.php'=>array('workflow_routes','roster_routes'),'telegram.php'=>array('telegram_routes'),'transfer.php'=>array('transfer_routes'),'people-finance.php'=>array('people_finance_routes'),'bookings.php'=>array('sheet_receiver','booking_routes'),'tasks.php'=>array('task_routes','task_telegram_tick'));
 foreach($functions as $library=>$names)foreach($names as $name)if(!function_exists($name))nhansu_api_error('Gói PRIVATE chưa đồng bộ: lib/'.$library.' chưa đầy đủ. Hãy thay file trùng bằng gói sửa mới.',503);
 if(ob_get_length()>0)nhansu_api_error('Có nội dung thừa khi nạp file PHP. Upload lại đầy đủ gói PRIVATE và kiểm tra trang kiemtra-capnhat.php.',503);
 $nhansuStage='initializing';$nhansuLibrary='';
 headers_private();
 $a=new App($private);if(ob_get_length()>0)nhansu_api_error('File cấu hình PHP có nội dung thừa. Kiểm tra thẻ mở PHP trong config.php; không gửi mật khẩu vào chat.',503);$method=$_SERVER['REQUEST_METHOD'];$path=$_GET['route']??'';if(!is_string($path)||!preg_match('~^/[a-zA-Z0-9_./-]+$~D',$path))fail('Đường dẫn API không hợp lệ.',404);
 if(!in_array($method,['GET','POST'],true))fail('Phương thức không được hỗ trợ.',405);
 $b=[];
 if($method==='POST'){
  $u=parse_url($a->config['app_url']);$origin=$u['scheme'].'://'.$u['host'].(isset($u['port'])?':'.$u['port']:'');
  if(isset($_SERVER['HTTP_ORIGIN'])&&$_SERVER['HTTP_ORIGIN']!==$origin||($_SERVER['HTTP_SEC_FETCH_SITE']??'')==='cross-site')fail('Nguồn yêu cầu không hợp lệ.',403);
  $multipart=$path==='/migration/import';$limit=$multipart?40000000:(in_array($path,['/attendance','/sheet-sync/receive','/bookings/import'],true)||preg_match('~^/tasks/[a-f0-9-]{36}/done$~D',$path)?512000:32000);
  if((int)($_SERVER['CONTENT_LENGTH']??0)>$limit)fail('Yêu cầu quá lớn.',413);
  if(!$multipart){if(!str_starts_with($_SERVER['CONTENT_TYPE']??'','application/json'))fail('Cần gửi dữ liệu JSON.',415);$raw=file_get_contents('php://input',false,null,0,$limit+1);if(strlen($raw)>$limit)fail('Yêu cầu quá lớn.',413);$b=json_decode($raw,true,32,JSON_THROW_ON_ERROR);if(!is_array($b)||array_is_list($b)&&$b!==[])fail('Dữ liệu không hợp lệ.');}else{$b=$_POST;}
 }
 $dispatch=function()use($a,$path,$b,$method){$r=sheet_receiver($a,$path,$b,$method);if($r!==null)return $r;$r=auth_routes($a,$path,$b,$method);if($r!==null)return $r;$a->authenticate();if($method==='POST'&&!hash_equals($a->csrf,$_SERVER['HTTP_X_NHANSU_CSRF']??''))fail('Phiên thao tác đã cũ. Vui lòng tải lại trang.',403);
  foreach(['booking_routes','task_routes','people_finance_routes','main_routes','workflow_routes','roster_routes','telegram_routes','transfer_routes'] as $handler){$r=$handler($a,$path,$b,$method);if($r!==null)return $r;}
  $r=location_routes($a,$path,$method);if($r!==null)return $r;$r=management_routes($a,$path,$b,$method);if($r!==null)return $r;fail('Không tìm thấy chức năng.',404);
 };
 $nhansuStage='request';$result=$method==='POST'?$a->tx($dispatch):$dispatch();json_response($result[1],$result[0]);
}catch(HttpError $e){nhansu_api_error($e->getMessage(),$e->status);}catch(JsonException $e){nhansu_api_error('Dữ liệu JSON không hợp lệ.',400);}catch(PDOException $e){$ref=nhansu_api_reference(get_class($e),$e->getFile(),$e->getLine(),(string)$e->getCode());nhansu_api_error($e->getCode()==='23000'?'Dữ liệu bị trùng hoặc xung đột.':'Không lưu được dữ liệu. Kiểm tra PHP error log theo mã tham chiếu.',$e->getCode()==='23000'?409:500,$ref);}catch(Throwable $e){$ref=nhansu_api_reference(get_class($e),$e->getFile(),$e->getLine(),(string)$e->getCode());$message=$nhansuStage==='loading'?'Không tải được '.$nhansuLibrary.'. Hãy upload lại đầy đủ gói PRIVATE; kiểm tra kiemtra-capnhat.php.':'Ứng dụng gặp lỗi PHP. Mở kiemtra-capnhat.php và kiểm tra PHP error log theo mã tham chiếu.';nhansu_api_error($message,500,$ref);}
