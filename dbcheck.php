<?php

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
echo 'DB_DATABASE env: '.env('DB_DATABASE').PHP_EOL;
echo 'DB config: '.config('database.connections.mysql.database').PHP_EOL;
