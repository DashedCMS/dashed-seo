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
            ->hasConfigFile(['dashed-seo', 'dashed-seo-checks'])
            ->hasMigrations([
                '2026_08_13_000001_create_site_audits_table',
                '2026_08_13_000002_create_site_audit_pages_table',
                '2026_08_13_000003_create_site_audit_links_table',
                '2026_08_13_000004_create_site_audit_findings_table',
                '2026_08_13_000005_create_site_audit_check_results_table',
                '2026_08_13_000006_create_site_audit_assets_table',
            ])
            ->runsMigrations();
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(SeoCheckRegistry::class);
    }
}
