<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackupRestore extends Command
{
    protected $signature = 'backup:restore {file : backup file name or path} {--yes : do not ask for confirmation}';

    protected $description = 'Restore the database from a .sql.gz backup (DESTRUCTIVE: replaces current data)';

    public function handle(): int
    {
        $file = $this->argument('file');
        $path = is_file($file) ? $file : BackupService::dir() . '/' . basename($file);
        if (! is_file($path)) {
            $this->error('Backup file not found.');

            return 1;
        }
        if (! $this->option('yes') && ! $this->confirm('This REPLACES all current data. Continue?')) {
            return 1;
        }
        $gz = gzopen($path, 'rb');
        $stmt = '';
        $n = 0;
        $pdo = DB::connection()->getPdo();
        while (($line = gzgets($gz)) !== false) {
            if ($line === "\n" || str_starts_with($line, '--')) {
                continue;
            }
            $stmt .= $line;
            if (str_ends_with(rtrim($line), ';')) {
                $pdo->exec($stmt);
                $stmt = '';
                $n++;
            }
        }
        gzclose($gz);
        $this->info("Restored {$n} statements.");

        return 0;
    }
}
