<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Checks\StructuredData;

use Dashed\DashedSeo\Checks\BaseCheck;
use Dashed\DashedSeo\Models\SiteAudit;

/**
 * Het CMS-model achter de pagina (model_type, uit ModelResolver) bepaalt
 * wat er hoort te staan; de verwachting per model staat in
 * dashed-seo.structured_data.expected. Op klassenaam vergeleken, zodat
 * dashed-seo geen afhankelijkheid op de webshop krijgt.
 */
class ProductSchemaMissingCheck extends BaseCheck
{
    use StructuredDataPages;
    use ExpectsSchemaForModel;

    public function key(): string
    {
        return 'product_schema_missing';
    }

    public function run(SiteAudit $audit): iterable
    {
        yield from $this->missingForModels($audit, ['product', 'productgroup']);
    }
}
