<?php

namespace App\Console\Commands;

use App\Models\Backup;
use App\Services\BackupService;
use Illuminate\Console\Command;

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
        if (! $this->option('yes') && ! $this->confirm('This REPLACES all current data (a safety backup of the current state is taken first). Continue?')) {
            return 1;
        }

        $backup = Backup::firstWhere('filename', basename($path));
        if (! $backup) {
            $this->error('This file is not registered in the backups table (restore must go through a tracked Backup row).');

            return 1;
        }

        BackupService::restore($backup);
        $this->info('Restore complete. A pre-restore safety backup of the previous state was created.');

        return 0;
    }
}
