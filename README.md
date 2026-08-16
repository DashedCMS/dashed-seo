# dashed-seo

Technische sitescan voor Dashed CMS: crawlt een site, beoordeelt hem op 55
controles in negen secties, en zet de uitkomst in de beheeromgeving.

## Draaien

    php artisan dashed:site-audit              # een scan voor de actieve site
    php artisan dashed:site-audit site --sync  # in de voorgrond, voor ontwikkelaars
    php artisan dashed:run-site-audits         # de wekelijkse ronde, alle sites met de schakelaar aan
    php artisan dashed:run-site-audits --sweep-only  # alleen vastgelopen scans opruimen
    php artisan dashed:prune-site-audits       # oude scans uitkleden

De planner draait de veegbeurt elk uur, de wekelijkse ronde maandagochtend en
het uitkleden daarna.

## Instellen

Systeem > Instellingen > Sitescan. Per site een basis-URL, een paginalimiet,
een diepte-limiet, een vertraging tussen verzoeken, de wekelijkse schakelaar en
de negen drempelwaarden. De basis-URL is verplicht: een site kent in het CMS
geen eigen adres.

De ingestelde waarden worden op elke scan vastgelegd en tijdens het draaien
teruggezet op de configuratie, zodat een oud rapport uitlegbaar blijft nadat
iemand de drempels heeft verschoven.

## Schermen

Systeem > Sitescans. Het overzicht met de knop om nu te scannen, het rapport
per scan, en de tabel per pagina.

## Wat er niet in zit

Geen backlinks, geen indexatiecijfers uit zoekmachines, geen Core Web Vitals
uit een echte browser, geen PDF-export, geen samenvattingsmail en geen
vergelijking met andere websites.
