<?php

namespace App\Console\Commands;

use App\Services\DrawService;
use Illuminate\Console\Command;

/** Non-winners who didn't pick within the choice window get Option B automatically. */
class AutoCreditExpiredDraws extends Command
{
    protected $signature = 'draws:auto-credit';

    protected $description = 'Move expired non-winner advances to wallet (choice window elapsed)';

    public function handle(DrawService $draw): int
    {
        $n = $draw->autoCreditExpired();
        $this->info("Auto-credited {$n} expired booking(s) to wallet.");

        return self::SUCCESS;
    }
}
