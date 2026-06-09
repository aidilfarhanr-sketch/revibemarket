<?php
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/functions.php';

$isProd = (string)revibe_env('APP_ENV','local') === 'production';
$isMulti = filter_var(revibe_env('MULTI_SERVER', false), FILTER_VALIDATE_BOOLEAN);
$checks=[];
$checks['php_version']=version_compare(PHP_VERSION,'8.1.0','>=');
foreach(['mysqli','pdo_mysql','mbstring','fileinfo','curl','json'] as $ext) $checks['ext_'.$ext]=extension_loaded($ext);
$checks['env_example']=is_file(__DIR__.'/../.env.example');
$checks['readme_main']=is_file(__DIR__.'/../README.md');
$checks['hosting_guide']=is_file(__DIR__.'/../docs/HOSTING_GUIDE.md') || is_file(__DIR__.'/../HOSTING_GUIDE.md');
$checks['multi_server_docs']=is_dir(__DIR__.'/../deploy/multiserver') && is_file(__DIR__.'/../docs/MULTI_SERVER_GUIDE.md');
$checks['docker_production_compose']=is_file(__DIR__.'/../docker-compose.production.yml');
$checks['env_file']=is_file(__DIR__.'/../.env');
$checks['app_debug_off_when_production']=!$isProd || !filter_var(revibe_env('APP_DEBUG',false), FILTER_VALIDATE_BOOLEAN);
$checks['app_url_not_localhost_when_production']=!$isProd || !preg_match('#https?://(localhost|127\.0\.0\.1)#i', (string)revibe_env('APP_URL',''));
$checks['app_url_https_when_production']=!$isProd || str_starts_with(strtolower((string)revibe_env('APP_URL','')), 'https://');
$checks['db_configured']=(string)revibe_env('DB_NAME','')!=='' && (string)revibe_env('DB_USER','')!=='';
foreach(['storage','storage/private','storage/cache','logs','uploads','backups'] as $path){
    $full=__DIR__.'/../'.$path;
    if(!is_dir($full)) @mkdir($full,0755,true);
    $checks[$path.'_writable']=is_writable($full);
}
try { $checks['storage_health']=(new StorageService())->health()['local_private_writable'] ?? false; } catch(Throwable $e){ $checks['storage_health']=false; }
try { $checks['cache_health']=(new CacheService())->health()['file_cache_writable'] ?? false; } catch(Throwable $e){ $checks['cache_health']=false; }
$checks['payment_flow_escrow']=(string)revibe_env('PAYMENT_FLOW','escrow')==='escrow';
$checks['admin_2fa_required_when_production']=!$isProd || filter_var(revibe_env('ADMIN_2FA_REQUIRED',false), FILTER_VALIDATE_BOOLEAN);
$checks['payment_sandbox_false_when_production']=!$isProd || !filter_var(revibe_env('PAYMENT_SANDBOX',true), FILTER_VALIDATE_BOOLEAN);
$checks['multi_server_session_redis']=!($isProd && $isMulti) || strtolower((string)revibe_env('SESSION_DRIVER','file'))==='redis';
$checks['multi_server_cache_redis']=!($isProd && $isMulti) || strtolower((string)revibe_env('CACHE_DRIVER','file'))==='redis';
$checks['multi_server_rate_limit_redis']=!($isProd && $isMulti) || strtolower((string)revibe_env('RATE_LIMIT_DRIVER','file'))==='redis';
$checks['multi_server_queue_redis']=!($isProd && $isMulti) || strtolower((string)revibe_env('QUEUE_DRIVER','sync'))==='redis';
$checks['multi_server_storage_remote']=!($isProd && $isMulti) || in_array(strtolower((string)revibe_env('STORAGE_DRIVER','local')), ['s3','r2','spaces'], true);
$checks['backup_scripts']=is_executable(__DIR__.'/backup_full.sh') || is_file(__DIR__.'/backup_full.sh');
$checks['rollback_docs']=is_file(__DIR__.'/../docs/ROLLBACK_100.md') || is_file(__DIR__.'/../deploy/multiserver/rollback-checklist.md');
$checks['no_env_committed']=!is_file(__DIR__.'/../.env') || getenv('CI') === false;
$ok=!in_array(false,$checks,true);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok'=>$ok,'production'=>$isProd,'multi_server'=>$isMulti,'checks'=>$checks,'time'=>date('c')], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
exit($ok?0:1);
