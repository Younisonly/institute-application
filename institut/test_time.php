<?php
function getTimeInWords(?string $time, string $locale = 'ar'): ?string
{
    if (! $time) {
        return null;
    }

    try {
        $carbon = \Illuminate\Support\Carbon::parse($time);
    } catch (\Exception $e) {
        return null;
    }

    $hour = $carbon->format('g');
    $minute = (int) $carbon->format('i');
    $isPm = $carbon->format('a') === 'pm';

    if ($locale === 'ar') {
        $hoursAr = [
            1 => 'الواحدة', 2 => 'الثانية', 3 => 'الثالثة', 4 => 'الرابعة', 
            5 => 'الخامسة', 6 => 'السادسة', 7 => 'السابعة', 8 => 'الثامنة', 
            9 => 'التاسعة', 10 => 'العاشرة', 11 => 'الحادية عشرة', 12 => 'الثانية عشرة'
        ];
        
        $amPmAr = $isPm ? 'مساءً' : 'صباحاً';
        
        $h = (int) $hour;
        
        if ($minute > 40) {
            $h = $h === 12 ? 1 : $h + 1;
        }
        
        $hourStr = $hoursAr[$h];
        
        $minuteStr = '';
        if ($minute === 0) {
            $minuteStr = 'تماماً';
        } elseif ($minute === 15) {
            $minuteStr = 'والربع';
        } elseif ($minute === 20) {
            $minuteStr = 'والثلث';
        } elseif ($minute === 30) {
            $minuteStr = 'والنصف';
        } elseif ($minute === 45) {
            $minuteStr = 'إلا ربع';
        } elseif ($minute === 40) {
            $minuteStr = 'إلا ثلث';
        } else {
            $f = new \NumberFormatter('ar', \NumberFormatter::SPELLOUT);
            $minuteStr = 'و ' . $f->format($minute) . ' دقيقة';
        }
        
        return "{$hourStr} {$minuteStr} {$amPmAr}";
    } else {
        $f = new \NumberFormatter('en', \NumberFormatter::SPELLOUT);
        
        $h = (int) $hour;
        
        if ($minute > 30) {
            $h = $h === 12 ? 1 : $h + 1;
        }
        
        $hourStr = ucfirst($f->format($h));
        $amPmEn = $isPm ? 'PM' : 'AM';
        
        if ($minute === 0) {
            return "{$hourStr} o'clock {$amPmEn}";
        } elseif ($minute === 15) {
            return "Quarter past {$hourStr} {$amPmEn}";
        } elseif ($minute === 30) {
            return "Half past {$hourStr} {$amPmEn}";
        } elseif ($minute === 45) {
            return "Quarter to {$hourStr} {$amPmEn}";
        } else {
            if ($minute < 30) {
                return ucfirst($f->format($minute)) . " past {$hourStr} {$amPmEn}";
            } else {
                return ucfirst($f->format(60 - $minute)) . " to {$hourStr} {$amPmEn}";
            }
        }
    }
}

echo getTimeInWords('08:00', 'ar') . "\n";
echo getTimeInWords('08:15', 'ar') . "\n";
echo getTimeInWords('08:30', 'ar') . "\n";
echo getTimeInWords('08:45', 'ar') . "\n";
echo getTimeInWords('08:40', 'ar') . "\n";
echo getTimeInWords('08:25', 'ar') . "\n";
echo getTimeInWords('13:00', 'ar') . "\n";

echo getTimeInWords('08:00', 'en') . "\n";
echo getTimeInWords('08:15', 'en') . "\n";
echo getTimeInWords('08:30', 'en') . "\n";
echo getTimeInWords('08:45', 'en') . "\n";
echo getTimeInWords('08:25', 'en') . "\n";
