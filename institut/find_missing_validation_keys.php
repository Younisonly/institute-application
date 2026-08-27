<?php
$langFile = include 'lang/ar/validation.php';

$output = shell_exec("grep -roh \"__('validation\.[a-zA-Z0-9_]*')\" app/ resources/ | sed \"s/__('validation\.//g\" | sed \"s/')//g\" | sort | uniq");
$foundKeys = array_filter(explode("\n", $output));

$missing = [];
foreach ($foundKeys as $key) {
    $key = trim($key);
    if (!array_key_exists($key, $langFile)) {
        if (!array_key_exists($key, $langFile['custom'] ?? []) && !array_key_exists($key, $langFile['attributes'] ?? [])) {
            $missing[] = $key;
        }
    }
}
echo "Missing Arabic validation keys:\n";
print_r($missing);
