<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../lib/bootstrap.php';require __DIR__.'/../lib/telegram.php';require __DIR__.'/../lib/transfer.php';require __DIR__.'/../lib/bookings.php';
try{$a=new App(dirname(__DIR__));$lock=fopen($a->storage.'/cron.lock','c');if(!flock($lock,LOCK_EX|LOCK_NB))exit;(new Telegram($a))->tick();$last=backup_list($a)[0]??null;if(!$last||now_ms()-$last['time']>=86400000){try{create_backup($a);$a->meta('backup_error','');}catch(Throwable $e){$a->meta('backup_error',$e->getMessage());throw $e;}}$a->run('DELETE FROM {{sessions}} WHERE expires<?',[now_ms()]);$a->run('DELETE FROM {{telegram_link_codes}} WHERE expires<?',[now_ms()]);echo "Nhansu cron OK\n";}catch(Throwable $e){error_log('Nhansu cron: '.$e->getMessage());exit(1);}
