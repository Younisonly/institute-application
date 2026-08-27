<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$log = \App\Models\AuditLog::where('action', 'attendance.session_created')->first();

// We will just execute the closure directly to see if it throws an error or what it returns.
$closure = function (mixed $state, \App\Models\AuditLog $record): \Illuminate\Support\HtmlString|string {
    $decoded = $state;
    if (is_string($state) && (str_starts_with(trim($state), '{') || str_starts_with(trim($state), '['))) {
        $decoded = json_decode($state, true) ?? $state;
    }

    if (is_array($decoded)) {
        $parts = [];
        if (array_is_list($decoded)) {
            // ... omitting for brevity since it's not a list
        }
        
        foreach ($decoded as $key => $value) {
            if ($key === 'by') continue;
            // Simplified resolve function for test
            $resolvedValue = $value;
            $valStr = is_scalar($resolvedValue) ? $resolvedValue : json_encode($resolvedValue, JSON_UNESCAPED_UNICODE);
            $transKey = 'general.meta_' . $key;
            $label = \Illuminate\Support\Facades\Lang::has($transKey) ? __($transKey) : $key;
            $parts[] = "<span class='badge'>{$label}: {$valStr}</span>";
        }
        return new \Illuminate\Support\HtmlString("<div class='container'>" . implode('', $parts) . "</div>");
    }
    
    return is_string($state) ? $state : '—';
};

var_dump((string)$closure($log->details, $log));
