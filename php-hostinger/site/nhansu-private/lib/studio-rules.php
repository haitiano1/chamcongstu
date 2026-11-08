<?php
declare(strict_types=1);
// Luật Studio: mọi người xem; chỉ Admin tạo/sửa/ngừng áp dụng; Admin và Quản lý xóa được.
// Xóa là xóa mềm (status=deleted): ẩn với mọi người, giữ dòng và lịch sử trước/sau; Event log cũ vẫn giữ mã/tên đã lưu.
// Điểm là điểm KPI trừ, không tự quy đổi thành tiền.
function studio_rule_input(array $b): array {
 $code=strtoupper(trim((string)($b['code']??'')));if(!preg_match('/^[A-Z0-9][A-Z0-9._-]{1,29}$/D',$code))fail('Mã lỗi gồm 2–30 ký tự: chữ không dấu, số, dấu gạch. Ví dụ NG-01.');
 $points=$b['points']??null;if(!is_int($points)||$points<0||$points>10000)fail('Điểm KPI trừ là số từ 0 đến 10000.');
 return ['code'=>$code,'name'=>input_text($b['name']??null,'Tên lỗi',2,200),'category'=>input_text($b['category']??null,'Loại luật',2,100),'content'=>input_text($b['content']??'','Nội dung',0,5000),'points'=>$points];
}
// Chọn luật khi ghi Event log: điểm lấy đúng theo luật (luật đang áp dụng), lưu kèm mã và tên lúc ghi.
function studio_rule_pick(App $a,array $b): ?array {
 $id=$b['rule_id']??null;if($id===null||$id==='')return null;
 $r=$a->one("SELECT id,code,name,points FROM {{studio_rules}} WHERE id=? AND status='active'",[entry_uuid($id)]);if(!$r)fail('Luật đã chọn không còn áp dụng. Tải lại.',409);return $r;
}
function studio_rule_free_code(App $a,string $code,?string $except=null): void {
 $hit=$a->one('SELECT id,status FROM {{studio_rules}} WHERE code=?'.($except?' AND id<>?':''),$except?[$code,$except]:[$code]);if(!$hit)return;
 if($hit['status']!=='deleted')fail('Mã lỗi '.$code.' đã có. Mỗi lỗi cần một mã riêng.',409);
 $a->run('UPDATE {{studio_rules}} SET code=? WHERE id=?',[substr($code,0,21).'~'.substr($hit['id'],0,8),$hit['id']]);
}
function studio_rule_audit(App $a,string $id,string $action,?array $before,?array $after,string $note=''): void {
 $a->run('INSERT INTO {{studio_rule_audit}}(rule_id,actor_id,action,before_json,after_json,note,time) VALUES(?,?,?,?,?,?,?)',[$id,$a->user['id'],$action,$before===null?null:json_encode($before,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),$after===null?null:json_encode($after,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),$note,now_ms()]);
}
function studio_rule_routes(App $a,string $path,array $b,string $method): ?array {
 if($path==='/guide'||str_starts_with($path,'/guide/'))return guide_routes($a,$path,$b,$method);
 if($path!=='/studio-rules'&&!str_starts_with($path,'/studio-rules/'))return null;
 $admin=$a->user['role']==='admin';
 if($path==='/studio-rules'&&$method==='GET'){
  if(function_exists('late_rule_seed'))late_rule_seed($a);
  $rows=$a->all('SELECT r.id,r.code,r.name,r.category,r.content,r.points,r.status,r.created_at,r.updated_at,r.version,(SELECT name FROM {{users}} WHERE id=r.updated_by) updater_name FROM {{studio_rules}} r'.($admin?" WHERE r.status!='deleted'":" WHERE r.status='active'").' ORDER BY r.category,r.code');
  return [200,['rows'=>$rows,'categories'=>array_values(array_unique(array_column($rows,'category'))),'can_edit'=>$admin,'can_delete'=>$admin||$a->user['role']==='manager']];
 }
 if(preg_match('~^/studio-rules/([a-f0-9-]{36})/audit$~D',$path,$m)&&$method==='GET'){
  $a->requireAdmin();return [200,['rows'=>$a->all('SELECT x.action,x.before_json,x.after_json,x.note,x.time,u.name actor_name FROM {{studio_rule_audit}} x JOIN {{users}} u ON u.id=x.actor_id WHERE x.rule_id=? ORDER BY x.id DESC',[entry_uuid($m[1])])]];
 }
 if(preg_match('~^/studio-rules/([a-f0-9-]{36})/delete$~D',$path,$m)&&$method==='POST'){
  $a->requireManager();$old=$a->one('SELECT * FROM {{studio_rules}} WHERE id=?',[entry_uuid($m[1])]);if(!$old||$old['status']==='deleted')fail('Không tìm thấy luật hoặc luật đã bị xóa.',404);entry_version($b,$old);
  $a->run("UPDATE {{studio_rules}} SET status='deleted',updated_by=?,updated_at=?,version=version+1 WHERE id=?",[$a->user['id'],now_ms(),$old['id']]);
  studio_rule_audit($a,$old['id'],'deleted',array_intersect_key($old,array_flip(['code','name','category','content','points','status'])),['status'=>'deleted'],input_text($b['note']??'','Lý do xóa',0,500));return [200,['ok'=>true]];
 }
 $a->requireAdmin();
 if($path==='/studio-rules'&&$method==='POST'){
  $r=studio_rule_input($b);studio_rule_free_code($a,$r['code']);
  $id=uuid_v4();$now=now_ms();
  $a->run("INSERT INTO {{studio_rules}}(id,code,name,category,content,points,status,created_by,created_at,updated_by,updated_at) VALUES(?,?,?,?,?,?,'active',?,?,?,?)",[$id,$r['code'],$r['name'],$r['category'],$r['content'],$r['points'],$a->user['id'],$now,$a->user['id'],$now]);
  studio_rule_audit($a,$id,'created',null,$r);return [201,['id'=>$id]];
 }
 if(preg_match('~^/studio-rules/([a-f0-9-]{36})/(edit|archive|restore)$~D',$path,$m)&&$method==='POST'){
  $old=$a->one('SELECT * FROM {{studio_rules}} WHERE id=?',[entry_uuid($m[1])]);if(!$old||$old['status']==='deleted')fail('Không tìm thấy luật.',404);entry_version($b,$old);
  $before=array_intersect_key($old,array_flip(['code','name','category','content','points','status']));
  if($m[2]==='edit'){
   $r=studio_rule_input($b);if($r['code']!==$old['code'])studio_rule_free_code($a,$r['code'],$old['id']);
   if($r===array_intersect_key($before,$r))fail('Chưa có thay đổi nào.');
   $a->run('UPDATE {{studio_rules}} SET code=?,name=?,category=?,content=?,points=?,updated_by=?,updated_at=?,version=version+1 WHERE id=?',[$r['code'],$r['name'],$r['category'],$r['content'],$r['points'],$a->user['id'],now_ms(),$old['id']]);
   studio_rule_audit($a,$old['id'],'edited',$before,$r+['status'=>$old['status']],input_text($b['note']??'','Ghi chú',0,500));return [200,['ok'=>true]];
  }
  $status=$m[2]==='archive'?'archived':'active';if($old['status']===$status)fail('Luật đã ở trạng thái này.',409);
  $a->run('UPDATE {{studio_rules}} SET status=?,updated_by=?,updated_at=?,version=version+1 WHERE id=?',[$status,$a->user['id'],now_ms(),$old['id']]);
  studio_rule_audit($a,$old['id'],$m[2]==='archive'?'archived':'restored',['status'=>$old['status']],['status'=>$status],input_text($b['note']??'','Ghi chú',0,500));return [200,['ok'=>true]];
 }
 fail('Không tìm thấy chức năng Luật Studio.',404);
}
// ===== Tìm hiểu web (chủ dự án yêu cầu 03/11/2026, Studio → Tìm hiểu web, #guide) =====
// Checklist từng chức năng cho Nhân viên: đọc hướng dẫn (có ảnh động), nắm rõ mới tick "đã được hướng dẫn". Lượt tick là bằng chứng
// (chủ dự án: "sau nhân viên lại cãi bảo em chưa biết, chưa được training"): lưu giờ + phiên bản + tiêu đề, không bỏ tick được.
// Sửa nội dung một mục thì tăng 'v' để mọi người xác nhận lại mục đó. Ảnh động: assets/guide-media.js (khóa trùng khóa mục).
const GUIDE_ITEMS=[
 'app'=>['v'=>1,'page'=>'attendance','title'=>'Mở app và tải bản mới','steps'=>[
  'Mở web anhgaustudio.com/nhansu, thêm vào màn hình chính (iPhone: nút Chia sẻ → Thêm vào MH chính; Android: ⋮ → Thêm vào màn hình chính). Lần sau mở bằng biểu tượng Anh Gấu.',
  'Thấy lỗi lạ hoặc Quản lý báo có bản mới: bấm nút ↻ ở góc trên bên phải để tải bản mới.',
  'Bấm chữ cái tên (avatar) ở góc phải để đổi mật khẩu hoặc đăng xuất. Không đưa mật khẩu cho người khác.',
  'Thanh dưới cùng: Chấm công, Lịch, Việc, Bảng công, Thêm (các mục còn lại).']],
 'telegram'=>['v'=>1,'page'=>'attendance','title'=>'Liên kết Telegram để nhận thông báo','steps'=>[
  'Ở đầu trang Chấm công, khung xanh "Liên kết Telegram" → bấm Liên kết ngay.',
  'Bấm "Mở Telegram, nhấn Start" → Telegram mở bot của studio → bấm Start. Quay lại app là khung xanh tự ẩn.',
  'Bot nhắc chấm vào ca, nhắc chấm ra, báo việc mới, kết quả duyệt, trả lời hỗ trợ và biên bản họp.',
  'Không liên kết thì không nhận được nhắc. Quên chấm công vẫn tính theo quy định.']],
 'checkin'=>['v'=>2,'page'=>'attendance','title'=>'Chấm công vào ca','steps'=>[
  'Vào trang Chấm công. Ca 1: 09:00–14:00, Ca 2: 14:00–19:00.',
  'Bấm đúng ca mình làm: Ca 1, Ca 2 hoặc Làm cả ngày. Chưa chọn ca thì nút chấm công bị khóa.',
  'Bấm nút Chấm công vào, cho phép camera và vị trí (GPS), chụp rõ mặt tại studio.',
  'Mốc đi muộn là khách đầu tiên của ca: tới sau giờ khách đầu tiên quá 30 phút là muộn. Trong tháng: lần 1 miễn, lần 2 trừ 20, lần 3 trừ 100, từ lần 4 trừ 300 điểm KPI.',
  'Đóng điểm danh: quá 1 tiếng sau khách đầu tiên (VD khách 10:00, 11:01 mới tới) thì ca đó không tính công. Bấm Ghi có mặt (chụp ảnh, GPS như thường) để khách bạn chụp vẫn tính hoa hồng.',
  'Ca mà cả studio không có khách vẫn chấm công, không tính công nhưng được thưởng "Ca làm không có khách" (xem Phiếu thưởng).']],
 'fullday'=>['v'=>1,'page'=>'attendance','title'=>'Làm cả ngày','steps'=>[
  'Chỉ chọn Làm cả ngày khi chấm vào lần đầu trong ngày, trong giờ Ca 1 (09:00–14:00).',
  'Bấm Làm cả ngày → tick "Hôm nay bạn làm cả ngày đúng không?" → chấm công vào.',
  'Đúng 14:00 app tự chấm ra Ca 1 và vào Ca 2. Cuối ngày chấm ra Ca 2 như thường.',
  'Chỉ làm 1 ca mà chọn Làm cả ngày sẽ bị phạt.']],
 'checkout'=>['v'=>1,'page'=>'attendance','title'=>'Chấm ra và làm tiếp','steps'=>[
  'Hết ca: ca đang làm hiện "Đang làm", app tự chọn ca đó → bấm Chấm công ra.',
  'Hạn chấm ra: Ca 1 trước 14:30, Ca 2 trước 19:30. Quá giờ phải gửi Yêu cầu bổ sung công.',
  'Về khi còn khách được giao: app cảnh báo, những khách đó không tính hoa hồng, Quản lý được báo.',
  'Đã chấm ra mà quay lại làm: bấm "Tôi đổi ý, làm tiếp" (trong giờ ca).',
  'Ca 2 phải xong hết Việc hằng ngày và việc có hạn hôm nay mới chấm ra được.']],
 'ot'=>['v'=>2,'page'=>'attendance','title'=>'Làm thêm giờ (OT)','steps'=>[
  'OT bắt đầu từ 19:00, chấm vào / ra OT riêng ở trang Chấm công, có hộp xác nhận.',
  'OT không tính theo giờ. Thưởng OT là hoa hồng khách OT: mỗi lịch chụp có giờ chụp trùng lượt OT của bạn = 1 khách × 2 lần mức hoa hồng của bạn. Quên chấm OT thì báo Quản lý chấm hộ đúng ngày giờ.',
  'Xem lại các lượt OT ở mục "Lịch sử OT của tôi" cuối trang Chấm công.']],
 'supplement'=>['v'=>2,'page'=>'attendance','title'=>'Yêu cầu bổ sung công','steps'=>[
  'Quên chấm công hoặc gặp sự cố: trang Chấm công → khung Yêu cầu bổ sung công → bấm Gửi yêu cầu.',
  'Bấm Ca 1, Ca 2, OT hoặc Cả 2 ca. Quên chấm cả lượt thì chọn "Cả vào và ra", nhập giờ vào, giờ ra thực tế, ghi rõ lý do → gửi.',
  'Quản lý duyệt mới được tính công. Xem trạng thái ở "Xem yêu cầu".',
  'Gửi nhầm: khi yêu cầu còn Chờ xử lý, bấm 🗑 góc phải yêu cầu để xóa.']],
 'bookings'=>['v'=>1,'page'=>'bookings','title'=>'Lịch chụp','steps'=>[
  'Bấm Lịch → Lịch chụp. Xem Toàn studio hoặc Lịch của tôi; điện thoại mở sẵn ngày hôm nay, bấm ‹ › để đổi ngày.',
  'Mỗi thẻ: giờ - tên khách - gói - ca - make up, dòng dưới là thợ. Bấm vào thẻ để xem đủ thông tin khách.',
  'Lịch Team: chụp xong mở lịch, nhập số khách theo từng gói bằng nút − / + rồi bấm Lưu số khách (tính vào hoa hồng).',
  'Hoa hồng khách chỉ tính khi bạn có chấm công trong giờ chụp của khách đó.']],
 'availability'=>['v'=>2,'page'=>'availability','title'=>'Đăng ký ca','steps'=>[
  'Bấm Lịch → Đăng ký ca. Thứ 7, Chủ nhật trang mở sẵn tuần tới.',
  'Thứ 7, Chủ nhật bắt buộc đi làm: 4 ca đã tự tính, không bỏ được.',
  'Tick thêm các ca chắc chắn đi làm cho đủ tối thiểu 10 ca một tuần, rồi bấm Chốt.',
  'Thứ 7 và Chủ nhật lúc 9h, 20h bot nhắc nếu chưa chốt tuần tới.',
  'Bỏ ca đã chốt trong vòng 2 ngày là nghỉ gấp, Quản lý được báo.']],
 'tasks'=>['v'=>1,'page'=>'tasks','title'=>'Việc và Nhắc nhở','steps'=>[
  'Bấm Việc → Việc được giao. Việc được chia theo ngày, tab Cần làm có số việc cần làm.',
  'Task: làm xong bấm Báo đã xong (chụp ảnh nếu việc yêu cầu). Nhắc nhở: đọc rồi bấm Đã xem.',
  'Task cần duyệt: báo xong là Chờ duyệt. Bị Chưa duyệt thì đọc lý do trên thẻ, làm lại và Báo đã xong lại.',
  'Không làm được: bấm Xin từ chối, ghi lý do, chờ Quản lý duyệt.',
  'Việc hằng ngày và việc có hạn hôm nay chưa xong thì không chấm ra Ca 2 được.']],
 'support'=>['v'=>1,'page'=>'tasks','title'=>'Hỗ trợ (thay cho nhắn tin riêng)','steps'=>[
  'Cần gì (thiết bị hỏng, đổi ca / nghỉ, lương thưởng…): Việc → Hỗ trợ → Gửi yêu cầu, hoặc khung "Cần hỗ trợ?" ở trang Chấm công.',
  'Chọn loại, ghi tiêu đề, nội dung, thêm ảnh nếu cần → Gửi yêu cầu. Quản lý / Admin nhận Telegram ngay.',
  'Quản lý trả lời trong yêu cầu, Telegram báo bạn. Mở yêu cầu để trả lời lại; không cần nhắn tin riêng.']],
 'payroll'=>['v'=>1,'page'=>'payroll','title'=>'Bảng công và Phiếu thưởng','steps'=>[
  'Bấm Bảng công. Phần trên: ngày đi làm, ca, OT, công, KPI còn lại, thanh chuyên cần (đủ 28 ngày / tháng được 500.000đ).',
  'Phiếu thưởng: từng khoản thưởng (chuyên cần, hoa hồng khách, Tips chia đều, ngoại cảnh, ăn uống…), KPI bị trừ và Thưởng thực nhận.',
  'Thấy sai: gửi Hỗ trợ loại Lương / thưởng để Quản lý kiểm tra.']],
 'kpi'=>['v'=>2,'page'=>'rules','title'=>'KPI, Luật Studio và Event log','steps'=>[
  'Mỗi tháng có 2.000 điểm KPI. 1 điểm bị trừ = 1.000đ, trừ vào Tổng thưởng.',
  'Có 4 mức: Nhắc nhở 0 KPI, Lỗi −20, Cảnh cáo −100, Kỷ luật −300. Mỗi lỗi đếm riêng trong tháng: Lần 1 Lỗi, Lần 2 Cảnh cáo, Lần 3 trở đi Kỷ luật. Luật nặng trừ đúng điểm của luật.',
  'Đọc kỹ Thêm → Luật Studio. Lỗi được ghi ở Thêm → Event log, ai cũng xem được.']],
 'advances'=>['v'=>1,'page'=>'advances','title'=>'Ứng lương','steps'=>[
  'Bấm Bảng công → Ứng lương → Gửi yêu cầu: chọn ngày muốn ứng, số tiền, ghi lý do.',
  'Không ứng gấp, nên báo trước ít nhất 1 ngày. Quản lý / Admin nhận Telegram và duyệt trong 24 giờ.',
  'Theo dõi trạng thái phiếu ngay trên trang Ứng lương.']],
 'meetings'=>['v'=>1,'page'=>'meetings','title'=>'Biên bản họp','steps'=>[
  'Sau buổi họp, biên bản chờ bạn xác nhận hiện khung đỏ ở đầu trang Chấm công, bot cũng nhắc.',
  'Mở biên bản (Thêm → Biên bản họp), đọc kỹ phần việc của mình rồi bấm "Đã đọc và xác nhận hiểu rõ nội dung".',
  'Biên bản sửa lại thì cần xác nhận lại.']],
];
function guide_items(): array {$out=[];foreach(GUIDE_ITEMS as $k=>$it)$out[]=['key'=>$k]+$it;return $out;}
function guide_checks(App $a,?int $user=null): array {
 $out=[];foreach($a->all('SELECT user_id,item,version,checked_at FROM {{guide_checks}}'.($user!==null?' WHERE user_id=?':'').' ORDER BY checked_at',$user!==null?[$user]:[]) as $r)if(isset(GUIDE_ITEMS[$r['item']])&&(int)$r['version']===GUIDE_ITEMS[$r['item']]['v'])$out[(int)$r['user_id']][$r['item']]=(int)$r['checked_at'];
 return $out;
}
function guide_notify(App $a,array $chats,string $text): int {
 if(!class_exists('Telegram'))return 0;$tg=support_telegram()??new Telegram($a);if(!$tg->configured())return 0;$sent=0;
 foreach($chats as $chat){try{$tg->call('sendMessage',['chat_id'=>$chat,'text'=>$text,'disable_web_page_preview'=>true]+$tg->button('Mở Tìm hiểu web',$a->config['app_url'].'/#guide'));$sent++;}catch(Throwable $e){$a->meta('telegram_error',$e->getMessage());}}
 return $sent;
}
function guide_routes(App $a,string $path,array $b,string $method): ?array {
 $manager=in_array($a->user['role'],['admin','manager'],true);$staff=$a->user['role']==='employee';$me=(int)$a->user['id'];$total=count(GUIDE_ITEMS);
 if($path==='/guide'&&$method==='GET'){
  $mine=$staff?(guide_checks($a,$me)[$me]??[]):[];
  $people=$manager?array_map(fn($p)=>['id'=>(int)$p['id'],'name'=>$p['name'],'checks'=>(object)(guide_checks($a,(int)$p['id'])[(int)$p['id']]??[])],finance_people($a)):[];
  return [200,['items'=>guide_items(),'mine'=>(object)$mine,'done'=>count($mine),'total'=>$total,'people'=>$people,'can_check'=>$staff,'can_manage'=>$manager]];
 }
 if($path==='/guide/check'&&$method==='POST'){
  if(!$staff)fail('Chỉ Nhân viên xác nhận checklist.',403);$key=$b['key']??'';if(!is_string($key)||!isset(GUIDE_ITEMS[$key]))fail('Mục không hợp lệ.');
  if(($b['confirm']??null)!==true)fail('Tick ô xác nhận đã nắm rõ chức năng.');
  $it=GUIDE_ITEMS[$key];$before=count(guide_checks($a,$me)[$me]??[]);
  $a->run('INSERT IGNORE INTO {{guide_checks}}(user_id,item,version,title,checked_at) VALUES(?,?,?,?,?)',[$me,$key,$it['v'],$it['title'],now_ms()]);
  $mine=guide_checks($a,$me)[$me]??[];$notified=0;
  if(count($mine)===$total&&$before<$total)$notified=guide_notify($a,array_column($a->all("SELECT l.chat_id FROM {{telegram_links}} l JOIN {{users}} u ON u.id=l.user_id WHERE u.role IN ('admin','manager') AND u.archived=0 AND u.status!='inactive'"),'chat_id'),"🎓 {$a->user['name']} đã xác nhận đủ $total/$total mục Tìm hiểu web (đã được hướng dẫn và nắm rõ từng chức năng).");
  return [200,['ok'=>true,'mine'=>(object)$mine,'done'=>count($mine),'total'=>$total,'notified'=>$notified]];
 }
 if($path==='/guide/remind'&&$method==='POST'){
  if(!$manager)fail('Chỉ Admin hoặc Quản lý gửi nhắc.',403);$checks=guide_checks($a);$sent=0;$who=[];
  foreach(finance_people($a) as $p){$left=$total-count($checks[(int)$p['id']]??[]);if($left<=0)continue;$who[]=$p['name'];
   $sent+=guide_notify($a,array_column($a->all('SELECT chat_id FROM {{telegram_links}} WHERE user_id=?',[$p['id']]),'chat_id'),"📚 Bạn còn $left/$total mục Tìm hiểu web chưa xác nhận.\nMở Thêm → Tìm hiểu web, đọc hướng dẫn từng chức năng, nắm rõ rồi tick xác nhận.");}
  return [200,['ok'=>true,'sent'=>$sent,'people'=>$who]];
 }
 return null;
}
