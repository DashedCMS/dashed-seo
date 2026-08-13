<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class AnalyseSiteAuditJob implements ShouldQueue
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
        // Placeholder: a later task (analysis phase) fills this in with the
        // actual check-running logic. CrawlSiteAuditBatchJob dispatches this
        // job once its work queue is empty and needs the class to exist.
    }
}
