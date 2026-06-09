<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/functions.php';
$count = (new RateLimitService($conn))->cleanup(172800);
echo "Rate limit cleanup: {$count}\n";
