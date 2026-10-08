<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackupRun extends Command
{
    protected $signature = 'backup:run {--force : run even if one was made today}';

    protected $description = 'Create a gzip SQL backup of the database';

    public function handle(): int
    {
        if (! $this->option('force') && ! BackupService::due()) {
            $this->info('Backup not due.');

            return 0;
        }
        $b = BackupService::run('auto');
        $b->status === 'ok' ? $this->info("Backup created: {$b->filename} ({$b->rows} rows)") : $this->error($b->error);

        return $b->status === 'ok' ? 0 : 1;
    }
}
