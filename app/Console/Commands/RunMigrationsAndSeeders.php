<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class RunMigrationsAndSeeders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:setup-database';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run migrations and seeders for production setup';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting database setup...');

        try {
            // Run migrations
            $this->info('Running migrations...');
            Artisan::call('migrate', ['--force' => true]);
            $this->info('Migrations completed successfully.');

            // Run seeders
            $this->info('Running seeders...');
            Artisan::call('db:seed', ['--force' => true]);
            $this->info('Seeders completed successfully.');

            // Check migration status
            $this->info('Checking migration status...');
            Artisan::call('migrate:status');
            $this->info('Database setup completed successfully!');

        } catch (\Exception $e) {
            $this->error('Error during database setup: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }
}