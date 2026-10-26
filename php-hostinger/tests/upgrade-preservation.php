<?php
require '/var/nhansu-private/lib/bootstrap.php';require '/var/nhansu-private/lib/people-finance.php';require '/var/nhansu-private/lib/bookings.php';
$a=new App('/var/nhansu-private');$before=[];foreach(['users','events','commissions','salary_advances','event_log','event_log_audit','shoot_bookings'] as $table)$before[$table]=(int)$a->scalar('SELECT COUNT(*) FROM {{'.$table.'}}');
$images=$a->scalar('SELECT SHA2(GROUP_CONCAT(SHA2(photo_jpeg,256) ORDER BY id SEPARATOR ","),256) FROM {{events}} WHERE photo_jpeg IS NOT NULL');
$a->meta('schema_version','1');$b=new App('/var/nhansu-private');foreach($before as $t=>$count)if((int)$b->scalar('SELECT COUNT(*) FROM {{'.$t.'}}')!==$count)throw new RuntimeException('Count changed: '.$t);
if($b->meta('schema_version')!=='5')throw new RuntimeException('Version not upgraded');
if($images!==$b->scalar('SELECT SHA2(GROUP_CONCAT(SHA2(photo_jpeg,256) ORDER BY id SEPARATOR ","),256) FROM {{events}} WHERE photo_jpeg IS NOT NULL'))throw new RuntimeException('Photo bytes changed');
$duplicates=(int)$b->scalar('SELECT COUNT(*) FROM {{event_log}} WHERE source_ref IS NOT NULL');if($duplicates!==50)throw new RuntimeException('Legacy duplicates');
echo "PASS 10 upgrade preservation checks; existing data/photo hashes preserved and 50 legacy logs remain unique\n";
