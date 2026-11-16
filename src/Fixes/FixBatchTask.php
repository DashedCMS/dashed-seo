<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Fixes;

use Dashed\DashedCore\Models\BackgroundTask;
use Dashed\DashedSeo\Filament\Resources\SiteAuditFixResource;

/**
 * Eén taak in het takenicoon van dashed-core voor een reeks voorstellen die
 * in bulk wordt doorgevoerd. Elk voorstel is een eigen job, mogelijk op
 * meerdere workers tegelijk, dus de teller loopt met een atomaire ophoging in
 * de database en niet via een instantie in het geheugen. Een los voorstel
 * krijgt geen taak: dat is in een paar seconden klaar.
 */
class FixBatchTask
{
    public const TYPE = 'site-audit-fixes';

    /**
     * Start de taak en geeft zijn id terug om aan de jobs mee te geven. Null
     * als er niets te melden valt of dashed-core het model nog niet kent.
     */
    public static function start(int $total, ?int $userId): ?int
    {
        if ($total < 2 || ! class_exists(BackgroundTask::class)) {
            return null;
        }

        return rescue(fn () => BackgroundTask::begin(
            self::TYPE,
            __('Sitescan: :aantal voorstellen doorvoeren', ['aantal' => $total]),
            $total,
            $userId,
        )->getKey(), null, report: false);
    }

    /**
     * Een voorstel is afgehandeld (doorgevoerd, verlopen of mislukt). De
     * laatste sluit de taak af; de claim op status voorkomt dat twee workers
     * dat allebei doen en de beheerder twee meldingen krijgt.
     */
    public static function advance(?int $taskId): void
    {
        if (! $taskId || ! class_exists(BackgroundTask::class)) {
            return;
        }

        rescue(function () use ($taskId): void {
            BackgroundTask::query()->whereKey($taskId)->active()->increment('done');

            $claimed = BackgroundTask::query()
                ->whereKey($taskId)
                ->active()
                ->whereColumn('done', '>=', 'total')
                ->update(['status' => BackgroundTask::STATUS_COMPLETED]);

            if ($claimed !== 1) {
                return;
            }

            $task = BackgroundTask::find($taskId);

            $task?->finish(
                __(':aantal voorstellen verwerkt.', ['aantal' => $task->total]),
                rescue(fn () => SiteAuditFixResource::getUrl('index'), null, report: false),
                __('Naar de voorstellen'),
            );
        }, report: false);
    }
}
