<?php
$arFile = 'lang/ar/general.php';
$enFile = 'lang/en/general.php';

$additionsAr = [
    'meta_session_id' => 'جلسة التحضير',
    'meta_type' => 'النوع',
    'meta_marks' => 'الدرجات',
    'meta_reason' => 'السبب',
    'meta_passed' => 'الناجحين',
    'meta_failed' => 'الراسبين',
    'meta_registration_id' => 'رقم التسجيل',
];

$additionsEn = [
    'meta_session_id' => 'Session',
    'meta_type' => 'Type',
    'meta_marks' => 'Marks',
    'meta_reason' => 'Reason',
    'meta_passed' => 'Passed',
    'meta_failed' => 'Failed',
    'meta_registration_id' => 'Registration ID',
];

function appendToFile($file, $additions) {
    $content = file_get_contents($file);
    $lines = explode("\n", $content);
    $lastBracketIndex = -1;
    for ($i = count($lines) - 1; $i >= 0; $i--) {
        if (trim($lines[$i]) === '];') {
            $lastBracketIndex = $i;
            break;
        }
    }
    
    if ($lastBracketIndex !== -1) {
        $inject = "";
        foreach ($additions as $key => $val) {
            $val = addslashes($val);
            $inject .= "    '$key' => '$val',\n";
        }
        array_splice($lines, $lastBracketIndex, 0, $inject);
        file_put_contents($file, implode("\n", $lines));
    }
}

appendToFile($arFile, $additionsAr);
appendToFile($enFile, $additionsEn);
echo "Done appending missing translations.";
