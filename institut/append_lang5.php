<?php
$arFile = 'lang/ar/general.php';
$enFile = 'lang/en/general.php';

$additionsAr = [
    'meta_student_id' => 'الطالب',
    'meta_course_id' => 'الدورة',
    'meta_price_snapshot' => 'السعر',
    'meta_start_month' => 'شهر البداية',
];

$additionsEn = [
    'meta_student_id' => 'Student',
    'meta_course_id' => 'Course',
    'meta_price_snapshot' => 'Price',
    'meta_start_month' => 'Start Month',
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
