<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Listeners;

use Dashed\DashedSeo\Settings\FixSettings;
use Dashed\DashedSeo\Events\SiteAuditCompleted;
use Dashed\DashedSeo\Jobs\ProposeSiteAuditFixesJob;

/**
 * De fixronde hangt aan het bestaande event en niet aan de jobketen van de
 * scan zelf, zodat een scan precies blijft doen wat hij deed.
 */
class ProposeFixesAfterAudit
{
    public function handle(SiteAuditCompleted $event): void
    {
        // De job controleert dit ook, maar hier scheelt het een lege job in de
        // wachtrij op elke installatie die dit niet gebruikt.
        if (! FixSettings::enabled($event->audit->site_id)) {
            return;
        }

        ProposeSiteAuditFixesJob::dispatch($event->audit->id);
    }
}
