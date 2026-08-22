<?php

declare(strict_types=1);

namespace Dashed\DashedSeo;

use Dashed\DashedSeo\Contracts\SeoFixer;

class SeoFixerRegistry
{
    /** @var array<string, SeoFixer> op check_key */
    protected array $byCheckKey = [];

    /** @var array<int, SeoFixer> */
    protected array $fixers = [];

    public function register(SeoFixer $fixer): void
    {
        foreach ($fixer->checkKeys() as $key) {
            if (isset($this->byCheckKey[$key])) {
                throw new \RuntimeException(sprintf(
                    "Controle '%s' heeft al een fixer (%s) en kan er geen tweede krijgen van %s.",
                    $key,
                    $this->byCheckKey[$key]::class,
                    $fixer::class,
                ));
            }

            $this->byCheckKey[$key] = $fixer;
        }

        $this->fixers[] = $fixer;
    }

    public function fixerFor(string $checkKey): ?SeoFixer
    {
        return $this->byCheckKey[$checkKey] ?? null;
    }

    /** @return array<int, SeoFixer> */
    public function fixers(): array
    {
        return $this->fixers;
    }

    /** @return array<int, string> Elke controle waar een fixer aan hangt. */
    public function checkKeys(): array
    {
        return array_keys($this->byCheckKey);
    }

    public function flush(): void
    {
        $this->byCheckKey = [];
        $this->fixers = [];
    }
}
