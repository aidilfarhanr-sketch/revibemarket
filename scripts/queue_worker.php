<?php

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/functions.php';
$limit = 25;
$daemon = false;
foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--limit=')) $limit = (int)substr($arg, 8);
    if ($arg === '--daemon') $daemon = true;
}
$maxRuntime = max(30, (int)revibe_env('QUEUE_MAX_RUNTIME_SECONDS', 3600));
$sleep = max(1, (int)revibe_env('QUEUE_SLEEP_SECONDS', 3));
$started = time();
$total = 0;
try {
    $service = new QueueService($conn);
    do {
        $processed = $service->process($limit);
        $total += $processed;
        if (!$daemon) break;
        if ($processed <= 0) sleep($sleep);
    } while ((time() - $started) < $maxRuntime);
    echo json_encode(['success'=>true,'processed'=>$total,'driver'=>$service->health()['driver'] ?? null,'duration_seconds'=>time()-$started], JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (Throwable $e) {
    revibe_log('critical','queue worker crashed',['error'=>$e->getMessage()]);
    if (class_exists('ErrorTrackingService')) (new ErrorTrackingService())->capture($e, ['script'=>'queue_worker'], 'critical');
    fwrite(STDERR, "Queue worker gagal. Lihat log dengan request/job id terkait." . PHP_EOL);
    exit(1);
}
