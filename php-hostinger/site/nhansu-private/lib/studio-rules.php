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
 if($path!=='/studio-rules'&&!str_starts_with($path,'/studio-rules/'))return null;
 $admin=$a->user['role']==='admin';
 if($path==='/studio-rules'&&$method==='GET'){
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
