<?php

namespace App\Filament\Widgets;

use App\Models\AuditLog;
use Filament\Widgets\Widget;

class RecentActivityWidget extends Widget
{
    protected static bool $isLazy = false;

    protected static ?string $pollingInterval = null;

    protected static ?int $sort = 7;

    protected int | string | array $columnSpan = 'full';

    protected static string $view = 'filament.widgets.recent-activity';

    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'accountant']) ?? false;
    }

    public function getLogs(): \Illuminate\Support\Collection
    {
        return AuditLog::query()
            ->with('user')
            ->latest('created_at')
            ->limit(10)
            ->get();
    }
}
