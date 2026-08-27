<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$batch = \App\Models\CourseBatch::first();
echo "Batch: {$batch->name}\n";
echo "Start: {$batch->start_date}\n";
$periodDays = $batch->periods->pluck('days')->flatten()->unique()->toArray();
print_r($periodDays);

$start = \Carbon\Carbon::parse($batch->start_date);
$end = \Carbon\Carbon::today();
$map = ['sun'=>0, 'mon'=>1, 'tue'=>2, 'wed'=>3, 'thu'=>4, 'fri'=>5, 'sat'=>6];
$validDayOfWeek = array_map(fn($d) => $map[$d], $periodDays);

$dates = [];
$current = $start->copy();
while ($current->lte($end)) {
    if (empty($validDayOfWeek) || in_array($current->dayOfWeek, $validDayOfWeek)) {
        $dates[] = $current->format('Y-m-d');
    }
    $current->addDay();
}
$dates = array_reverse($dates);
print_r(array_slice($dates, 0, 5));
