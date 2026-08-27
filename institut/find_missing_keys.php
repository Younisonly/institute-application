<?php
$langFile = include 'lang/ar/general.php';
$langKeys = array_keys($langFile);

$output = shell_exec("grep -roh \"__('general\.[a-zA-Z0-9_]*')\" app/ resources/ | sed \"s/__('general\.//g\" | sed \"s/')//g\" | sort | uniq");
$foundKeys = array_filter(explode("\n", $output));

$missing = [];
foreach ($foundKeys as $key) {
    $key = trim($key);
    // Ignore dynamic keys like `registration_status_`
    if (str_ends_with($key, '_')) continue;
    
    if (!array_key_exists($key, $langFile)) {
        $missing[] = $key;
    }
}
echo "Missing Arabic keys:\n";
print_r($missing);

$langFileEn = include 'lang/en/general.php';
$missingEn = [];
foreach ($foundKeys as $key) {
    $key = trim($key);
    if (str_ends_with($key, '_')) continue;
    if (!array_key_exists($key, $langFileEn)) {
        $missingEn[] = $key;
    }
}
echo "\nMissing English keys:\n";
print_r($missingEn);
