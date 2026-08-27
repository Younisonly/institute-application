<?php
$arFile = 'lang/ar/general.php';
$enFile = 'lang/en/general.php';

$additionsAr = [
    'meta_batch_id' => 'رقم الدفعة',
    'meta_date' => 'التاريخ',
    'meta_students' => 'عدد الطلاب',
];

$additionsEn = [
    'meta_batch_id' => 'Batch ID',
    'meta_date' => 'Date',
    'meta_students' => 'Students Count',
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
