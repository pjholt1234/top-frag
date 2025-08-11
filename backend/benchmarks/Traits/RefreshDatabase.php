<?php

namespace App\Benchmarks\Traits;

use Illuminate\Contracts\Console\Kernel;

trait RefreshDatabase
{
    /**
     * Refresh the database before each benchmark method.
     *
     * @BeforeMethods({"refreshDatabase"})
     */
    public function refreshDatabase(): void
    {
        /** @var Kernel $artisan */
        $artisan = app(Kernel::class);

        // Drop & recreate tables
        $artisan->call('migrate:fresh', [
            '--force' => true,
        ]);
    }
}
