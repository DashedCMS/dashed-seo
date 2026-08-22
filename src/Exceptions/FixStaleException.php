<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Exceptions;

use RuntimeException;

/**
 * De wereld is veranderd tussen het voorstel en het toepassen, op een manier
 * die de vingerafdruk niet ziet.
 *
 * De gewone verlooptoets zet de oude waarden van het subject naast de huidige.
 * Een fixer die een record aanmaakt in plaats van wijzigt heeft dat subject nog
 * niet, en dan is er niets om tegen af te zetten. Zo'n fixer kan zelf wel
 * kijken of zijn aanname nog klopt, en gooit dan dit.
 *
 * ApplySiteAuditFixJob vangt hem apart op en zet de rij op verlopen in plaats
 * van op mislukt: er is niets kapotgegaan, het voorstel is alleen achterhaald.
 */
class FixStaleException extends RuntimeException
{
}
