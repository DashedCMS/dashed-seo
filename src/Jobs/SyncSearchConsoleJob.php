<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Dashed\DashedSeo\SearchConsole\DropNotifier;
use Dashed\DashedSeo\SearchConsole\SearchConsoleSync;

class SyncSearchConsoleJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 1200;

    public int $tries = 2;

    public int $uniqueFor = 3600;

    public function __construct(public string $siteId)
    {
    }

    public function uniqueId(): string
    {
        return $this->siteId;
    }

    public function handle(): void
    {
        app(SearchConsoleSync::class)->sync($this->siteId);
        app(DropNotifier::class)->notify($this->siteId);
    }
}
