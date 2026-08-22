<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Fixes\Content;

use Dashed\DashedAi\Facades\Ai;
use Dashed\DashedSeo\Fixes\BaseFixer;
use Dashed\DashedAi\Enums\AiCapability;
use Dashed\DashedSeo\Fixes\FixProposal;
use Illuminate\Support\Facades\Storage;
use Dashed\DashedSeo\Models\SiteAuditFinding;
use RalphJSmit\Filament\MediaLibrary\Models\MediaLibraryItem;

/**
 * Alt-teksten voor precies de afbeeldingen die de scan mist, in plaats van
 * voor de hele mediabibliotheek zoals CreateAltTextsForAllMediaItems doet.
 *
 * Een bevinding noemt vaak meerdere afbeeldingen. We lopen ze langs op zoek
 * naar de eerste die in de mediabibliotheek te vinden is en nog geen
 * alt-tekst heeft, en slaan daarbij gratis over wat geen AI-aanroep kost:
 * geen media-item, al een alt-tekst, een niet-ondersteund mime-type, of het
 * bestand ontbreekt op de schijf. Zodra de AI voor een afbeelding
 * daadwerkelijk aangeroepen is, stoppen we, ongeacht of dat een bruikbare
 * tekst opleverde. ProposeSiteAuditFixesJob telt namelijk maar één
 * AI-aanroep per voorstel tegen het plafond, en een pagina met tien
 * afbeeldingen zou anders tien aanroepen kunnen kosten terwijl er maar één
 * meetelt. Levert de aangeroepen AI niets bruikbaars op, dan komt de
 * afbeelding bij de volgende scan opnieuw aan de beurt.
 */
class AltTextFixer extends BaseFixer
{
    public function checkKeys(): array
    {
        return ['missing_alt'];
    }

    public function usesAi(): bool
    {
        return true;
    }

    public function label(): string
    {
        return __('Alt-tekst laten schrijven');
    }

    public function propose(SiteAuditFinding $finding): ?FixProposal
    {
        if (! Ai::default(AiCapability::Vision)) {
            return null;
        }

        foreach ((array) ($finding->context['images'] ?? []) as $src) {
            if (! is_string($src)) {
                continue;
            }

            $item = MediaItemLocator::forSrc($src);

            if (! $item || trim((string) $item->alt_text) !== '') {
                continue;
            }

            $aangeroepen = false;
            $tekst = $this->describe($item, $aangeroepen);

            if ($tekst !== null) {
                return new FixProposal(
                    subject: $item,
                    locale: null,
                    before: ['alt_text' => $item->alt_text],
                    after: ['alt_text' => $tekst],
                    summary: __('Alt-tekst ":tekst" voor :bestand', [
                        'tekst' => $tekst,
                        'bestand' => basename((string) parse_url($src, PHP_URL_PATH)),
                    ]),
                );
            }

            // De AI is voor deze afbeelding daadwerkelijk aangeroepen en gaf
            // niets bruikbaars terug: stoppen, niet doorgaan naar de
            // volgende afbeelding. Anders zou deze bevinding meer dan één
            // AI-aanroep kunnen kosten terwijl het plafond er maar één telt.
            if ($aangeroepen) {
                return null;
            }
        }

        return null;
    }

    /**
     * Beschrijft de afbeelding met de AI. Zet $aangeroepen op true zodra de
     * AI daadwerkelijk aangeroepen is, ook als dat niets bruikbaars
     * opleverde: de aanroeper gebruikt dat om te weten of doorgaan naar de
     * volgende afbeelding nog gratis is.
     */
    protected function describe(MediaLibraryItem $item, bool &$aangeroepen): ?string
    {
        $aangeroepen = false;
        $media = $item->media->first();

        if (! $media || ! in_array($media->mime_type, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return null;
        }

        // getPath() geeft het volledige pad inclusief de root van de schijf,
        // bedoeld voor lokale bestandstoegang. Storage::disk()->exists()/get()
        // verwachten een pad relatief aan die root, zoals spatie zelf ook
        // gebruikt in bijvoorbeeld Media::stream(). Met getPath() zou dit op
        // elke schijf met een echte root (elke lokale schijf, dus ook een
        // testomgeving) altijd "niet gevonden" opleveren; op de s3-schijf
        // van productie viel dat toevallig niet op omdat s3 geen root kent.
        $pad = $media->getPathRelativeToRoot();

        if (! Storage::disk($media->disk)->exists($pad)) {
            return null;
        }

        $aangeroepen = true;
        $this->countAiCall();

        $antwoord = Ai::vision(
            'Geef een korte, duidelijke alt-tekst voor deze afbeelding. Maximaal 200 tekens, '
            . 'beschrijvend, zonder HTML en zonder aanhalingstekens.',
            base64_encode(Storage::disk($media->disk)->get($pad)),
            $media->mime_type,
        );

        $tekst = trim((string) $antwoord);

        return $tekst === '' ? null : mb_substr($tekst, 0, 200);
    }
}
