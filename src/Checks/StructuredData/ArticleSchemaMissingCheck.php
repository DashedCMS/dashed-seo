<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Checks\StructuredData;

use Dashed\DashedSeo\Checks\BaseCheck;
use Dashed\DashedSeo\Models\SiteAudit;

class ArticleSchemaMissingCheck extends BaseCheck
{
    use StructuredDataPages;
    use ExpectsSchemaForModel;

    public function key(): string
    {
        return 'article_schema_missing';
    }

    public function run(SiteAudit $audit): iterable
    {
        yield from $this->missingForModels($audit, ['article']);
    }
}
