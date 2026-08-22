<?php

declare(strict_types=1);

namespace Dashed\DashedSeo;

use Illuminate\Support\Facades\Event;
use Spatie\LaravelPackageTools\Package;
use Dashed\DashedSeo\Events\SiteAuditCompleted;
use Dashed\DashedSeo\Listeners\ProposeFixesAfterAudit;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Dashed\DashedSeo\Filament\Pages\Settings\DashedSeoSettingsPage;

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
                '2026_08_17_000009_add_from_cms_to_site_audit_pages_table',
                '2026_08_22_000010_create_site_audit_fixes_table',
                '2026_08_23_000011_add_finding_id_to_site_audit_fixes_table',
            ])
            ->runsMigrations()
            ->hasViews()
            ->hasCommands([
                \Dashed\DashedSeo\Commands\RunSiteAudit::class,
                \Dashed\DashedSeo\Commands\RunSiteAudits::class,
                \Dashed\DashedSeo\Commands\PruneSiteAudits::class,
            ]);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(SeoCheckRegistry::class);
        $this->app->singleton(SeoFixerRegistry::class);
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

            // Elke nacht kijken wie er aan de beurt is. De frequentie staat per
            // site in de instellingen (dagelijks, wekelijks, maandelijks), dus
            // een vaste dag in de week hier zou dagelijks scannen onmogelijk
            // maken.
            $schedule->command('dashed:run-site-audits')
                ->dailyAt('03:00')
                ->withoutOverlapping();

            $schedule->command('dashed:prune-site-audits')
                ->weeklyOn(1, '04:30')
                ->withoutOverlapping();
        });

        cms()->builder('plugins', array_merge(cms()->builder('plugins') ?: [], [
            new DashedSeoPlugin(),
        ]));

        cms()->registerSettingsPage(
            DashedSeoSettingsPage::class,
            'Sitescan',
            'magnifying-glass-circle',
            'Basis-URL, limieten en drempelwaarden voor de technische sitescan'
        );

        $registry = $this->app->make(SeoCheckRegistry::class);

        foreach ([
            // Crawlen & Indexatie
            \Dashed\DashedSeo\Checks\Crawling\Status4xxCheck::class,
            \Dashed\DashedSeo\Checks\Crawling\Status5xxCheck::class,
            \Dashed\DashedSeo\Checks\Crawling\UnreachableCheck::class,
            \Dashed\DashedSeo\Checks\Crawling\BlockedByNoindexCheck::class,
            \Dashed\DashedSeo\Checks\Crawling\BlockedByNofollowCheck::class,
            \Dashed\DashedSeo\Checks\Crawling\BlockedByRobotsCheck::class,
            \Dashed\DashedSeo\Checks\Crawling\OnlyFoundViaCmsCheck::class,

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

        $fixers = $this->app->make(SeoFixerRegistry::class);

        foreach ([
            \Dashed\DashedSeo\Fixes\Sitemap\RebuildSitemapFixer::class,
            \Dashed\DashedSeo\Fixes\Sitemap\SitemapInRobotsFixer::class,
            \Dashed\DashedSeo\Fixes\Redirects\PermanentRedirectFixer::class,
            \Dashed\DashedSeo\Fixes\Redirects\FlattenRedirectChainFixer::class,
            \Dashed\DashedSeo\Fixes\Redirects\RemoveRedirectLoopFixer::class,
            \Dashed\DashedSeo\Fixes\Links\RelinkToFinalUrlFixer::class,
            \Dashed\DashedSeo\Fixes\Links\RelinkBrokenInternalFixer::class,
            \Dashed\DashedSeo\Fixes\Links\RemoveInternalNofollowFixer::class,
            \Dashed\DashedSeo\Fixes\Links\UnlinkBrokenExternalFixer::class,
            \Dashed\DashedSeo\Fixes\Crawling\MissingPageRedirectFixer::class,
            \Dashed\DashedSeo\Fixes\Meta\MetaTitleFixer::class,
            \Dashed\DashedSeo\Fixes\Meta\MetaDescriptionFixer::class,
            \Dashed\DashedSeo\Fixes\Content\AltTextFixer::class,
            \Dashed\DashedSeo\Fixes\Links\AnchorTextFixer::class,
        ] as $fixer) {
            $fixers->register(new $fixer());
        }

        Event::listen(SiteAuditCompleted::class, ProposeFixesAfterAudit::class);
    }
}
