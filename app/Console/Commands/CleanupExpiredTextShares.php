<?php

namespace App\Console\Commands;

use App\Models\TextShare;
use Illuminate\Console\Command;

class CleanupExpiredTextShares extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'text-share:cleanup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete expired text shares from the database';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Cleaning up expired text shares...');

        $deleted = TextShare::where('expires_at', '<', now())->delete();

        $this->info("Deleted {$deleted} expired text shares.");

        return Command::SUCCESS;
    }
}
