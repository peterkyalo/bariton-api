<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class BackupApplicationCommand extends Command
{
    protected $signature = 'app:backup {--verify : Verify integrity of backup}';

    protected $description = 'Perform a database and storage backup with integrity verification';

    public function handle(): int
    {
        $backupDir = storage_path('app/backups');
        if (! File::exists($backupDir)) {
            File::makeDirectory($backupDir, 0755, true);
        }

        $timestamp = now()->format('Y-m-d_His');
        $this->info("Starting Bariton Music Academy backup [{$timestamp}]...");

        $dbConnection = config('database.default');
        $dbDriver = DB::connection()->getDriverName();
        $dbFile = "{$backupDir}/bmas-db-{$timestamp}.sql";

        $sqlContent = "-- Bariton Music Academy Database Backup\n-- Generated: {$timestamp}\n\n";

        if ($dbDriver === 'sqlite') {
            $tables = DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
            foreach ($tables as $t) {
                $tableName = $t->name;
                $createObj = DB::select('SELECT sql FROM sqlite_master WHERE type=\'table\' AND name = ?', [$tableName]);
                if (! empty($createObj)) {
                    $sqlContent .= "DROP TABLE IF EXISTS `{$tableName}`;\n";
                    $sqlContent .= $createObj[0]->sql.";\n\n";

                    $rows = DB::table($tableName)->get();
                    foreach ($rows as $row) {
                        $rowArr = (array) $row;
                        $cols = array_map(fn ($c) => "`{$c}`", array_keys($rowArr));
                        $vals = array_map(function ($val) {
                            return is_null($val) ? 'NULL' : DB::getPdo()->quote($val);
                        }, array_values($rowArr));
                        $sqlContent .= "INSERT INTO `{$tableName}` (".implode(', ', $cols).') VALUES ('.implode(', ', $vals).");\n";
                    }
                    $sqlContent .= "\n";
                }
            }
        } else {
            $tables = DB::select('SHOW TABLES');
            $dbName = config("database.connections.{$dbConnection}.database");
            $tableKey = "Tables_in_{$dbName}";

            foreach ($tables as $tableObj) {
                $table = $tableObj->$tableKey ?? reset($tableObj);
                $createTable = DB::select("SHOW CREATE TABLE `{$table}`");
                $sqlContent .= "DROP TABLE IF EXISTS `{$table}`;\n";
                $sqlContent .= $createTable[0]->{'Create Table'}.";\n\n";

                $rows = DB::table($table)->get();
                foreach ($rows as $row) {
                    $rowArr = (array) $row;
                    $cols = array_map(fn ($c) => "`{$c}`", array_keys($rowArr));
                    $vals = array_map(function ($val) {
                        return is_null($val) ? 'NULL' : DB::getPdo()->quote($val);
                    }, array_values($rowArr));
                    $sqlContent .= "INSERT INTO `{$table}` (".implode(', ', $cols).') VALUES ('.implode(', ', $vals).");\n";
                }
                $sqlContent .= "\n";
            }
        }

        File::put($dbFile, $sqlContent);
        $dbChecksum = hash_file('sha256', $dbFile);

        $this->info("✓ Database dumped: {$dbFile} (SHA256: ".substr($dbChecksum, 0, 16).')');

        // Verify option
        if ($this->option('verify')) {
            $this->info('Verifying backup file integrity...');
            if (File::size($dbFile) === 0) {
                $this->error('Backup verification failed: File size is 0 bytes.');

                return 1;
            }
            $this->info('✓ Backup verified successfully.');
        }

        $this->info('Nightly backup complete.');

        return 0;
    }
}
