<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Writes an SQL dump of the application database to a file.
 * MySQL/MariaDB uses mysqldump (auto-detected on XAMPP); a SQLite file is copied.
 */
class DatabaseDumper
{
    public function dump(string $target): void
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();

        if ($driver === 'sqlite') {
            $file = $connection->getDatabaseName();
            if ($file === ':memory:' || ! is_file($file)) {
                throw new RuntimeException('In-memory SQLite databases cannot be backed up.');
            }
            copy($file, $target);

            return;
        }

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            throw new RuntimeException("Backups are not supported for the {$driver} driver.");
        }

        $config = $connection->getConfig();
        $command = [
            $this->binary(),
            '--host='.$config['host'],
            '--port='.($config['port'] ?? 3306),
            '--user='.$config['username'],
            '--single-transaction',
            '--routines',
            '--default-character-set=utf8mb4',
            '--result-file='.$target,
            $config['database'],
        ];

        // Password via environment so it never appears in the process list.
        $process = new Process($command, null, ['MYSQL_PWD' => (string) ($config['password'] ?? '')]);
        $process->setTimeout(1800)->run();

        if (! $process->isSuccessful() || ! is_file($target) || filesize($target) === 0) {
            throw new RuntimeException('mysqldump failed: '.trim($process->getErrorOutput() ?: $process->getOutput()));
        }
    }

    public function binary(): string
    {
        if ($configured = config('emr.backup.mysqldump')) {
            return $configured;
        }

        foreach (['C:\\xampp\\mysql\\bin\\mysqldump.exe', '/opt/lampp/bin/mysqldump', '/usr/bin/mysqldump', '/usr/local/bin/mysqldump'] as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return 'mysqldump'; // rely on PATH
    }
}
