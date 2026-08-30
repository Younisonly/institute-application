<?php

$arGeneralFile = __DIR__ . '/lang/ar/general.php';
$enGeneralFile = __DIR__ . '/lang/en/general.php';
$arValFile     = __DIR__ . '/lang/ar/validation.php';
$enValFile     = __DIR__ . '/lang/en/validation.php';

$additionsAr = [
    'teacher_assignments_history' => 'تأريخ تكليفات التدريس',
    'teaching_assignments' => 'تكليفات التدريس',
    'teaching_sessions' => 'جلسات التدريس',
    'teaching_session' => 'جلسة تدريس',
    'primary_teacher' => 'المدرس الأساسي',
    'actual_teacher' => 'المدرس الفعلي',
    'status_completed' => 'مكتملة',
    'status_substituted' => 'مستبدلة (بديل)',
    'status_cancelled' => 'ملغاة',
    'status_postponed' => 'مؤجلة',
    'planned_hours' => 'الساعات المخططة',
    'actual_hours' => 'الساعات الفعلية',
    'cancellation_reason' => 'سبب الإلغاء',
    'role_primary' => 'أساسي',
    'role_co_teacher' => 'مدرس مشارك',
    'role_assistant' => 'مساعد',
    'role_substitute' => 'بديل',
    'cannot_modify_closed_payroll_session' => 'لا يمكن تعديل أو حذف جلسة تدريس في شهر راتب معتمد أو مدفوع',
];

$additionsEn = [
    'teacher_assignments_history' => 'Teacher Assignments History',
    'teaching_assignments' => 'Teaching Assignments',
    'teaching_sessions' => 'Teaching Sessions',
    'teaching_session' => 'Teaching Session',
    'primary_teacher' => 'Primary Teacher',
    'actual_teacher' => 'Actual Teacher',
    'status_completed' => 'Completed',
    'status_substituted' => 'Substituted',
    'status_cancelled' => 'Cancelled',
    'status_postponed' => 'Postponed',
    'planned_hours' => 'Planned Hours',
    'actual_hours' => 'Actual Hours',
    'cancellation_reason' => 'Cancellation Reason',
    'role_primary' => 'Primary',
    'role_co_teacher' => 'Co-Teacher',
    'role_assistant' => 'Assistant',
    'role_substitute' => 'Substitute',
    'cannot_modify_closed_payroll_session' => 'Cannot modify or delete a teaching session in an approved or paid salary month',
];

$valAr = [
    'primary_teacher_id' => 'المدرس الأساسي',
    'actual_teacher_id' => 'المدرس الفعلي',
    'planned_hours' => 'الساعات المخططة',
    'actual_hours' => 'الساعات الفعلية',
    'cancellation_reason' => 'سبب الإلغاء',
    'role' => 'الدور',
];

$valEn = [
    'primary_teacher_id' => 'primary teacher',
    'actual_teacher_id' => 'actual teacher',
    'planned_hours' => 'planned hours',
    'actual_hours' => 'actual hours',
    'cancellation_reason' => 'cancellation reason',
    'role' => 'role',
];

function updateLangFile(string $file, array $newKeys): void {
    $data = include $file;
    foreach ($newKeys as $k => $v) {
        $data[$k] = $v;
    }
    ksort($data);
    $content = "<?php\n\nreturn " . var_export($data, true) . ";\n";
    file_put_contents($file, $content);
}

function updateValidationAttributes(string $file, array $newAttrs): void {
    $data = include $file;
    if (!isset($data['attributes'])) {
        $data['attributes'] = [];
    }
    foreach ($newAttrs as $k => $v) {
        $data['attributes'][$k] = $v;
    }
    ksort($data['attributes']);
    $content = "<?php\n\nreturn " . var_export($data, true) . ";\n";
    file_put_contents($file, $content);
}

updateLangFile($arGeneralFile, $additionsAr);
updateLangFile($enGeneralFile, $additionsEn);
updateValidationAttributes($arValFile, $valAr);
updateValidationAttributes($enValFile, $valEn);

echo "Successfully updated language files.\n";
