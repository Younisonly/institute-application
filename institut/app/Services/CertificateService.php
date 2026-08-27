<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Certificate;
use App\Models\InstituteSetting;
use App\Models\ProgramType;
use App\Models\Student;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Program-level certificate register (proposal §14): issuance is only
 * possible after ProgressionService::graduationEligible passes (every
 * required curriculum course has a passing attempt). Each certificate is
 * a permanent record with a sequential number and a public verification
 * code; voiding keeps the row (rule 3) and every event is audited.
 */
class CertificateService
{
    /** Notify everyone allowed to issue/void certificates except the actor. */
    private function notifyRoles(User $actor, string $title, string $body, bool $success = true): void
    {
        $recipients = User::query()
            ->role(['admin', 'accountant'])
            ->whereKeyNot($actor->id)
            ->get();

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::make()
            ->title($title)
            ->body($body)
            ->{$success ? 'success' : 'danger'}()
            ->sendToDatabase($recipients);
    }
    /** Allocate the next sequential certificate number atomically. */
    public function nextCertificateNo(): int
    {
        return DB::transaction(function (): int {
            $settings = InstituteSetting::query()->lockForUpdate()->firstOrFail();

            $number = (int) $settings->certificate_next_no;
            $settings->increment('certificate_next_no');

            return $number;
        });
    }

    private function uniqueVerificationCode(): string
    {
        do {
            $code = strtoupper(Str::random(10));
        } while (Certificate::query()->where('verification_code', $code)->exists());

        return $code;
    }

    /**
     * Issue a certificate for a completed program. Throws with the list of
     * missing required courses when the student is not eligible yet, and
     * refuses a second issued certificate for the same (student, program).
     */
    public function issue(Student $student, ProgramType $program, array $opts = []): Certificate
    {
        $user = auth()->user();

        return DB::transaction(function () use ($student, $program, $opts, $user): Certificate {
            $evaluation = app(ProgressionService::class)->graduationEligible((int) $student->id, $program);

            if (! $evaluation['eligible']) {
                $missing = $evaluation['missing'] !== []
                    ? __('general.missing_required_courses', ['courses' => implode(', ', $evaluation['missing'])])
                    : __('general.certificate_no_curriculum');

                throw ValidationException::withMessages(['program_id' => $missing]);
            }

            if (Certificate::query()
                ->where('student_id', $student->id)
                ->where('program_id', $program->id)
                ->where('status', Certificate::STATUS_ISSUED)
                ->exists()) {
                throw ValidationException::withMessages(['program_id' => __('general.certificate_already_issued')]);
            }

            $number = $this->nextCertificateNo();
            $snapshot = app(ProgressionService::class)->earnedCoursesSnapshot((int) $student->id, $program);

            $certificate = Certificate::create([
                'student_id' => $student->id,
                'program_id' => $program->id,
                'certificate_no' => str_pad((string) $number, 5, '0', STR_PAD_LEFT),
                'title_ar' => (string) $program->name,
                'title_en' => (string) $program->name,
                'issue_date' => now()->toDateString(),
                'completion_date' => now()->toDateString(),
                'status' => Certificate::STATUS_ISSUED,
                'verification_code' => $this->uniqueVerificationCode(),
                'issued_by' => $user?->id,
                'earned_courses' => $snapshot,
            ]);

            AuditLog::log('certificate.issued', 'certificate', (int) $certificate->id, [
                'certificate_no' => $certificate->certificate_no,
                'student_id' => $student->id,
                'program_id' => $program->id,
                'verification_code' => $certificate->verification_code,
                'required' => $evaluation['required'],
                'passed' => $evaluation['passed'],
                'balance' => $evaluation['balance'],
                'note' => $opts['note'] ?? null,
            ]);

            if ($user instanceof User) {
                $this->notifyRoles(
                    $user,
                    __('general.certificate_issued_notification'),
                    __('general.certificate_issued_notification_body', [
                        'no' => $certificate->certificate_no,
                        'student' => $student->name,
                    ]),
                );
            }

            return $certificate;
        });
    }

    /** Void a certificate — the row and audit trail stay (never hard-delete). */
    public function void(Certificate $certificate, string $reason): Certificate
    {
        $user = auth()->user();

        return DB::transaction(function () use ($certificate, $reason, $user): Certificate {
            if ($certificate->isVoided()) {
                throw ValidationException::withMessages(['void_reason' => __('general.certificate_already_voided')]);
            }

            $certificate->update([
                'status' => Certificate::STATUS_VOIDED,
                'voided_at' => now(),
                'void_reason' => $reason,
            ]);

            AuditLog::log('certificate.voided', 'certificate', (int) $certificate->id, [
                'certificate_no' => $certificate->certificate_no,
                'verification_code' => $certificate->verification_code,
                'student_id' => $certificate->student_id,
                'reason' => $reason,
                'by' => $user?->id,
            ]);

            if ($user instanceof User) {
                $this->notifyRoles(
                    $user,
                    __('general.certificate_voided_notification'),
                    __('general.certificate_voided_notification_body', [
                        'no' => $certificate->certificate_no,
                        'student' => $certificate->student?->name ?? '—',
                    ]),
                    false,
                );
            }

            return $certificate;
        });
    }
}