<?php
declare(strict_types=1);

const LOG_CATEGORIES=['missing_punch','late','early_out','absence','customer','task','other'];
// Điểm KPI theo số lần mắc cùng một lỗi trong tháng (chủ dự án chốt 02/11/2026): lần 1 Lỗi 20, lần 2 Cảnh cáo 100, lần 3 trở đi Kỷ luật 300; Nhắc nhở 0 điểm (offense 0) do người ghi chọn, chủ dự án đổi tên 06/11/2026.
// Luật nặng có điểm riêng từ LOG_FIXED_POINTS trở lên luôn trừ đúng điểm đó. Đi muộn DM-01 do hệ thống tự tính (late_points).
const LOG_TIER_POINTS=[1=>20,2=>100,3=>300];
const LOG_TIER_NAMES=[1=>'Lỗi',2=>'Cảnh cáo',3=>'Kỷ luật'];
const LOG_FIXED_POINTS=100;
function input_text(mixed $value,string $label,int $min=0,int $max=1000): string {
 if(!is_string($value)||!preg_match('//u',$value))fail($label.' không hợp lệ.');
 $value=trim($value);$length=text_length($value);
 if($length<$min||$length>$max)fail($label.' cần từ '.$min.'–'.$max.' ký tự.');
 return $value;
}
function entry_uuid(mixed $id): string {
 if(!is_string($id)||!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/iD',$id))fail('Mã yêu cầu không hợp lệ.');
 return strtolower($id);
}
function finance_range(): array {
 $month=valid_month($_GET['month']??substr(local_day(now_ms()),0,7));
 $start=$month.'-01';$end=date('Y-m-t',strtotime($start));
 if(!empty($_GET['date']))$start=$end=valid_day($_GET['date']);
 return [$start,$end];
}
// Điểm KPI: mỗi nhân viên có 2000 điểm mỗi tháng, trừ theo Event log đã xác nhận/đã xử lý trong tháng.
// Lọc tháng giống hệt trang Event log để hai nơi cùng một con số.
// Tiền: 1 điểm bị trừ = 1.000đ, trừ vào Tổng thưởng (hiện là hoa hồng mỗi khách hệ thống tính), tối đa bằng Tổng thưởng,
// không trừ vào lương theo công. Lương cuối = lương theo công + Tổng thưởng − trừ KPI.
const KPI_MONTHLY_POINTS=2000;
const KPI_POINT_VND=1000;
function kpi_month_deductions(App $a,string $month): array {
 $start=valid_month($month).'-01';$end=date('Y-m-t',strtotime($start));
 $rows=$a->all("SELECT r.user_id,COALESCE(SUM(-r.points),0) deducted FROM {{event_log}} r WHERE r.user_id IS NOT NULL AND r.status IN ('confirmed','resolved') AND ((r.date_precision='day' AND r.event_date>=? AND r.event_date<=?) OR (r.date_precision='day_range' AND r.event_date<=? AND r.end_date>=?) OR (r.date_precision IN ('month','range') AND r.event_date<=? AND (CASE WHEN r.end_month='' THEN r.event_date ELSE r.end_month END)>=?)) GROUP BY r.user_id",[$start,$end,$end,$start,$month,$month]);
 $out=[];foreach($rows as $r)$out[(int)$r['user_id']]=(int)$r['deducted'];return $out;
}
// Thưởng tiến độ (chủ dự án chốt 29/10/2026), áp dụng từ tháng 11/2026:
// - Chuyên cần: Nhân viên đi làm đủ 28 ngày/tháng (tháng 2: 26) được cộng 500.000đ vào thưởng.
// - Hoa hồng lịch khách: mỗi khách đã chụp (thợ chính hoặc phụ) = mức của người đó × hệ số gói.
const PROGRESS_BONUS_FROM='2026-11';
const ATTENDANCE_BONUS_VND=500000;
// Team 349 tính như 399 (mỗi dòng/người), Couple 999 ×2 (chủ dự án chốt 29/10/2026). Body 999 cùng giá nên cũng ×2.
const BOOKING_PACKAGE_UNITS=[349=>1,399=>1,699=>1,999=>2,1499=>4,2999=>8];
const BOOKING_RATE_SEED=[['huy',17500],['cuong',15000]];
// Hoa hồng khách OT (chủ dự án yêu cầu 08/11/2026, từ tháng 11/2026 thay cho đơn giá × lượt OT): lịch có giờ chụp trùng một lượt OT
// của người đó (09/11/2026: không bắt lịch bắt đầu từ 19:00, khách 18:30 chụp lúc đã vào OT cũng tính) thì 1 lịch = 1 khách
// (không nhân hệ số gói) × mức hoa hồng × 2. Huy 17.500, Cường 15.000, Tín 10.000.
// Tín (Trực Fanpage) chỉ có hoa hồng khách OT, không có hoa hồng khách ca thường. Lịch OT không tính thêm vào hoa hồng thường.
const BOOKING_OT_FACTOR=2;const BOOKING_RATE_SEED_OT=[['tin',10000]];
function attendance_target(string $month): int {return substr($month,5,2)==='02'?26:28;}
// "399", "1.499", "1499k", "1.499.000", "Gói 699 + ..." → 399 / 1499 / 699; chỉ ghi chữ "Couple" hoặc "Body" → 999, "Team" → 349.
// Lịch Team (chủ dự án yêu cầu 03/11/2026): chụp xong thợ nhập số khách theo từng gói (team 10 người, mỗi người một gói),
// bảng booking_team_counts, chỉ lưu trên web. Lịch Team đã nhập thì cả nhóm Team cùng ngày, cùng giờ tính theo số đã nhập
// (các dòng Team khác cùng giờ trên Sheet không đếm thêm); chưa nhập thì mỗi dòng Team vẫn là 1 khách như trước.
function booking_team_counts(App $a,array $ids): array {
 $ids=array_values(array_unique($ids));if(!$ids)return [];$out=[];
 foreach($a->all('SELECT booking_id,price,count FROM {{booking_team_counts}} WHERE count>0 AND booking_id IN ('.implode(',',array_fill(0,count($ids),'?')).') ORDER BY price',$ids) as $r)$out[$r['booking_id']][(int)$r['price']]=(int)$r['count'];
 return $out;
}
// Số khách và số suất hoa hồng của từng lịch (id => customers, units, unpriced). Dùng chung cho phiếu lương và thống kê Lịch chụp.
function booking_customer_items(App $a,array $rows): array {
 $team=[];foreach($rows as $r)if(booking_package_price($r['package_label'])===BOOKING_TEAM_PRICE)$team[$r['id']]=$r['shoot_day'].'|'.$r['minutes'];
 $counts=booking_team_counts($a,array_keys($team));$entered=[];foreach($counts as $id=>$c)$entered[$team[$id]]=true;$out=[];
 foreach($rows as $r){$id=$r['id'];if(isset($out[$id]))continue;
  if(isset($counts[$id])){$units=0;foreach($counts[$id] as $price=>$n)$units+=$n*(BOOKING_PACKAGE_UNITS[$price]??0);$out[$id]=['customers'=>array_sum($counts[$id]),'units'=>$units,'unpriced'=>false,'team'=>$counts[$id]];continue;}
  if(isset($team[$id])&&isset($entered[$team[$id]])){$out[$id]=['customers'=>0,'units'=>0,'unpriced'=>false,'team'=>null];continue;}
  $units=BOOKING_PACKAGE_UNITS[booking_package_price($r['package_label'])??0]??0;$out[$id]=['customers'=>1,'units'=>$units,'unpriced'=>!$units,'team'=>null];}
 return $out;
}
function booking_package_price(string $label): ?int {
 if(!preg_match('/\d{1,3}(?:[.,]\d{3})+|\d+/',$label,$m)){$text=mb_strtolower($label,'UTF-8');return str_contains($text,'couple')||str_contains($text,'body')?999:(str_contains($text,'team')?349:null);}
 $n=(int)preg_replace('/\D/','',$m[0]);return $n>=10000?intdiv($n,1000):$n;
}
function booking_rates_seed(App $a): void {
 if(!$a->meta('booking_rate_ot_seeded')){$a->meta('booking_rate_ot_seeded','1');booking_rates_seed_list($a,BOOKING_RATE_SEED_OT);}
 if($a->meta('booking_rates_seeded'))return;$a->meta('booking_rates_seeded','1');booking_rates_seed_list($a,BOOKING_RATE_SEED);
}
function booking_rates_seed_list(App $a,array $seed): void {
 $admin=$a->scalar("SELECT id FROM {{users}} WHERE role='admin' AND archived=0 ORDER BY id LIMIT 1");if(!$admin||!function_exists('task_fold'))return;
 $people=$a->all("SELECT id,name FROM {{users}} WHERE archived=0 AND status!='inactive'");
 foreach($seed as [$key,$rate]){
  // Chỉ gán khi tên gọi khớp đúng một người; trùng hoặc không có thì Admin tự nhập trong hồ sơ nhân viên.
  $hits=array_values(array_filter($people,fn($p)=>(explode(' ',task_fold($p['name']))[substr_count(task_fold($p['name']),' ')]??'')===$key));
  if(count($hits)===1)$a->run('INSERT IGNORE INTO {{booking_rates}} VALUES(?,?,?,?)',[$hits[0]['id'],$rate,$admin,now_ms()]);
 }
}
function booking_rate_save(App $a,int $user,int $rate): void {
 $old=(int)($a->scalar('SELECT rate FROM {{booking_rates}} WHERE user_id=?',[$user])?:0);if($old===$rate)return;
 $a->run('INSERT INTO {{booking_rates}} VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE rate=VALUES(rate),updated_by=VALUES(updated_by),updated_at=VALUES(updated_at)',[$user,$rate,$a->user['id'],now_ms()]);$a->audit($user,'booking_rate');
}
function booking_rates(App $a): array {
 booking_rates_seed($a);$out=[];foreach($a->all('SELECT user_id,rate FROM {{booking_rates}} WHERE rate>0') as $r)$out[(int)$r['user_id']]=(int)$r['rate'];return $out;
}
// Khoảng thời gian có mặt theo chấm công của từng người: [giờ chấm vào, giờ chấm ra] thật, không cắt theo khung ca.
// Ca quên chấm ra coi như có mặt tới hạn checkout (14:30 / 19:30); OT đang mở tính tới hiện tại.
function attendance_presence(App $a,array $users,string $from,string $to,int $now): array {
 if(!$users)return [];$by=[];$out=[];
 foreach($a->all('SELECT id,user_id,type,time,shift,work_date FROM {{events}} WHERE user_id IN ('.implode(',',array_fill(0,count($users),'?')).') AND time>=? AND time<?',[...$users,day_time($from)-86400000,day_time($to)+2*86400000]) as $e)$by[(int)$e['user_id']][]=['id'=>(int)$e['id'],'time'=>(int)$e['time']]+$e;
 foreach($by as $id=>$events){$p=pairs($events);
  foreach($p['pairs'] as $s)$out[$id][]=[(int)$s['entry']['time'],(int)$s['exit']['time'],$s['entry']['shift']??null,(int)$s['entry']['id'],(int)$s['exit']['id']];
  foreach($p['pending'] as $e){$w=$e['shift']==='ot'?null:bounds($e['work_date']??local_day($e['time']),$e['shift']??null);$out[$id][]=[$e['time'],$w?max($e['time'],$w['end']+1800000):($e['shift']==='ot'?$now:$e['time']),$e['shift']??null,(int)$e['id'],null];}
 }
 return $out;
}
// Khách đã chụp trong tháng (lịch đang hoạt động, đã qua giờ chụp) của người có mức hoa hồng.
// Chỉ tính khi thợ có mặt trong giờ chụp: chấm vào trước khi khách chụp xong và chưa chấm ra trước giờ khách tới. Đã chấm ra
// (VD làm cả ngày, về 17h thì khách 18h, 19h) hoặc không chấm công hôm đó thì khách không tính, đếm vào 'absent' (chủ dự án chốt 31/10/2026).
function booking_month_commissions(App $a,string $month,int $now): array {
 $rates=booking_rates($a);if(!$rates)return [];
 $start=valid_month($month).'-01';$end=date('Y-m-t',strtotime($start));$today=local_day($now);$minute=intdiv($now-day_time($today),60000);$out=[];$rows=[];
 foreach($a->all("SELECT DISTINCT s.user_id,b.id,b.shoot_day,b.minutes,b.package_label,b.note,b.customer_name FROM {{shoot_bookings}} b JOIN {{booking_staff}} s ON s.booking_id=b.id WHERE b.status='active' AND b.shoot_day>=? AND b.shoot_day<=?",[$start,$end]) as $r)
  if(isset($rates[(int)$r['user_id']])&&!($r['shoot_day']>$today||($r['shoot_day']===$today&&(int)$r['minutes']>$minute)))$rows[]=$r;
 $present=attendance_presence($a,array_values(array_unique(array_map(fn($r)=>(int)$r['user_id'],$rows))),$start,$end,$now);
 $ot=booking_month_ot($a,$rates,$start,$end,$now);
 foreach($rates as $id=>$rate)$out[$id]=['customers'=>0,'units'=>0,'unpriced'=>0,'unpriced_list'=>[],'absent'=>0,'rate'=>$rate,'ot'=>$ot[$id]];
 $items=booking_customer_items($a,$rows);
 foreach($rows as $r){$id=(int)$r['user_id'];if(isset($ot[$id]['ids'][$r['id']]))continue;
  $from=day_time($r['shoot_day'])+(int)$r['minutes']*60000;$to=$from+booking_length($r['package_label'],$r['note'])*60000;
  $it=$items[$r['id']];if(!$it['customers'])continue;
  $here=false;foreach($present[$id]??[] as [$in,$exit])if($in<$to&&$exit>$from){$here=true;break;}
  if(!$here){$out[$id]['absent']+=$it['customers'];continue;}
  $out[$id]['customers']+=$it['customers'];$out[$id]['units']+=$it['units'];if($it['unpriced']){$out[$id]['unpriced']++;
   // Ghi rõ lịch nào chưa có mức (chủ dự án hỏi 02/11/2026): ô Gói trống (khách tới mới chọn) hoặc gói không thuộc 349/399/699/999/1499/2999.
   $out[$id]['unpriced_list'][]=['day'=>$r['shoot_day'],'time'=>booking_hm((int)$r['minutes']),'customer_name'=>$r['customer_name'],'package_label'=>$r['package_label']];}
 }
 return $out;
}
// Các khoản thưởng tháng Admin/Quản lý nhập trong phiếu lương (chủ dự án yêu cầu 31/10/2026; sau này nối thêm từ Google Sheet, cột source).
// ot_rate là đơn giá mỗi lượt OT: thưởng OT = số lượt OT hoàn tất × đơn giá. Các khoản còn lại là số tiền.
// Khoản tự tính, khóa không sửa tay từ PROGRESS_BONUS_FROM (chủ dự án chốt 02/11/2026, "để nó set cứng"): Tiền khách Tips (Tip tháng chia đều),
// Ngoại cảnh (150k/lịch có chấm công), Ca làm không có khách (số ca × lương 1/2 ngày), Trực Fanpage (2.000.000đ/tháng).
const PAYROLL_AUTO_KINDS=['tips','outdoor','no_customer','fanpage'];
const PAYROLL_AUTO_WHY=['tips'=>'tự chia từ Tip của lịch khách','outdoor'=>'tự tính từ lịch ngoại cảnh có chấm công','no_customer'=>'tự tính: số ca × lương 1/2 ngày','fanpage'=>'cố định 2.000.000đ/tháng'];
// Nhóm thưởng (chủ dự án chốt 02/11/2026): Thợ chụp (Huy, Cường) có mọi khoản thưởng; Trực Fanpage (Tín) chỉ có Chuyên cần,
// Ca làm không có khách, Tiền khách Tips (Tip chia 3), Ăn uống 300.000đ (thêm 03/11/2026) và Thưởng trực Fanpage 2.000.000đ (không Ngoại cảnh, Chỉnh ảnh, OT, hoa hồng khách…).
// Chọn trong hồ sơ nhân viên (bảng payroll_profiles, mặc định Thợ chụp); lần đầu tự đặt Tín là Trực Fanpage.
const PAYROLL_PROFILES=['photo'=>'Thợ chụp','fanpage'=>'Trực Fanpage'];
const FANPAGE_BONUS_VND=2000000;
function profile_kinds(string $profile): array {return $profile==='fanpage'?['tips','no_customer','meal','fanpage']:array_values(array_diff(array_keys(array_slice(PAYROLL_BONUS_KINDS,1,null,true)),['fanpage']));}
function payroll_profiles_seed(App $a): void {
 if(!function_exists('task_fold')||$a->meta('payroll_profiles_seeded'))return;$a->meta('payroll_profiles_seeded','1');
 $admin=$a->scalar("SELECT id FROM {{users}} WHERE role='admin' AND archived=0 ORDER BY id LIMIT 1");if(!$admin)return;
 $hits=array_values(array_filter(finance_people($a),fn($p)=>(explode(' ',task_fold($p['name']))[substr_count(task_fold($p['name']),' ')]??'')==='tin'));
 if(count($hits)===1)$a->run("INSERT IGNORE INTO {{payroll_profiles}} VALUES(?,'fanpage',?,?)",[$hits[0]['id'],$admin,now_ms()]);
}
function payroll_profiles(App $a): array {payroll_profiles_seed($a);$out=[];foreach($a->all('SELECT user_id,profile FROM {{payroll_profiles}}') as $r)$out[(int)$r['user_id']]=$r['profile'];return $out;}
function payroll_profile(App $a,int $user): string {return payroll_profiles($a)[$user]??'photo';}
function payroll_profile_save(App $a,int $user,string $profile): void {
 $old=(string)($a->scalar('SELECT profile FROM {{payroll_profiles}} WHERE user_id=?',[$user])?:'photo');if($old===$profile)return;
 $a->run('INSERT INTO {{payroll_profiles}} VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE profile=VALUES(profile),updated_by=VALUES(updated_by),updated_at=VALUES(updated_at)',[$user,$profile,$a->user['id'],now_ms()]);$a->audit($user,'bonus_profile:'.$old.'>'.$profile);
}
// Chỉnh ảnh (chủ dự án yêu cầu 02/11/2026): cuối tháng nhập số ảnh đã chỉnh trên phiếu lương, tự nhân 2.000đ/ảnh thành khoản Chỉnh ảnh.
// Số ảnh lưu ở payroll_bonuses kind retouch_count (là số ảnh, không phải tiền, như ot_rate là đơn giá).
const RETOUCH_PHOTO_VND=2000;
const PAYROLL_COUNT_KINDS=['retouch_count'=>'Số ảnh chỉnh'];
// Ăn uống (chủ dự án yêu cầu 02/11/2026): mặc định 300.000đ/tháng cho mỗi Nhân viên, đi ăn thì Trừ bớt (Nhập thưởng hoặc sửa trên phiếu lương).
// payroll_bonuses kind meal lưu phần cộng/trừ so với mức mặc định (có thể âm); tổng nhân viên thấy = mặc định + phần đó, không dưới 0.
const MEAL_DEFAULT_VND=300000;
function bonus_base(string $kind,string $month): int {return $kind==='meal'&&$month>=PROGRESS_BONUS_FROM?MEAL_DEFAULT_VND:0;}
const PAYROLL_BONUS_KINDS=['ot_rate'=>'Đơn giá OT','tips'=>'Tiền khách Tips','praise'=>'Nóng / Khen','referral'=>'Giới thiệu khách','outdoor'=>'Ngoại cảnh','full_day'=>'Full khách ngày','retouch'=>'Chỉnh ảnh','no_customer'=>'Ca làm không có khách','meal'=>'Ăn uống','fanpage'=>'Trực Fanpage'];
function payroll_bonuses(App $a,string $month): array {
 $out=[];foreach($a->all('SELECT b.user_id,b.kind,b.amount,b.updated_at,u.name FROM {{payroll_bonuses}} b LEFT JOIN {{users}} u ON u.id=b.updated_by WHERE b.month=?',[$month]) as $r){
  $id=(int)$r['user_id'];if(!isset(PAYROLL_BONUS_KINDS[$r['kind']])&&!isset(PAYROLL_COUNT_KINDS[$r['kind']]))continue;$out[$id]['items'][$r['kind']]=(int)$r['amount'];
  if((int)$r['updated_at']>($out[$id]['at']??0))$out[$id]=['at'=>(int)$r['updated_at'],'by'=>(string)$r['name']]+$out[$id];}
 return $out;
}
// Quyền Nhập thưởng (chủ dự án yêu cầu 02/11/2026): Admin, Quản lý luôn có; Nhân viên cần Admin tick "Nhập thưởng" (payroll_bonus_editors)
// trong hồ sơ, VD Tín nhập thưởng cho nhân viên mỗi ngày. Nhân viên được cấp quyền không tự nhập thưởng cho chính mình.
function bonus_can_enter(App $a): bool {
 if(in_array($a->user['role'],['admin','manager'],true))return true;
 return $a->user['role']==='employee'&&(bool)$a->one('SELECT user_id FROM {{payroll_bonus_editors}} WHERE user_id=?',[$a->user['id']]);
}
function bonus_target(App $a,mixed $id): int {
 if(!is_int($id)||!$a->one("SELECT id FROM {{users}} WHERE id=? AND role='employee' AND archived=0 AND status!='inactive'",[$id]))fail('Nhân viên không tồn tại hoặc đã nghỉ.');
 if($a->user['role']==='employee'&&$id===(int)$a->user['id'])fail('Không tự nhập thưởng cho chính mình. Quản lý hoặc Admin nhập khoản này.',403);
 return $id;
}
// Mỗi lần thay đổi thưởng lưu một dòng payroll_bonus_entries (cộng thêm theo ngày, hoặc chênh lệch khi sửa tổng trên phiếu lương)
// để nhân viên xem được lịch sử trong Phiếu thưởng.
function bonus_entry_add(App $a,string $id,string $month,string $day,int $user,string $kind,int $amount,string $note): void {
 $a->run('INSERT INTO {{payroll_bonus_entries}}(id,month,day,user_id,kind,amount,note,created_by,created_at) VALUES(?,?,?,?,?,?,?,?,?)',[$id,$month,$day,$user,$kind,$amount,$note,$a->user['id'],now_ms()]);
}
function bonus_set(App $a,string $month,int $user,string $kind,int $amount): void {
 if($amount)$a->run("INSERT INTO {{payroll_bonuses}} VALUES(?,?,?,?,'web',?,?) ON DUPLICATE KEY UPDATE amount=VALUES(amount),source='web',updated_by=VALUES(updated_by),updated_at=VALUES(updated_at)",[$month,$user,$kind,$amount,$a->user['id'],now_ms()]);
 else $a->run('DELETE FROM {{payroll_bonuses}} WHERE month=? AND user_id=? AND kind=?',[$month,$user,$kind]);
}
function payroll_bonus_routes(App $a,string $path,array $b,string $method): ?array {
 if($path==='/payroll/bonus-entries'&&$method==='GET'){
  $month=valid_month($_GET['month']??'');$can=bonus_can_enter($a);$me=(int)$a->user['id'];$staff=$a->user['role']==='employee';
  $where='e.month=?';$params=[$month];if(!$can){$where.=' AND e.user_id=?';$params[]=$me;}
  $entries=$a->all('SELECT e.id,e.day,e.user_id,e.kind,e.amount,e.note,e.created_at,u.name,c.name created_by_name FROM {{payroll_bonus_entries}} e JOIN {{users}} u ON u.id=e.user_id LEFT JOIN {{users}} c ON c.id=e.created_by WHERE '.$where.' ORDER BY e.day DESC,e.created_at DESC LIMIT 500',$params);
  $people=[];
  if($can){$totals=payroll_bonuses($a,$month);
   $tips=$month>=PROGRESS_BONUS_FROM?tip_month($a,$month):null;$outdoor=$tips?outdoor_month($a,$month,now_ms()):[];$profiles=$tips?payroll_profiles($a):[];$auto=[...PAYROLL_AUTO_KINDS,'retouch'];
   foreach(finance_people($a) as $u){if($staff&&(int)$u['id']===$me)continue;$items=$totals[(int)$u['id']]['items']??[];unset($items['ot_rate']);
    if($tips&&($share=tip_share($a,$month,(int)$u['id'],$tips))!==null)$items['tips']=$share;
    if(isset($outdoor[(int)$u['id']]))$items['outdoor']=$outdoor[(int)$u['id']]['amount'];
    $photos=$items['retouch_count']??0;unset($items['retouch_count']);$kinds=null;
    if($tips){$items['retouch']=$photos*RETOUCH_PHOTO_VND;$items['meal']=max(0,MEAL_DEFAULT_VND+($items['meal']??0));$profile=$profiles[(int)$u['id']]??'photo';$kinds=array_values(array_diff(profile_kinds($profile),$auto));
     // Khung Nhập thưởng chỉ hiện các khoản nhập tay được theo nhóm; khoản tự tính (Tips, Ngoại cảnh, Chỉnh ảnh, Ca không khách, Trực Fanpage) xem trên phiếu.
     $items=array_intersect_key($items,array_flip($kinds));}
    $people[]=['id'=>(int)$u['id'],'name'=>$u['name'],'items'=>$items,'total'=>array_sum($items),'kinds'=>$kinds];}}
  return [200,['month'=>$month,'can_enter'=>$can,'closed'=>(bool)$a->closure($month),'kinds'=>array_diff_key(array_slice(PAYROLL_BONUS_KINDS,1,null,true),$month>=PROGRESS_BONUS_FROM?array_flip([...PAYROLL_AUTO_KINDS,'retouch']):[]),'people'=>$people,'entries'=>$entries,'me'=>$me]];
 }
 // Nhật ký Tip (chủ dự án yêu cầu 02/11/2026): bấm "Tiền khách Tips" trên phiếu lương / phiếu thưởng xem tổng Tip tháng,
 // Tip từng khách (ngày, ca, giờ) và ai nhập, lúc nào. Mọi role xem lịch khách đầy đủ nên cùng xem được nhật ký này.
 if($path==='/payroll/tips'&&$method==='GET'){
  $month=valid_month($_GET['month']??'');$t=tip_month($a,$month);$ids=array_column($t['items'],'id');
  $log=$a->all('SELECT l.id,l.booking_id,l.shoot_day,l.minutes,l.customer_name,l.old_amount,l.amount,l.label,l.source,l.time,u.name actor_name FROM {{booking_tip_log}} l LEFT JOIN {{users}} u ON u.id=l.actor_id WHERE l.shoot_day>=? AND l.shoot_day<=? ORDER BY l.time DESC,l.id DESC LIMIT 1000',[$month.'-01',$month.'-31']);
  $last=[];$count=[];foreach($log as $l){$count[$l['booking_id']]=($count[$l['booking_id']]??0)+1;$last[$l['booking_id']]??=$l;}
  $shift=fn(int $m)=>$m<840?($m<540?'Trước Ca 1':'Ca 1'):($m<1140?'Ca 2':'OT');
  $items=array_map(fn($r)=>['id'=>$r['id'],'day'=>$r['shoot_day'],'time'=>booking_hm((int)$r['minutes']),'shift'=>$shift((int)$r['minutes']),'customer_name'=>$r['customer_name'],'package_label'=>$r['package_label'],'label'=>$r['tip_label'],'amount'=>$r['amount'],
   'entered_by'=>isset($last[$r['id']])?($last[$r['id']]['source']==='sheet'&&!$last[$r['id']]['actor_name']?'Google Sheet':(string)$last[$r['id']]['actor_name']):null,'entered_at'=>isset($last[$r['id']])?(int)$last[$r['id']]['time']:null,'edits'=>$count[$r['id']]??0],$t['items']);
  $log=array_map(fn($l)=>['day'=>$l['shoot_day'],'time'=>booking_hm((int)$l['minutes']),'shift'=>$shift((int)$l['minutes']),'customer_name'=>$l['customer_name'],'old_amount'=>(int)$l['old_amount'],'amount'=>(int)$l['amount'],'source'=>$l['source'],'by'=>$l['source']==='sheet'&&!$l['actor_name']?'Google Sheet':(string)$l['actor_name'],'at'=>(int)$l['time'],'live'=>in_array($l['booking_id'],$ids,true)],$log);
  return [200,['month'=>$month,'applies'=>$month>=PROGRESS_BONUS_FROM,'total'=>$t['total'],'split'=>$t['split'],'share'=>$t['share'],'people'=>array_column($t['people'],'name'),'items'=>$items,'log'=>$log]];
 }
 if($path==='/payroll/bonuses/add'&&$method==='POST'){
  if(!bonus_can_enter($a))fail('Bạn chưa được Admin cấp quyền Nhập thưởng.',403);
  $id=entry_uuid($b['id']??null);
  if($a->one('SELECT id FROM {{payroll_bonus_entries}} WHERE id=?',[$id]))return [200,['ok'=>true,'already_saved'=>true]];
  $day=valid_day($b['day']??null);if($day>local_day(now_ms())||$day<'2026-01-01')fail('Ngày thưởng phải từ hôm nay trở về trước.');$month=substr($day,0,7);
  if($a->closure($month))fail('Tháng này đã chốt lương. Admin cần mở lại trước khi nhập thưởng.',409);
  $user=bonus_target($a,$b['user_id']??null);$kind=$b['kind']??'';
  if(!is_string($kind)||$kind==='ot_rate'||!isset(PAYROLL_BONUS_KINDS[$kind]))fail('Khoản thưởng không hợp lệ.');
  if(in_array($kind,PAYROLL_AUTO_KINDS,true)&&$month>=PROGRESS_BONUS_FROM)fail(PAYROLL_BONUS_KINDS[$kind].' '.PAYROLL_AUTO_WHY[$kind].', không nhập tay.');
  if($month>=PROGRESS_BONUS_FROM&&!in_array($kind,profile_kinds(payroll_profile($a,$user)),true))fail(PAYROLL_BONUS_KINDS[$kind].' không áp dụng cho nhóm Trực Fanpage.');
  if($kind==='retouch'&&$month>=PROGRESS_BONUS_FROM)fail('Chỉnh ảnh: cuối tháng Quản lý / Admin nhập số ảnh đã chỉnh trên phiếu lương (2.000đ/ảnh).');
  $amount=$b['amount']??null;if(!is_int($amount)||$amount===0||abs($amount)>100000000)fail('Số tiền cần khác 0 và không quá 100.000.000đ.');
  $note=input_text($b['note']??'','Ghi chú',0,300);
  $total=$a->tx(function()use($a,$id,$month,$day,$user,$kind,$amount,$note){
   $old=(int)($a->scalar('SELECT amount FROM {{payroll_bonuses}} WHERE month=? AND user_id=? AND kind=? FOR UPDATE',[$month,$user,$kind])?:0);$new=$old+$amount;
   $base=bonus_base($kind,$month);
   if($base+$new<0)fail(PAYROLL_BONUS_KINDS[$kind].' tháng này mới có '.number_format(max(0,$base+$old),0,',','.').'đ, không trừ bớt quá số đó.');
   if($base+$new>100000000)fail('Tổng '.PAYROLL_BONUS_KINDS[$kind].' trong tháng không quá 100.000.000đ.');
   bonus_set($a,$month,$user,$kind,$new);bonus_entry_add($a,$id,$month,$day,$user,$kind,$amount,$note);
   $a->audit($user,'bonus:'.$month.':'.$kind.':'.$old.'>'.$new);return $new;
  });
  return [201,['ok'=>true,'total'=>$total]];
 }
 // Bổ sung công (chủ dự án yêu cầu 06/11/2026): ca không khách của một nhân viên được tính công như ca có khách, VD khách cuối 13:30 chụp lấn sang Ca 2.
 if($path==='/payroll/shift-credit'&&$method==='POST'){
  $a->requireManager();$day=valid_day($b['day']??null);$shift=$b['shift']??'';$user=$b['user_id']??null;$on=$b['on']??null;
  if(!in_array($shift,['day_first','day_second'],true)||!is_int($user)||!is_bool($on))fail('Dữ liệu không hợp lệ.');
  if($a->closure(substr($day,0,7)))fail('Tháng này đã chốt lương. Admin cần mở lại trước khi bổ sung công.',409);
  $row=array_values(array_filter($a->summaries(substr($day,0,7)),fn($p)=>(int)$p['id']===$user))[0]??null;if(!$row)fail('Nhân viên không tồn tại hoặc đã nghỉ.');
  $label=$day.':'.$shift;
  if($on){
   if(!in_array($label,$row['unbookedDays'],true))fail('Ca này không còn là ca không khách. Tải lại.',409);
   $note=trim((string)($b['note']??''));if(mb_strlen($note)>300)fail('Ghi chú tối đa 300 ký tự.');
   $a->run('INSERT INTO {{shift_credits}}(user_id,day,shift,note,created_by,created_at) VALUES(?,?,?,?,?,?)',[$user,$day,$shift,$note,$a->user['id'],now_ms()]);
  }elseif(!$a->run('DELETE FROM {{shift_credits}} WHERE user_id=? AND day=? AND shift=?',[$user,$day,$shift])->rowCount())fail('Ca này không còn được bổ sung công. Tải lại.',409);
  $a->audit($user,'shift_credit:'.($on?'add':'remove').':'.$label);return [200,['ok'=>true]];
 }
 if($path!=='/payroll/bonuses'||$method!=='POST')return null;
 $a->requireManager();$month=valid_month($b['month']??'');
 if($a->closure($month))fail('Tháng này đã chốt lương. Admin cần mở lại trước khi sửa thưởng.',409);
 if(!is_int($b['user_id']??null)||!$a->one("SELECT id FROM {{users}} WHERE id=? AND role='employee' AND archived=0 AND status!='inactive'",[$b['user_id']]))fail('Nhân viên không tồn tại hoặc đã nghỉ.');
 if(!is_array($b['items']??null)||!$b['items'])fail('Chưa có khoản thưởng nào.');
 foreach($b['items'] as $kind=>$amount)if(isset(PAYROLL_COUNT_KINDS[$kind])){if(!is_int($amount)||$amount<0||$amount>100000)fail(PAYROLL_COUNT_KINDS[$kind].' cần là số từ 0 đến 100.000.');}elseif(!isset(PAYROLL_BONUS_KINDS[$kind])||!is_int($amount)||$amount<0||$amount>100000000)fail((PAYROLL_BONUS_KINDS[$kind]??'Khoản thưởng').' cần là số tiền từ 0 đến 100.000.000đ.');
 $a->tx(function()use($a,$b,$month){
  $fanpage=payroll_profile($a,$b['user_id'])==='fanpage';
  $today=local_day(now_ms());$day=$month===substr($today,0,7)?$today:($month<substr($today,0,7)?date('Y-m-t',strtotime($month.'-01T00:00:00Z')):$month.'-01');
  foreach($b['items'] as $kind=>$amount){
   // Tiền khách Tips, Ngoại cảnh tự tính từ lịch khách, khóa không sửa (chủ dự án chốt 02/11/2026: "để nó set cứng").
   if($month>=PROGRESS_BONUS_FROM&&(in_array($kind,[...PAYROLL_AUTO_KINDS,'retouch'],true)||($fanpage&&!in_array($kind,profile_kinds('fanpage'),true))))continue;
   $old=(int)($a->scalar('SELECT amount FROM {{payroll_bonuses}} WHERE month=? AND user_id=? AND kind=?',[$month,$b['user_id'],$kind])?:0);
   // Ăn uống: ô trên phiếu là tổng (mặc định 300.000đ ± phần đã cộng/trừ), lưu phần chênh với mặc định.
   if($base=bonus_base($kind,$month)){$shown=max(0,$base+$old);if($amount===$shown)continue;bonus_set($a,$month,$b['user_id'],$kind,$amount-$base);
    bonus_entry_add($a,uuid_v4(),$month,$day,$b['user_id'],$kind,$amount-$shown,'Sửa tổng trên phiếu lương: '.number_format($shown,0,',','.').'đ → '.number_format($amount,0,',','.').'đ');$a->audit($b['user_id'],'bonus:'.$month.':'.$kind.':'.$old.'>'.($amount-$base));continue;}
   if($old===$amount)continue;
   bonus_set($a,$month,$b['user_id'],$kind,$amount);
   if($kind==='retouch_count'){if($month>=PROGRESS_BONUS_FROM)bonus_entry_add($a,uuid_v4(),$month,$day,$b['user_id'],'retouch',($amount-$old)*RETOUCH_PHOTO_VND,'Số ảnh chỉnh: '.number_format($old,0,',','.').' → '.number_format($amount,0,',','.').' ảnh × '.number_format(RETOUCH_PHOTO_VND,0,',','.').'đ');$a->audit($b['user_id'],'bonus:'.$month.':'.$kind.':'.$old.'>'.$amount);continue;}
   if($kind!=='ot_rate')bonus_entry_add($a,uuid_v4(),$month,$day,$b['user_id'],$kind,$amount-$old,'Sửa tổng trên phiếu lương: '.number_format($old,0,',','.').'đ → '.number_format($amount,0,',','.').'đ');
   $a->audit($b['user_id'],'bonus:'.$month.':'.$kind.':'.$old.'>'.$amount);}
 });
 return [200,['ok'=>true]];
}
// Ca không khách được Admin/Quản lý bổ sung công trong tháng, theo từng nhân viên.
// Khách OT của từng người có mức hoa hồng: lịch đang hoạt động, đã qua giờ chụp, giờ chụp trùng một lượt OT của người đó.
// Xét theo chấm OT (tự chấm hoặc chấm hộ), không theo thợ trên lịch (Sheet không có cột Thợ, Tín không được gán lịch).
// 'sessions' là các lượt OT trong tháng (giờ vào–ra) để phiếu lương đối chiếu khi không thấy khách.
function booking_month_ot(App $a,array $rates,string $start,string $end,int $now): array {
 $out=[];foreach($rates as $id=>$rate)$out[$id]=['customers'=>0,'rate'=>$rate*BOOKING_OT_FACTOR,'list'=>[],'ids'=>[],'sessions'=>[]];if(!$rates)return $out;
 $today=local_day($now);$minute=intdiv($now-day_time($today),60000);$present=attendance_presence($a,array_keys($rates),$start,$end,$now);
 $lo=day_time($start);$hi=day_time($end)+86400000;$ots=[];foreach($present as $id=>$list)foreach($list as $s)if(($s[2]??null)==='ot'&&$s[1]>$lo&&$s[0]<$hi){$ots[$id][]=$s;$out[$id]['sessions'][]=['in'=>(int)$s[0],'out'=>(int)$s[1],'in_id'=>$s[3]??null,'out_id'=>$s[4]??null];}
 if(!$ots)return $out;
 $rows=array_values(array_filter($a->all("SELECT id,shoot_day,minutes,package_label,note,customer_name FROM {{shoot_bookings}} WHERE status='active' AND shoot_day>=? AND shoot_day<=? ORDER BY shoot_day,minutes",[$start,$end]),fn($r)=>!($r['shoot_day']>$today||($r['shoot_day']===$today&&(int)$r['minutes']>$minute))));
 foreach($rows as $r){$from=day_time($r['shoot_day'])+(int)$r['minutes']*60000;$to=$from+booking_length($r['package_label'],$r['note'])*60000;
  foreach($ots as $id=>$list){$here=false;foreach($list as $s)if($s[0]<$to&&$s[1]>$from){$here=true;break;}if(!$here)continue;
   $out[$id]['customers']++;$out[$id]['ids'][$r['id']]=true;$out[$id]['list'][]=['day'=>$r['shoot_day'],'time'=>booking_hm((int)$r['minutes']),'customer_name'=>$r['customer_name'],'package_label'=>$r['package_label']];}}
 return $out;
}
function shift_credits(App $a,string $month): array {
 $out=[];foreach($a->all('SELECT c.user_id,c.day,c.shift,c.note,c.created_at,u.name by_name FROM {{shift_credits}} c LEFT JOIN {{users}} u ON u.id=c.created_by WHERE c.day>=? AND c.day<=? ORDER BY c.day,c.shift',[$month.'-01',$month.'-31']) as $r)$out[(int)$r['user_id']][]=['key'=>$r['day'].':'.$r['shift'],'note'=>$r['note'],'by'=>$r['by_name']??'','at'=>(int)$r['created_at']];
 return $out;
}
// Gắn KPI vào từng dòng bảng lương. $money=false: tháng đã chốt từ trước khi có KPI, chỉ thêm điểm, giữ nguyên tiền đã chốt.
function payroll_apply(App $a,string $month,array $rows,bool $money=true): array {
 $kpi=kpi_month_deductions($a,$month);
 $progress=$month>=PROGRESS_BONUS_FROM;$bookings=$progress?booking_month_commissions($a,$month,now_ms()):[];$bonuses=$money?payroll_bonuses($a,$month):[];$tips=$money&&$progress?tip_month($a,$month):null;$outdoor=$tips?outdoor_month($a,$month,now_ms()):[];$profiles=$progress?payroll_profiles($a):[];
 foreach($rows as &$p){$used=$kpi[$p['id']]??0;$p['kpi_quota']=KPI_MONTHLY_POINTS;$p['kpi_deducted']=$used;$p['kpi_left']=KPI_MONTHLY_POINTS-$used;$profile=$progress?($profiles[(int)$p['id']]??'photo'):null;
  if($progress&&$p['role']==='employee'){$p['attendance_target']=attendance_target($month);$p['attendance_bonus_vnd']=ATTENDANCE_BONUS_VND;$p['attendance_bonus']=$p['days']>=$p['attendance_target']?ATTENDANCE_BONUS_VND:0;}
  // Trực Fanpage không có hoa hồng khách chụp.
  if($profile!=='fanpage'&&($b=$bookings[$p['id']]??null)){$p['booking_customers']=$b['customers'];$p['booking_units']=$b['units'];$p['booking_rate']=$b['rate'];$p['booking_unpriced']=$b['unpriced'];$p['booking_unpriced_list']=$b['unpriced_list'];$p['booking_absent']=$b['absent'];$p['booking_commission']=$b['rate']*$b['units'];}
  if($progress&&$p['role']==='employee'&&($o=$bookings[$p['id']]['ot']??null)){$p['ot_booking_customers']=$o['customers'];$p['ot_booking_rate']=$o['rate'];$p['ot_booking_base']=intdiv($o['rate'],BOOKING_OT_FACTOR);$p['ot_booking_list']=$o['list'];$p['ot_sessions']=$o['sessions'];}
  if(!$money)continue;$extra=$bonuses[$p['id']]??[];$items=$extra['items']??[];$p['ot_rate']=$items['ot_rate']??0;unset($items['ot_rate']);$photos=$items['retouch_count']??0;unset($items['retouch_count']);
  if($progress){$p['bonus_profile']=$profile;$p['bonus_kinds']=profile_kinds($profile);
   // Ca làm không có khách (chủ dự án chốt 02/11/2026): số ca × lương 1/2 ngày (lương cứng / số ngày của tháng / 2).
   $empty=(int)round(($p['monthly_salary']??0)*($p['unbookedShifts']??0)/(2*max(1,(int)($p['monthDays']??30))));
   // Tips chia đều cho cả 3 nhân viên (chủ dự án chốt 02/11/2026: "Tip chia 3"), kể cả nhóm Trực Fanpage.
   $p['tip_total']=$tips['total'];$p['tip_split']=$tips['split'];$share=tip_share($a,$month,(int)$p['id'],$tips);if($share!==null)$p['tip_share']=$share;
   // Trực Fanpage cũng có Ăn uống 300.000đ/tháng như Thợ chụp (chủ dự án yêu cầu 03/11/2026).
   if($profile==='fanpage'){$p['ot_rate']=0;$p['meal_base']=MEAL_DEFAULT_VND;$p['meal_adjust']=$items['meal']??0;$items=['tips'=>$share??0,'no_customer'=>$empty,'meal'=>max(0,MEAL_DEFAULT_VND+$p['meal_adjust']),'fanpage'=>FANPAGE_BONUS_VND];}
   else{unset($items['fanpage']);$items['no_customer']=$empty;$p['retouch_count']=$photos;$p['retouch_rate']=RETOUCH_PHOTO_VND;$items['retouch']=$photos*RETOUCH_PHOTO_VND;$p['meal_base']=MEAL_DEFAULT_VND;$p['meal_adjust']=$items['meal']??0;$items['meal']=max(0,MEAL_DEFAULT_VND+$p['meal_adjust']);
    if($share!==null)$items['tips']=$share;else unset($items['tips']);
    if($o=$outdoor[(int)$p['id']]??null){$items['outdoor']=$o['amount'];$p['outdoor_count']=$o['count'];$p['outdoor_days']=$o['days'];$p['outdoor_missed']=$o['missed'];$p['outdoor_rate']=OUTDOOR_BONUS_VND;}else unset($items['outdoor']);}}
  // Từ 11/2026 thưởng OT = hoa hồng khách OT (cả nhóm Trực Fanpage); tháng trước giữ lượt OT × đơn giá nhập tay.
  $p['ot_bonus']=$progress?($p['ot_booking_customers']??0)*($p['ot_booking_rate']??0):$p['otSessions']*$p['ot_rate'];if($progress)$p['ot_rate']=0;$p['bonus_items']=$items;$p['bonus_updated_at']=$extra['at']??null;$p['bonus_updated_by']=$extra['by']??null;
  $p['bonus_total']=(int)$p['commission']+($p['booking_commission']??0)+($p['attendance_bonus']??0)+$p['ot_bonus']+array_sum($items);$p['kpi_money']=$used*KPI_POINT_VND;$p['kpi_applied']=max(0,min($p['kpi_money'],$p['bonus_total']));
  $p['gross_salary']=(int)$p['base_salary']+$p['bonus_total'];$p['salary']=$p['gross_salary']-$p['kpi_applied'];}
 unset($p);return $rows;
}
// Ứng lương trên phiếu lương (chủ dự án yêu cầu 04/11/2026): phiếu Đã chi có ngày chi trong tháng trừ vào Tổng tiền cuối cùng.
// Tính trực tiếp từ mục Ứng lương (cả tháng đã chốt), nên Sửa ngày phiếu ứng thì phiếu lương tháng đó đổi theo.
// Phiếu chờ duyệt / đã duyệt chưa chi (theo ngày đề nghị) chỉ nhắc, chưa trừ. Chỉ Admin/Quản lý thấy.
function advance_payroll(App $a,string $month,array $rows): array {
 $start=$month.'-01';$end=date('Y-m-t',strtotime($start));$paid=[];$open=[];
 foreach($a->all("SELECT id,user_id,requested_date,paid_date,amount,reason FROM {{salary_advances}} WHERE status='paid' AND paid_date>=? AND paid_date<=? ORDER BY paid_date,created_at",[$start,$end]) as $r)$paid[(int)$r['user_id']][]=['id'=>$r['id'],'paid_date'=>$r['paid_date'],'requested_date'=>$r['requested_date'],'amount'=>(int)$r['amount'],'reason'=>mb_substr($r['reason'],0,80)];
 foreach($a->all("SELECT user_id,COUNT(*) n,SUM(amount) total FROM {{salary_advances}} WHERE status IN ('pending','approved') AND requested_date>=? AND requested_date<=? GROUP BY user_id",[$start,$end]) as $r)$open[(int)$r['user_id']]=['count'=>(int)$r['n'],'total'=>(int)$r['total']];
 foreach($rows as &$p){$items=$paid[(int)$p['id']]??[];$p['advance_items']=$items;$p['advance_total']=array_sum(array_column($items,'amount'));
  $p['advance_open_count']=$open[(int)$p['id']]['count']??0;$p['advance_open_total']=$open[(int)$p['id']]['total']??0;$p['payout']=(int)($p['salary']??0)-$p['advance_total'];}
 unset($p);return $rows;
}
function entry_version(array $body,array $row): void {
 if(!is_int($body['version']??null)||$body['version']!==$row['version'])fail('Dữ liệu đã thay đổi. Tải lại trước khi xử lý.',409);
}
function finance_audit(App $a,string $kind,string $id,string $action,string $note): void {
 $table=$kind==='advance'?'advance_audit':'event_log_audit';$column=$kind==='advance'?'advance_id':'event_log_id';
 $a->run('INSERT INTO {{'.$table.'}}('.$column.',actor_id,action,note,time) VALUES(?,?,?,?,?)',[$id,$a->user['id'],$action,$note,now_ms()]);
}
// Danh sách người để chọn / lọc (chủ dự án yêu cầu 02/11/2026): chỉ tài khoản Nhân viên; Admin, Quản lý, Makeup không ghi tên vào.
function finance_people(App $a,bool $all=false): array {
 return $a->all("SELECT id,name,employee_code,role,archived,status FROM {{users}} WHERE role='employee'".($all?'':" AND archived=0 AND status!='inactive'").' ORDER BY name');
}
function finance_page(): int {
 $p=filter_var($_GET['page']??1,FILTER_VALIDATE_INT);if(!$p||$p<1||$p>100000)fail('Trang không hợp lệ.');return $p;
}
// Ứng lương (chủ dự án yêu cầu 02/11/2026): không ứng gấp. Nhân viên tạo phiếu lúc nào cũng được (kể cả ngày ứng là hôm nay),
// nhưng không được duyệt liền: Telegram báo ngay Admin và Quản lý (trừ người gửi), họ duyệt trong 24 giờ tới.
function advance_request_text(string $name,string $day,int $amount,string $reason,int $now): string {
 $weekday=['CN','T2','T3','T4','T5','T6','T7'][(int)date('w',strtotime($day.'T00:00:00Z'))];
 return "💰 Anh Gấu Studio · Yêu cầu ứng lương\n".$name.' xin ứng '.number_format($amount,0,',','.')."đ\nNgày muốn nhận: ".$weekday.' '.date('d/m/Y',strtotime($day.'T00:00:00Z'))."\nLý do: ".$reason
  ."\n\nVui lòng duyệt trong 24 giờ tới (trước ".gmdate('H:i d/m',(int)floor($now/1000)+86400+7*3600).'). Vào mục Ứng lương để Duyệt hoặc Từ chối.';
}
function advance_notify_managers(App $a,string $text,?Telegram $tg=null): int {
 if(!class_exists('Telegram'))return 0;$tg??=new Telegram($a);if(!$tg->configured())return 0;$sent=0;
 foreach($a->all("SELECT l.chat_id FROM {{telegram_links}} l JOIN {{users}} u ON u.id=l.user_id WHERE u.role IN ('admin','manager') AND u.archived=0 AND u.status!='inactive' AND u.id!=? ORDER BY u.id",[$a->user['id']]) as $m){
  try{$tg->call('sendMessage',['chat_id'=>$m['chat_id'],'text'=>$text,'disable_web_page_preview'=>true]+$tg->button('Mở Ứng lương',$a->config['app_url'].'/#advances'));$sent++;}catch(Throwable $e){$a->meta('telegram_error',$e->getMessage());}
 }
 return $sent;
}
function advance_routes(App $a,string $path,array $b,string $method): ?array {
 if($path!=='/advances'&&!str_starts_with($path,'/advances/'))return null;
 $manager=$a->user['role']!=='employee';
 if($path==='/advances'&&$method==='POST'){
  $id=entry_uuid($b['id']??null);$day=valid_day($b['requested_date']??null);
  if($day<'2020-01-01'||day_time($day)>now_ms()+366*86400000)fail('Ngày ứng không hợp lệ.');
  if(!is_int($b['amount']??null)||$b['amount']<1||$b['amount']>100000000)fail('Số tiền ứng cần là số nguyên từ 1 đến 100.000.000đ.');
  $reason=input_text($b['reason']??null,'Lý do ứng',5,1000);
  $existing=$a->one('SELECT * FROM {{salary_advances}} WHERE id=?',[$id]);
  if($existing){if($existing['user_id']===$a->user['id']&&$existing['requested_date']===$day&&$existing['amount']===$b['amount']&&$existing['reason']===$reason)return [200,['id'=>$id,'already_saved'=>true]];fail('Mã yêu cầu đã được sử dụng.',409);}
  $a->run("INSERT INTO {{salary_advances}}(id,user_id,requested_date,amount,reason,created_at,review_note,payment_note) VALUES(?,?,?,?,?,?,'','')",[$id,$a->user['id'],$day,$b['amount'],$reason,now_ms()]);
  finance_audit($a,'advance',$id,'created',$reason);
  $sent=advance_notify_managers($a,advance_request_text($a->user['name'],$day,$b['amount'],$reason,now_ms()));
  return [201,['id'=>$id,'notified'=>$sent]];
 }
 if($path==='/advances'&&$method==='GET'){
  [$start,$end]=finance_range();$basis=$_GET['basis']??'requested';if(!in_array($basis,['requested','paid'],true))fail('Loại ngày không hợp lệ.');
  $column=$basis==='paid'?'r.paid_date':'r.requested_date';$where=$column.'>=? AND '.$column.'<=?';$params=[$start,$end];
  $scope=$_GET['scope']??'all';if(!in_array($scope,['all','mine'],true))fail('Phạm vi không hợp lệ.');
  if(!$manager||$scope==='mine'){$where.=' AND r.user_id=?';$params[]=$a->user['id'];}
  if(!empty($_GET['user_id'])){if(!ctype_digit((string)$_GET['user_id']))fail('Nhân viên không hợp lệ.');$where.=' AND r.user_id=?';$params[]=(int)$_GET['user_id'];}
  // Số phiếu theo từng trạng thái (nút lọc trạng thái), cùng tháng / nhân viên / phạm vi nhưng không lọc trạng thái.
  $counts=['all'=>0];foreach($a->all('SELECT r.status,COUNT(*) n FROM {{salary_advances}} r WHERE '.$where.' GROUP BY r.status',$params) as $c){$counts[$c['status']]=(int)$c['n'];$counts['all']+=(int)$c['n'];}
  $status=$_GET['status']??'';if($status!==''){if(!in_array($status,['pending','approved','paid','rejected','cancelled'],true))fail('Trạng thái không hợp lệ.');$where.=' AND r.status=?';$params[]=$status;}
  $sum="COUNT(*) requests,COALESCE(SUM(r.amount),0) requested_total,COALESCE(SUM(CASE WHEN r.status='pending' THEN r.amount ELSE 0 END),0) pending_total,COALESCE(SUM(CASE WHEN r.status='approved' THEN r.amount ELSE 0 END),0) approved_total,COALESCE(SUM(CASE WHEN r.status='paid' THEN r.amount ELSE 0 END),0) paid_total";
  $summary=$a->one('SELECT '.$sum.' FROM {{salary_advances}} r WHERE '.$where,$params);
  $people=$manager?finance_people($a,true):[];$page=finance_page();
  $rows=$a->all('SELECT r.*,u.name,u.employee_code,u.role,(SELECT name FROM {{users}} WHERE id=r.reviewed_by) reviewer_name,(SELECT name FROM {{users}} WHERE id=r.paid_by) payer_name FROM {{salary_advances}} r JOIN {{users}} u ON u.id=r.user_id WHERE '.$where.' ORDER BY '.$column.' DESC,r.created_at DESC,r.id LIMIT 100 OFFSET '.(($page-1)*100),$params);
  $byPerson=$a->all('SELECT r.user_id,u.name,'.$sum.' FROM {{salary_advances}} r JOIN {{users}} u ON u.id=r.user_id WHERE '.$where.' GROUP BY r.user_id,u.name ORDER BY u.name',$params);
  $byDay=$a->all('SELECT '.$column.' day,'.$sum.' FROM {{salary_advances}} r WHERE '.$where.' GROUP BY '.$column.' ORDER BY '.$column.' DESC',$params);
  return [200,['rows'=>$rows,'summary'=>$summary,'by_person'=>$byPerson,'by_day'=>$byDay,'people'=>$people,'page'=>$page,'total'=>(int)$summary['requests'],'start'=>$start,'end'=>$end,'basis'=>$basis,'status_counts'=>$counts]];
 }
 if(preg_match('~^/advances/([a-f0-9-]{36})/(review|pay|cancel|audit|date)$~D',$path,$m)){
  $id=entry_uuid($m[1]);$row=$a->one('SELECT * FROM {{salary_advances}} WHERE id=?',[$id]);if(!$row)fail('Không tìm thấy yêu cầu ứng lương.',404);
  if(!$manager&&$row['user_id']!==$a->user['id'])fail('Bạn không có quyền xem yêu cầu này.',403);
  if($m[2]==='audit'&&$method==='GET')return [200,['rows'=>$a->all('SELECT h.*,u.name actor_name FROM {{advance_audit}} h JOIN {{users}} u ON u.id=h.actor_id WHERE advance_id=? ORDER BY h.time,h.id',[$id])]];
  if($method!=='POST')fail('Không tìm thấy chức năng.',404);
  if($m[2]==='cancel'){
   if($row['user_id']!==$a->user['id'])fail('Chỉ được hủy yêu cầu của chính mình.',403);
   entry_version($b,$row);if($row['status']!=='pending')fail('Chỉ hủy được yêu cầu đang chờ duyệt.',409);
   $a->run("UPDATE {{salary_advances}} SET status='cancelled',version=version+1 WHERE id=?",[$id]);finance_audit($a,'advance',$id,'cancelled','Nhân viên hủy yêu cầu.');return [200,['ok'=>true]];
  }
  $a->requireManager();if($row['user_id']===$a->user['id'])fail('Không được tự duyệt, xác nhận chi hoặc sửa ngày yêu cầu của mình.',403);$a->target($row['user_id'],true);entry_version($b,$row);
  // Sửa ngày (chủ dự án yêu cầu 04/11/2026): trả lương xong thì chuyển các phiếu ứng thuộc kỳ lương trước về tháng trước,
  // để danh sách và thống kê tháng mới bắt đầu lại. Đổi ngày đề nghị, phiếu đã chi đổi cả ngày đã chi; lịch sử giữ ngày cũ.
  if($m[2]==='date'){
   $req=valid_day($b['requested_date']??null);if($req<'2020-01-01'||day_time($req)>now_ms()+366*86400000)fail('Ngày đề nghị không hợp lệ.');
   $paid=$row['paid_date'];
   if($row['status']==='paid'){$paid=valid_day($b['paid_date']??null);if($paid<'2020-01-01'||$paid>local_day(now_ms()))fail('Ngày đã chi phải từ 2020 đến hôm nay.');}
   if($req===$row['requested_date']&&$paid===$row['paid_date'])fail('Ngày chưa thay đổi.');
   $note=input_text($b['note']??'','Lý do đổi ngày',0,500);$dmy=fn($d)=>$d?implode('/',array_reverse(explode('-',$d))):'—';$parts=[];
   if($req!==$row['requested_date'])$parts[]='Ngày đề nghị '.$dmy($row['requested_date']).' → '.$dmy($req);
   if($paid!==$row['paid_date'])$parts[]='Ngày đã chi '.$dmy($row['paid_date']).' → '.$dmy($paid);
   $a->run('UPDATE {{salary_advances}} SET requested_date=?,paid_date=?,version=version+1 WHERE id=?',[$req,$paid,$id]);
   finance_audit($a,'advance',$id,'date_changed',implode(' · ',$parts).($note!==''?' · '.$note:''));
   return [200,['ok'=>true,'requested_date'=>$req,'paid_date'=>$paid]];
  }
  if($m[2]==='review'){
   if($row['status']!=='pending')fail('Yêu cầu đã được xử lý.',409);
   $status=$b['status']??'';if(!in_array($status,['approved','rejected'],true))fail('Kết quả duyệt không hợp lệ.');
   $note=input_text($b['note']??'','Ghi chú',$status==='rejected'?5:0,1000);
   $a->run('UPDATE {{salary_advances}} SET status=?,reviewed_by=?,reviewed_at=?,review_note=?,version=version+1 WHERE id=?',[$status,$a->user['id'],now_ms(),$note,$id]);finance_audit($a,'advance',$id,$status,$note);return [200,['ok'=>true]];
  }
  if($m[2]==='pay'){
   if($row['status']!=='approved')fail('Chỉ xác nhận chi yêu cầu đã được duyệt và chưa chi.',409);
   if(($b['confirmed']??false)!==true)fail('Cần xác nhận đã thực sự giao/chuyển tiền.');
   $day=valid_day($b['paid_date']??null);if($day<'2020-01-01'||$day>local_day(now_ms())||$day<local_day($row['created_at']))fail('Ngày đã chi phải từ ngày tạo yêu cầu đến hôm nay.');
   $note=input_text($b['note']??'','Ghi chú chi tiền',0,1000);
   $a->run("UPDATE {{salary_advances}} SET status='paid',paid_date=?,paid_by=?,paid_at=?,payment_note=?,version=version+1 WHERE id=?",[$day,$a->user['id'],now_ms(),$note,$id]);finance_audit($a,'advance',$id,'paid',$day.' · '.$note);return [200,['ok'=>true]];
  }
 }
 fail('Không tìm thấy chức năng ứng lương.',404);
}
// Admin và Quản lý luôn được ghi, sửa, xử lý Event log; Nhân viên cần Admin tick "Viết Event Log" trong hồ sơ (chủ dự án chốt 30/10/2026).
// Xóa log chỉ Admin và Quản lý (chủ dự án yêu cầu 30/10/2026).
function log_permissions(App $a): array {
 $full=in_array($a->user['role'],['admin','manager'],true);$delegated=$a->user['role']==='employee'&&(bool)$a->one('SELECT user_id FROM {{event_log_recorders}} WHERE user_id=?',[$a->user['id']]);
 return ['can_record'=>$full||$delegated,'can_manage'=>$full||$delegated,'can_delete'=>$full,'can_grant'=>$a->user['role']==='admin','delegated'=>$delegated];
}
// Điểm KPI khi ghi Event log: [điểm trừ, lần mắc lỗi trong tháng hoặc null]. Luật nặng: điểm cố định của luật.
// offense 1/2/3 → 20/100/300, 0 = Nhắc nhở, không trừ điểm. Không gửi offense mà chọn luật thường (Telegram /log, giao diện cũ) thì tự đếm số lần trong tháng;
// hộp ghi nhanh (nghỉ gấp, đối soát chấm công) gửi offense=null kèm deduction để giữ đúng điểm đã chọn.
function log_points(App $a,?array $rule,array $b,?int $subject,string $day,?string $except=null): array {
 if($rule&&(int)$rule['points']>=LOG_FIXED_POINTS)return [(int)$rule['points'],null];
 $n=$b['offense']??null;
 if($n!==null){if(!is_int($n)||$n<0||$n>3)fail('Chọn lần mắc lỗi trong tháng: Lần 1, Lần 2 hoặc Lần 3.');return [$n?LOG_TIER_POINTS[$n]:0,$n?:null];}
 $d=$b['deduction']??0;
 if($rule&&!array_key_exists('offense',$b)&&$d===0){if($subject===null)return [0,null];$n=min(3,log_offense_count($a,$subject,$rule['id'],$day,$except)+1);return [LOG_TIER_POINTS[$n],$n];}
 if(!is_int($d)||!in_array($d,[0,20,100,300],true))fail('Điểm KPI cần là 0, 20, 100 hoặc 300.');
 return [$d,null];
}
// Số lần người này đã bị ghi cùng luật trong tháng của $day (log chờ xác nhận, đã xác nhận, đã xử lý; không tính log đã hủy / đã xóa).
function log_offense_count(App $a,int $user,string $rule,string $day,?string $except=null): int {
 $m=substr($day,0,7);
 return (int)$a->scalar("SELECT COUNT(*) FROM {{event_log}} WHERE user_id=? AND rule_id=? AND event_date>=? AND event_date<=? AND status IN ('pending','confirmed','resolved')".($except?' AND id<>?':''),[$user,$rule,$m.'-01',$m.'-31',...($except?[$except]:[])]);
}
function log_points_note(int $deduction,?int $offense,?array $rule): string {
 if($rule&&(int)$rule['points']>=LOG_FIXED_POINTS)return ' Luật nặng '.$rule['code'].': trừ cố định '.$deduction.' điểm KPI.';
 return $offense?' Lần '.$offense.' trong tháng ('.LOG_TIER_NAMES[$offense].'), trừ '.$deduction.' điểm KPI.':'';
}
// Cấp quyền trong hồ sơ nhân viên (chủ dự án yêu cầu 30/10/2026): Viết Event Log, Lên lịch chụp; thêm Sửa Biên bản họp (02/11/2026). Chỉ Admin tick;
// Admin và Quản lý luôn có đủ các quyền nên không giữ dòng cấp quyền cho họ; Makeup không được cấp quyền nào.
const USER_GRANTS=['event_log'=>['event_log_recorders','grant_event_log','revoke_event_log'],'bookings'=>['booking_creators','grant_booking_create','revoke_booking_create'],'meetings'=>['meeting_editors','grant_meeting_edit','revoke_meeting_edit'],'bonus'=>['payroll_bonus_editors','grant_bonus_entry','revoke_bonus_entry']];
function user_grants(App $a): array {$out=[];foreach(USER_GRANTS as $k=>[$table])$out[$k]=array_fill_keys(array_map('intval',array_column($a->all('SELECT user_id FROM {{'.$table.'}}'),'user_id')),true);return $out;}
function save_user_grants(App $a,int $id,string $role,mixed $grants): void {
 if(!is_array($grants))fail('Cấp quyền không hợp lệ.');
 foreach(USER_GRANTS as $k=>[$table,$grant,$revoke]){
  // Giao diện cũ (chưa có ô mới) không gửi khóa này: giữ nguyên quyền đang có, trừ khi không còn là Nhân viên.
  if(!array_key_exists($k,$grants)&&$role==='employee')continue;
  if(array_key_exists($k,$grants)&&!is_bool($grants[$k]))fail('Cấp quyền không hợp lệ.');
  $had=(bool)$a->one('SELECT user_id FROM {{'.$table.'}} WHERE user_id=?',[$id]);$want=$role==='employee'&&($grants[$k]??false);if($want===$had)continue;
  if($want)$a->run('INSERT INTO {{'.$table.'}} VALUES(?,?,?)',[$id,$a->user['id'],now_ms()]);else $a->run('DELETE FROM {{'.$table.'}} WHERE user_id=?',[$id]);
  if($role==='employee')$a->audit($id,$want?$grant:$revoke);
 }
}
// Người được cấp quyền (không phải Admin) sửa, bổ sung, xử lý hoặc hủy log ghi về chính mình: không áp dụng ngay,
// tạo yêu cầu kèm nội dung trước/sau; Quản lý hoặc Admin khác duyệt mới áp dụng (chủ dự án chốt 29/10/2026).
const LOG_STATUS_LABELS=['pending'=>'Chờ xác nhận','confirmed'=>'Đã xác nhận','resolved'=>'Đã xử lý','void'=>'Đã hủy','historical'=>'Log lưu trữ','deleted'=>'Đã xóa'];
const LOG_CATEGORY_LABELS=['missing_punch'=>'Quên chấm công','late'=>'Đi trễ','early_out'=>'Về sớm','absence'=>'Vắng ca','customer'=>'Khách hàng','task'=>'Công việc','other'=>'Khác'];
const LOG_CHANGE_LABELS=['edit'=>'Chỉnh sửa nội dung','classify'=>'Bổ sung ngày / nhân viên','resolve'=>'Xử lý / hủy log','delete'=>'Xóa log'];
function log_needs_approval(App $a,array $row): bool {return $a->user['role']!=='admin'&&$row['user_id']!==null&&(int)$row['user_id']===(int)$a->user['id'];}
function log_person(App $a,?int $id): string {return $id?(string)$a->scalar('SELECT name FROM {{users}} WHERE id=?',[$id]):'Chưa gắn nhân viên';}
function log_change_text(array $fields): string {return implode('; ',array_map(fn($k,$v)=>$k.': '.$v,array_keys($fields),$fields));}
function log_request_change(App $a,array $row,string $action,array $b,array $before,array $after): array {
 if($a->one("SELECT id FROM {{event_log_change_requests}} WHERE event_log_id=? AND status='pending'",[$row['id']]))fail('Log này đã có yêu cầu đang chờ Quản lý duyệt.',409);
 $id=uuid_v4();unset($b['version']);
 $a->run("INSERT INTO {{event_log_change_requests}}(id,event_log_id,action,payload_json,before_json,after_json,base_version,requested_by,requested_at,status,review_note) VALUES(?,?,?,?,?,?,?,?,?,'pending','')",[$id,$row['id'],$action,json_encode($b,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),json_encode($before,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),json_encode($after,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),$row['version'],$a->user['id'],now_ms()]);
 finance_audit($a,'log',$row['id'],'change_requested',LOG_CHANGE_LABELS[$action].' · chờ Quản lý duyệt | Trước: '.log_change_text($before).' | Sau: '.log_change_text($after));
 return [202,['pending_approval'=>true,'request_id'=>$id]];
}
function log_change_routes(App $a,string $path,array $b,string $method): ?array {
 if(!preg_match('~^/event-log/requests/([a-f0-9-]{36})/(approve|reject|cancel)$~D',$path,$m)||$method!=='POST')return null;
 $req=$a->one('SELECT * FROM {{event_log_change_requests}} WHERE id=?',[entry_uuid($m[1])]);if(!$req)fail('Không tìm thấy yêu cầu.',404);if($req['status']!=='pending')fail('Yêu cầu đã được xử lý.',409);
 $note=input_text($b['note']??'','Ghi chú',$m[2]==='reject'?5:0,1000);$label=LOG_CHANGE_LABELS[$req['action']]??$req['action'];
 $close=fn(string $status)=>$a->run('UPDATE {{event_log_change_requests}} SET status=?,reviewed_by=?,reviewed_at=?,review_note=? WHERE id=?',[$status,$a->user['id'],now_ms(),$note,$req['id']]);
 if($m[2]==='cancel'){if($req['requested_by']!==$a->user['id'])fail('Chỉ người gửi được rút yêu cầu.',403);$close('cancelled');finance_audit($a,'log',$req['event_log_id'],'change_cancelled','Rút yêu cầu '.$label.($note!==''?' | '.$note:''));return [200,['ok'=>true]];}
 if(!in_array($a->user['role'],['admin','manager'],true))fail('Chỉ Quản lý hoặc Admin được duyệt yêu cầu sửa/hủy log.',403);
 if($req['requested_by']===$a->user['id'])fail('Không được tự duyệt yêu cầu của mình.',403);
 if($m[2]==='reject'){$close('rejected');finance_audit($a,'log',$req['event_log_id'],'change_rejected','Từ chối '.$label.' | '.$note);return [200,['ok'=>true]];}
 $row=$a->one('SELECT * FROM {{event_log}} WHERE id=?',[$req['event_log_id']]);if(!$row)fail('Không tìm thấy sự việc.',404);
 if($row['version']!==$req['base_version'])fail('Log đã thay đổi sau khi gửi yêu cầu. Hãy từ chối để người gửi tạo lại.',409);
 $requester=$a->one("SELECT * FROM {{users}} WHERE id=? AND archived=0 AND status!='inactive'",[$req['requested_by']]);if(!$requester)fail('Người gửi đã nghỉ. Hãy từ chối yêu cầu.',409);
 // Áp dụng đúng thao tác người gửi đã yêu cầu (vẫn kiểm tra quyền và dữ liệu của họ), rồi ghi người duyệt.
 $approver=$a->user;$a->user=$requester;
 try{log_routes($a,'/event-log/'.$row['id'].'/'.$req['action'],json_decode($req['payload_json'],true,32,JSON_THROW_ON_ERROR)+['version'=>$req['base_version']],'POST',true);}finally{$a->user=$approver;}
 $close('approved');finance_audit($a,'log',$req['event_log_id'],'change_approved','Duyệt '.$label.' của '.$requester['name'].($note!==''?' | '.$note:''));
 return [200,['ok'=>true]];
}
function log_change_requests(App $a): array {
 $approver=in_array($a->user['role'],['admin','manager'],true);
 $rows=$a->all("SELECT c.id,c.event_log_id,c.action,c.before_json,c.after_json,c.requested_by,c.requested_at,u.name requester_name,l.description,l.event_date,(SELECT name FROM {{users}} WHERE id=l.user_id) subject_name FROM {{event_log_change_requests}} c JOIN {{users}} u ON u.id=c.requested_by JOIN {{event_log}} l ON l.id=c.event_log_id WHERE c.status='pending'".($approver?'':' AND c.requested_by=?').' ORDER BY c.requested_at',$approver?[]:[$a->user['id']]);
 foreach($rows as &$r){$r['before']=json_decode($r['before_json'],true);$r['after']=json_decode($r['after_json'],true);unset($r['before_json'],$r['after_json']);$r['can_review']=$approver&&$r['requested_by']!==$a->user['id'];}unset($r);
 return $rows;
}
function log_routes(App $a,string $path,array $b,string $method,bool $approved=false): ?array {
 if($path!=='/event-log'&&!str_starts_with($path,'/event-log/'))return null;
 if($r=log_change_routes($a,$path,$b,$method))return $r;
 $perms=log_permissions($a);$manage=$perms['can_manage'];
 if($path==='/event-log/source'&&$method==='GET'){$file=__DIR__.'/legacy-event-log.pdf';if(!is_file($file))fail('Chưa có PDF gốc.',404);header('Content-Type: application/pdf');header('Content-Disposition: inline; filename="EVENT-LOG-STUDIO.pdf"');readfile($file);exit;}
 if(preg_match('~^/event-log/([a-f0-9-]{36})/classify$~D',$path,$m)&&$method==='POST'){
  if(!$manage)fail('Chỉ Admin, Quản lý hoặc nhân viên được Admin cấp quyền mới được chỉnh sửa Event log.',403);$row=$a->one('SELECT * FROM {{event_log}} WHERE id=?',[$m[1]]);if(!$row||$row['status']==='deleted')fail('Không tìm thấy log.',404);entry_version($b,$row);if(!in_array($row['status'],['pending','historical'],true))fail('Chỉ bổ sung thông tin cho log chưa xác nhận hoặc log lưu trữ.',409);
  $subject=$b['user_id']??null;if($subject!==null){if(!is_int($subject))fail('Nhân viên không hợp lệ.');if(!$a->one("SELECT id FROM {{users}} WHERE id=? AND role='employee'",[$subject]))fail('Chỉ gắn log cho tài khoản Nhân viên.');}
  $precision=$b['date_precision']??'';if(!in_array($precision,['day','month','unknown'],true))fail('Độ chính xác ngày không hợp lệ.');
  $day=$precision==='day'?valid_day($b['event_date']??null):($precision==='month'?valid_month($b['event_date']??null):'');if($day&&$day>local_day(now_ms()))fail('Ngày không được ở tương lai.');
  $note=input_text($b['note']??null,'Lý do bổ sung',5,1000);
  if(!$approved&&log_needs_approval($a,$row))return log_request_change($a,$row,'classify',$b,['Ngày'=>$row['event_date']?:'Chưa xác định','Nhân viên'=>log_person($a,$row['user_id'])],['Ngày'=>$day?:'Chưa xác định','Nhân viên'=>log_person($a,$subject),'Lý do'=>$note]);
  $a->run("UPDATE {{event_log}} SET user_id=?,event_date=?,date_precision=?,end_month='',updated_by=?,updated_at=?,version=version+1 WHERE id=?",[$subject,$day,$precision,$a->user['id'],now_ms(),$row['id']]);
  $beforeName=$row['user_id']?$a->scalar('SELECT name FROM {{users}} WHERE id=?',[$row['user_id']]):'Chưa gắn nhân viên';$afterName=$subject?$a->scalar('SELECT name FROM {{users}} WHERE id=?',[$subject]):'Chưa gắn nhân viên';finance_audit($a,'log',$row['id'],'classified','Ngày: '.($row['event_date']?:'Chưa xác định').' → '.($day?:'Chưa xác định').' | Nhân viên: '.$beforeName.' → '.$afterName.' | '.$note);return [200,['ok'=>true]];
 }
 if($path==='/event-log/recorders'&&$method==='POST'){
  $a->requireAdmin();if(!is_int($b['user_id']??null)||!is_bool($b['enabled']??null))fail('Quyền ghi Event log không hợp lệ.');
  $target=$a->target($b['user_id']);if($target['role']!=='employee')fail('Admin và Quản lý luôn có quyền chỉnh sửa Event log.');
  if($b['enabled'])$a->run('INSERT INTO {{event_log_recorders}} VALUES(?,?,?) ON DUPLICATE KEY UPDATE granted_by=VALUES(granted_by),granted_at=VALUES(granted_at)',[$target['id'],$a->user['id'],now_ms()]);else $a->run('DELETE FROM {{event_log_recorders}} WHERE user_id=?',[$target['id']]);
  $a->audit($target['id'],$b['enabled']?'grant_event_log':'revoke_event_log');return [200,['ok'=>true]];
 }
 if($path==='/event-log'&&$method==='POST'){
  if(!$perms['can_record'])fail('Bạn chưa được cấp quyền ghi Event log.',403);
  $id=entry_uuid($b['id']??null);$day=valid_day($b['event_date']??null);if($day<'2020-01-01'||$day>local_day(now_ms()))fail('Ngày sự việc không được ở tương lai.');
  $subject=$b['user_id']??null;if($subject!==null){if(!is_int($subject))fail('Nhân viên không hợp lệ.');$target=$a->one("SELECT id,role FROM {{users}} WHERE id=? AND archived=0 AND status!='inactive' AND role='employee'",[$subject]);if(!$target)fail('Chỉ ghi Event log cho tài khoản Nhân viên đang làm.');}
  $rule=function_exists('studio_rule_pick')?studio_rule_pick($a,$b):null;
  [$deduction,$offense]=log_points($a,$rule,$b,$subject,$day);if($subject===null&&$deduction!==0)fail($rule?'Nhật ký chung studio không trừ điểm. Chọn nhân viên liên quan để áp luật '.$rule['code'].'.':'Nhật ký chung studio chỉ ghi nhận, không trừ điểm.');
  $category=$b['category']??'';if(!in_array($category,LOG_CATEGORIES,true))fail('Loại sự việc không hợp lệ.');
  $description=input_text($b['description']??null,'Nội dung sự việc',5,2000);$existing=$a->one('SELECT * FROM {{event_log}} WHERE id=?',[$id]);
  if($existing){if($existing['created_by']===$a->user['id']&&$existing['user_id']===$subject&&$existing['event_date']===$day&&$existing['category']===$category&&$existing['points']===-$deduction&&$existing['description']===$description)return [200,['id'=>$id,'already_saved'=>true]];fail('Mã sự việc đã được sử dụng.',409);}
  $status=$manage?'confirmed':'pending';$a->run("INSERT INTO {{event_log}}(id,user_id,event_date,category,description,rule_id,rule_code,rule_name,points,offense,status,created_by,created_at,updated_by,updated_at,resolution) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'')",[$id,$subject,$day,$category,$description,$rule['id']??null,$rule['code']??'',$rule['name']??'',-$deduction,$offense,$status,$a->user['id'],now_ms(),$a->user['id'],now_ms()]);
  finance_audit($a,'log',$id,'created','Ghi nhận bởi người được cấp quyền chỉnh sửa Event log.'.log_points_note($deduction,$offense,$rule));return [201,['id'=>$id,'status'=>$status]];
 }
 // Gợi ý ô Lần mắc lỗi khi chọn nhân viên + luật trên form ghi Event log: đã bị ghi luật này bao nhiêu lần trong tháng.
 if($path==='/event-log/offenses'&&$method==='GET'){
  if(!$perms['can_record'])fail('Bạn chưa được cấp quyền ghi Event log.',403);
  if(!ctype_digit((string)($_GET['user_id']??'')))fail('Nhân viên không hợp lệ.');
  $rule=studio_rule_pick($a,['rule_id'=>(string)($_GET['rule_id']??'')]);if(!$rule)fail('Chọn luật.');
  $day=valid_day(($_GET['day']??'')?:local_day(now_ms()));
  return [200,['count'=>log_offense_count($a,(int)$_GET['user_id'],$rule['id'],$day),'month'=>substr($day,0,7),'fixed'=>(int)$rule['points']>=LOG_FIXED_POINTS]];
 }
 if($path==='/event-log'&&$method==='GET'){
  [$start,$end]=finance_range();$period=$_GET['period']??'month';if(!in_array($period,['all','month','unknown'],true))fail('Khoảng lịch sử không hợp lệ.');$params=[];$where="r.status!='deleted'";if($period==='unknown')$where.=" AND r.date_precision='unknown'";elseif($period==='month'){if(!empty($_GET['date'])){$where.=" AND ((r.date_precision='day' AND r.event_date=?) OR (r.date_precision='day_range' AND r.event_date<=? AND r.end_date>=?))";$params=[$start,$start,$start];}else{$month=substr($start,0,7);$where.=" AND ((r.date_precision='day' AND r.event_date>=? AND r.event_date<=?) OR (r.date_precision='day_range' AND r.event_date<=? AND r.end_date>=?) OR (r.date_precision IN ('month','range') AND r.event_date<=? AND (CASE WHEN r.end_month='' THEN r.event_date ELSE r.end_month END)>=?))";$params=[$start,$end,$end,$start,$month,$month];}}
  if(!empty($_GET['user_id'])){if(!ctype_digit((string)$_GET['user_id']))fail('Nhân viên không hợp lệ.');$where.=' AND r.user_id=?';$params[]=(int)$_GET['user_id'];}
  $status=$_GET['status']??'';if($status!==''){if(!in_array($status,['pending','confirmed','resolved','void','historical'],true))fail('Trạng thái không hợp lệ.');$where.=' AND r.status=?';$params[]=$status;}
  $q=input_text($_GET['q']??'','Từ khóa',0,200);if($q!==''){$where.=' AND (r.description LIKE ? OR r.related_names LIKE ? OR r.source_label LIKE ? OR EXISTS(SELECT 1 FROM {{users}} person WHERE person.id=r.user_id AND person.name LIKE ?))';$params=[...$params,'%'.$q.'%','%'.$q.'%','%'.$q.'%','%'.$q.'%'];}
  $page=finance_page();$per=(int)($_GET['per_page']??100);if(!in_array($per,[10,20,50,100],true))fail('Số dòng mỗi trang không hợp lệ.');$summary=$a->one("SELECT COUNT(*) total,COALESCE(SUM(CASE WHEN r.status='pending' THEN 1 ELSE 0 END),0) pending,COALESCE(SUM(CASE WHEN r.status IN ('confirmed','resolved') THEN -r.points ELSE 0 END),0) deduction_points FROM {{event_log}} r WHERE ".$where,$params);
  $rows=$a->all('SELECT r.*,u.name,u.employee_code,u.role,(SELECT name FROM {{users}} WHERE id=r.created_by) creator_name,(SELECT name FROM {{users}} WHERE id=r.updated_by) updater_name,(SELECT c.action FROM {{event_log_change_requests}} c WHERE c.event_log_id=r.id AND c.status=\'pending\' LIMIT 1) pending_change FROM {{event_log}} r LEFT JOIN {{users}} u ON u.id=r.user_id WHERE '.$where.' ORDER BY r.event_date DESC,r.created_at DESC,r.id LIMIT '.$per.' OFFSET '.(($page-1)*$per),$params);
  $byPerson=$a->all("SELECT r.user_id,u.name,COUNT(*) total,COALESCE(SUM(CASE WHEN r.status IN ('confirmed','resolved') THEN -r.points ELSE 0 END),0) deduction_points FROM {{event_log}} r LEFT JOIN {{users}} u ON u.id=r.user_id WHERE ".$where.' GROUP BY r.user_id,u.name ORDER BY u.name',$params);
  $people=finance_people($a,true);
  $recorders=$perms['can_grant']?array_column($a->all('SELECT user_id FROM {{event_log_recorders}}'),'user_id'):[];
  return [200,['rows'=>$rows,'summary'=>$summary,'by_person'=>$byPerson,'people'=>$people,'recorders'=>$recorders,'change_requests'=>log_change_requests($a),'permissions'=>$perms+['can_approve'=>in_array($a->user['role'],['admin','manager'],true)],'page'=>$page,'per_page'=>$per,'total'=>(int)$summary['total'],'start'=>$start,'end'=>$end,'period'=>$period]];
 }
 if(preg_match('~^/event-log/([a-f0-9-]{36})/edit$~D',$path,$m)&&$method==='POST'){
  if(!$manage)fail('Chỉ Admin, Quản lý hoặc nhân viên được Admin cấp quyền mới được chỉnh sửa Event log.',403);
  $row=$a->one('SELECT * FROM {{event_log}} WHERE id=?',[$m[1]]);if(!$row||$row['status']==='deleted')fail('Không tìm thấy sự việc.',404);entry_version($b,$row);
  $description=input_text($b['description']??null,'Nội dung sự việc',5,2000);$note=input_text($b['note']??null,'Lý do chỉnh sửa',5,1000);$category=$b['category']??'';if(!in_array($category,LOG_CATEGORIES,true))fail('Loại sự việc không hợp lệ.');
  if(!$approved&&log_needs_approval($a,$row))return log_request_change($a,$row,'edit',$b,['Loại'=>LOG_CATEGORY_LABELS[$row['category']]??$row['category'],'Nội dung'=>$row['description']],['Loại'=>LOG_CATEGORY_LABELS[$category],'Nội dung'=>$description,'Lý do'=>$note]);
  $a->run('UPDATE {{event_log}} SET description=?,category=?,updated_by=?,updated_at=?,version=version+1 WHERE id=?',[$description,$category,$a->user['id'],now_ms(),$row['id']]);
  finance_audit($a,'log',$row['id'],'edited',$note.' | Loại: '.$row['category'].' → '.$category.' | Nội dung trước: '.$row['description'].' | Nội dung sau: '.$description);return [200,['ok'=>true]];
 }
 if(preg_match('~^/event-log/([a-f0-9-]{36})/(resolve|audit)$~D',$path,$m)){
  $id=entry_uuid($m[1]);$row=$a->one('SELECT * FROM {{event_log}} WHERE id=?',[$id]);if(!$row||$row['status']==='deleted')fail('Không tìm thấy sự việc.',404);
  if($m[2]==='audit'&&$method==='GET')return [200,['rows'=>$a->all('SELECT h.*,u.name actor_name FROM {{event_log_audit}} h JOIN {{users}} u ON u.id=h.actor_id WHERE event_log_id=? ORDER BY h.time,h.id',[$id])]];
  if($m[2]==='resolve'&&$method==='POST'){
   if(!$manage)fail('Chỉ Admin, Quản lý hoặc nhân viên được Admin cấp quyền mới được chỉnh sửa Event log.',403);entry_version($b,$row);
   $status=$b['status']??'';$allowed=match($row['status']){'pending'=>['confirmed','void'],'historical'=>['confirmed','void'],'confirmed'=>['resolved','void'],'resolved'=>['void'],default=>[]};
   if(!in_array($status,$allowed,true))fail('Trạng thái sự việc đã thay đổi hoặc thao tác không hợp lệ.',409);
   if($row['created_by']===$a->user['id']&&$row['status']==='pending')fail('Không được tự xác nhận nhật ký đã gửi của mình.',403);
   $note=input_text($b['note']??null,'Ghi chú xử lý',5,2000);
   $points=$row['points'];$offense=isset($row['offense'])?(int)$row['offense']:null;$rule=null;if($status==='confirmed'&&function_exists('studio_rule_pick'))$rule=studio_rule_pick($a,$b);
   if($status==='confirmed'&&($rule||array_key_exists('offense',$b)||array_key_exists('deduction',$b))){[$d,$offense]=log_points($a,$rule,$b,$row['user_id'],strlen((string)$row['event_date'])===10?$row['event_date']:local_day(now_ms()),$row['id']);if($row['user_id']===null&&$d!==0)fail('Nhật ký chung studio không trừ điểm.');$points=-$d;}
   if(!$approved&&log_needs_approval($a,$row))return log_request_change($a,$row,'resolve',$b,['Trạng thái'=>LOG_STATUS_LABELS[$row['status']]??$row['status'],'Điểm KPI'=>abs($row['points']),'Luật'=>$row['rule_code']?:'—'],['Trạng thái'=>LOG_STATUS_LABELS[$status],'Điểm KPI'=>abs($points),'Luật'=>$rule['code']??($row['rule_code']?:'—'),'Giải trình'=>$note]);
   $a->run('UPDATE {{event_log}} SET status=?,points=?,offense=?,resolution=?,rule_id=COALESCE(?,rule_id),rule_code=COALESCE(?,rule_code),rule_name=COALESCE(?,rule_name),updated_by=?,updated_at=?,version=version+1 WHERE id=?',[$status,$points,$offense,$note,$rule['id']??null,$rule['code']??null,$rule['name']??null,$a->user['id'],now_ms(),$id]);finance_audit($a,'log',$id,$status,$note.($rule?' | Luật: '.$rule['code'].' · '.$rule['name']:'').($points!==$row['points']?' | Điểm: '.$row['points'].' → '.$points:'').($offense&&$offense!==(isset($row['offense'])?(int)$row['offense']:null)?' | Lần '.$offense.' trong tháng ('.LOG_TIER_NAMES[$offense].')':''));return [200,['ok'=>true]];
  }
 }
 // Xóa log: ẩn khỏi danh sách và không tính KPI, vẫn giữ dòng và lịch sử. Gỡ liên kết đối soát chấm công / nghỉ gấp để ghi lại được.
 if(preg_match('~^/event-log/([a-f0-9-]{36})/delete$~D',$path,$m)&&$method==='POST'){
  if(!$perms['can_delete'])fail('Chỉ Admin hoặc Quản lý được xóa Event log.',403);
  $row=$a->one('SELECT * FROM {{event_log}} WHERE id=?',[$m[1]]);if(!$row||$row['status']==='deleted')fail('Không tìm thấy sự việc hoặc log đã bị xóa.',404);entry_version($b,$row);
  $note=input_text($b['note']??null,'Lý do xóa',5,1000);
  if(!$approved&&log_needs_approval($a,$row))return log_request_change($a,$row,'delete',$b,['Trạng thái'=>LOG_STATUS_LABELS[$row['status']]??$row['status'],'Điểm KPI'=>abs($row['points'])],['Trạng thái'=>LOG_STATUS_LABELS['deleted'],'Điểm KPI'=>0,'Lý do'=>$note]);
  $a->run("UPDATE {{event_log}} SET status='deleted',updated_by=?,updated_at=?,version=version+1 WHERE id=?",[$a->user['id'],now_ms(),$row['id']]);
  $a->run("UPDATE {{event_log_change_requests}} SET status='rejected',reviewed_by=?,reviewed_at=?,review_note='Log đã bị xóa.' WHERE event_log_id=? AND status='pending'",[$a->user['id'],now_ms(),$row['id']]);
  $links=array_column($a->all('SELECT source_key FROM {{attendance_issue_links}} WHERE event_log_id=?',[$row['id']]),'source_key');if($links)$a->run('DELETE FROM {{attendance_issue_links}} WHERE event_log_id=?',[$row['id']]);
  finance_audit($a,'log',$row['id'],'deleted',$note.' | Trạng thái trước: '.(LOG_STATUS_LABELS[$row['status']]??$row['status']).' | Điểm KPI: '.abs($row['points']).($links?' | Gỡ liên kết đối soát: '.implode(', ',$links):''));return [200,['ok'=>true]];
 }
 fail('Không tìm thấy chức năng Event log.',404);
}
function people_finance_routes(App $a,string $path,array $b,string $method): ?array {
 return advance_routes($a,$path,$b,$method)??payroll_bonus_routes($a,$path,$b,$method)??log_routes($a,$path,$b,$method);
}

function load_legacy_event_log(App $a): void {
 $file=__DIR__.'/legacy-event-log.json';if(!is_file($file))return;
 $meta='legacy_log_import_202510_v3';if($a->meta($meta)==='done')return;
 $admin=$a->one("SELECT id FROM {{users}} WHERE role='admin' AND archived=0 ORDER BY id LIMIT 1");if(!$admin)return;
 $data=json_decode(file_get_contents($file),true,64,JSON_THROW_ON_ERROR);if(($data['format']??'')!=='anhgau-legacy-log-v1')return;
 $a->tx(function()use($a,$data,$admin,$meta){
  if($a->meta($meta)==='done')return;
  foreach($data['entries'] as $r){$old=$a->one('SELECT * FROM {{event_log}} WHERE source_ref=?',[$r['source_ref']]);if($old){
   if(!$a->one("SELECT id FROM {{event_log_audit}} WHERE event_log_id=? AND action='classified'",[$old['id']])){
    $a->run('UPDATE {{event_log}} SET event_date=?,date_precision=?,original_date=?,end_month=?,end_date=?,version=version+1 WHERE id=?',[$r['event_date'],$r['date_precision'],$r['original_date'],$r['end_month'],$r['end_date'],$old['id']]);
    finance_audit_actor($a,$old['id'],$admin['id'],'source_date_refined',$r['date_note']);
    if($old['status']==='pending'&&$old['points']===0&&!$a->one("SELECT id FROM {{event_log_audit}} WHERE event_log_id=? AND action IN ('confirmed','resolved','void')",[$old['id']]))$a->run("UPDATE {{event_log}} SET status='historical' WHERE id=?",[$old['id']]);
   }continue;}
   $id=$r['id'];$a->run("INSERT INTO {{event_log}}(id,user_id,event_date,category,description,source_ref,source_label,date_precision,original_date,end_month,end_date,related_names,points,status,created_by,created_at,updated_by,updated_at,resolution) VALUES(?,NULL,?,'other',?,?,?,?,?,?,?,?,0,'historical',?,?,?,?,?)",[$id,$r['event_date'],$r['description'],$r['source_ref'],$r['source_label'],$r['date_precision'],$r['original_date'],$r['end_month'],$r['end_date'],$r['related_names'],$admin['id'],now_ms(),$admin['id'],now_ms(),'Nhập từ tài liệu lịch sử; chưa xác minh sự việc, danh tính viết tắt hoặc điểm KPI.']);
   finance_audit_actor($a,$id,$admin['id'],'legacy_import','Nhập nguyên nội dung mục '.$r['number'].' từ PDF. '.$r['date_note']);
  }
  $a->meta($meta,'done');
 });
}
function finance_audit_actor(App $a,string $id,int $actor,string $action,string $note): void {
 $a->run('INSERT INTO {{event_log_audit}}(event_log_id,actor_id,action,note,time) VALUES(?,?,?,?,?)',[$id,$actor,$action,$note,now_ms()]);
}
