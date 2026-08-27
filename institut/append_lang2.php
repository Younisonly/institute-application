<?php
$arFile = 'lang/ar/general.php';
$enFile = 'lang/en/general.php';

$additionsAr = [
    'entity_AttendanceRecord' => 'سجل تحضير',
    'attendance.recorded' => 'تسجيل تحضير',
    'journal_entry.voided' => 'إلغاء قيد يومية',
    'course_batch.cancelled' => 'إلغاء الدفعة',
];

$additionsEn = [
    'entity_AttendanceRecord' => 'Attendance Record',
    'attendance.recorded' => 'Recorded Attendance',
    'journal_entry.voided' => 'Voided Journal Entry',
    'course_batch.cancelled' => 'Cancelled Batch',
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
