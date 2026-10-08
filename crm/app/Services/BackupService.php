<?php

namespace App\Services;

use App\Models\Backup;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;

/** Pure-PHP MySQL dump (works on shared hosting without mysqldump), gzip-compressed, kept under storage/app/backups. */
class BackupService
{
    public static function dir(): string
    {
        $d = storage_path('app/backups');
        if (! is_dir($d)) {
            @mkdir($d, 0775, true);
            @file_put_contents($d . '/.htaccess', "Require all denied\nDeny from all\n");
        }

        return $d;
    }

    public static function run(string $kind = 'auto', ?int $userId = null): Backup
    {
        @set_time_limit(0);
        $name = 'backup-' . now()->format('Y-m-d_His') . '-' . \Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(4)) . '.sql.gz';
        $path = self::dir() . '/' . $name;
        $tables = 0;
        $rows = 0;

        try {
            $gz = gzopen($path, 'wb6');
            if (! $gz) {
                throw new \RuntimeException('تعذر إنشاء ملف النسخة الاحتياطية.');
            }
            gzwrite($gz, "-- Bajaj CRM backup " . now()->toDateTimeString() . "\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n");
            $pdo = DB::connection()->getPdo();
            $db = DB::connection()->getDatabaseName();

            foreach (DB::select('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"') as $t) {
                $table = array_values((array) $t)[0];
                if (in_array($table, ['sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs'], true)) {
                    continue;
                }
                $tables++;
                $create = (array) DB::selectOne("SHOW CREATE TABLE `{$table}`");
                gzwrite($gz, "DROP TABLE IF EXISTS `{$table}`;\n" . array_values($create)[1] . ";\n\n");

                $cols = null;
                $buffer = [];
                foreach (DB::table($table)->cursor() as $row) {
                    $row = (array) $row;
                    $cols ??= '`' . implode('`,`', array_keys($row)) . '`';
                    $vals = array_map(fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), array_values($row));
                    $buffer[] = '(' . implode(',', $vals) . ')';
                    $rows++;
                    if (count($buffer) >= 200) {
                        gzwrite($gz, "INSERT INTO `{$table}` ({$cols}) VALUES\n" . implode(",\n", $buffer) . ";\n");
                        $buffer = [];
                    }
                }
                if ($buffer) {
                    gzwrite($gz, "INSERT INTO `{$table}` ({$cols}) VALUES\n" . implode(",\n", $buffer) . ";\n");
                }
                gzwrite($gz, "\n");
            }
            gzwrite($gz, "SET FOREIGN_KEY_CHECKS=1;\n-- end of backup\n");
            gzclose($gz);

            $b = Backup::create(['filename' => $name, 'size' => filesize($path), 'tables' => $tables, 'rows' => $rows, 'kind' => $kind, 'status' => 'ok', 'created_by' => $userId]);
            Settings::set('last_backup_at', now()->toDateTimeString());
            self::prune();

            return $b;
        } catch (\Throwable $e) {
            @unlink($path);

            return Backup::create(['filename' => $name, 'size' => 0, 'kind' => $kind, 'status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 500), 'created_by' => $userId]);
        }
    }

    public static function prune(): void
    {
        $days = max(1, (int) Settings::get('backup_retention_days', 14));
        Backup::where('created_at', '<', now()->subDays($days))->get()->each(function (Backup $b) {
            @unlink($b->path());
            $b->delete();
        });
    }

    /** True when auto-backup is enabled and none was made today. */
    public static function due(): bool
    {
        if (! Settings::get('backup_auto', 1)) {
            return false;
        }
        $last = Settings::get('last_backup_at');

        return ! $last || ! \Illuminate\Support\Carbon::parse($last)->isToday();
    }
}
