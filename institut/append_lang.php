<?php
$arFile = 'lang/ar/general.php';
$enFile = 'lang/en/general.php';

$additionsAr = [
    // Entities
    'entity_Registration' => 'تسجيل',
    'entity_CourseBatch' => 'دفعة',
    'entity_Course' => 'دورة',
    'entity_FiscalYearClosing' => 'إغلاق مالي',
    'entity_AttendanceSession' => 'جلسة تحضير',
    'entity_RegistrationItem' => 'عنصر تسجيل',
    'entity_ProgramType' => 'نوع البرنامج',
    'entity_certificate' => 'شهادة',
    'entity_Book' => 'كتاب',
    'entity_StudentTransaction' => 'حركة طالب',
    'entity_OtherPeopleTransaction' => 'حركة جهات أخرى',
    'entity_Item' => 'صنف',
    
    // Actions
    'fiscal_year.closed' => 'إغلاق السنة المالية',
    'fiscal_year.reopened' => 'إعادة فتح السنة المالية',
    'course_batch.opened' => 'فتح الدفعة',
    'course_batch.completed' => 'إكمال الدفعة',
    'course_batch.status_changed' => 'تغيير حالة الدفعة',
    'course_batch.reopened' => 'إعادة فتح الدفعة',
    'course_batch.auto_cancelled' => 'إلغاء الدفعة تلقائياً',
    'course.completed' => 'إكمال الدورة',
    'attendance.session_created' => 'إنشاء جلسة تحضير',
    'registration.grade_snapshot' => 'حفظ لقطة الدرجات',
    'registration.created' => 'تسجيل جديد',
    'registration.eligibility_overridden' => 'تجاوز أهلية التسجيل',
    'registration.transferred' => 'تحويل التسجيل',
    'registration.closed' => 'إغلاق التسجيل',
    'registration.suspended' => 'تعليق التسجيل',
    'registration.active' => 'تنشيط التسجيل',
    'registration.item_voided' => 'إلغاء عنصر تسجيل',
    'registration.month_added' => 'إضافة شهر للتسجيل',
    'registration.program_created' => 'إنشاء برنامج تسجيل',
    'registration.graded' => 'رصد درجة',
    'registration.result_reopened' => 'إعادة فتح النتيجة',
    'registration.completed' => 'إكمال التسجيل',
    'certificate.issued' => 'إصدار شهادة',
    'certificate.voided' => 'إلغاء شهادة',
    'book.sold' => 'بيع كتاب لطالب',
    'book.sold_walkin' => 'بيع كتاب لزائر',
    
    // Meta keys
    'meta_student_id' => 'رقم الطالب',
    'meta_course_id' => 'رقم الدورة',
    'meta_price_snapshot' => 'المبلغ',
    'meta_start_month' => 'شهر البداية',
    'meta_year' => 'السنة',
    'meta_by' => 'بواسطة',
    'meta_result' => 'النتيجة',
    'meta_finalized_at' => 'وقت الاعتماد',
    'meta_finalized_by' => 'تم الاعتماد بواسطة',
    'meta_reason' => 'السبب',
];

$additionsEn = [
    // Entities
    'entity_Registration' => 'Registration',
    'entity_CourseBatch' => 'Batch',
    'entity_Course' => 'Course',
    'entity_FiscalYearClosing' => 'Fiscal Closing',
    'entity_AttendanceSession' => 'Attendance Session',
    'entity_RegistrationItem' => 'Registration Item',
    'entity_ProgramType' => 'Program Type',
    'entity_certificate' => 'Certificate',
    'entity_Book' => 'Book',
    'entity_StudentTransaction' => 'Student Txn',
    'entity_OtherPeopleTransaction' => 'Other Txn',
    'entity_Item' => 'Item',
    
    // Actions
    'fiscal_year.closed' => 'Closed Fiscal Year',
    'fiscal_year.reopened' => 'Reopened Fiscal Year',
    'course_batch.opened' => 'Opened Batch',
    'course_batch.completed' => 'Completed Batch',
    'course_batch.status_changed' => 'Changed Batch Status',
    'course_batch.reopened' => 'Reopened Batch',
    'course_batch.auto_cancelled' => 'Auto-Cancelled Batch',
    'course.completed' => 'Completed Course',
    'attendance.session_created' => 'Created Attendance Session',
    'registration.grade_snapshot' => 'Saved Grade Snapshot',
    'registration.created' => 'Created Registration',
    'registration.eligibility_overridden' => 'Overrode Eligibility',
    'registration.transferred' => 'Transferred Registration',
    'registration.closed' => 'Closed Registration',
    'registration.suspended' => 'Suspended Registration',
    'registration.active' => 'Activated Registration',
    'registration.item_voided' => 'Voided Item',
    'registration.month_added' => 'Added Month',
    'registration.program_created' => 'Created Program',
    'registration.graded' => 'Graded',
    'registration.result_reopened' => 'Reopened Result',
    'registration.completed' => 'Completed Registration',
    'certificate.issued' => 'Issued Certificate',
    'certificate.voided' => 'Voided Certificate',
    'book.sold' => 'Sold Book (Student)',
    'book.sold_walkin' => 'Sold Book (Walk-in)',
    
    // Meta keys
    'meta_student_id' => 'Student ID',
    'meta_course_id' => 'Course ID',
    'meta_price_snapshot' => 'Price',
    'meta_start_month' => 'Start Month',
    'meta_year' => 'Year',
    'meta_by' => 'By',
    'meta_result' => 'Result',
    'meta_finalized_at' => 'Finalized At',
    'meta_finalized_by' => 'Finalized By',
    'meta_reason' => 'Reason',
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
echo "Done appending.";
