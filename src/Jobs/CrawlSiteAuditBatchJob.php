<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class CrawlSiteAuditBatchJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public int $auditId)
    {
        $this->onQueue('seo');
    }

    public function handle(): void
    {
        // Placeholder: task 10 fills this in with the actual crawl batch logic.
    }
}
