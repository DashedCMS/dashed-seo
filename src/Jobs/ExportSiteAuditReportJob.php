<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Jobs;

use Throwable;
use RuntimeException;
use Filament\Actions\Action;
use Illuminate\Bus\Queueable;
use Dashed\DashedCore\Models\User;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\View;
use Dashed\DashedSeo\Models\SiteAudit;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Filament\Notifications\Notification;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Dashed\DashedSeo\Filament\Support\ReportData;
use Dashed\DashedCore\Jobs\Concerns\CreatesExportRecord;

/**
 * Het rapport als PDF, in de achtergrond. Een scan van een paar honderd
 * pagina's levert duizenden bevindingen op en die staan er allemaal in, dus
 * dit hoort niet in het verzoek van de knop thuis.
 */
class ExportSiteAuditReportJob implements ShouldQueue
{
    use CreatesExportRecord;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    // Eén poging: een tweede ronde levert bij een halve uitdraai alleen een
    // tweede bestand op, en de exportrij vertelt zelf al wat er misging.
    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(public int $auditId, public User $user)
    {
        $audit = SiteAudit::find($auditId);

        $this->createExportRecord(
            type: 'site_audit_report',
            label: __('Sitescan-rapport'),
            parameters: [
                'audit_id' => $auditId,
                'site_id' => $audit?->site_id,
                'scan_date' => $audit?->created_at?->format('d-m-Y H:i'),
            ],
            userId: $user->id,
            disk: 'public',
        );
    }

    public function handle(): void
    {
        try {
            $this->markExportAsProcessing();

            $audit = SiteAudit::find($this->auditId);

            if (! $audit) {
                throw new RuntimeException(__('De scan bestaat niet meer, dus er valt niets uit te draaien.'));
            }

            $html = View::make('dashed-seo::pdf.report', [
                'audit' => $audit,
                'report' => ReportData::full($audit),
            ])->render();

            $pdf = App::make('dompdf.wrapper');
            $pdf->loadHTML($html);
            $output = $pdf->output();

            $fileName = 'sitescan-' . $audit->site_id . '-' . $audit->created_at?->format('Y-m-d-His') . '.pdf';
            $filePath = 'dashed/exports/' . now()->format('Y/m') . '/' . $this->exportId . '/' . $fileName;

            Storage::disk('public')->put($filePath, $output);

            $this->markExportAsCompleted($filePath, $fileName);

            Notification::make()
                ->title(__('Het sitescan-rapport staat klaar'))
                ->body(__('De scan van :datum is als PDF uitgedraaid.', [
                    'datum' => $audit->created_at?->format('d-m-Y H:i'),
                ]))
                ->persistent()
                ->actions([
                    Action::make('download')
                        ->label(__('Download het rapport'))
                        ->button()
                        ->url(Storage::disk('public')->url($filePath))
                        ->openUrlInNewTab(),
                ])
                ->success()
                ->sendToDatabase($this->user)
                ->send();
        } catch (Throwable $e) {
            $this->markExportAsFailed($e);

            throw $e;
        }
    }

    public function failed(Throwable $exception): void
    {
        $this->markExportAsFailed($exception);
    }
}
