<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Checks\Links;

use Dashed\DashedSeo\Models\SiteAudit;
use Dashed\DashedSeo\Checks\BaseCheck;
use Dashed\DashedSeo\Models\SiteAuditLink;

class SingleIncomingLinkCheck extends BaseCheck
{
    public function key(): string
    {
        return 'single_incoming_link';
    }

    public function run(SiteAudit $audit): iterable
    {
        foreach ($this->indexablePages($audit)->orderBy('id')->cursor() as $page) {
            // Tel verwijzende pagina's, niet linkrijen: een menulink en een
            // thumbnail vanaf dezelfde pagina zijn een verwijzende pagina, geen twee.
            $count = SiteAuditLink::where('audit_id', $audit->id)
                ->where('to_page_id', $page->id)
                ->where('from_page_id', '!=', $page->id)
                ->distinct('from_page_id')
                ->count('from_page_id');

            if ($count === 1) {
                yield $this->finding($page, ['incoming' => 1]);
            }
        }
    }
}
