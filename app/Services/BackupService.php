<?php

namespace App\Services;

use App\Support\Audit;
use App\Support\DatabaseDumper;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Finder\Finder;
use ZipArchive;

/**
 * Full backup = database dump + private files (patient photos, imaging)
 * + public uploads, in one zip. Old backups are pruned after each run.
 */
class BackupService
{
    public function __construct(protected DatabaseDumper $dumper) {}

    public function directory(): string
    {
        $dir = config('emr.backup.path');
        File::ensureDirectoryExists($dir);

        return $dir;
    }

    public function create(): array
    {
        $name = 'emr-backup-'.now()->format('Y-m-d_His').'.zip';
        $zipPath = $this->directory().DIRECTORY_SEPARATOR.$name;
        $sql = tempnam(sys_get_temp_dir(), 'emr-sql');

        try {
            $this->dumper->dump($sql);

            $zip = new ZipArchive;
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException("Cannot create {$zipPath}");
            }
            $zip->addFile($sql, 'database.sql');

            $files = 0;
            foreach (config('emr.backup.folders') as $folder) {
                if (! is_dir($folder)) {
                    continue;
                }
                $prefix = 'files/'.Str::afterLast(str_replace('\\', '/', rtrim($folder, '\\/')), '/');
                foreach (Finder::create()->files()->in($folder)->ignoreDotFiles(true) as $file) {
                    $zip->addFile($file->getRealPath(), $prefix.'/'.str_replace('\\', '/', $file->getRelativePathname()));
                    $files++;
                }
            }
            $zip->setArchiveComment('Hospital EMR backup · '.setting('hospital_name').' · '.now()->toIso8601String());
            $zip->close();
        } catch (\Throwable $e) {
            @unlink($zipPath);
            Audit::log('backup_failed', 'Backup failed: '.Str::limit($e->getMessage(), 200));
            throw $e;
        } finally {
            @unlink($sql);
        }

        $pruned = $this->prune();
        Audit::log('backup_created', "Backup {$name} created ({$files} files, ".$this->human(filesize($zipPath)).')');

        return ['name' => $name, 'path' => $zipPath, 'size' => filesize($zipPath), 'files' => $files, 'pruned' => $pruned];
    }

    /**
     * @return Collection<int, array{name: string, size: int, created: \Illuminate\Support\Carbon}>
     */
    public function list(): Collection
    {
        return collect(File::glob($this->directory().DIRECTORY_SEPARATOR.'emr-backup-*.zip'))
            ->map(fn ($path) => [
                'name' => basename($path),
                'size' => filesize($path),
                'created' => \Illuminate\Support\Carbon::createFromTimestamp(filemtime($path)),
            ])
            ->sortByDesc('created')->values();
    }

    public function latest(): ?array
    {
        return $this->list()->first();
    }

    /**
     * Resolve a backup file safely (no path traversal).
     */
    public function path(string $name): string
    {
        abort_unless(preg_match('/^emr-backup-[\d_\-]+\.zip$/', $name) === 1, 404);
        $path = $this->directory().DIRECTORY_SEPARATOR.$name;
        abort_unless(is_file($path), 404);

        return $path;
    }

    public function prune(): int
    {
        $cutoff = now()->subDays(max(1, (int) setting('backup_retention_days')));
        $deleted = 0;

        // Always keep the newest backup, however old.
        foreach ($this->list()->slice(1) as $backup) {
            if ($backup['created']->lt($cutoff)) {
                File::delete($this->directory().DIRECTORY_SEPARATOR.$backup['name']);
                $deleted++;
            }
        }

        return $deleted;
    }

    public function human(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < 3) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 1).' '.$units[$i];
    }
}
