<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Fixes\Content;

use Dashed\DashedAi\Facades\Ai;
use Dashed\DashedSeo\Fixes\BaseFixer;
use Dashed\DashedAi\Enums\AiCapability;
use Dashed\DashedSeo\Fixes\FixProposal;
use Illuminate\Support\Facades\Storage;
use Dashed\DashedFiles\Models\MediaItem;
use Dashed\DashedSeo\Models\SiteAuditFix;
use Dashed\DashedFiles\Classes\MediaTexts;
use Dashed\DashedFiles\Classes\MediaLibrary;
use Dashed\DashedSeo\Models\SiteAuditFinding;

/**
 * Alt-teksten voor precies de afbeeldingen die de scan mist, in plaats van
 * voor de hele mediabibliotheek zoals CreateAltTextsForAllMediaItems doet.
 *
 * Een bevinding noemt vaak meerdere afbeeldingen. We lopen ze langs op zoek
 * naar de eerste die in de mediabibliotheek te vinden is en in de taal van
 * de pagina nog geen alt-tekst heeft, en slaan daarbij gratis over wat geen AI-aanroep kost:
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

        // Met een oudere dashed-files (zonder teksten per taal): het gedrag van
        // daarvoor, één alt-tekst in de ene kolom.
        $perLocale = static::hasLocales();

        // De taal van de pagina waar de afbeelding zonder alt-tekst op staat.
        // Onbekend (een site met meer talen en een pagina zonder taal), of een
        // taal die de mediabibliotheek niet kent: dan de spiegeltaal.
        $locale = null;
        $first = null;

        if ($perLocale) {
            $first = MediaTexts::firstLocale();
            $locale = $this->localeFor($finding);
            $locale = is_string($locale) && MediaTexts::isLocale($locale) ? $locale : $first;
        }

        foreach ((array) ($finding->context['images'] ?? []) as $src) {
            if (! is_string($src)) {
                continue;
            }

            $item = MediaItemLocator::forSrc($src);

            // Gratis overslaan: geen item, in de prullenbak, een bijlage van een
            // bezoeker of bestelling, of in DEZE taal al een alt-tekst.
            if (! $item || (method_exists($item, 'trashed') && $item->trashed()) || ! static::maySend($item)) {
                continue;
            }

            $have = $perLocale ? $item->translations('alt') : array_filter(['' => trim((string) $item->alt_text)]);

            if ($perLocale ? isset($have[$locale]) : $have !== []) {
                continue;
            }

            $aangeroepen = false;
            $tekst = $this->describe($item, $aangeroepen, $locale, $perLocale ? ($have[$first] ?? null) : null);

            if ($tekst !== null) {
                return new FixProposal(
                    subject: $item,
                    locale: $locale,
                    // De waarde van deze taal, onder de naam van het veld.
                    before: ['alt_text' => $perLocale ? null : $item->alt_text],
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

    /** Of dashed-files alt-teksten per taal kent. Zonder: alles zoals voorheen, op de ene kolom. */
    protected static function hasLocales(): bool
    {
        return class_exists('Dashed\\DashedFiles\\Classes\\MediaTexts')
            && method_exists('Dashed\\DashedFiles\\Classes\\MediaLibrary', 'setTexts')
            && method_exists(MediaItem::class, 'translations');
    }

    /** Geen bijlage van een bezoeker of bestelling naar de AI-dienst. */
    protected static function maySend(MediaItem $item): bool
    {
        $privacy = 'Dashed\\DashedFiles\\Classes\\MediaPrivacy';

        return ! class_exists($privacy) || ! $privacy::isProtected($item);
    }

    /**
     * Schrijven en terugdraaien gaan door de ene deur van de
     * mediabibliotheek, met de taal van de fix: zo kloppen de tekstkolom (de
     * spiegel van de spiegeltaal), de vertalingen en de cache.
     */
    protected function write(SiteAuditFix $fix, array $values): void
    {
        $subject = $fix->subject;

        if (! static::hasLocales() || ! $subject || ! array_key_exists('alt_text', $values)) {
            parent::write($fix, $values);

            return;
        }

        // Op de oude motor wijst de morph-alias naar het model van het
        // pakket: dan het eigen model op dezelfde rij.
        if (! $subject instanceof MediaItem && $subject->getMorphClass() === MediaItem::MORPH_ALIAS) {
            $subject = app(MediaLibrary::class)->find($subject->getKey(), includeWithoutFile: true);
        }

        if (! $subject instanceof MediaItem) {
            parent::write($fix, $values);

            return;
        }

        $locale = is_string($fix->locale) && $fix->locale !== '' ? $fix->locale : MediaTexts::firstLocale();
        $value = $values['alt_text'];

        app(MediaLibrary::class)->setText($subject, 'alt', $locale, $value === null ? null : mb_substr((string) $value, 0, 255));
    }

    /**
     * Beschrijft de afbeelding met de AI, in de gevraagde taal. Zet
     * $aangeroepen op true zodra de AI daadwerkelijk aangeroepen is, ook als
     * dat niets bruikbaars opleverde: de aanroeper gebruikt dat om te weten
     * of doorgaan naar de volgende afbeelding nog gratis is.
     *
     * Staat de alt-tekst er in de spiegeltaal al, dan wordt die vertaald:
     * een tekstaanroep zonder beeld, een fractie van de prijs.
     */
    protected function describe(MediaItem $item, bool &$aangeroepen, ?string $locale = null, ?string $bron = null): ?string
    {
        $aangeroepen = false;
        $opties = ['purpose' => 'media_alt_text', 'skip_tone_of_voice' => true];
        $taal = $locale === null ? '' : ', geschreven in de taal met code "' . $locale . '"';

        if ($bron !== null && $locale !== null && Ai::default(AiCapability::Text)) {
            $aangeroepen = true;
            $this->countAiCall();

            $antwoord = Ai::text(
                'Vertaal deze alt-tekst van een afbeelding op een website naar de taal met code "' . $locale . '". '
                . 'Maximaal 200 tekens, zonder HTML en zonder aanhalingstekens. Antwoord met alleen de vertaling.' . "\n\n" . $bron,
                $opties,
            );

            return $this->schoon($antwoord);
        }

        $beeld = $this->beeld($item);

        if ($beeld === null) {
            return null;
        }

        $aangeroepen = true;
        $this->countAiCall();

        $antwoord = Ai::vision(
            'Geef een korte, duidelijke alt-tekst voor deze afbeelding' . $taal . '. '
            . 'Maximaal 200 tekens, beschrijvend, zonder HTML en zonder aanhalingstekens. Antwoord met alleen de alt-tekst.',
            $beeld['data'],
            $beeld['mime'],
            $opties,
        );

        return $this->schoon($antwoord);
    }

    /**
     * Het beeld voor de AI. Via dashed-ai als die het kan: het formaat
     * `medium` in plaats van het origineel en een grens van 5 MB, zodat er
     * geen foto van tientallen megabytes in het geheugen en over de lijn
     * gaat. Een oudere dashed-ai: het origineel, met dezelfde grens.
     *
     * @return array{data: string, mime: string}|null
     */
    protected function beeld(MediaItem $item): ?array
    {
        $vision = 'Dashed\\DashedAi\\Media\\MediaVision';

        if (class_exists($vision) && method_exists($vision, 'imageFor')) {
            return $vision::imageFor($item);
        }

        // Het huidige bestand: na "vervangen" hangt het vorige er nog een tijd naast (collectie library-replaced).
        $media = method_exists($item, 'file') ? $item->file() : $item->media->first();

        if (! $media || ! in_array($media->mime_type, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return null;
        }

        // Het pad binnen de schijf (getPath() is het absolute pad, voor lokale toegang).
        $pad = $media->getPathRelativeToRoot();
        $schijf = Storage::disk($media->disk);

        if (! $schijf->exists($pad) || $schijf->size($pad) > 5 * 1024 * 1024) {
            return null;
        }

        return ['data' => base64_encode($schijf->get($pad)), 'mime' => (string) $media->mime_type];
    }

    private function schoon(?string $antwoord): ?string
    {
        $tekst = trim((string) preg_replace('/\s+/u', ' ', strip_tags((string) $antwoord)));

        if (mb_strlen($tekst) > 200) {
            $tekst = rtrim(mb_substr($tekst, 0, 200));
        }

        return $tekst === '' ? null : $tekst;
    }
}
