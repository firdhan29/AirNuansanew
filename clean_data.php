<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$deleted = App\Models\Income::whereYear('date', '1999')->delete();
echo "Deleted $deleted rows\n";
