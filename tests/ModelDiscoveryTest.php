<?php

namespace ValerianMemsk\SemanticSearch\Tests;

use ValerianMemsk\SemanticSearch\Contracts\VectorDriverContract;
use ValerianMemsk\SemanticSearch\Embedders\EmbedderManager;
use ValerianMemsk\SemanticSearch\Services\SemanticSearchService;

class ModelDiscoveryTest extends TestCase
{
    public function test_extracts_fqcn_when_class_constant_precedes_declaration(): void
    {
        $service = new SemanticSearchService(
            $this->createStub(EmbedderManager::class),
            $this->createStub(VectorDriverContract::class)
        );

        $path = sys_get_temp_dir().'/semsearch_'.uniqid().'.php';
        file_put_contents($path, <<<'PHP'
<?php

namespace App\Models;

#[\Attribute(Some\Other::class)]
class Product extends \Illuminate\Database\Eloquent\Model
{
}
PHP);

        $this->assertSame('App\Models\Product', $service->extractFullyQualifiedClassName($path));

        unlink($path);
    }
}
