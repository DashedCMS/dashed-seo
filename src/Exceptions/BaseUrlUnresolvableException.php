<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Exceptions;

use RuntimeException;

/**
 * Gegooid door StartSiteAuditJob::resolveEffectiveBaseUrl() wanneer de
 * server wel antwoordde, maar de URL waar dat antwoord vandaan kwam niet
 * vastgesteld kon worden (effectiveUri() gaf null terug), of wel vastgesteld
 * kon worden maar geen geldige URL bleek (UrlNormalizer::normalize() wees
 * 'm af). Beide zijn een andere reden om te falen dan een onbereikbare
 * basis-URL: de server leeft, maar welke URL voortaan de scan-basis is staat
 * niet vast. Stilzwijgend teruggeven van de oorspronkelijke, niet-opgeloste
 * $base zou precies het symptoom reproduceren dat resolveEffectiveBaseUrl()
 * moet voorkomen: een scan die doorzet op het verkeerde adres en er toch
 * geslaagd uitziet.
 */
class BaseUrlUnresolvableException extends RuntimeException
{
}
