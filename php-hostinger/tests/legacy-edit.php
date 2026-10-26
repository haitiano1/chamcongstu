<?php
require '/var/nhansu-private/lib/bootstrap.php';require '/var/nhansu-private/lib/bookings.php';
$a=new App('/var/nhansu-private');$a->user=$a->one("SELECT * FROM {{users}} WHERE role='admin' LIMIT 1");$checks=0;
try{$a->tx(function()use($a,&$checks){$r=$a->one("SELECT * FROM {{event_log}} WHERE source_label LIKE '%Mục 35'");$target=(int)$a->scalar("SELECT id FROM {{users}} WHERE role='employee' LIMIT 1");$before=(int)$a->scalar('SELECT COUNT(*) FROM {{event_log_audit}} WHERE event_log_id=?',[$r['id']]);
 $result=log_routes($a,'/event-log/'.$r['id'].'/classify',['user_id'=>$target,'date_precision'=>'day','event_date'=>'2025-09-01','version'=>$r['version'],'note'=>'Kiểm thử bổ sung, sẽ rollback toàn bộ.'],'POST');if($result[0]!==200)throw new RuntimeException('Classify failed');$checks++;
 $changed=$a->one('SELECT * FROM {{event_log}} WHERE id=?',[$r['id']]);foreach(['description','source_ref','source_label','original_date'] as $field){if($changed[$field]!==$r[$field])throw new RuntimeException('Source changed');$checks++;}
 if((int)$a->scalar('SELECT COUNT(*) FROM {{event_log_audit}} WHERE event_log_id=?',[$r['id']])!==$before+1)throw new RuntimeException('Missing audit');$checks++;
 log_routes($a,'/event-log/'.$r['id'].'/resolve',['status'=>'confirmed','deduction'=>20,'version'=>$changed['version'],'note'=>'Kiểm thử xác nhận điểm có điều chỉnh.'],'POST');if($a->scalar('SELECT points FROM {{event_log}} WHERE id=?',[$r['id']])!==-20)throw new RuntimeException('Point confirmation failed');$checks++;
 throw new RuntimeException('ROLLBACK_TEST');});}catch(RuntimeException $e){if($e->getMessage()!=='ROLLBACK_TEST')throw $e;}
echo "PASS $checks historical edit/point confirmation checks; fixture changes rolled back\n";
