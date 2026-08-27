<?php

namespace App\Filament\Concerns;

use Illuminate\Database\Eloquent\Model;

trait HasRbac
{
    /**
     * Fine-grained capabilities per ARCHITECTURE_PROPOSAL §6 (academic
     * column covered by 'admin' in this app). Missing capability = admin only.
     */
    protected const CAPABILITIES = [
        'result.finalize' => ['admin'],
        'attendance.correct' => ['admin'],
        'grade.enter' => ['admin', 'registrar', 'teacher'],
        'batch.cancel' => ['admin'],
        'certificate.issue' => ['admin', 'accountant'],
        'certificate.void' => ['admin', 'accountant'],
    ];

    public static function canDo(string $capability): bool
    {
        return static::userHasAnyRole(static::CAPABILITIES[$capability] ?? ['admin']);
    }

    public static function canAccess(): bool
    {
        return static::userHasAnyRole(static::accessRoles());
    }

    public static function canCreate(): bool
    {
        return static::userHasAnyRole(static::createRoles());
    }

    public static function canEdit(Model $record): bool
    {
        return static::userHasAnyRole(static::editRoles());
    }

    public static function canDelete(Model $record): bool
    {
        return static::userHasAnyRole(static::deleteRoles());
    }

    public static function canForceDelete(Model $record): bool
    {
        return static::userHasAnyRole(['admin']);
    }

    public static function canRestore(Model $record): bool
    {
        return static::userHasAnyRole(['admin']);
    }

    protected static function accessRoles(): array
    {
        return ['admin'];
    }

    protected static function createRoles(): array
    {
        return ['admin'];
    }

    protected static function editRoles(): array
    {
        return ['admin'];
    }

    protected static function deleteRoles(): array
    {
        return ['admin'];
    }

    protected static function userHasAnyRole(array $roles): bool
    {
        return auth()->user()?->hasAnyRole($roles) ?? false;
    }
}