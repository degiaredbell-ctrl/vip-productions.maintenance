<?php

require __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$rows = App\Models\Machine::where('type', 'utility')
    ->whereNotNull('sub_category')
    ->select('id', 'code', 'sub_category')
    ->limit(5)
    ->get();
foreach ($rows as $r) {
    echo $r->id.':'.$r->code.':'.$r->sub_category.PHP_EOL;
}
