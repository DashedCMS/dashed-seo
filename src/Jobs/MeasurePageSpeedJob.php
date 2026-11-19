<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Dashed\DashedSeo\PageSpeed\PageSpeedMonitor;

class MeasurePageSpeedJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    // Tien URL's maal twee strategieën, elk tot twee minuten bij Google.
    public int $timeout = 2700;

    public int $tries = 1;

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
        app(PageSpeedMonitor::class)->measureSite($this->siteId);
    }
}
