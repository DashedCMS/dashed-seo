<?php

declare(strict_types=1);

namespace Dashed\DashedSeo;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class DashedSeoServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('dashed-seo')
            ->hasConfigFile();
    }
}
