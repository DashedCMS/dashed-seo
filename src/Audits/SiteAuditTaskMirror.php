<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Audits;

use Dashed\DashedSeo\Models\SiteAudit;
use Dashed\DashedSeo\Models\SiteAuditPage;
use Dashed\DashedCore\Models\BackgroundTask;
use Dashed\DashedSeo\Filament\Resources\SiteAuditResource;

/**
 * Spiegelt een sitescan naar het takenicoon van dashed-core: gecrawlde
 * pagina's van het aantal gevonden adressen. Aangeroepen vanuit
 * SiteAudit::booted(), dus elke statuswijziging en elke partij van de crawler
 * komt hier langs. Achter class_exists, want een oudere dashed-core kent
 * BackgroundTask nog niet.
 */
class SiteAuditTaskMirror
{
    public static function sync(SiteAudit $audit): void
    {
        if (! class_exists(BackgroundTask::class)) {
            return;
        }

        rescue(function () use ($audit): void {
            $status = match ($audit->status) {
                SiteAudit::STATUS_PENDING => BackgroundTask::STATUS_QUEUED,
                SiteAudit::STATUS_COMPLETED => BackgroundTask::STATUS_COMPLETED,
                SiteAudit::STATUS_FAILED, SiteAudit::STATUS_CANCELLED => BackgroundTask::STATUS_FAILED,
                default => BackgroundTask::STATUS_RUNNING,
            };

            $total = (int) $audit->urls_found ?: null;

            // Alleen tijdens het crawlen tellen: daarna is alles opgehaald en
            // zegt de telling niets meer over wat er nog moet gebeuren.
            $done = match (true) {
                $audit->status === SiteAudit::STATUS_CRAWLING => $audit->pages()
                    ->where('crawl_status', SiteAuditPage::CRAWL_DONE)
                    ->count(),
                $audit->status === SiteAudit::STATUS_PENDING => 0,
                $status === BackgroundTask::STATUS_FAILED => null,
                default => (int) $total,
            };

            $completed = $status === BackgroundTask::STATUS_COMPLETED;

            BackgroundTask::mirror($audit, array_filter([
                'type' => 'site-audit',
                'label' => __('Sitescan: :site', ['site' => parse_url((string) $audit->base_url, PHP_URL_HOST) ?: $audit->base_url]),
                'status' => $status,
                'done' => $done,
                'total' => $total,
                'notify' => false,
            ], fn ($value) => $value !== null) + [
                'message' => match (true) {
                    $status === BackgroundTask::STATUS_FAILED => $audit->failure_reason,
                    $completed => __('Score :score, :paginas pagina\'s, :fouten fouten.', [
                        'score' => $audit->health_score,
                        'paginas' => (int) $audit->pages_crawled,
                        'fouten' => (int) $audit->error_count,
                    ]),
                    default => null,
                },
                'result_url' => $completed
                    ? rescue(fn () => SiteAuditResource::getUrl('report', ['record' => $audit->getKey()]), null, report: false)
                    : null,
                'result_label' => $completed ? __('Naar het rapport') : null,
            ]);
        }, report: false);
    }

    /**
     * Een scan die verdwijnt terwijl zijn taak nog loopt (AuditGuard trekt een
     * dubbele claim meteen weer in) mag niet als lopend blijven staan.
     */
    public static function forget(SiteAudit $audit): void
    {
        if (! class_exists(BackgroundTask::class)) {
            return;
        }

        rescue(fn () => BackgroundTask::query()
            ->where('subject_type', $audit->getMorphClass())
            ->where('subject_id', (string) $audit->getKey())
            ->active()
            ->delete(), report: false);
    }
}
