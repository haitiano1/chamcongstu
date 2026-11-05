<?php
// Cập nhật một chạm (05/11/2026): chữ ký, danh sách file cho phép, lỗi giữa chừng phải để nguyên hosting, bản mới hỏng phải tự trả về. Không cần database, không ra mạng.
foreach(['bootstrap','update'] as $f)require_once __DIR__.'/../site/nhansu-private/lib/'.$f.'.php';
$n=0;function verify($v,$message){global $n;if(!$v)throw new RuntimeException('FAIL: '.$message);$n++;}
function fails(callable $fn,string $text,string $message){try{$fn();}catch(HttpError $e){verify(str_contains($e->getMessage(),$text),$message.': '.$e->getMessage());return;}verify(false,$message.': không báo lỗi');}
$keys=[];foreach(['good','other'] as $k){$pair=openssl_pkey_new(['private_key_bits'=>2048,'private_key_type'=>OPENSSL_KEYTYPE_RSA]);$keys[$k]=[$pair,openssl_pkey_get_details($pair)['key']];}
$sign=function(string $json,string $k='good')use($keys){openssl_sign($json,$sig,$keys[$k][0],OPENSSL_ALGO_SHA256);return base64_encode($sig);};
$public=$keys['good'][1];$sha=fn(string $s)=>hash('sha256',$s);

// Danh sách cho phép
foreach([['private','lib/bookings.php'],['private','lib/people-finance.php'],['private','bin/cron.php'],['public','api.php'],['public','index.php'],['public','kiemtra-capnhat.php'],['public','assets/app.js'],['public','assets/vendor/leaflet/leaflet.css'],['public','assets/guide-kpi-1.jpg'],['public','assets/app.webmanifest']] as [$z,$p])verify(update_allowed($z,$p),"Cho phép $p");
foreach([['private','config.php'],['private','lib/../config.php'],['private','storage/x.php'],['private','.htaccess'],['private','lib/sub/x.php'],['private','bin/x.php'],['private','lib/x.json'],['public','.htaccess'],['public','.user.ini'],['public','assets/.htaccess'],['public','assets/x.php'],['public','assets/x.phtml'],['public','assets/x.php.js/../y.php'],['public','../index.php'],['public','../../wp-config.php'],['public','wp-config.php'],['public','chuyen-du-lieu.php'],['other','lib/a.php'],['public',"assets/a.js\n"]] as [$z,$p])verify(!update_allowed($z,$p),'Chặn '.json_encode($p));

// Chữ ký
$make=fn(array $files,string $v='1.3.56')=>json_encode(['version'=>$v,'date'=>'06/11/2026','notes'=>['n'],'manual'=>[],'files'=>$files],JSON_UNESCAPED_UNICODE);
$ok=$make([['public','assets/a.js',$sha('x')]]);
verify(update_manifest($ok,$sign($ok),$public)['version']==='1.3.56','Đúng chữ ký');
verify(update_manifest($ok.' ',$sign($ok),$public)===null,'Sửa nội dung sau khi ký bị từ chối');
verify(update_manifest($ok,$sign($ok,'other'),$public)===null,'Khóa lạ bị từ chối');verify(update_manifest($ok,'',$public)===null,'Không có chữ ký bị từ chối');verify(update_manifest($ok,'%%%',$public)===null,'Chữ ký rác bị từ chối');
$evil=$make([['private','config.php',$sha('x')]]);verify(update_manifest($evil,$sign($evil),$public)===null,'Đúng chữ ký mà có file cấm vẫn từ chối');
$noFile=$make([]);verify(update_manifest($noFile,$sign($noFile),$public)===null,'Bản rỗng bị từ chối');

// Chọn bản: nhánh nào cao hơn thì lấy, không theo nhánh đầu.
$A=str_repeat('a',40);$B=str_repeat('b',40);$refs="0059{$A} HEAD\0multi_ack\n003d{$A} refs/heads/main\n0058{$B} refs/heads/cap-nhat-2026-10-27-viec-mobile\n";
$j1=$make([['public','assets/a.js',$sha('x')]],'1.3.56');$j2=$make([['public','assets/a.js',$sha('y')]],'1.3.57');$src=['refs'=>'R','raw'=>'W'];
$web=['R'=>$refs,"W/$A/php-hostinger/release.json"=>$j1,"W/$A/php-hostinger/release.sig"=>$sign($j1),"W/$B/php-hostinger/release.json"=>$j2,"W/$B/php-hostinger/release.sig"=>$sign($j2)."\n"];$get=fn(array $w)=>fn(string $u)=>$w[$u]??null;
$r=update_latest($src,$get($web),$public);verify($r['manifest']['version']==='1.3.57'&&$r['commit']===$B&&$r['base']==="W/$B/php-hostinger/site/",'Lấy bản cao hơn, theo mã commit');
$w=$web;unset($w["W/$B/php-hostinger/release.json"]);verify(update_latest($src,$get($w),$public)['manifest']['version']==='1.3.56','Nhánh chưa có bản phát hành thì bỏ qua');
$w=$web;$w["W/$B/php-hostinger/release.sig"]=$sign($j2,'other');verify(update_latest($src,$get($w),$public)['manifest']['version']==='1.3.56','Nhánh sai chữ ký thì không lấy');
$w=$web;$w["W/$A/php-hostinger/release.sig"]=$w["W/$B/php-hostinger/release.sig"]=$sign($j1,'other');fails(fn()=>update_latest($src,$get($w),$public),'sai chữ ký','Mọi nhánh sai chữ ký');
fails(fn()=>update_latest($src,$get([]),$public),'Không kết nối được','Mất mạng');fails(fn()=>update_latest($src,$get(['R'=>$refs]),$public),'chưa có bản','Chưa phát hành');
fails(fn()=>update_latest($src,$get(['R'=>"003d{$A} refs/heads/main-old\n"]+$web),$public),'chưa có bản','Nhánh tên gần giống không tính');

// Một hosting giả
$tmp=sys_get_temp_dir().'/agup-'.bin2hex(random_bytes(4));$dirs=['private'=>"$tmp/nhansu-private",'public'=>"$tmp/public_html/nhansu"];$work="$tmp/nhansu-private/storage/update";
$tree=['nhansu-private/lib/rules.php'=>'<?php // cũ','nhansu-private/lib/bookings.php'=>'<?php // cũ','nhansu-private/lib/same.php'=>'<?php // giữ','nhansu-private/lib/legacy-event-log.json'=>'{"riêng":1}','nhansu-private/config.php'=>'<?php return ["db_password"=>"bí mật"];','nhansu-private/storage/photo.jpg'=>'ảnh','nhansu-private/.htaccess'=>'Deny from all','public_html/nhansu/index.php'=>'<?php // cũ','public_html/nhansu/.htaccess'=>'# gốc','public_html/nhansu/assets/app.js'=>'cũ','public_html/nhansu/assets/keep.js'=>'giữ','public_html/.htaccess'=>'# wordpress','public_html/wp-config.php'=>'<?php // wp'];
$reset=function()use($tmp,$tree,$work){update_rm($tmp);foreach($tree as $p=>$body){@mkdir(dirname("$tmp/$p"),0755,true);file_put_contents("$tmp/$p",$body);}mkdir($work,0700,true);};
$snapshot=function()use($tmp){$out=[];foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp,FilesystemIterator::SKIP_DOTS)) as $f)if($f->isFile()&&!str_contains($f->getPathname(),'/storage/update/'))$out[substr($f->getPathname(),strlen($tmp)+1)]=file_get_contents($f->getPathname());ksort($out);return $out;};
$new=['private/lib/rules.php'=>'<?php // mới rules','private/lib/bookings.php'=>'<?php // mới','private/lib/same.php'=>'<?php // giữ','private/lib/update.php'=>'<?php // file mới','public/index.php'=>'<?php // mới','public/assets/app.js'=>'mới','public/assets/keep.js'=>'giữ','public/assets/vendor/x/new.css'=>'css mới'];
$files=[];$serve=[];foreach($new as $k=>$body){[$z,$p]=explode('/',$k,2);$files[]=[$z,$p,$sha($body)];$serve['B/'.UPDATE_ZONES[$z]."/$p"]=$body;}$manifest=['version'=>'1.3.56','files'=>$files];
$asked=[];$fetch=function(array $w)use(&$asked){return function(string $u)use($w,&$asked){$asked[]=$u;return $w[$u]??null;};};
$untouched=function(array $before,string $message)use($snapshot,$tmp,$work){verify($snapshot()===$before,$message.': hosting nguyên vẹn');verify(!glob("$work/stage-*"),$message.': không sót thư mục tạm');};

// Thành công: chỉ tải file đổi, thứ tự backend trước, index.php cuối.
$reset();$before=$snapshot();verify(count(update_changed($dirs,$manifest))===6,'Chỉ 6 file khác hash');
$r=update_apply($dirs,$work,$manifest,'B/',$fetch($serve),fn()=>'ok');verify($r['files']===6&&$r['health']==='ok','Cập nhật xong 6 file');
verify(update_changed($dirs,$manifest)===[],'Sau cập nhật mọi file khớp');verify(!array_filter($asked,fn($u)=>str_contains($u,'same.php')||str_contains($u,'keep.js')),'File không đổi không tải lại');
verify(str_ends_with(end($asked),'public_html/nhansu/index.php'),'index.php thay cuối');$pos=array_flip($asked);verify($pos['B/nhansu-private/lib/rules.php']>$pos['B/nhansu-private/lib/bookings.php']&&$pos['B/nhansu-private/lib/rules.php']<$pos['B/public_html/nhansu/assets/app.js'],'Backend trước, trang web sau');
$after=$snapshot();foreach(['nhansu-private/config.php','nhansu-private/storage/photo.jpg','nhansu-private/.htaccess','nhansu-private/lib/legacy-event-log.json','public_html/nhansu/.htaccess','public_html/.htaccess','public_html/wp-config.php'] as $p)verify($after[$p]===$tree[$p],"Không đụng $p");
verify(!array_filter(array_keys($after),fn($p)=>str_ends_with($p,'.agnew')),'Không sót .agnew');verify($after['public_html/nhansu/assets/vendor/x/new.css']==='css mới','Tạo được thư mục con mới');
verify(update_apply($dirs,$work,$manifest,'B/',$fetch([]),fn()=>'failed')['files']===0,'Đã khớp thì không làm gì');
// Quay về bản trước: đúng từng byte, file mới bị gỡ.
update_restore($dirs,"$work/".$r['backup']);$back=$snapshot();foreach($before as $p=>$body)verify($back[$p]===$body,"Quay về: $p");verify(!isset($back['nhansu-private/lib/update.php']),'Quay về: gỡ file mới thêm');

// Lỗi trước khi thay: hosting nguyên vẹn.
$reset();$before=$snapshot();$w=$serve;unset($w['B/public_html/nhansu/index.php']);fails(fn()=>update_apply($dirs,$work,$manifest,'B/',$fetch($w),fn()=>'ok'),'Chưa thay file nào','Thiếu file cuối cùng');$untouched($before,'Thiếu file');
$w=$serve;$w['B/public_html/nhansu/assets/app.js']='bị tráo';fails(fn()=>update_apply($dirs,$work,$manifest,'B/',$fetch($w),fn()=>'ok'),'không khớp','File bị tráo');$untouched($before,'File bị tráo');
$bad='<?php function {';$m2=$manifest;foreach($m2['files'] as &$f)if($f[1]==='lib/bookings.php')$f[2]=$sha($bad);unset($f);$w=$serve;$w['B/nhansu-private/lib/bookings.php']=$bad;fails(fn()=>update_apply($dirs,$work,$m2,'B/',$fetch($w),fn()=>'ok'),'lỗi cú pháp','PHP lỗi cú pháp');$untouched($before,'PHP lỗi cú pháp');

// Bản mới không chạy: tự trả về.
$reset();$before=$snapshot();fails(fn()=>update_apply($dirs,$work,$manifest,'B/',$fetch($serve),fn()=>'failed'),'tự trả về bản cũ','Bản mới hỏng');$untouched($before,'Bản mới hỏng');verify(!glob("$work/backup-*"),'Bản mới hỏng: không giữ sao lưu thừa');
// Không tự kiểm tra được (mạng nội bộ hosting chặn): giữ bản mới, báo cho Admin.
$reset();$r=update_apply($dirs,$work,$manifest,'B/',$fetch($serve),fn()=>'unknown');verify($r['health']==='unknown'&&update_changed($dirs,$manifest)===[],'Không tự kiểm tra được thì giữ bản mới');

// Ghi lỗi giữa chừng (một thư mục không ghi được): trả về phần đã thay.
if(function_exists('posix_getuid')&&posix_getuid()!==0){$reset();$before=$snapshot();chmod($dirs['public'].'/assets',0555);fails(fn()=>update_apply($dirs,$work,$manifest,'B/',$fetch($serve),fn()=>'ok'),'trả về như cũ','Không ghi được assets');chmod($dirs['public'].'/assets',0755);$untouched($before,'Ghi lỗi giữa chừng');}

// Giữ 3 bản sao lưu gần nhất.
$reset();foreach(['20260101000000','20260102000000','20260103000000','20260104000000','20260105000000'] as $i=>$d){mkdir("$work/backup-$d-aaaaaa");}update_prune($work);verify(array_map('basename',glob("$work/backup-*"))===['backup-20260103000000-aaaaaa','backup-20260104000000-aaaaaa','backup-20260105000000-aaaaaa'],'Giữ 3 bản gần nhất');
// Hai người bấm cùng lúc.
$l=update_lock($work);$child=fopen("$work/lock",'c');verify(!flock($child,LOCK_EX|LOCK_NB),'Khóa chặn lượt thứ hai');flock($l,LOCK_UN);
update_rm($tmp);echo "OK update: $n kiểm tra\n";
