<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ConsolidateMigrationsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:consolidate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Consolidate multiple migration files into a single schema dump (Squash)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->warn('This operation will consolidate all current migrations into a single schema file.');
        
        if (!$this->confirm('Are you sure you want to proceed?')) {
            return;
        }

        try {
            $this->info('Dumping schema...');
            $result = \Illuminate\Support\Facades\Artisan::call('schema:dump', [
                '--prune' => true,
            ]);

            if ($result === 0) {
                $this->info('Migrations consolidated successfully into database/schema/*.sql');
                $this->comment('You can now cleanup old migration files that are covered by the dump.');
            } else {
                $this->error('Schema dump failed. Ensure your database driver is supported (MySQL/PostgreSQL) and tools (mysqldump/pg_dump) are in PATH.');
            }
        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
        }
    }
}
