<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;

class BackupRun extends Command
{
    protected $signature = 'backup:run {--keep=30 : Cuántos respaldos guardar}';

    protected $description = 'Respalda la base de datos (pg_dump) en storage/app/backups';

    public function handle(BackupService $backups): int
    {
        try {
            $file = $backups->run((int) $this->option('keep'));
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $this->info("Respaldo guardado: {$file}");

        return self::SUCCESS;
    }
}
