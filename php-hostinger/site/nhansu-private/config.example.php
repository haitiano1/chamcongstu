<?php
// Copy to config.php. This folder must stay OUTSIDE public_html.
return [
 'db_host' => getenv('NHANSU_DB_HOST') ?: 'localhost',
 'db_port' => (int)(getenv('NHANSU_DB_PORT') ?: 3306),
 'db_name' => getenv('NHANSU_DB_NAME') ?: 'YOUR_SEPARATE_DATABASE',
 'db_user' => getenv('NHANSU_DB_USER') ?: 'YOUR_SEPARATE_DATABASE_USER',
 'db_password' => getenv('NHANSU_DB_PASSWORD') ?: 'YOUR_DATABASE_PASSWORD',
 'table_prefix' => 'agns_',
 'app_url' => getenv('NHANSU_APP_URL') ?: 'https://anhgaustudio.com/nhansu',
 'telegram_token' => getenv('NHANSU_TELEGRAM_TOKEN') ?: '',
];
