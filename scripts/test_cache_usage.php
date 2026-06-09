<?php
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/functions.php';
$cache = new CacheService();
$key='public:test_cache_usage';
$cache->put($key, ['ok'=>true,'time'=>time()], 60);
$hit=$cache->get($key);
$cache->forget($key);
echo json_encode(['success'=>is_array($hit) && !empty($hit['ok']), 'hit'=>$hit, 'health'=>$cache->health()], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) . PHP_EOL;
