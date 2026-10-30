<?php
declare(strict_types=1);
require_once __DIR__.'/people-finance.php';

function uuid_v4(): string {$b=random_bytes(16);$b[6]=chr((ord($b[6])&15)|64);$b[8]=chr((ord($b[8])&63)|128);$h=bin2hex($b);return substr($h,0,8).'-'.substr($h,8,4).'-'.substr($h,12,4).'-'.substr($h,16,4).'-'.substr($h,20);}
function alias_key(string $name): string {
 $name=trim(preg_replace('/\s+/u',' ',input_text($name,'Tên trên Sheet',1,150)));
 // Locale-independent case folding for Vietnamese; do not remove accents or guess identities.
 $upper='ABCDEFGHIJKLMNOPQRSTUVWXYZÀÁẢÃẠĂẰẮẲẴẶÂẦẤẨẪẬÈÉẺẼẸÊỀẾỂỄỆÌÍỈĨỊÒÓỎÕỌÔỒỐỔỖỘƠỜỚỞỠỢÙÚỦŨỤƯỪỨỬỮỰỲÝỶỸỴĐ';
 $lower='abcdefghijklmnopqrstuvwxyzàáảãạăằắẳẵặâầấẩẫậèéẻẽẹêềếểễệìíỉĩịòóỏõọôồốổỗộơờớởỡợùúủũụưừứửữựỳýỷỹỵđ';
 return strtr($name,array_combine(preg_split('//u',$upper,-1,PREG_SPLIT_NO_EMPTY),preg_split('//u',$lower,-1,PREG_SPLIT_NO_EMPTY)));
}
function booking_shift(int $minutes): ?string {return $minutes>=540&&$minutes<840?'day_first':($minutes>=840&&$minutes<1140?'day_second':null);}
function expected_shift(App $a,int $id,string $day,string $shift,bool $snapshot=true): ?array {
 if(!in_array($shift,['day_first','day_second'],true))return null;
 if($snapshot&&($saved=$a->one('SELECT * FROM {{booking_shift_snapshots}} WHERE user_id=? AND day=? AND shift=?',[$id,$day,$shift])))return $saved['source']==='booking'?['expected_at'=>$saved['expected_at'],'late_after'=>$saved['expected_at']+1800000,'source'=>'booking','frozen'=>true,'booking_ids'=>json_decode($saved['booking_ids_json'],true)]:null;
 $range=$shift==='day_first'?[540,840]:[840,1140];
 $rows=$a->all("SELECT b.id,b.minutes FROM {{shoot_bookings}} b JOIN {{booking_staff}} s ON s.booking_id=b.id WHERE s.user_id=? AND b.shoot_day=? AND b.status='active' AND b.minutes>=? AND b.minutes<? ORDER BY b.minutes,b.id",[$id,$day,...$range]);
 if($rows)return ['expected_at'=>day_time($day)+$rows[0]['minutes']*60000,'late_after'=>day_time($day)+$rows[0]['minutes']*60000+1800000,'source'=>'booking','frozen'=>false,'booking_ids'=>array_values(array_unique(array_column($rows,'id')))];
 return null;
}
// Derive one roster entry per person/day/regular shift from live booking assignments.
// No writes to manual shift_assignments; Sheet edits/cancellations cannot leave stale copies.
function booking_roster(App $a,string $start,string $end): array {
 $own=$a->user['role']==='employee';$rows=$a->all("SELECT b.id,b.shoot_day,b.minutes,s.user_id,u.name,u.role,u.status,u.archived,u.employee_code FROM {{shoot_bookings}} b JOIN {{booking_staff}} s ON s.booking_id=b.id JOIN {{users}} u ON u.id=s.user_id WHERE b.status='active' AND b.shoot_day>=? AND b.shoot_day<=? AND b.minutes>=540 AND b.minutes<1140 AND u.archived=0 AND u.status!='inactive'".($own?' AND s.user_id=?':'').' ORDER BY b.shoot_day,b.minutes,b.id',[$start,$end,...($own?[$a->user['id']]:[])]);
 $out=[];foreach($rows as $r){$shift=booking_shift($r['minutes']);$k=$r['user_id'].'_'.$r['shoot_day'].'_'.$shift;if(!isset($out[$k]))$out[$k]=['id'=>'booking_'.$k,'user_id'=>$r['user_id'],'day'=>$r['shoot_day'],'shift'=>$shift,'name'=>$r['name'],'role'=>$r['role'],'status'=>$r['status'],'archived'=>$r['archived'],'employee_code'=>$r['employee_code'],'source'=>'booking','from_bookings'=>true,'first_minutes'=>$r['minutes'],'expected_at'=>day_time($r['shoot_day'])+$r['minutes']*60000,'late_after'=>day_time($r['shoot_day'])+$r['minutes']*60000+1800000,'booking_ids'=>[]];$out[$k]['booking_ids'][$r['id']]=true;}
 foreach($out as &$r){$r['booking_ids']=array_keys($r['booking_ids']);$r['booking_count']=count($r['booking_ids']);}unset($r);return $out;
}
// Ca có khách được giao (thợ chính hoặc phụ, lịch đang hoạt động) của một người trong tháng: 'YYYY-MM-DD:shift' => true.
function booked_shifts(App $a,int $user,string $month): array {
 $out=[];foreach($a->all("SELECT b.shoot_day,b.minutes FROM {{shoot_bookings}} b JOIN {{booking_staff}} s ON s.booking_id=b.id WHERE s.user_id=? AND b.status='active' AND b.shoot_day>=? AND b.shoot_day<=? AND b.minutes>=540 AND b.minutes<1140",[$user,$month.'-01',$month.'-31']) as $r)if($shift=booking_shift((int)$r['minutes']))$out[$r['shoot_day'].':'.$shift]=true;
 return $out;
}
function expected_shifts(App $a,int $id,string $day): array {$out=[];foreach(['day_first','day_second'] as $shift)if($e=expected_shift($a,$id,$day,$shift))$out[$shift]=$e;return $out;}
function freeze_booking_shift(App $a,int $id,string $day,string $shift): void {
 if($a->one('SELECT user_id FROM {{booking_shift_snapshots}} WHERE user_id=? AND day=? AND shift=?',[$id,$day,$shift]))return;
 $e=expected_shift($a,$id,$day,$shift,false)??['expected_at'=>bounds($day,$shift)['start'],'source'=>'default','booking_ids'=>[]];
 $a->run('INSERT IGNORE INTO {{booking_shift_snapshots}} VALUES(?,?,?,?,?,?,?)',[$id,$day,$shift,$e['expected_at'],$e['source'],json_encode($e['booking_ids'],JSON_THROW_ON_ERROR),now_ms()]);
}
function freeze_existing_booking_day(App $a,string $day): void {
 foreach($a->all("SELECT DISTINCT user_id,shift FROM {{events}} WHERE work_date=? AND type='in' AND shift IN ('day_first','day_second')",[$day]) as $r)freeze_booking_shift($a,$r['user_id'],$day,$r['shift']);
}
// Sheet bỏ trống cả 2 cột thợ: mặc định Huy (chính) - Cường (phụ) như lịch thêm trên web (chủ dự án chốt 30/10/2026).
function relink_booking(App $a,array $row,?array $defaults=null): void {
 $a->run('DELETE FROM {{booking_staff}} WHERE booking_id=?',[$row['id']]);
 if(trim($row['main_alias'])===''&&trim($row['support_alias'])==='')$row=($defaults??booking_default_staff($a))+$row;
 foreach(['main_alias'=>'main','support_alias'=>'support'] as $field=>$role)if($row[$field]!==''){
  $person=$a->one("SELECT u.id FROM {{booking_aliases}} m JOIN {{users}} u ON u.id=m.user_id WHERE m.alias_key=? AND u.archived=0 AND u.status!='inactive'",[alias_key($row[$field])]);
  if($person)$a->run('INSERT IGNORE INTO {{booking_staff}} VALUES(?,?,?)',[$row['id'],$person['id'],$role]);
 }
}
// Thêm lịch trên web (chủ dự án chốt 29/10/2026): mỗi khung giờ 1 khách. Mặc định 399/699 · 1 tiếng (khách tới mới chọn, ô Gói để trống);
// Couple 999, 1499, 2999 phải chọn, chừa 2 tiếng (chụp + make up); Team 349k/người không có số tiếng cố định: chọn giữ chỗ 1–4 tiếng, ghi "Giữ N tiếng".
// Chỉ Admin và người được Admin cấp quyền. Lịch mới vào hàng chờ; Apps Script lấy về mỗi phút, chèn dòng vào tab "Tháng N" rồi báo lại.
const BOOKING_WEB_PACKAGES=[399=>60,699=>60,999=>120,1499=>120,2999=>120];
const BOOKING_TEAM_PRICE=349;
const BOOKING_OPEN_MINUTES=540,BOOKING_CLOSE_MINUTES=1140;
// Lịch thêm trên web mặc định thợ chính Huy, thợ phụ Cường (chủ dự án chốt 29/10/2026); đổi thợ thì sửa trên Sheet.
const BOOKING_DEFAULT_STAFF=['main_alias'=>'huy','support_alias'=>'cuong'];
// Đúng chữ các lựa chọn của cột Makeup và Đặc Biệt (dropdown) trên Sheet; mỗi cột chọn 1, không bắt buộc (chủ dự án chốt 29/10/2026).
const BOOKING_MAKEUP=['nam'=>'Nam','nu'=>'Nữ','toc'=>'Làm Tóc'];
const BOOKING_SPECIAL=['oto'=>'Ô TÔ','ngoai'=>'Ngoại Cảnh','nhom'=>'Nhóm tại Stu','sanpham'=>'Sản Phẩm'];
// Một lựa chọn hoặc để trống; app cũ còn trong bộ nhớ đệm gửi mảng thì nhận mảng tối đa 1 phần tử.
function booking_pick(mixed $value,array $options,string $label): string {
 if(is_array($value)&&array_is_list($value)&&count($value)<=1)$value=$value[0]??'';
 if($value===null||$value==='')return '';if(!is_string($value)||!isset($options[$value]))fail('Lựa chọn '.$label.' không hợp lệ.');return $options[$value];
}
function booking_default_staff(App $a): array {
 $out=['main_alias'=>'','support_alias'=>''];if(!function_exists('task_fold'))return $out;
 $rows=$a->all("SELECT m.alias_label,u.name FROM {{booking_aliases}} m JOIN {{users}} u ON u.id=m.user_id WHERE u.archived=0 AND u.status!='inactive' ORDER BY m.alias_label");
 foreach(BOOKING_DEFAULT_STAFF as $field=>$key){
  foreach($rows as $r)if(task_fold($r['alias_label'])===$key){$out[$field]=$r['alias_label'];break;}
  if($out[$field]==='')foreach($rows as $r){$parts=explode(' ',task_fold($r['name']));if(end($parts)===$key){$out[$field]=$r['alias_label'];break;}}
 }
 return $out;
}
// Gói có số tiếng cố định thì theo gói; Team, ô Gói trống (399/699 chưa chọn) hoặc gói khác: Ghi chú có "Giữ N tiếng" thì N tiếng, không thì 1 tiếng.
function booking_length(string $label,string $note=''): int {
 $known=BOOKING_WEB_PACKAGES[booking_package_price($label)??0]??null;if($known!==null)return $known;
 return preg_match('/gi(?:u|ữ) (?:ch(?:o|ỗ) )?([1-8]) ?(?:tieng|tiếng|h)\b/u',function_exists('task_fold')?task_fold($note):mb_strtolower($note,'UTF-8'),$m)?(int)$m[1]*60:60;
}
// Bỏ dấu, chữ thường để so tiêu đề cột / lựa chọn (task_fold có trong tasks.php; file đó chưa nạp thì dùng Normalizer).
function booking_fold(string $s): string {
 if(function_exists('task_fold'))return task_fold($s);$s=mb_strtolower(trim($s),'UTF-8');
 return preg_replace('/\s+/u',' ',str_replace('đ','d',class_exists('Normalizer')?preg_replace('/\p{Mn}/u','',Normalizer::normalize($s,Normalizer::FORM_D)):$s));
}
// Giá trị form Thêm/Sửa lịch rút từ một lịch: gói, giữ chỗ Team, Make up, Đặc biệt (cột Sheet, không có thì đoạn "Make up: …" trong Ghi chú)
// và phần ghi chú còn lại. Gói khác Couple/1499/2999/Team (trống, 399, 699…) là mặc định; không đổi thì giữ nguyên chữ trên Sheet.
function booking_form(array $row): array {
 $price=booking_package_price($row['package_label']);$package=in_array($price,[999,1499,2999,BOOKING_TEAM_PRICE],true)?$price:null;
 $code=function(?string $value,array $options){if($value===null)return '';foreach($options as $k=>$label)if(booking_fold($label)===booking_fold($value))return $k;return '';};
 $cells=[];foreach(json_decode($row['sheet_fields_json']??'null',true)?:[] as $f){$l=booking_fold((string)($f['label']??''));if(preg_match('/^(make ?up|trang diem)\b/',$l))$cells['makeup']??=(string)$f['value'];elseif($l==='dac biet')$cells['special']??=(string)$f['value'];}
 $makeup=$cells['makeup']??null;$special=$cells['special']??null;$hold=null;$plain=[];
 foreach(preg_split('/\s*·\s*/u',trim($row['note']),-1,PREG_SPLIT_NO_EMPTY) as $part){$f=booking_fold($part);
  if(preg_match('/^make ?up\s*:/',$f)){$makeup??=trim(substr($part,strpos($part,':')+1));continue;}
  if(preg_match('/^dac biet\s*:/',$f)){$special??=trim(substr($part,strpos($part,':')+1));continue;}
  if($package===BOOKING_TEAM_PRICE&&$hold===null&&preg_match('/^giu (?:cho )?([1-8]) ?(?:tieng|h)$/',$f,$m)){$hold=min(240,(int)$m[1]*60);continue;}
  $plain[]=$part;}
 return ['day'=>$row['shoot_day'],'time'=>booking_hm((int)$row['minutes']),'package'=>$package,'hold'=>$package===BOOKING_TEAM_PRICE?($hold??60):null,'customer_name'=>$row['customer_name'],'customer_phone'=>$row['customer_phone'],'makeup'=>$code($makeup,BOOKING_MAKEUP),'special'=>$code($special,BOOKING_SPECIAL),'note'=>implode(' · ',$plain)];
}
// Sửa trên web: cập nhật luôn bản chụp các cột Sheet của lịch để popup và form sửa hiện đúng ngay; lần đồng bộ sau thay bằng dữ liệu thật trên Sheet.
function booking_patch_fields(?string $json,array $set): ?string {
 $fields=json_decode($json??'null',true);if(!is_array($fields))return $json;
 $key=function(string $label){$l=booking_fold($label);foreach(['day'=>['ngay','date'],'time'=>['time','gio','gio chup'],'customer_name'=>['ten khach','khach','khach hang'],'package_label'=>['goi','package'],'customer_phone'=>['sdt','so dien thoai'],'note'=>['ghi chu'],'special'=>['dac biet']] as $k=>$names)if(in_array($l,$names,true))return $k;return preg_match('/^(make ?up|trang diem)\b/',$l)?'makeup':null;};
 $out=[];$seen=[];foreach($fields as $f){$k=$key((string)($f['label']??''));if($k===null||!array_key_exists($k,$set)){$out[]=$f;continue;}$seen[$k]=true;if($set[$k]!=='')$out[]=['label'=>$f['label'],'value'=>$set[$k]];}
 foreach(['customer_name'=>'Tên khách','customer_phone'=>'SĐT','package_label'=>'Gói','makeup'=>'Makeup','special'=>'Đặc Biệt','note'=>'Ghi chú'] as $k=>$label)if(!isset($seen[$k])&&($set[$k]??'')!=='')$out[]=['label'=>$label,'value'=>$set[$k]];
 return json_encode($out,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
}
// Kiểm tra form Thêm/Sửa lịch. Sửa lịch giữ nguyên ngày cũ thì không giới hạn khoảng ngày (lịch tháng trước vẫn sửa tên, ghi chú được).
function booking_input(array $b,?string $keepDay=null): array {
 $range=booking_web_range();$day=valid_day($b['day']??null);$vn=fn($d)=>implode('/',array_reverse(explode('-',$d)));if($day!==$keepDay&&($day<$range['min']||$day>$range['max']))fail('Chỉ chọn ngày chụp từ '.$vn($range['min']).' đến '.$vn($range['max']).'.');
 $time=$b['time']??'';if(!is_string($time)||!preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/D',$time,$m))fail('Giờ chụp phải dạng HH:mm.');$start=(int)$m[1]*60+(int)$m[2];
 // Không chọn gói = 399/699 (khách tới mới chọn), 1 tiếng. Team: giữ chỗ 1–4 tiếng, mặc định 2.
 $price=$b['package']??null;if($price!==null&&(!is_int($price)||!isset(BOOKING_WEB_PACKAGES[$price])&&$price!==BOOKING_TEAM_PRICE))fail('Gói không hợp lệ: để mặc định 399/699 hoặc chọn Couple 999, 1499, 2999, Team 349.');
 $team=$price===BOOKING_TEAM_PRICE;$hold=$team?($b['hold']??120):null;if($team&&!in_array($hold,[60,120,180,240],true))fail('Gói Team: giữ chỗ 1–4 tiếng.');
 // Cọc ghi luôn trong tên khách (chủ dự án chốt 29/10/2026), không có ô riêng.
 $name=input_text($b['customer_name']??null,'Tên khách',1,150);$phone=input_text($b['customer_phone']??'','Số điện thoại',0,50);$plain=input_text($b['note']??'','Ghi chú',0,1900);
 if($phone!==''&&!preg_match('/^[0-9 +().-]{8,20}$/D',$phone))fail('Số điện thoại không hợp lệ.');
 $makeup=booking_pick($b['makeup']??'',BOOKING_MAKEUP,'Make up');$special=booking_pick($b['special']??'',BOOKING_SPECIAL,'Đặc biệt');
 // Ghi chú trên Sheet: "Giữ N tiếng" (gói Team) + ghi chú; Make up / Đặc biệt ghi cột Makeup / Đặc Biệt, Sheet không có cột đó thì script ghép vào đầu Ghi chú như trên web.
 $sheetNote=implode(' · ',array_filter([$team?'Giữ '.intdiv($hold,60).' tiếng':'',$plain]));
 return ['day'=>$day,'start'=>$start,'price'=>$price,'team'=>$team,'hold'=>$hold,'customer_name'=>$name,'customer_phone'=>$phone,'plain'=>$plain,'makeup'=>$makeup,'special'=>$special,'sheet_note'=>$sheetNote,
  'note'=>implode(' · ',array_filter([$special!==''?'Đặc biệt: '.$special:'',$makeup!==''?'Make up: '.$makeup:'',$sheetNote]))];
}
// Mỗi khung giờ 1 khách. Mọi POST đã khóa write_lock trong giao dịch nên hai người thêm/sửa cùng lúc không lọt trùng giờ.
function booking_check_slot(App $a,string $day,int $start,int $end,?string $exclude=null): void {
 if($start<420||$end>1320)fail('Giờ chụp cần trong khoảng 07:00–22:00.');
 foreach(booking_busy($a,$day) as $x)if($x['id']!==$exclude&&$start<$x['end']&&$x['start']<$end)fail('Khung '.booking_hm($start).'–'.booking_hm($end).' trùng lịch '.booking_hm($x['start']).'–'.booking_hm($x['end']).($x['customer_name']!==''?' của '.$x['customer_name']:'').'. Chọn giờ khác.',409);
}
function booking_hm(int $minutes): string {return sprintf('%02d:%02d',intdiv($minutes,60),$minutes%60);}
function booking_sheet_tab(string $day): string {return 'Tháng '.(int)substr($day,5,2);}
function booking_permissions(App $a): array {
 $admin=$a->user['role']==='admin';$granted=(bool)$a->one('SELECT user_id FROM {{booking_creators}} WHERE user_id=?',[$a->user['id']]);
 return ['can_create'=>$admin||$granted,'can_grant'=>$admin];
}
// Từ đầu tháng trước đến 6 tháng tới: Apps Script đọc đúng các tab trong khoảng này nên tên "Tháng N" không bị nhầm năm.
function booking_web_range(): array {$today=local_day(now_ms());return ['min'=>date('Y-m-d',strtotime(substr($today,0,7).'-01T12:00:00Z -1 month')),'max'=>local_day(day_time($today)+183*86400000)];}
function booking_busy(App $a,string $day): array {
 return array_map(fn($r)=>['id'=>$r['id'],'start'=>(int)$r['minutes'],'end'=>(int)$r['minutes']+booking_length($r['package_label'],$r['note']),'customer_name'=>$r['customer_name'],'package_label'=>$r['package_label']],$a->all("SELECT id,minutes,customer_name,package_label,note FROM {{shoot_bookings}} WHERE shoot_day=? AND status='active' ORDER BY minutes,id",[$day]));
}
function booking_enqueue(App $a,string $booking,string $action,string $tab,array $payload=[]): void {
 $a->run('INSERT INTO {{booking_sheet_queue}}(id,booking_id,action,tab,payload_json,created_by,created_at) VALUES(?,?,?,?,?,?,?)',[uuid_v4(),$booking,$action,$tab,json_encode($payload,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),$a->user['id']??null,now_ms()]);
}
// Sửa lịch: lịch web chưa kịp ghi xuống Sheet thì việc ghi lịch mới tự lấy nội dung mới; còn lại thêm việc "update" chỉ ghi các ô được sửa
// (gộp các ô của lần sửa trước chưa ghi xong). Việc ghi lịch mới chưa xong cũng nhận nội dung mới để lần thử lại ghi đúng.
function booking_enqueue_update(App $a,string $booking,string $tab,array $payload,array $changed): void {
 $create=$a->one("SELECT id,status,sent_at FROM {{booking_sheet_queue}} WHERE booking_id=? AND action='create' AND status IN ('pending','failed') ORDER BY created_at DESC LIMIT 1",[$booking]);
 if($create){$a->run('UPDATE {{booking_sheet_queue}} SET payload_json=? WHERE id=?',[json_encode(array_diff_key($payload,['from_day'=>1]),JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),$create['id']]);if($create['status']==='pending'&&$create['sent_at']===null)return;}
 $first=null;foreach($a->all("SELECT id,payload_json FROM {{booking_sheet_queue}} WHERE booking_id=? AND action='update' AND status IN ('pending','failed') ORDER BY created_at,id",[$booking]) as $q){$old=json_decode($q['payload_json'],true)?:[];$first??=$old['from_day']??null;$changed=array_values(array_unique([...($old['changed']??[]),...$changed]));$a->run("UPDATE {{booking_sheet_queue}} SET status='skipped',done_at=? WHERE id=?",[now_ms(),$q['id']]);}
 booking_enqueue($a,$booking,'update',$tab,['from_day'=>$first??$payload['from_day']??null]+$payload+['changed'=>$changed]);
}
// Việc ghi Sheet chưa xong của từng lịch (chờ hoặc lỗi), để thẻ và popup báo cho người thêm lịch.
function booking_sheet_states(App $a,array $ids): array {
 if(!$ids)return [];$out=[];
 foreach($a->all("SELECT booking_id,action,status,error,created_at FROM {{booking_sheet_queue}} WHERE status IN ('pending','failed') AND booking_id IN (".implode(',',array_fill(0,count($ids),'?')).') ORDER BY created_at',$ids) as $q)$out[$q['booking_id']]=['action'=>$q['action'],'status'=>$q['status'],'error'=>$q['error']];
 return $out;
}
function booking_queue_skip(App $a,string $booking,bool $unsentOnly=false): int {
 return $a->run("UPDATE {{booking_sheet_queue}} SET status='skipped',done_at=? WHERE booking_id=? AND status IN ('pending','failed')".($unsentOnly?" AND action='create' AND sent_at IS NULL":''),[now_ms(),$booking])->rowCount();
}
// Apps Script hỏi việc cần ghi: lịch mới (create), lịch sửa (update: chỉ các ô trong changed), lịch hủy (cancel) và lịch xóa trên web (delete: xóa dòng).
function booking_queue_poll(App $a): array {
 $out=[];
 foreach($a->all("SELECT * FROM {{booking_sheet_queue}} WHERE status='pending' ORDER BY created_at,id LIMIT 20") as $q){
  $bk=$a->one('SELECT * FROM {{shoot_bookings}} WHERE id=?',[$q['booking_id']]);
  if(!$bk||($bk['status']==='deleted')!==($q['action']==='delete')){$a->run("UPDATE {{booking_sheet_queue}} SET status='skipped',done_at=? WHERE id=?",[now_ms(),$q['id']]);continue;}
  $a->run('UPDATE {{booking_sheet_queue}} SET sent_at=? WHERE id=?',[now_ms(),$q['id']]);$payload=json_decode($q['payload_json'],true)?:[];
  $out[]=['id'=>$q['id'],'booking_id'=>$q['booking_id'],'action'=>$q['action'],'tab'=>$q['action']==='create'?$q['tab']:$bk['source_tab'],'day'=>$bk['shoot_day'],'time'=>booking_hm((int)$bk['minutes']),'length'=>booking_length($bk['package_label'],$bk['note']),
   // Make up, Đặc biệt gửi riêng: Sheet có cột đó thì ghi vào cột, không thì script ghép vào Ghi chú.
   'values'=>['customer_name'=>$bk['customer_name'],'customer_phone'=>$bk['customer_phone'],'package_label'=>$bk['package_label'],'main_alias'=>$bk['main_alias'],'support_alias'=>$bk['support_alias'],'deposit_label'=>$bk['deposit_label'],'note'=>$payload['note']??$bk['note'],'makeup'=>$payload['makeup']??'','special'=>$payload['special']??'']]+($q['action']==='update'?['changed'=>$payload['changed']??[],'from_day'=>$payload['from_day']??null]:[]);
 }
 return ['queue'=>$out];
}
function booking_queue_ack(App $a,array $acks,string $sheet): array {
 if(!array_is_list($acks)||count($acks)>50)fail('Danh sách xác nhận không hợp lệ.');$done=0;
 foreach($acks as $k){
  if(!is_array($k))fail('Danh sách xác nhận không hợp lệ.');$q=$a->one('SELECT * FROM {{booking_sheet_queue}} WHERE id=?',[entry_uuid($k['id']??null)]);if(!$q||$q['status']!=='pending')continue;
  if(($k['ok']??null)===true){
   $a->run("UPDATE {{booking_sheet_queue}} SET status='done',error='',done_at=? WHERE id=?",[now_ms(),$q['id']]);$done++;
   // Lịch mới, hoặc lịch sửa đổi ngày/giờ làm dòng chuyển chỗ (có thể sang tab tháng khác): lưu tab và dòng mới.
   if(in_array($q['action'],['create','update'],true)){$tab=input_text($k['tab']??$q['tab'],'Tên tab',1,150);$row=is_int($k['row']??null)&&$k['row']>0?$k['row']:0;$a->run("UPDATE {{shoot_bookings}} SET source_sheet=?,source_tab=?,source_row=? WHERE id=? AND source_sheet IN (?,'web')",[$sheet,$tab,$row,$q['booking_id'],$sheet]);}
  }else{$error=trim(preg_replace('/[\x00-\x1F\x7F]+/u',' ',is_string($k['error']??null)&&preg_match('//u',$k['error'])?$k['error']:'Apps Script không ghi được.'));
   // Lịch đã xóa không còn popup để bấm Thử ghi lại: giữ việc xóa dòng ở hàng chờ, script tự thử lại lần sau.
   if($q['action']==='delete')$a->run('UPDATE {{booking_sheet_queue}} SET error=?,sent_at=NULL WHERE id=?',[mb_substr($error,0,300),$q['id']]);
   else $a->run("UPDATE {{booking_sheet_queue}} SET status='failed',error=?,done_at=? WHERE id=?",[mb_substr($error,0,300),now_ms(),$q['id']]);}
 }
 return ['acked'=>count($acks),'done'=>$done];
}
function import_bookings(App $a,array $b,string $sheet,?int $actor): array {
 $rows=$b['rows']??null;$tab=input_text($b['tab']??'','Tên tab',1,150);
 if(!is_array($rows)||!array_is_list($rows)||count($rows)>1000)fail('Mỗi lần đồng bộ tối đa 1.000 lịch.');
 $seen=[];$normalized=[];
 foreach($rows as $i=>$r){if(!is_array($r))fail('Dòng lịch không hợp lệ.');$id=entry_uuid($r['id']??null);if(isset($seen[$id]))fail('Trùng mã lịch trong dữ liệu gửi lên.');$seen[$id]=true;
  $day=valid_day($r['day']??null);if($day<'2020-01-01')fail('Thiếu năm hoặc ngày lịch không hợp lệ.');$time=$r['time']??'';
  if(!is_string($time)||!preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/D',$time,$m))fail('Giờ lịch phải dạng HH:mm.');
  $status=$r['status']??'active';if(!in_array($status,['active','cancelled'],true))fail('Trạng thái lịch không hợp lệ.');
  $row=['id'=>$id,'source_sheet'=>$sheet,'source_tab'=>$tab,'source_row'=>is_int($r['source_row']??null)&&$r['source_row']>0?$r['source_row']:$i+1,'shoot_day'=>$day,'minutes'=>(int)$m[1]*60+(int)$m[2]];
  foreach(['package_label'=>150,'customer_name'=>150,'customer_phone'=>50,'main_alias'=>150,'support_alias'=>150,'note'=>2000,'deposit_label'=>50,'received_label'=>50] as $field=>$max)$row[$field]=input_text($r[$field]??'',$field,0,$max);
  // Toàn bộ ô có dữ liệu trên dòng Sheet (tiêu đề → giá trị), chỉ Quản lý/Admin xem. Bản Apps Script cũ không gửi thì giữ nguyên dữ liệu đã lưu.
  if(array_key_exists('sheet_fields',$r)){$fields=$r['sheet_fields'];if(!is_array($fields)||!array_is_list($fields)||count($fields)>80)fail('Dữ liệu cột Sheet không hợp lệ.');$clean=[];
   foreach($fields as $f){if(!is_array($f))fail('Dữ liệu cột Sheet không hợp lệ.');$value=input_text($f['value']??'','Giá trị cột Sheet',0,2000);if($value==='')continue;$clean[]=['label'=>input_text($f['label']??'','Tiêu đề cột Sheet',1,100),'value'=>$value];}
   $row['sheet_fields_json']=json_encode($clean,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);}
  $row['status']=$status;$old=$a->one('SELECT * FROM {{shoot_bookings}} WHERE id=?',[$id]);
  // Tab theo tháng: dòng được chuyển sang tab khác của cùng Sheet thì đổi tab theo; lịch thêm trên web khi chưa nối Sheet ('web') nhận Sheet này.
  if($old&&$old['source_sheet']!==$sheet&&$old['source_sheet']!=='web')fail('Mã lịch thuộc Sheet khác. Không thay đổi nguồn của một mã lịch.',409);
  $normalized[]=[$row,$old];
 }
 $pending=[];if($seen)foreach($a->all("SELECT id,booking_id,action FROM {{booking_sheet_queue}} WHERE (status='pending' OR status='failed' AND action='update') AND booking_id IN (".implode(',',array_fill(0,count($seen),'?')).')',array_keys($seen)) as $q)$pending[$q['booking_id']][]=$q;
 $changed=0;$created=0;
 foreach($normalized as [$row,$old]){
  // Lịch Quản lý/Admin đã xóa trên web không được Sheet đồng bộ lại.
  if($old&&$old['status']==='deleted')continue;
  // Dòng đã lên Sheet thì xong việc "create". Hủy/sửa trên web chưa ghi xuống Sheet: giữ dữ liệu web, không để Sheet cũ ghi đè.
  $waiting=false;foreach($pending[$row['id']]??[] as $q){if($q['action']==='create'||$row['status']==='cancelled')$a->run("UPDATE {{booking_sheet_queue}} SET status='done',error='',done_at=? WHERE id=?",[now_ms(),$q['id']]);else $waiting=true;}
  if($waiting)continue;
  if($old){$diff=array_keys(array_filter($row,fn($v,$k)=>$old[$k]!==$v,ARRAY_FILTER_USE_BOTH));if(!$diff)continue;
   // Chèn/xóa dòng phía trên chỉ làm đổi số dòng: cập nhật lặng lẽ, không ghi lịch sử.
   if($diff===['source_row']){$a->run('UPDATE {{shoot_bookings}} SET source_row=? WHERE id=?',[$row['source_row'],$row['id']]);continue;}}
  freeze_existing_booking_day($a,$row['shoot_day']);if($old&&$old['shoot_day']!==$row['shoot_day'])freeze_existing_booking_day($a,$old['shoot_day']);
  if($old){$cols=array_values(array_diff(array_keys($row),['id']));$a->run('UPDATE {{shoot_bookings}} SET '.implode(',',array_map(fn($c)=>$c.'=?',$cols)).',updated_at=? WHERE id=?',[...array_map(fn($c)=>$row[$c],$cols),now_ms(),$row['id']]);$changed++;}
  else{$cols=array_keys($row);$a->run('INSERT INTO {{shoot_bookings}} ('.implode(',',$cols).',created_at,updated_at) VALUES('.implode(',',array_fill(0,count($cols)+2,'?')).')',[...array_values($row),now_ms(),now_ms()]);$created++;}
  relink_booking($a,$row);$a->run('INSERT INTO {{booking_audit}}(booking_id,actor_id,action,before_json,after_json,time) VALUES(?,?,?,?,?,?)',[$row['id'],$actor,$old?'updated':'created',$old?json_encode($old,JSON_THROW_ON_ERROR):null,json_encode($row,JSON_THROW_ON_ERROR),now_ms()]);
 }
 // Missing rows are deliberately NOT cancelled. Deletion/sorting/partial batches cannot erase history.
 $a->meta('sheet_sync_last',json_encode(['time'=>now_ms(),'sheet'=>$sheet,'tab'=>$tab,'received'=>count($rows),'created'=>$created,'updated'=>$changed],JSON_THROW_ON_ERROR));
 return ['received'=>count($rows),'created'=>$created,'updated'=>$changed];
}
function sheet_receiver(App $a,string $path,array $b,string $method): ?array {
 if($path!=='/sheet-sync/receive')return null;if($method!=='POST')fail('Chỉ nhận POST.',405);
 $file=$a->storage.'/sheet-sync-key';if(!is_file($file))fail('Kết nối Sheet chưa bật.',403);$key=trim(file_get_contents($file));
 $timestamp=$_SERVER['HTTP_X_STUDIO_TIME']??'';$nonce=$_SERVER['HTTP_X_STUDIO_NONCE']??'';$signature=$_SERVER['HTTP_X_STUDIO_SIGNATURE']??'';
 if(!ctype_digit($timestamp)||abs(now_ms()-(int)$timestamp)>300000||!preg_match('/^[a-f0-9-]{36}$/iD',$nonce)||!preg_match('/^[a-f0-9]{64}$/D',$signature))fail('Chữ ký đồng bộ không hợp lệ hoặc hết hạn.',403);
 $raw=file_get_contents('php://input');if(!hash_equals(hash_hmac('sha256',$timestamp.'.'.$nonce.'.'.$raw,$key),$signature))fail('Chữ ký đồng bộ không đúng.',403);
 $sheet=$a->meta('sheet_sync_id');if(!$sheet||($b['sheet_id']??null)!==$sheet)fail('Sheet không khớp kết nối đã cấu hình.',403);
 if($a->one('SELECT nonce FROM {{sheet_sync_nonces}} WHERE nonce=?',[$nonce]))fail('Yêu cầu đã được nhận, không xử lý trùng.',409);
 $a->run('DELETE FROM {{sheet_sync_nonces}} WHERE created_at<?',[now_ms()-600000]);$a->run('INSERT INTO {{sheet_sync_nonces}} VALUES(?,?)',[$nonce,now_ms()]);
 if(($b['poll']??null)===true)return [200,booking_queue_poll($a)];
 if(array_key_exists('ack',$b))return [200,booking_queue_ack($a,is_array($b['ack'])?$b['ack']:[],$sheet)];
 return [200,import_bookings($a,$b,$sheet,null)];
}
function attendance_issues(App $a,string $month,?int $person=null): array {
 valid_month($month);$first=$month.'-01';$last=date('Y-m-t',strtotime($first));$end=min($last,local_day(now_ms()));$now=now_ms();$issues=[];
 $people=$a->all("SELECT id,name,role FROM {{users}} WHERE archived=0".($person!==null?' AND id=?':''),$person!==null?[$person]:[]);
 foreach($people as $p){$events=$a->events($p['id']);$lateCount=0;
  for($t=day_time($first);$t<=day_time($end);$t+=86400000){$day=local_day($t);
   foreach(['day_first','day_second'] as $shift){$e=expected_shift($a,$p['id'],$day,$shift);if(!$e||$e['source']==='default')continue;$expected=$e['expected_at'];$w=bounds($day,$shift);
    $own=array_values(array_filter($events,fn($x)=>$x['work_date']===$day&&$x['shift']===$shift));$ins=array_values(array_filter($own,fn($x)=>$x['type']==='in'));$outs=array_values(array_filter($own,fn($x)=>$x['type']==='out'));
    $types=[];if($ins&&$ins[0]['time']>$expected+1800000){$late=$ins[0]['time']-$expected;$types[]=['kind'=>'late','late_minutes'=>(int)floor($late/60000),'over_30'=>$late>1800000];}
    if($now>$w['end']+1800000&&(!$ins||!$outs))$types[]=['kind'=>!$ins&&!$outs?'missing_both':'missing_punch','late_minutes'=>0,'over_30'=>false];
    foreach($types as $type){$key=$p['id'].'_'.$day.'_'.$shift.'_'.$type['kind'];$existing=$a->one('SELECT r.id,r.status FROM {{attendance_issue_links}} l JOIN {{event_log}} r ON r.id=l.event_log_id WHERE l.source_key=?',[$key]);$ordinal=null;
     if($type['kind']==='late'&&($existing['status']??'')!=='void')$ordinal=++$lateCount;
     $deduction=$type['kind']==='late'?($ordinal===null||$ordinal===1?0:($ordinal===2?20:($ordinal===3?100:300))):20;
     $issues[]=$p+['source_key'=>$key,'day'=>$day,'shift'=>$shift,'expected_at'=>$expected,'late_after'=>$expected+1800000,'expected_source'=>$e['source'],'frozen'=>$e['frozen'],'actual_in'=>$ins[0]['time']??null,'actual_out'=>$outs[0]['time']??null,'late_ordinal'=>$ordinal,'suggested_deduction'=>$deduction,'event_log_id'=>$existing['id']??null,'event_log_status'=>$existing['status']??null]+$type;
    }
   }
  }
 }
 usort($issues,fn($x,$y)=>[$y['day'],$y['expected_at'],$x['id']]<=>[$x['day'],$x['expected_at'],$y['id']]);return $issues;
}
// Calendar visibility is separate from attendance assignment and edit permissions.
function booking_view_person(App $a): ?int {
 $scope=$_GET['scope']??'all';if(!in_array($scope,['all','mine'],true))fail('Phạm vi lịch không hợp lệ.');
 return $scope==='mine'?$a->user['id']:null;
}
// Lịch đã có trước khi áp thợ mặc định: gán lại một lần (giữ mốc đối soát của ngày đã checkin như khi ghép tên thợ).
function booking_default_staff_once(App $a): void {
 if($a->meta('booking_default_staff')==='1')return;
 $a->tx(function()use($a){if($a->meta('booking_default_staff')==='1')return;$defaults=booking_default_staff($a);$rows=$a->all("SELECT * FROM {{shoot_bookings}} WHERE TRIM(main_alias)='' AND TRIM(support_alias)=''");
  foreach(array_unique(array_column($rows,'shoot_day')) as $day)freeze_existing_booking_day($a,$day);foreach($rows as $row)relink_booking($a,$row,$defaults);$a->meta('booking_default_staff','1');});
}
function booking_routes(App $a,string $path,array $b,string $method): ?array {
 if(str_starts_with($path,'/bookings'))booking_default_staff_once($a);
 if($path==='/attendance-issues'&&$method==='GET'){$a->requireManager();$month=valid_month($_GET['month']??substr(local_day(now_ms()),0,7));$person=empty($_GET['user_id'])?null:filter_var($_GET['user_id'],FILTER_VALIDATE_INT);if($person===false)fail('Nhân viên không hợp lệ.');$rows=attendance_issues($a,$month,$person);if(!empty($_GET['date'])){$day=valid_day($_GET['date']);$rows=array_values(array_filter($rows,fn($r)=>$r['day']===$day));}return [200,['rows'=>$rows,'month'=>$month,'can_record_log'=>log_permissions($a)['can_manage']]];}
 if($path==='/attendance-issues/log'&&$method==='POST'){
  if(!log_permissions($a)['can_manage'])fail('Bạn chưa được Admin cấp quyền chỉnh sửa Event log.',403);
  $a->requireManager();$key=input_text($b['source_key']??null,'Mã sự việc',5,150);$month=valid_month($b['month']??null);$issue=null;
  foreach(attendance_issues($a,$month) as $r)if($r['source_key']===$key){$issue=$r;break;}if(!$issue)fail('Sự cố không còn trong dữ liệu đối soát. Tải lại.',409);
  if($issue['event_log_id'])return [200,['id'=>$issue['event_log_id'],'already_saved'=>true]];
  $note=input_text($b['description']??null,'Nội dung xác nhận',5,2000);$id=uuid_v4();$result=log_routes($a,'/event-log',['id'=>$id,'user_id'=>$issue['id'],'event_date'=>$issue['day'],'category'=>$issue['kind']==='late'?'late':'missing_punch','description'=>$note,'deduction'=>$b['deduction']??0,'rule_id'=>$b['rule_id']??null],'POST');
  $a->run('INSERT INTO {{attendance_issue_links}} VALUES(?,?)',[$key,$id]);return $result;
 }
 if($path==='/sheet-sync/status'&&$method==='GET'){$a->requireManager();$last=json_decode($a->meta('sheet_sync_last')??'null',true);$aliases=$a->all('SELECT m.*,u.name FROM {{booking_aliases}} m JOIN {{users}} u ON u.id=m.user_id ORDER BY m.alias_label');return [200,['sheet_id'=>$a->meta('sheet_sync_id')??'1JCyjUuLzCJy05yRvjWMKEGmwbvCEAiSjnyi1cNcuZow','configured'=>is_file($a->storage.'/sheet-sync-key'),'last_sync'=>$last,'aliases'=>$aliases,'people'=>finance_people($a),'can_configure'=>$a->user['role']==='admin','creators'=>$a->user['role']==='admin'?array_map('intval',array_column($a->all('SELECT user_id FROM {{booking_creators}}'),'user_id')):[]]];}
 if($path==='/sheet-sync/configure'&&$method==='POST'){
  $a->requireAdmin();$sheet=$b['sheet_id']??'';if(!is_string($sheet)||!preg_match('/^[A-Za-z0-9_-]{20,80}$/D',$sheet))fail('Mã Google Sheet không hợp lệ.');
  $key=bin2hex(random_bytes(32));$file=$a->storage.'/sheet-sync-key';if(file_put_contents($file,$key,LOCK_EX)===false)fail('Không lưu được khóa kết nối.',500);chmod($file,0600);$a->meta('sheet_sync_id',$sheet);$a->audit($a->user['id'],'configure_sheet_sync');return [200,['key'=>$key,'sheet_id'=>$sheet,'endpoint'=>$a->config['app_url'].'/api.php?route=%2Fsheet-sync%2Freceive']];
 }
 if($path==='/booking-aliases'&&$method==='POST'){
  $a->requireManager();$label=input_text($b['alias']??null,'Tên trên Sheet',1,150);$key=alias_key($label);if(!is_int($b['user_id']??null))fail('Chọn nhân viên.');$a->target($b['user_id']);
  foreach($a->all("SELECT DISTINCT shoot_day FROM {{shoot_bookings}} WHERE status='active'") as $d)freeze_existing_booking_day($a,$d['shoot_day']);
  $a->run('INSERT INTO {{booking_aliases}} VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE alias_label=VALUES(alias_label),user_id=VALUES(user_id),updated_by=VALUES(updated_by),updated_at=VALUES(updated_at)',[$key,$label,$b['user_id'],$a->user['id'],now_ms()]);
  foreach($a->all('SELECT * FROM {{shoot_bookings}}') as $row)relink_booking($a,$row);
  $a->run('INSERT INTO {{booking_audit}}(booking_id,actor_id,action,before_json,after_json,time) VALUES(NULL,?,?,NULL,?,?)',[$a->user['id'],'map_alias',json_encode(['alias'=>$label,'user_id'=>$b['user_id']],JSON_THROW_ON_ERROR),now_ms()]);return [200,['ok'=>true]];
 }
 if($path==='/bookings/slots'&&$method==='GET'){
  if(!booking_permissions($a)['can_create'])fail('Bạn chưa được Admin cấp quyền thêm lịch.',403);$day=valid_day($_GET['day']??null);
  return [200,['day'=>$day,'busy'=>booking_busy($a,$day),'packages'=>array_map(fn($price,$minutes)=>['price'=>$price,'minutes'=>$minutes],array_keys(BOOKING_WEB_PACKAGES),BOOKING_WEB_PACKAGES),'open'=>BOOKING_OPEN_MINUTES,'close'=>BOOKING_CLOSE_MINUTES,'range'=>booking_web_range(),'tab'=>booking_sheet_tab($day),'staff'=>booking_default_staff($a),'makeup'=>BOOKING_MAKEUP,'special'=>BOOKING_SPECIAL,'sheet_connected'=>is_file($a->storage.'/sheet-sync-key')]];
 }
 if($path==='/bookings/create'&&$method==='POST'){
  if(!booking_permissions($a)['can_create'])fail('Bạn chưa được Admin cấp quyền thêm lịch.',403);
  $in=booking_input($b);booking_check_slot($a,$in['day'],$in['start'],$in['start']+($in['team']?$in['hold']:($in['price']!==null?BOOKING_WEB_PACKAGES[$in['price']]:60)));
  $row=['id'=>uuid_v4(),'source_sheet'=>$a->meta('sheet_sync_id')?:'web','source_tab'=>booking_sheet_tab($in['day']),'source_row'=>0,'shoot_day'=>$in['day'],'minutes'=>$in['start'],'package_label'=>$in['price']===null?'':(string)$in['price'],'customer_name'=>$in['customer_name'],'customer_phone'=>$in['customer_phone'],'deposit_label'=>'']+booking_default_staff($a)+['note'=>$in['note'],'received_label'=>'','status'=>'active'];
  freeze_existing_booking_day($a,$in['day']);$cols=array_keys($row);
  $a->run('INSERT INTO {{shoot_bookings}} ('.implode(',',$cols).',created_at,updated_at) VALUES('.implode(',',array_fill(0,count($cols)+2,'?')).')',[...array_values($row),now_ms(),now_ms()]);
  relink_booking($a,$row);booking_enqueue($a,$row['id'],'create',$row['source_tab'],['makeup'=>$in['makeup'],'special'=>$in['special'],'note'=>$in['sheet_note']]);
  $a->run('INSERT INTO {{booking_audit}}(booking_id,actor_id,action,before_json,after_json,time) VALUES(?,?,?,?,?,?)',[$row['id'],$a->user['id'],'created_web',null,json_encode($row,JSON_THROW_ON_ERROR),now_ms()]);
  return [200,['id'=>$row['id'],'tab'=>$row['source_tab'],'sheet_connected'=>is_file($a->storage.'/sheet-sync-key')]];
 }
 // Sửa lịch (chủ dự án yêu cầu 30/10/2026): cùng quyền Thêm/Hủy lịch, cùng form Thêm lịch; chỉ lưu và ghi xuống Sheet những ô thật sự đổi.
 // Thợ không sửa ở đây (đổi thợ sửa trên Sheet).
 if($path==='/bookings/update'&&$method==='POST'){
  if(!booking_permissions($a)['can_create'])fail('Bạn chưa được Admin cấp quyền sửa lịch.',403);
  $id=entry_uuid($b['id']??null);$old=$a->one('SELECT * FROM {{shoot_bookings}} WHERE id=?',[$id]);
  if(!$old||$old['status']==='deleted')fail('Không tìm thấy lịch.',404);if($old['status']!=='active')fail('Lịch đã hủy, không sửa được.',409);
  $was=booking_form($old);$in=booking_input($b,$old['shoot_day']);
  $changed=array_keys(array_filter(['day'=>$in['day']!==$old['shoot_day'],'time'=>$in['start']!==(int)$old['minutes'],'customer_name'=>$in['customer_name']!==$old['customer_name'],'customer_phone'=>$in['customer_phone']!==$old['customer_phone'],
   'package_label'=>$in['price']!==$was['package'],'makeup'=>$in['makeup']!==(BOOKING_MAKEUP[$was['makeup']]??''),'special'=>$in['special']!==(BOOKING_SPECIAL[$was['special']]??''),
   // Ghi chú trên Sheet gồm "Giữ N tiếng" của gói Team: đổi giữ chỗ hoặc đổi sang/khỏi gói Team cũng là sửa Ghi chú.
   'note'=>$in['plain']!==$was['note']||$in['price']!==$was['package']&&in_array(BOOKING_TEAM_PRICE,[$in['price'],$was['package']],true)||$in['team']&&$in['hold']!==$was['hold']]));
  if(!$changed)return [200,['ok'=>true,'changed'=>[]]];
  $row=['shoot_day'=>$in['day'],'minutes'=>$in['start'],'customer_name'=>$in['customer_name'],'customer_phone'=>$in['customer_phone'],'package_label'=>in_array('package_label',$changed,true)?($in['price']===null?'':(string)$in['price']):$old['package_label'],'note'=>array_intersect($changed,['makeup','special','note'])?$in['note']:$old['note']];
  booking_check_slot($a,$row['shoot_day'],$row['minutes'],$row['minutes']+booking_length($row['package_label'],$row['note']),$id);
  $set=['day'=>implode('/',array_slice(array_reverse(explode('-',$in['day'])),0,2)),'time'=>booking_hm($in['start']),'customer_name'=>$in['customer_name'],'customer_phone'=>$in['customer_phone'],'package_label'=>$row['package_label'],'makeup'=>$in['makeup'],'special'=>$in['special'],'note'=>$in['sheet_note']];
  if($old['sheet_fields_json']!==null)$row['sheet_fields_json']=booking_patch_fields($old['sheet_fields_json'],array_intersect_key($set,array_flip($changed)));
  freeze_existing_booking_day($a,$old['shoot_day']);if($row['shoot_day']!==$old['shoot_day'])freeze_existing_booking_day($a,$row['shoot_day']);
  $a->run('UPDATE {{shoot_bookings}} SET '.implode(',',array_map(fn($c)=>$c.'=?',array_keys($row))).',updated_at=? WHERE id=?',[...array_values($row),now_ms(),$id]);
  booking_enqueue_update($a,$id,$old['source_tab'],['makeup'=>$in['makeup'],'special'=>$in['special'],'note'=>$in['sheet_note'],'from_day'=>$old['shoot_day']],$changed);
  $a->run('INSERT INTO {{booking_audit}}(booking_id,actor_id,action,before_json,after_json,time) VALUES(?,?,?,?,?,?)',[$id,$a->user['id'],'updated_web',json_encode($old,JSON_THROW_ON_ERROR),json_encode($row+['changed'=>$changed],JSON_THROW_ON_ERROR),now_ms()]);
  return [200,['ok'=>true,'changed'=>$changed,'tab'=>$old['source_tab'],'sheet_connected'=>is_file($a->storage.'/sheet-sync-key')]];
 }
 if($path==='/bookings/cancel'&&$method==='POST'){
  if(!booking_permissions($a)['can_create'])fail('Bạn chưa được Admin cấp quyền hủy lịch.',403);
  $id=entry_uuid($b['id']??null);$reason=input_text($b['reason']??'','Lý do hủy',0,300);$old=$a->one('SELECT * FROM {{shoot_bookings}} WHERE id=?',[$id]);
  if(!$old||$old['status']==='deleted')fail('Không tìm thấy lịch.',404);if($old['status']!=='active')fail('Lịch này đã hủy.',409);
  freeze_existing_booking_day($a,$old['shoot_day']);$a->run("UPDATE {{shoot_bookings}} SET status='cancelled',updated_at=? WHERE id=?",[now_ms(),$id]);
  // Lịch web chưa kịp ghi xuống Sheet: bỏ luôn việc ghi, khỏi chèn rồi lại hủy.
  if(!booking_queue_skip($a,$id,true))booking_enqueue($a,$id,'cancel',$old['source_tab'],['reason'=>$reason]);
  $a->run('INSERT INTO {{booking_audit}}(booking_id,actor_id,action,before_json,after_json,time) VALUES(?,?,?,?,?,?)',[$id,$a->user['id'],'cancelled_web',json_encode($old,JSON_THROW_ON_ERROR),json_encode(['status'=>'cancelled','reason'=>$reason],JSON_THROW_ON_ERROR),now_ms()]);
  return [200,['ok'=>true]];
 }
 if($path==='/bookings/sheet-retry'&&$method==='POST'){
  if(!booking_permissions($a)['can_create'])fail('Bạn chưa được Admin cấp quyền thêm lịch.',403);$id=entry_uuid($b['id']??null);
  $n=$a->run("UPDATE {{booking_sheet_queue}} SET status='pending',error='',sent_at=NULL,done_at=NULL WHERE booking_id=? AND status='failed'",[$id])->rowCount();if(!$n)fail('Lịch này không có lỗi ghi Sheet.',409);
  return [200,['ok'=>true]];
 }
 if($path==='/bookings/creators'&&$method==='POST'){
  $a->requireAdmin();if(!is_int($b['user_id']??null)||!is_bool($b['enabled']??null))fail('Quyền thêm lịch không hợp lệ.');
  $target=$a->target($b['user_id']);if($target['role']==='admin')fail('Admin luôn được thêm lịch.');
  if($b['enabled'])$a->run('INSERT INTO {{booking_creators}} VALUES(?,?,?) ON DUPLICATE KEY UPDATE granted_by=VALUES(granted_by),granted_at=VALUES(granted_at)',[$target['id'],$a->user['id'],now_ms()]);else $a->run('DELETE FROM {{booking_creators}} WHERE user_id=?',[$target['id']]);
  $a->audit($target['id'],$b['enabled']?'grant_booking_create':'revoke_booking_create');return [200,['ok'=>true]];
 }
 if($path==='/bookings/import'&&$method==='POST'){$a->requireManager();$sheet=input_text($b['sheet_id']??'manual','Mã nguồn',1,80);return [200,import_bookings($a,$b,$sheet,$a->user['id'])];}
 if($path==='/bookings/delete'&&$method==='POST'){
  // Xóa mềm: ẩn khỏi lịch, thống kê, đối soát và nhắc Telegram; giữ dòng và nhật ký trước/sau trong database.
  $a->requireManager();$id=entry_uuid($b['id']??null);$reason=input_text($b['reason']??'','Lý do xóa',0,300);
  $old=$a->one('SELECT * FROM {{shoot_bookings}} WHERE id=?',[$id]);if(!$old)fail('Không tìm thấy lịch.',404);if($old['status']==='deleted')fail('Lịch này đã bị xóa.',409);
  freeze_existing_booking_day($a,$old['shoot_day']);$a->run("UPDATE {{shoot_bookings}} SET status='deleted',updated_at=? WHERE id=?",[now_ms(),$id]);
  // Xóa luôn dòng trên Google Sheet. Lịch web chưa kịp ghi xuống Sheet thì chỉ bỏ việc ghi.
  if(!booking_queue_skip($a,$id,true)){booking_queue_skip($a,$id);booking_enqueue($a,$id,'delete',$old['source_tab'],['reason'=>$reason]);}
  $a->run('INSERT INTO {{booking_audit}}(booking_id,actor_id,action,before_json,after_json,time) VALUES(?,?,?,?,?,?)',[$id,$a->user['id'],'deleted',json_encode($old,JSON_THROW_ON_ERROR),json_encode(['status'=>'deleted','reason'=>$reason],JSON_THROW_ON_ERROR),now_ms()]);
  return [200,['ok'=>true]];
 }
 if($path==='/bookings/days'&&$method==='GET'){
  $start=valid_day($_GET['start']??'');$end=valid_day($_GET['end']??'');if(day_time($end)<day_time($start)||day_time($end)-day_time($start)>61*86400000)fail('Chỉ xem tối đa 62 ngày mỗi lần.');$person=booking_view_person($a);
  $days=$a->all("SELECT b.shoot_day day,COUNT(*) count FROM {{shoot_bookings}} b WHERE b.status='active' AND b.shoot_day>=? AND b.shoot_day<=?".($person!==null?' AND EXISTS(SELECT 1 FROM {{booking_staff}} own WHERE own.booking_id=b.id AND own.user_id=?)':'').' GROUP BY b.shoot_day ORDER BY b.shoot_day',[$start,$end,...($person!==null?[$person]:[])]);
  return [200,['days'=>$days,'start'=>$start,'end'=>$end]];
 }
 if($path==='/bookings/summary'&&$method==='GET'){
  $today=local_day(now_ms());$minute=(int)date('G')*60+(int)date('i');$person=booking_view_person($a);
  $where=" WHERE b.status!='deleted'".($person!==null?' AND EXISTS(SELECT 1 FROM {{booking_staff}} own WHERE own.booking_id=b.id AND own.user_id=?)':'');
  $summary=$a->one("SELECT COALESCE(SUM(b.status='active'),0) active,COALESCE(SUM(b.status='active' AND b.shoot_day=?),0) today,COALESCE(SUM(b.status='active' AND (b.shoot_day>? OR (b.shoot_day=? AND b.minutes>=?))),0) upcoming,COALESCE(SUM(b.status='active' AND NOT EXISTS(SELECT 1 FROM {{booking_staff}} assigned WHERE assigned.booking_id=b.id)),0) unassigned,COALESCE(SUM(b.status='cancelled'),0) cancelled FROM {{shoot_bookings}} b".$where,[$today,$today,$today,$minute,...($person!==null?[$person]:[])]);
  foreach($summary as &$value)$value=(int)$value;unset($value);return [200,$summary+['day'=>$today,'scope'=>$person!==null?'my_bookings':'all_synced_bookings']];
 }
 if($path==='/bookings'&&$method==='GET'){
  [$start,$end]=finance_range();$where="b.status!='deleted' AND b.shoot_day>=? AND b.shoot_day<=?";$params=[$start,$end];
  $person=booking_view_person($a);if($person!==null){$where.=' AND EXISTS(SELECT 1 FROM {{booking_staff}} s WHERE s.booking_id=b.id AND s.user_id=?)';$params[]=$person;}
  $page=finance_page();$total=(int)$a->scalar('SELECT COUNT(*) FROM {{shoot_bookings}} b WHERE '.$where,$params);
  // Mọi role xem đủ thông tin Sheet trong popup; chỉ Quản lý/Admin được xóa lịch.
  $rows=$a->all('SELECT b.* FROM {{shoot_bookings}} b WHERE '.$where.' ORDER BY b.shoot_day,b.minutes,b.id LIMIT 100 OFFSET '.(($page-1)*100),$params);
  $states=booking_sheet_states($a,array_column($rows,'id'));$perms=booking_permissions($a);
  foreach($rows as &$row){if($perms['can_create']&&$row['status']==='active')$row['edit']=booking_form($row);$row['sheet_fields']=json_decode($row['sheet_fields_json']??'null',true);unset($row['sheet_fields_json']);$row['sheet_state']=$states[$row['id']]??null;$row['staff']=$a->all('SELECT s.user_id,s.staff_role,u.name FROM {{booking_staff}} s JOIN {{users}} u ON u.id=s.user_id WHERE s.booking_id=?',[$row['id']]);$row['shift']=booking_shift($row['minutes'])??($row['minutes']>=1140?'ot':'outside');}
  return [200,['rows'=>$rows,'total'=>$total,'page'=>$page,'start'=>$start,'end'=>$end,'permissions'=>$perms,'default_staff'=>booking_default_staff($a)]];
 }
 return null;
}
