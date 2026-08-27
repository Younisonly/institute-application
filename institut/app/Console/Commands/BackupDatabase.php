<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

class BackupDatabase extends Command
{
    protected $signature = 'backup:database';

    protected $description = 'Dump the MySQL database into storage/app/backups and prune old backups';

    public function handle(): int
    {
        $db = config('database.connections.mysql.database');
        $user = config('database.connections.mysql.username');
        $pass = config('database.connections.mysql.password');
        $host = config('database.connections.mysql.host');
        $dir = storage_path('app/backups');

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $file = $dir.'/'.now()->format('Y-m-d_His').'.sql';
        $command = sprintf(
            'mysqldump --host=%s --user=%s %s %s > %s',
            escapeshellarg($host),
            escapeshellarg($user),
            $pass !== '' ? '--password='.escapeshellarg($pass) : '',
            escapeshellarg($db),
            escapeshellarg($file)
        );

        $result = Process::run($command);

        if (! $result->successful()) {
            $this->error('Backup failed: '.$result->errorOutput());

            return self::FAILURE;
        }

        collect(glob($dir.'/*.sql') ?: [])
            ->filter(fn (string $path) => filemtime($path) < now()->subDays(30)->getTimestamp())
            ->each(fn (string $path) => unlink($path));

        $this->info('Backup saved: '.$file);

        return self::SUCCESS;
    }
}
