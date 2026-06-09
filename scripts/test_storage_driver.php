<?php
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/functions.php';
$service = new StorageService($conn);
$key = 'tests/storage_' . date('Ymd_His') . '.txt';
$put = $service->put('revibe storage test ' . date('c'), $key, 'private');
$exists = $service->exists($key);
$get = $service->get($key);
$service->delete($key);
echo json_encode(['success'=>(bool)$put && $exists && is_string($get), 'put'=>$put, 'exists'=>$exists, 'health'=>$service->health()], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) . PHP_EOL;
