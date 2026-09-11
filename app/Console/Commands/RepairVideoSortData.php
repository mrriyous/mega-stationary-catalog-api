<?php

namespace App\Console\Commands;

use App\Services\VideoSortService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RepairVideoSortData extends Command
{
    protected $signature = 'video-sort:repair';

    protected $description = 'Repair missing, duplicated, or stale video sorting data';

    public function handle(VideoSortService $videoSorts): int
    {
        DB::transaction(fn () => $videoSorts->repairAll());
        $this->info('Video sorting data repaired.');

        return self::SUCCESS;
    }
}
