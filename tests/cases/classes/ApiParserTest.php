<?php namespace Winter\Docs\Tests\Classes;

use TestCase;
use Winter\Docs\Classes\ApiParser;

class ApiParserTest extends TestCase
{
    protected $apiParser;

    public function setUp(): void
    {
        parent::setUp();

        $this->apiParser = new ApiParser([
            dirname(dirname(__DIR__)) . '/fixtures/api',
            dirname(dirname(__DIR__)) . '/fixtures/utilities'
        ]);
    }

    public function testGetPaths()
    {
        $this->assertCount(6, $this->apiParser->getPaths());

        $fixturePath = dirname(dirname(__DIR__)) . '/fixtures/';
        $filenames = $this->apiParser->getPaths();
        sort($filenames, SORT_NATURAL);

        $this->assertEquals([
            $fixturePath . 'api/contracts/Db.php',
            $fixturePath . 'api/database/BaseDb.php',
            $fixturePath . 'api/database/Mysql.php',
            $fixturePath . 'utilities/traits/IsUtility.php',
            $fixturePath . 'utilities/utilities/NumberUtility.php',
            $fixturePath . 'utilities/utilities/StringUtility.php',
        ], $filenames);
    }

    public function testParse()
    {
        $this->apiParser->parse();

        // Ensure we have 4 namespaces
        $namespaces = $this->apiParser->getNamespaces();
        sort($namespaces, SORT_NATURAL);
        $this->assertCount(4, $namespaces);

        $this->assertEquals([
            'Docs\\Api\\Contracts',
            'Docs\\Api\\Database',
            'Docs\\Utilities\\Traits',
            'Docs\\Utilities\\Utilities'
        ], $namespaces);

        // Ensure we have 6 classes
        $classes = $this->apiParser->getClasses();
        $classNames = array_keys($classes);
        sort($classNames, SORT_NATURAL);
        $this->assertCount(6, $classes);

        $this->assertEquals([
            'Docs\\Api\\Contracts\\Db',
            'Docs\\Api\\Database\\BaseDb',
            'Docs\\Api\\Database\\Mysql',
            'Docs\\Utilities\\Traits\\IsUtility',
            'Docs\\Utilities\\Utilities\\NumberUtility',
            'Docs\\Utilities\\Utilities\\StringUtility',
        ], $classNames);

        // --- Inspect the Mysql class

        // It should have 1 property, 2 constants and 3 methods locally, and 1 property and 1 method inherited.
        $this->assertCount(1, $classes['Docs\\Api\\Database\\Mysql']['properties']);
        $this->assertCount(2, $classes['Docs\\Api\\Database\\Mysql']['constants']);
        $this->assertCount(3, $classes['Docs\\Api\\Database\\Mysql']['methods']);
        $this->assertCount(1, $classes['Docs\\Api\\Database\\Mysql']['inherited']['properties']);
        $this->assertCount(0, $classes['Docs\\Api\\Database\\Mysql']['inherited']['constants']);
        $this->assertCount(1, $classes['Docs\\Api\\Database\\Mysql']['inherited']['methods']);
    }
}
