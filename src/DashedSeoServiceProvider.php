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
                '2026_08_13_000007_add_icons_to_site_audit_pages_table',
                '2026_08_14_000008_add_pages_blocked_to_site_audits_table',
            ])
            ->runsMigrations()
            ->hasCommands([
                \Dashed\DashedSeo\Commands\RunSiteAudit::class,
                \Dashed\DashedSeo\Commands\RunSiteAudits::class,
                \Dashed\DashedSeo\Commands\PruneSiteAudits::class,
            ]);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(SeoCheckRegistry::class);
    }

    public function packageBooted(): void
    {
        $this->app->booted(function (): void {
            $schedule = app(\Illuminate\Console\Scheduling\Schedule::class);

            // Vegen elk uur, niet wekelijks: een vastgelopen handmatige scan
            // zou de site anders tot een week lang op slot houden.
            $schedule->command('dashed:run-site-audits --sweep-only')
                ->hourly()
                ->withoutOverlapping();

            $schedule->command('dashed:run-site-audits')
                ->weeklyOn(1, '03:00')
                ->withoutOverlapping();

            $schedule->command('dashed:prune-site-audits')
                ->weeklyOn(1, '04:30')
                ->withoutOverlapping();
        });

        $registry = $this->app->make(SeoCheckRegistry::class);

        foreach ([
            // Crawlen & Indexatie
            \Dashed\DashedSeo\Checks\Crawling\Status4xxCheck::class,
            \Dashed\DashedSeo\Checks\Crawling\Status5xxCheck::class,
            \Dashed\DashedSeo\Checks\Crawling\UnreachableCheck::class,
            \Dashed\DashedSeo\Checks\Crawling\BlockedByNoindexCheck::class,
            \Dashed\DashedSeo\Checks\Crawling\BlockedByNofollowCheck::class,
            \Dashed\DashedSeo\Checks\Crawling\BlockedByRobotsCheck::class,

            // Redirects
            \Dashed\DashedSeo\Checks\Redirects\Status3xxCheck::class,
            \Dashed\DashedSeo\Checks\Redirects\TemporaryRedirectCheck::class,
            \Dashed\DashedSeo\Checks\Redirects\LongRedirectChainCheck::class,
            \Dashed\DashedSeo\Checks\Redirects\RedirectLoopCheck::class,

            // Sitemap
            \Dashed\DashedSeo\Checks\Sitemap\SitemapRedirectCheck::class,
            \Dashed\DashedSeo\Checks\Sitemap\SitemapNoindexCheck::class,
            \Dashed\DashedSeo\Checks\Sitemap\Sitemap4xxCheck::class,
            \Dashed\DashedSeo\Checks\Sitemap\SitemapNotInRobotsCheck::class,
            \Dashed\DashedSeo\Checks\Sitemap\MissingFromSitemapCheck::class,

            // Meta Tags
            \Dashed\DashedSeo\Checks\MetaTags\TitleMissingCheck::class,
            \Dashed\DashedSeo\Checks\MetaTags\DuplicateTitleCheck::class,
            \Dashed\DashedSeo\Checks\MetaTags\TitleTooLongCheck::class,
            \Dashed\DashedSeo\Checks\MetaTags\TitleTooShortCheck::class,
            \Dashed\DashedSeo\Checks\MetaTags\DescriptionMissingCheck::class,
            \Dashed\DashedSeo\Checks\MetaTags\DuplicateDescriptionCheck::class,
            \Dashed\DashedSeo\Checks\MetaTags\DescriptionTooLongCheck::class,
            \Dashed\DashedSeo\Checks\MetaTags\CanonicalMissingCheck::class,
            \Dashed\DashedSeo\Checks\MetaTags\CanonicalPointsElsewhereCheck::class,
            \Dashed\DashedSeo\Checks\MetaTags\CanonicalToNonIndexableCheck::class,

            // Content
            \Dashed\DashedSeo\Checks\Content\H1MissingCheck::class,
            \Dashed\DashedSeo\Checks\Content\H1EmptyCheck::class,
            \Dashed\DashedSeo\Checks\Content\MultipleH1Check::class,
            \Dashed\DashedSeo\Checks\Content\TitleEqualsH1Check::class,
            \Dashed\DashedSeo\Checks\Content\MissingAltCheck::class,
            \Dashed\DashedSeo\Checks\Content\ThinContentCheck::class,
            \Dashed\DashedSeo\Checks\Content\BrokenImageCheck::class,

            // Lokalisatie
            \Dashed\DashedSeo\Checks\Localisation\HreflangLangMismatchCheck::class,
            \Dashed\DashedSeo\Checks\Localisation\MultipleHreflangForPageCheck::class,
            \Dashed\DashedSeo\Checks\Localisation\HreflangBrokenTargetCheck::class,
            \Dashed\DashedSeo\Checks\Localisation\HreflangMissingReturnCheck::class,
            \Dashed\DashedSeo\Checks\Localisation\HreflangNoSelfReferenceCheck::class,
            \Dashed\DashedSeo\Checks\Localisation\HreflangInvalidCodeCheck::class,

            // Snelheid
            \Dashed\DashedSeo\Checks\Speed\SlowResponseCheck::class,
            \Dashed\DashedSeo\Checks\Speed\HtmlTooLargeCheck::class,
            \Dashed\DashedSeo\Checks\Speed\NoCompressionCheck::class,

            // CSS & JS
            \Dashed\DashedSeo\Checks\Assets\CssTooLargeCheck::class,
            \Dashed\DashedSeo\Checks\Assets\CssNotCompressedCheck::class,
            \Dashed\DashedSeo\Checks\Assets\JsTooLargeCheck::class,
            \Dashed\DashedSeo\Checks\Assets\MixedContentCheck::class,

            // Links
            \Dashed\DashedSeo\Checks\Links\NoIncomingLinksCheck::class,
            \Dashed\DashedSeo\Checks\Links\SingleIncomingLinkCheck::class,
            \Dashed\DashedSeo\Checks\Links\InternalLinkToRedirectCheck::class,
            \Dashed\DashedSeo\Checks\Links\InternalLinkTo4xxCheck::class,
            \Dashed\DashedSeo\Checks\Links\InternalNofollowLinkCheck::class,
            \Dashed\DashedSeo\Checks\Links\InternalLinkWithoutAnchorCheck::class,
            \Dashed\DashedSeo\Checks\Links\ExternalNofollowLinkCheck::class,
            \Dashed\DashedSeo\Checks\Links\ExternalLinkWithoutAnchorCheck::class,
            \Dashed\DashedSeo\Checks\Links\BrokenExternalLinkCheck::class,
            \Dashed\DashedSeo\Checks\Links\FaviconMissingCheck::class,
        ] as $class) {
            $registry->register(new $class());
        }
    }
}
