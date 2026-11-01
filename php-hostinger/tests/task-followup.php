<?php
// Theo dõi hạn sau yêu cầu từ chối và Xóa việc hằng ngày (chủ dự án yêu cầu 01/11/2026).
// Chạy trong giao dịch rồi hoàn tác; Telegram tắt, không gửi tin thật.
foreach(['bootstrap','telegram','bookings','people-finance','tasks'] as $f)require_once __DIR__.'/../site/nhansu-private/lib/'.$f.'.php';
$a=new App('/var/nhansu-private');$n=0;function verify($v,$message){global $n;if(!$v)throw new RuntimeException('FAIL: '.$message);$n++;}
function denied(callable $fn,int $status,string $message){try{$fn();}catch(HttpError $e){verify($e->status===$status,$message.' (mã '.$e->status.': '.$e->getMessage().')');return;}verify(false,$message.' (không bị chặn)');}
class Rollback extends Exception {}
try{$a->tx(function()use($a){
 foreach(['telegram_links','task_declines'] as $t)$a->run('DELETE FROM {{'.$t.'}}');$a->config['telegram_token']='';
 $admin=$a->one("SELECT * FROM {{users}} WHERE role='admin' AND archived=0 ORDER BY id LIMIT 1");
 $make=function(string $key,string $role)use($a){$a->run("INSERT INTO {{users}}(name,email,password,role,employee_code,notes) VALUES(?,?,'x',?,?,'')",['TF '.$key,'tf.'.$key.'@local.test',$role,'TF-'.strtoupper($key)]);return $a->one('SELECT * FROM {{users}} WHERE id=LAST_INSERT_ID()');};
 $e=$make('mot','employee');$e2=$make('hai','employee');$m=$make('quanly','manager');
 $as=function(array $u)use($a){$a->user=$u;};$route=fn(string $path,array $b=[],string $method='POST')=>task_routes($a,$path,$b,$method);
 $task=fn(array $ids,string $kind='task',string $title='Dọn phòng chụp 2')=>task_create($a,(int)$admin['id'],['kind'=>$kind,'title'=>$title,'audience'=>'people','user_ids'=>$ids])['id'];
 $follow=function()use($a,$as,$m,$route){$as($m);$r=[];foreach($route('/tasks',[],'GET')[1]['decline_followups'] as $x)$r[$x['id']]=$x;return $r;};
 $ver=fn(string $id)=>(int)$a->scalar('SELECT version FROM {{tasks}} WHERE id=?',[$id]);$due=fn(string $id)=>$a->scalar('SELECT due_at FROM {{tasks}} WHERE id=?',[$id]);

 // Không duyệt: việc vẫn treo, hiện ở Theo dõi hạn tới khi báo xong.
 $t1=$task([(int)$e['id']]);$as($e);$d1=$route("/tasks/$t1/decline",['reason'=>'Hôm nay em bận việc nhà'])[1]['id'];
 verify(!isset($follow()[$t1]),'Đang chờ duyệt thì chưa vào Theo dõi hạn');
 $as($admin);$route("/tasks/declines/$d1/reject",['note'=>'Việc gấp, cố làm nhé']);
 $f=$follow();verify(isset($f[$t1])&&$f[$t1]['waiting']===['TF mot']&&$f[$t1]['declines'][0]['status']==='rejected'&&$f[$t1]['due_at']===null,'Không duyệt → hiện Theo dõi hạn, còn chờ TF mot, chưa có hạn');
 $as($e);denied(fn()=>$route("/tasks/$t1/due",['version'=>$ver($t1),'due_at'=>now_ms()+3600000]),403,'Nhân viên không chỉnh hạn được');
 $as($m);$at=now_ms()+2*3600000;$r=$route("/tasks/$t1/due",['version'=>$ver($t1),'due_at'=>$at]);verify($r[0]===200&&(int)$due($t1)===$at,'Quản lý đặt hạn');
 verify($follow()[$t1]['due_at']==$at,'Theo dõi hạn hiện hạn mới');
 denied(fn()=>$route("/tasks/$t1/due",['version'=>$ver($t1)-1,'due_at'=>$at+60000]),409,'Phiên bản cũ bị chặn');
 denied(fn()=>$route("/tasks/$t1/due",['version'=>$ver($t1),'due_at'=>$at]),400,'Hạn không đổi bị báo');
 denied(fn()=>$route("/tasks/$t1/due",['version'=>$ver($t1),'due_at'=>now_ms()-3600000]),400,'Hạn đã qua bị chặn');
 $route("/tasks/$t1/due",['version'=>$ver($t1),'due_at'=>null]);verify($due($t1)===null,'Bỏ hạn');
 $audit=$a->all("SELECT before_json,after_json FROM {{task_audit}} WHERE task_id=? AND action='due_changed' ORDER BY id",[$t1]);verify(count($audit)===2&&json_decode($audit[0]['after_json'],true)['due_at']===$at,'Lịch sử ghi Đổi hạn trước/sau');
 $as($e);$route("/tasks/$t1/done",['note'=>'xong']);verify(!isset($follow()[$t1]),'Báo xong → hết hiện Theo dõi hạn');

 // Duyệt: gỡ người xin; người còn lại vẫn làm → hiện tới khi người còn lại xong. Một người nhận duy nhất → tự hủy, không hiện.
 $t2=$task([(int)$e['id'],(int)$e2['id']]);$as($e);$d2=$route("/tasks/$t2/decline",['reason'=>'Em không biết làm việc này'])[1]['id'];
 $as($m);$route("/tasks/declines/$d2/approve",['note'=>'']);$f=$follow();verify(isset($f[$t2])&&$f[$t2]['waiting']===['TF hai']&&$f[$t2]['declines'][0]['status']==='approved','Duyệt → còn TF hai, vẫn hiện Theo dõi hạn');
 $route("/tasks/$t2/due",['version'=>$ver($t2),'due_at'=>now_ms()+86400000]);verify($due($t2)!==null,'Chỉnh hạn sau khi duyệt');
 $as($e2);$route("/tasks/$t2/done",['note'=>'']);verify(!isset($follow()[$t2]),'Người còn lại xong → hết hiện');
 $t3=$task([(int)$e['id']]);$as($e);$d3=$route("/tasks/$t3/decline",['reason'=>'Em xin nghỉ hôm nay'])[1]['id'];$as($admin);$route("/tasks/declines/$d3/approve",['note'=>'']);
 verify(!isset($follow()[$t3]),'Việc tự hủy (không còn ai nhận) không hiện');

 // Nhắc nhở: không duyệt → hiện tới khi bấm Đã xem; đặt được hạn xem.
 $t4=$task([(int)$e['id']],'notice','Nhắc đeo thẻ');$as($e);$d4=$route("/tasks/$t4/decline",['reason'=>'Em đã đeo rồi ạ'])[1]['id'];$as($m);$route("/tasks/declines/$d4/reject",['note'=>'Vẫn đọc lại nhé']);
 verify(isset($follow()[$t4]),'Nhắc nhở không duyệt → hiện Theo dõi hạn');$route("/tasks/$t4/due",['version'=>$ver($t4),'due_at'=>now_ms()+3600000]);verify($due($t4)!==null,'Nhắc nhở đặt được hạn');
 $as($e);$route("/tasks/$t4/seen");verify(!isset($follow()[$t4]),'Đã xem → hết hiện');
 // Hủy việc thì hết hiện.
 $t5=$task([(int)$e['id']]);$as($e);$d5=$route("/tasks/$t5/decline",['reason'=>'Em bận chụp khách'])[1]['id'];$as($admin);$route("/tasks/declines/$d5/reject",['note'=>'Làm sau ca']);
 verify(isset($follow()[$t5]),'Có trước khi hủy');$as($m);$route("/tasks/$t5/cancel",['version'=>$ver($t5),'reason'=>'Không cần nữa']);verify(!isset($follow()[$t5]),'Hủy việc → hết hiện');
 denied(fn()=>$route("/tasks/$t5/due",['version'=>$ver($t5),'due_at'=>now_ms()+3600000]),409,'Việc đã hủy không chỉnh hạn');
 $as($e);verify(task_routes($a,'/tasks',[],'GET')[1]['decline_followups']===[],'Nhân viên không nhận danh sách Theo dõi hạn');

 // Xóa việc hằng ngày: hủy việc hôm nay chưa xong, giữ việc đã xong và ngày trước; không tạo lại; nhân viên không xóa được.
 $today=local_day(now_ms());$as($m);
 $tpl=fn(string $title,array $u)=>$route('/tasks/templates',['user_id'=>(int)$u['id'],'title'=>$title,'require_photo'=>0])[1]['id'];
 $p1=$tpl('Lau kính cửa',$e);$p2=$tpl('Tắt điều hòa',$e2);
 $todayTask=fn(string $p)=>$a->one('SELECT * FROM {{tasks}} WHERE template_id=? AND task_day=?',[$p,$today]);
 verify($todayTask($p1)&&$todayTask($p2),'Thêm việc hằng ngày có ngay hôm nay');
 $old=task_uuid();$a->run("INSERT INTO {{tasks}}(id,kind,title,body,due_at,require_photo,audience,status,cancel_reason,created_by,created_at,updated_by,updated_at,template_id,task_day) VALUES(?,'task','Lau kính cửa','',NULL,0,'people','open','',?,?,?,?,?,?)",[$old,$m['id'],now_ms(),$m['id'],now_ms(),$p1,local_day(now_ms()-86400000)]);
 $pv=fn(string $p)=>(int)$a->scalar('SELECT version FROM {{task_templates}} WHERE id=?',[$p]);
 $as($e);denied(fn()=>$route("/tasks/templates/$p1/delete",['version'=>$pv($p1)]),403,'Nhân viên không xóa được');
 $as($m);denied(fn()=>$route("/tasks/templates/$p1/delete",['version'=>$pv($p1)-1]),409,'Phiên bản cũ không xóa');
 $r=$route("/tasks/templates/$p1/delete",['version'=>$pv($p1)]);verify($r[0]===200&&!$a->one('SELECT id FROM {{task_templates}} WHERE id=?',[$p1]),'Xóa việc hằng ngày');
 verify($todayTask($p1)['status']==='cancelled'&&$todayTask($p1)['cancel_reason']==='Đã xóa việc hằng ngày','Việc hôm nay chưa xong bị hủy');
 verify($a->scalar('SELECT status FROM {{tasks}} WHERE id=?',[$old])==='open','Việc ngày trước giữ nguyên');
 verify((bool)$a->one("SELECT id FROM {{task_audit}} WHERE task_id=? AND action='template_deleted'",[$p1]),'Lịch sử ghi Xóa');
 daily_tasks_ensure($a,local_day(now_ms()+86400000));verify(!$a->one('SELECT id FROM {{tasks}} WHERE template_id=? AND task_day=?',[$p1,local_day(now_ms()+86400000)]),'Ngày mai không tạo lại');
 verify(!in_array($p1,array_column($route('/tasks',[],'GET')[1]['templates'],'id'),true),'Không còn trong danh sách');
 // Đã xong hôm nay thì giữ nguyên khi xóa.
 $as($e2);$route('/tasks/'.$todayTask($p2)['id'].'/done',['note'=>'']);$as($admin);$route("/tasks/templates/$p2/delete",['version'=>$pv($p2)]);
 verify($todayTask($p2)['status']==='open'&&$a->one('SELECT done_at FROM {{task_recipients}} WHERE task_id=?',[$todayTask($p2)['id']])['done_at']!==null,'Việc hôm nay đã xong giữ nguyên');
 denied(fn()=>$route("/tasks/templates/$p2/delete",['version'=>1]),404,'Xóa lần nữa báo không tìm thấy');
 throw new Rollback();
});}catch(Rollback){}
echo "PASS $n task follow-up / daily delete checks\n";
