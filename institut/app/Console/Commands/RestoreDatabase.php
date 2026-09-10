<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Process;

class RestoreDatabase extends Command
{
    protected $signature = 'finance:restore {--file= : Path to the .sql backup file to restore}';

    protected $description = 'Restore MySQL database from a .sql backup file';

    public function handle(): int
    {
        $file = $this->option('file');

        if (! $file) {
            $dir = storage_path('app/backups');
            $backups = glob($dir.'/*.sql') ?: [];

            if (empty($backups)) {
                $this->error('No backup files found in ' . $dir . ' and no --file option provided.');

                return self::FAILURE;
            }

            usort($backups, fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));

            $choices = array_map(fn (string $path) => basename($path) . ' (' . date('d/m/Y H:i', filemtime($path)) . ')', $backups);

            $selected = $this->choice('Select a backup file to restore:', $choices, 0);

            $selectedIndex = array_search($selected, $choices, true);
            $file = $backups[$selectedIndex];
        }

        if (! file_exists($file)) {
            $this->error('Backup file not found: ' . $file);

            return self::FAILURE;
        }

        if (! $this->confirm('WARNING: Restoring this backup will overwrite the current database. Proceed?', false)) {
            $this->info('Restore cancelled.');

            return self::SUCCESS;
        }

        $db = config('database.connections.mysql.database');
        $user = config('database.connections.mysql.username');
        $pass = config('database.connections.mysql.password');
        $host = config('database.connections.mysql.host');

        $command = sprintf(
            'mysql --host=%s --user=%s %s %s < %s 2>/dev/null',
            escapeshellarg($host),
            escapeshellarg($user),
            $pass !== '' ? '--password='.escapeshellarg($pass) : '',
            escapeshellarg($db),
            escapeshellarg($file)
        );

        $result = Process::run($command);

        if (! $result->successful()) {
            $this->error('Restore failed: ' . $result->errorOutput());

            return self::FAILURE;
        }

        Artisan::call('cache:clear');

        $this->info('Database restored successfully from: ' . $file);

        return self::SUCCESS;
    }
}
