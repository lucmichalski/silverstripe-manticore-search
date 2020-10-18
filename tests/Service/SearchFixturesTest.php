<?php declare(strict_types = 1);

namespace Suilven\ManticoreSearch\Tests\Service;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Dev\SapphireTest;
use Suilven\FreeTextSearch\Container\Facet;
use Suilven\FreeTextSearch\Container\FacetCount;
use Suilven\FreeTextSearch\Helper\BulkIndexingHelper;
use Suilven\FreeTextSearch\Indexes;
use Suilven\FreeTextSearch\Types\SearchParamTypes;
use Suilven\ManticoreSearch\Helper\ReconfigureIndexesHelper;
use Suilven\ManticoreSearch\Service\Searcher;
use Suilven\ManticoreSearch\Tests\Models\FlickrAuthor;
use Suilven\ManticoreSearch\Tests\Models\FlickrPhoto;
use Suilven\ManticoreSearch\Tests\Models\FlickrSet;
use Suilven\ManticoreSearch\Tests\Models\FlickrTag;

class SearchFixturesTest extends SapphireTest
{
    protected static $fixture_file = ['tests/fixtures/sitetree.yml', 'tests/fixtures/flickrphotos.yml'];

    protected static $extra_dataobjects = [
        FlickrPhoto::class,
        FlickrTag::class,
        FlickrSet::class,
        FlickrAuthor::class,
    ];

    /** @var int */
    private static $pageID;

    public function setUp(): void
    {
        parent::setUp();

        /** @var \Suilven\FreeTextSearch\Indexes $indexesService */
        $indexesService = new Indexes();
        $indexesArray = $indexesService->getIndexes();
        $helper = new ReconfigureIndexesHelper();
        $helper->reconfigureIndexes($indexesArray);

        \error_log('INDEXING');
        $helper = new BulkIndexingHelper();
        $helper->bulkIndex('sitetree');
        $helper->bulkIndex('flickrphotos');
        $helper->bulkIndex('members');
        \error_log('/INDEXING');
    }


    public function testSimilar(): void
    {
        $page = $this->objFromFixture(SiteTree::class, 'sitetree_49');
        $searcher = new Searcher();
        $searcher->setIndexName('sitetree');
        $result = $searcher->searchForSimilar($page);

        // 1 is the original doc, 3 others contain sheep, 1 contains mussy an 1 contains shuttlecock
        $this->assertEquals(6, $result->getTotaNumberOfResults());
        $hits = $result->getRecords();
        $ids = [];
        foreach ($hits as $hit) {
            $ids[] = $hit->ID;
        }

        $this->assertEquals([49, 40, 45, 21, 36, 47], $ids);
    }


    public function testAndSearch(): void
    {
        $searcher = new Searcher();
        $searcher->setIndexName('sitetree');
        $searcher->setSearchType(SearchParamTypes::AND);
        $result = $searcher->search('sheep shuttlecock');
        $this->assertEquals(1, $result->getTotaNumberOfResults());
        $hits = $result->getRecords();
        $ids = [];
        foreach ($hits as $hit) {
            $ids[] = $hit->ID;
        }
        $this->assertEquals([49], $ids);
    }


    public function testORSearch(): void
    {
        $searcher = new Searcher();
        $searcher->setIndexName('sitetree');
        $searcher->setSearchType(SearchParamTypes::OR);
        $result = $searcher->search('sheep shuttlecock');
        $this->assertEquals(5, $result->getTotaNumberOfResults());
        $hits = $result->getRecords();
        $ids = [];
        foreach ($hits as $hit) {
            $ids[] = $hit->ID;
        }
        $this->assertEquals([49, 45, 21, 36, 47], $ids);
    }


    public function testFlickrFacetsEmptySearchTerm(): void
    {
        $searcher = new Searcher();
        $searcher->setIndexName('flickrphotos');
        $searcher->setSearchType(SearchParamTypes::AND);
        $searcher->setFacettedTokens(['ISO', 'Aperture', 'Orientation']);
        $result = $searcher->search('*');
        $this->assertEquals(50, $result->getTotaNumberOfResults());
        $hits = $result->getRecords();
        $ids = [];
        foreach ($hits as $hit) {
            $ids[] = $hit->ID;
        }
        $this->assertEquals([1,2,3,4,5,6,7,8,9,10,11,12,13,14,15], $ids);

        $facets = $result->getFacets();
        $this->assertEquals([
            1600 => 7,
            800 => 11,
            400 => 6,
            200 => 8,
            100 => 10,
            64 => 4,
            25 => 4,
        ], $facets[0]->asKeyValueArray());

        $this->assertEquals([
            32 => 1,
            27 => 9,
            22 => 6,
            16 => 7,
            11 => 7,
            8 => 5,
            5 => 6,
            4 => 3,
            2 => 6,
        ], $facets[1]->asKeyValueArray());

        $this->assertEquals([
            90 => 20,
            0 => 30,
        ], $facets[2]->asKeyValueArray());

        $this->checkSumDocumentCount($facets[0], 50);
        $this->checkSumDocumentCount($facets[1], 50);
        $this->checkSumDocumentCount($facets[2], 50);
    }


    public function testFlickrFacetsIncludeSearchTerm(): void
    {
        $searcher = new Searcher();
        $searcher->setIndexName('flickrphotos');
        $searcher->setSearchType(SearchParamTypes::AND);
        $searcher->setFacettedTokens(['ISO', 'Aperture', 'Orientation']);
        $result = $searcher->search('Tom');
        $this->assertEquals(13, $result->getTotaNumberOfResults());
        $hits = $result->getRecords();
        $ids = [];
        foreach ($hits as $hit) {
            $ids[] = $hit->ID;
        }

        $this->assertEquals([5,9,12,16,23,24,29,32,34,43,46,47,48], $ids);

        $facets = $result->getFacets();

        $this->assertEquals([
            1600 => 2,
            800 => 3,
            400 => 1,
            200 => 2,
            100 => 2,
            25 => 3,
        ], $facets[0]->asKeyValueArray());

        $this->assertEquals([
            27 => 1,
            16 => 3,
            11 => 2,
            8 => 3,
            5 => 1,
            2 => 3,
        ], $facets[1]->asKeyValueArray());

        $this->assertEquals([
            90 => 5,
            0 => 8,
        ], $facets[2]->asKeyValueArray());

        $this->checkSumDocumentCount($facets[0], 13);
        $this->checkSumDocumentCount($facets[1], 13);
        $this->checkSumDocumentCount($facets[2], 13);
    }


    /**
     * @param Facet $facet
     * @param int $expectedCount
     */
    private function checkSumDocumentCount($facet, $expectedCount)
    {
        $sum = 0;
        $kvArray = $facet->asKeyValueArray();

        /** @var FacetCount $key */
        foreach(array_keys($kvArray) as $key) {
            $sum = $sum + $kvArray[$key];
        }

        $this->assertEquals($expectedCount, $sum);
    }
}
