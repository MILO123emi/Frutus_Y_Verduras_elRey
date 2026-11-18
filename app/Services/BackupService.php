<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Respaldo de la base de datos con pg_dump (formato custom, comprimido), en
 * storage/app/backups. Guarda los últimos N y borra los más viejos.
 * Restaurar: pg_restore --clean --if-exists -d frutverdrey <archivo>.dump
 */
class BackupService
{
    public function directory(): string
    {
        return storage_path('app/backups');
    }

    public function run(int $keep = 30): string
    {
        File::ensureDirectoryExists($this->directory());
        $db = config('database.connections.pgsql');
        $file = $this->directory().'/elrey_'.now('America/Tegucigalpa')->format('Y-m-d_His').'.dump';

        $process = new Process(
            [env('PG_DUMP_PATH', 'pg_dump'), '-Fc', '-h', $db['host'], '-p', (string) $db['port'], '-U', $db['username'], '-f', $file, $db['database']],
            null,
            ['PGPASSWORD' => (string) $db['password']],
            null,
            300,
        );
        $process->run();

        if (! $process->isSuccessful() || ! is_file($file) || filesize($file) === 0) {
            @unlink($file);
            throw new RuntimeException('No se pudo hacer el respaldo: '.trim($process->getErrorOutput() ?: 'pg_dump falló'));
        }

        $this->prune($keep);

        return $file;
    }

    /** @return array<int, array{name:string, size:int, date:string}> más nuevo primero */
    public function list(): array
    {
        if (! is_dir($this->directory())) {
            return [];
        }

        return collect(File::files($this->directory()))
            ->filter(fn ($f) => str_ends_with($f->getFilename(), '.dump'))
            ->sortByDesc(fn ($f) => $f->getFilename())
            ->map(fn ($f) => ['name' => $f->getFilename(), 'size' => $f->getSize(), 'date' => date('c', $f->getMTime())])
            ->values()->all();
    }

    private function prune(int $keep): void
    {
        foreach (array_slice($this->list(), $keep) as $old) {
            @unlink($this->directory().'/'.$old['name']);
        }
    }
}
