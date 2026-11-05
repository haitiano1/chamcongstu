<?php
declare(strict_types=1);
// Cập nhật một chạm (chủ dự án yêu cầu 05/11/2026): Admin bấm nút trên web, máy chủ tự lấy bản mới từ GitHub, khỏi upload tay trên hPanel.
// release.json (danh sách file + sha256) phải có chữ ký đúng khóa riêng trên máy chủ dự án, nên lộ tài khoản GitHub hay Admin cũng không cài được mã lạ.
// Chỉ thay file trong danh sách cho phép, không xóa file, không đụng config.php, storage/, .htaccess, .user.ini.
// Tải đủ và kiểm tra xong mới thay; thay lỗi hoặc bản mới không chạy thì tự trả về như cũ.
const UPDATE_REPO='haitiano1/chamcongstu';
const UPDATE_BRANCHES=['main','cap-nhat-2026-10-27-viec-mobile'];
const UPDATE_SITE='php-hostinger/site';
const UPDATE_ZONES=['private'=>'nhansu-private','public'=>'public_html/nhansu'];
const UPDATE_KEEP=3;
const UPDATE_PUBLIC_KEY=<<<'PEM'
-----BEGIN PUBLIC KEY-----
MIIBojANBgkqhkiG9w0BAQEFAAOCAY8AMIIBigKCAYEAky0nI85KuFpohGb0Kyzr
CwzHyyDF17gCKKWJf+TJjxVTfqupq9hN080k2PhoixiwTYRNrtxXXtPWVizA2FqZ
LJfif7F7Oho386AuVewVgTUeFpa284nz0tvBGNZS+DOPvzHZrj19VisrkY/NYhbX
zPBaZ6zED4tOGqh6QMSl9zBXVjfr4ePgwa0qCu1LOMt37tAPbSBQhjCFNnr/Mvz7
M3MhfA8L0uR3fd69aBVAnlg3ODX8P+OyTXM0kS4Kx7HE1dbJM5wWUQNFKm5y6049
c2+dONdL5PAbf0/BxuRJdxE0SdI/ozXUd8W/97EodFeiOirLqlBdAEupRw9nAkBG
WOtt4e9mI2jMAHFTt6DriEVQh4thAOEy6bTPg+YoMarZ4ASYno0kSpydjFIjAZs6
YYL1YikIFrV3jo2NJV9Wmv8EelM1S1sLigGqZ3RqyYukUAb3rFsc+jEgT7x/282h
hqot8i19f2dsflDrIlzRxNc+WWw7YQBp8EM7N9u1jfwrAgMBAAE=
-----END PUBLIC KEY-----
PEM;
function update_allowed(string $zone,string $path): bool {
 if(str_contains($path,'..'))return false;
 if($zone==='private')return (bool)preg_match('~^(lib/[a-z0-9-]+\.php|bin/cron\.php)$~D',$path);
 return $zone==='public'&&(bool)preg_match('~^((api|index|kiemtra-capnhat)\.php|assets/([A-Za-z0-9_-][A-Za-z0-9._-]*/)*[A-Za-z0-9_-][A-Za-z0-9._-]*\.(js|css|map|png|jpe?g|webp|svg|ico|woff2?|webmanifest))$~D',$path);
}
function update_manifest(string $json,string $sig,string $key=UPDATE_PUBLIC_KEY): ?array {
 if(openssl_verify($json,(string)base64_decode($sig,true),$key,OPENSSL_ALGO_SHA256)!==1)return null;
 $m=json_decode($json,true);if(!is_array($m)||!is_string($m['version']??null)||!preg_match('/^\d+\.\d+\.\d+$/D',$m['version'])||!is_array($m['files']??null)||!$m['files'])return null;
 foreach($m['files'] as $f)if(!is_array($f)||count($f)<3||!is_string($f[0])||!is_string($f[1])||!is_string($f[2])||!update_allowed($f[0],$f[1])||!preg_match('/^[a-f0-9]{64}$/D',$f[2]))return null;
 return $m;
}
function update_get(string $url): ?string {
 $ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>45,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_USERAGENT=>'anhgau-nhansu-update']);
 $body=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);return $code===200&&is_string($body)?$body:null;
}
function update_source(App $a): array {return $a->config['update_source']??['refs'=>'https://github.com/'.UPDATE_REPO.'.git/info/refs?service=git-upload-pack','raw'=>'https://raw.githubusercontent.com/'.UPDATE_REPO];}
// Lấy theo mã commit (không theo tên nhánh) để không dính bộ nhớ đệm vài phút của GitHub. Nhánh nào có bản cao hơn thì lấy nhánh đó.
function update_latest(array $src,callable $get,string $key=UPDATE_PUBLIC_KEY): array {
 $refs=$get($src['refs']);if($refs===null)fail('Không kết nối được GitHub. Thử lại sau.',502);$best=null;$forged=false;
 foreach(UPDATE_BRANCHES as $branch){
  if(!preg_match('~([a-f0-9]{40}) refs/heads/'.preg_quote($branch,'~').'(?=[\s\x00]|$)~',$refs,$hit))continue;
  $root=$src['raw'].'/'.$hit[1].'/';$json=$get($root.'php-hostinger/release.json');$sig=$get($root.'php-hostinger/release.sig');if($json===null||$sig===null)continue;
  $manifest=update_manifest($json,trim($sig),$key);if($manifest===null){$forged=true;continue;}
  if(!$best||version_compare($manifest['version'],$best['manifest']['version'],'>'))$best=['manifest'=>$manifest,'base'=>$root.UPDATE_SITE.'/','commit'=>$hit[1]];
 }
 if($best)return $best;fail($forged?'Bản trên GitHub sai chữ ký nên không cài. Báo người phụ trách kỹ thuật kiểm tra ngay.':'GitHub chưa có bản phát hành nào.',502);
}
function update_dirs(): array {return ['private'=>dirname(__DIR__),'public'=>dirname((string)($_SERVER['SCRIPT_FILENAME']??''))];}
function update_changed(array $dirs,array $manifest): array {return array_values(array_filter($manifest['files'],function(array $f)use($dirs){$file=$dirs[$f[0]].'/'.$f[1];return !is_file($file)||hash_file('sha256',$file)!==$f[2];}));}
function update_rm(string $path): void {if(is_dir($path)){foreach(scandir($path)?:[] as $x)if($x!=='.'&&$x!=='..')update_rm($path.'/'.$x);@rmdir($path);}elseif(is_file($path))@unlink($path);}
function update_flush(): void {if(function_exists('opcache_reset'))@opcache_reset();clearstatcache(true);}
// Backend trước, trang web sau, index.php cuối cùng: điện thoại không tải trang mới khi máy chủ còn mã cũ.
function update_rank(array $f): int {return $f[0]==='private'?($f[1]==='lib/rules.php'?1:0):(str_starts_with($f[1],'assets/')?2:($f[1]==='index.php'?4:3));}
function update_put(string $from,string $target): bool {
 if(!is_dir(dirname($target))&&!@mkdir(dirname($target),0755,true))return false;$tmp=$target.'.agnew';
 if(!@copy($from,$tmp))return false;@chmod($tmp,0644);if(@rename($tmp,$target)){if(function_exists('opcache_invalidate'))@opcache_invalidate($target,true);return true;}@unlink($tmp);return false;
}
function update_restore(array $dirs,string $backup): void {
 foreach(array_reverse(json_decode((string)file_get_contents($backup.'/items.json'),true)?:[]) as $i=>$item){$target=$dirs[$item['zone']].'/'.$item['path'];
  if($item['existed'])update_put($backup.'/'.$item['n'],$target);else @unlink($target);}
 update_flush();
}
function update_prune(string $work): void {$all=glob($work.'/backup-*',GLOB_ONLYDIR)?:[];sort($all);foreach(array_slice($all,0,max(0,count($all)-UPDATE_KEEP)) as $old)update_rm($old);}
function update_apply(array $dirs,string $work,array $manifest,string $base,callable $get,callable $health): array {
 $files=update_changed($dirs,$manifest);if(!$files)return ['files'=>0,'health'=>'ok','backup'=>null];
 usort($files,fn($x,$y)=>update_rank($x)<=>update_rank($y));
 foreach(glob($work.'/stage-*',GLOB_ONLYDIR)?:[] as $old)update_rm($old);// lượt trước bị hosting cắt giữa chừng
 $id=gmdate('YmdHis').'-'.bin2hex(random_bytes(3));$stage=$work.'/stage-'.$id;$backup=$work.'/backup-'.$id;
 if(!@mkdir($stage,0700,true))fail('Không tạo được thư mục tạm trên hosting. Chưa thay file nào.',500);
 try{
  // Tải và kiểm tra đủ mọi file trước. Lỗi ở đây thì hosting vẫn nguyên.
  foreach($files as $n=>$f){
   $body=$get($base.UPDATE_ZONES[$f[0]].'/'.$f[1]);if($body===null)fail('Không tải được '.$f[1].'. Chưa thay file nào, thử lại sau.',502);
   if(!hash_equals($f[2],hash('sha256',$body)))fail('File '.$f[1].' tải về không khớp bản đã ký. Chưa thay file nào.',502);
   if(str_ends_with($f[1],'.php')&&function_exists('token_get_all'))try{token_get_all($body,TOKEN_PARSE);}catch(ParseError){fail('File '.$f[1].' lỗi cú pháp. Chưa thay file nào.',502);}
   if(file_put_contents($stage.'/'.$n,$body)===false)fail('Hosting hết chỗ hoặc không ghi được thư mục tạm. Chưa thay file nào.',500);
  }
  if(!@mkdir($backup,0700))fail('Không tạo được bản sao lưu. Chưa thay file nào.',500);$items=[];
  foreach($files as $n=>$f){$target=$dirs[$f[0]].'/'.$f[1];$existed=is_file($target);if($existed&&!@copy($target,$backup.'/'.$n)){update_rm($backup);fail('Không sao lưu được '.$f[1].'. Chưa thay file nào.',500);}$items[]=['n'=>$n,'zone'=>$f[0],'path'=>$f[1],'existed'=>$existed];}
  file_put_contents($backup.'/items.json',json_encode($items,JSON_THROW_ON_ERROR));
  foreach($files as $n=>$f)if(!update_put($stage.'/'.$n,$dirs[$f[0]].'/'.$f[1])){update_restore($dirs,$backup);update_rm($backup);fail('Không ghi được '.$f[1].' nên đã trả về như cũ. Kiểm tra quyền file trên hosting.',500);}
  update_flush();$state=$health();
  if($state==='failed'){update_restore($dirs,$backup);update_rm($backup);fail('Bản mới không chạy được trên hosting nên đã tự trả về bản cũ. App vẫn dùng bình thường.',500);}
  update_prune($work);return ['files'=>count($files),'health'=>$state,'backup'=>basename($backup)];
 }finally{update_rm($stage);}
}
function update_health(App $a): string {
 $url=rtrim((string)$a->config['app_url'],'/').'/api.php?route=/status';$state='unknown';
 for($i=0;$i<3;$i++){if($i)sleep(2);$ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>30,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_USERAGENT=>'anhgau-nhansu-update']);
  $body=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);$data=json_decode(is_string($body)?$body:'',true);
  if($code===200&&is_array($data)&&($data['runtime']??'')==='php-mysql')return 'ok';$state=$code===200||$code>=500?'failed':'unknown';}
 return $state;
}
function update_read(string $file): array {return is_file($file)?(json_decode((string)file_get_contents($file),true)?:[]):[];}
function update_log(string $work,array $entry): void {$all=update_read($work.'/history.json');array_unshift($all,$entry);file_put_contents($work.'/history.json',json_encode(array_slice($all,0,20),JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE));}
function update_work(App $a): string {$work=$a->storage.'/update';if(!is_dir($work)&&!@mkdir($work,0700,true))fail('Không tạo được thư mục cập nhật trong storage.',500);return $work;}
function update_lock(string $work): mixed {$lock=fopen($work.'/lock','c');if(!$lock||!flock($lock,LOCK_EX|LOCK_NB))fail('Đang có một lượt cập nhật chạy. Chờ xong rồi thử lại.',409);return $lock;}
function update_status(App $a,array $dirs): array {
 $work=update_work($a);$last=update_read($work.'/last.json');if($last&&!is_dir($work.'/'.($last['backup']??'')))$last=[];
 $out=['current'=>APP_RELEASE,'last'=>$last?:null,'history'=>update_read($work.'/history.json'),'writable'=>is_writable($dirs['private'].'/lib')&&is_writable($dirs['public'].'/assets')&&is_writable($dirs['public'])];
 try{
  if(!extension_loaded('openssl')||!extension_loaded('curl'))fail('Hosting chưa bật OpenSSL hoặc cURL cho PHP.',503);
  $release=update_latest(update_source($a),'update_get');$m=$release['manifest'];$changed=update_changed($dirs,$m);$order=version_compare($m['version'],APP_RELEASE);
  return $out+['latest'=>$m['version'],'date'=>$m['date']??'','notes'=>$m['notes']??[],'manual'=>$m['manual']??[],'changed'=>count($changed),'state'=>$order>0?'available':($order<0?'ahead':($changed?'repair':'current'))];
 }catch(HttpError $e){return $out+['error'=>$e->getMessage()];}
}
function update_routes(App $a,string $path,array $b,string $method): ?array {
 if(!str_starts_with($path,'/update/'))return null;$a->requireAdmin();$dirs=update_dirs();
 if($path==='/update/status'&&$method==='GET')return [200,update_status($a,$dirs)];
 if($path==='/update/install'&&$method==='POST'){
  @set_time_limit(300);ignore_user_abort(true);$work=update_work($a);$lock=update_lock($work);$release=update_latest(update_source($a),'update_get');$m=$release['manifest'];
  if(($b['version']??null)!==$m['version'])fail('Bản trên GitHub vừa đổi. Tải lại trang rồi bấm lại.',409);
  if(version_compare($m['version'],APP_RELEASE,'<'))fail('Bản trên GitHub cũ hơn bản đang chạy nên không cài.',409);
  $from=APP_RELEASE;$result=update_apply($dirs,$work,$m,$release['base'],'update_get',fn()=>update_health($a));
  if($result['files']){$entry=['action'=>'install','from'=>$from,'to'=>$m['version'],'files'=>$result['files'],'at'=>now_ms(),'by'=>$a->user['name'],'commit'=>substr($release['commit'],0,7),'health'=>$result['health']];
   file_put_contents($work.'/last.json',json_encode($entry+['backup'=>$result['backup']],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE));update_log($work,$entry);}
  flock($lock,LOCK_UN);return [200,['ok'=>true,'version'=>$m['version'],'files'=>$result['files'],'health'=>$result['health'],'manual'=>$m['manual']??[]]];
 }
 if($path==='/update/rollback'&&$method==='POST'){
  $work=update_work($a);$lock=update_lock($work);$last=update_read($work.'/last.json');$dir=$work.'/'.($last['backup']??'');
  if(!$last||!preg_match('/^backup-[0-9a-f-]+$/D',(string)$last['backup'])||!is_file($dir.'/items.json'))fail('Không còn bản trước để quay về.',409);
  update_restore($dirs,$dir);update_rm($dir);@unlink($work.'/last.json');
  update_log($work,['action'=>'rollback','from'=>$last['to'],'to'=>$last['from'],'files'=>$last['files'],'at'=>now_ms(),'by'=>$a->user['name']]);
  flock($lock,LOCK_UN);return [200,['ok'=>true,'version'=>$last['from']]];
 }
 return null;
}
