<?php
if ($argc < 2) {
    echo "Usage: php describe_table.php <table>\n";
    exit(1);
}
$table = $argv[1];
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$cols = Illuminate\Support\Facades\DB::select("SHOW COLUMNS FROM $table");
foreach ($cols as $c) {
    echo $c->Field . "\t" . $c->Type . "\n";
}
