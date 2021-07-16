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
        $this->assertCount(7, $this->apiParser->getPaths());

        $fixturePath = dirname(dirname(__DIR__)) . '/fixtures/';
        $filenames = $this->apiParser->getPaths();
        sort($filenames, SORT_NATURAL);

        $this->assertEquals([
            $fixturePath . 'api/contracts/Db.php',
            $fixturePath . 'api/database/BaseDb.php',
            $fixturePath . 'api/database/Mysql.php',
            $fixturePath . 'api/database/Mysqli.php',
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
        $this->assertCount(7, $classes);

        $this->assertEquals([
            'Docs\\Api\\Contracts\\Db',
            'Docs\\Api\\Database\\BaseDb',
            'Docs\\Api\\Database\\Mysql',
            'Docs\\Api\\Database\\Mysqli',
            'Docs\\Utilities\\Traits\\IsUtility',
            'Docs\\Utilities\\Utilities\\NumberUtility',
            'Docs\\Utilities\\Utilities\\StringUtility',
        ], $classNames);

        // --- Inspect the Mysql class

        // This class is a normal, non-abstract, non-final extends the BaseDb class, and implements the Db contract.
        $this->assertEquals('Mysql', $classes['Docs\\Api\\Database\\Mysql']['name']);
        $this->assertEquals('class', $classes['Docs\\Api\\Database\\Mysql']['type']);
        $this->assertEquals(false, $classes['Docs\\Api\\Database\\Mysql']['abstract']);
        $this->assertEquals(false, $classes['Docs\\Api\\Database\\Mysql']['final']);
        $this->assertEquals('Docs\\Api\\Database\\BaseDb', $classes['Docs\\Api\\Database\\Mysql']['extends']);
        $this->assertEquals('Docs\\Api\\Contracts\\Db', $classes['Docs\\Api\\Database\\Mysql']['implements'][0]);

        // It should have 1 property, 2 constants and 3 methods locally, and 1 property and 1 method inherited.
        $this->assertCount(1, $classes['Docs\\Api\\Database\\Mysql']['properties']);
        $this->assertCount(2, $classes['Docs\\Api\\Database\\Mysql']['constants']);
        $this->assertCount(3, $classes['Docs\\Api\\Database\\Mysql']['methods']);
        $this->assertCount(1, $classes['Docs\\Api\\Database\\Mysql']['inherited']['properties']);
        $this->assertCount(0, $classes['Docs\\Api\\Database\\Mysql']['inherited']['constants']);
        $this->assertCount(1, $classes['Docs\\Api\\Database\\Mysql']['inherited']['methods']);

        // "queryCache" property
        $this->assertEquals('queryCache', $classes['Docs\\Api\\Database\\Mysql']['properties'][0]['name']);
        $this->assertEquals('array', $classes['Docs\\Api\\Database\\Mysql']['properties'][0]['type']);
        $this->assertEquals('<p>Query cache.</p>', $classes['Docs\\Api\\Database\\Mysql']['properties'][0]['docs']['summary']);

        // "queryLog" property (inherited)
        $this->assertEquals('queryLog', $classes['Docs\\Api\\Database\\Mysql']['inherited']['properties'][0]['property']['name']);
        // - The docs say it is a string, but it is an array based on the default value
        $this->assertEquals('array', $classes['Docs\\Api\\Database\\Mysql']['inherited']['properties'][0]['property']['type']);
        $this->assertEquals('<p>Mismatched type in docs</p>', $classes['Docs\\Api\\Database\\Mysql']['inherited']['properties'][0]['property']['docs']['summary']);
        $this->assertEquals('string', $classes['Docs\\Api\\Database\\Mysql']['inherited']['properties'][0]['property']['docs']['var']['type']);

        // "MYSQL_SAFE" constant
        $this->assertEquals('MYSQL_SAFE', $classes['Docs\\Api\\Database\\Mysql']['constants'][0]['name']);
        $this->assertEquals(1, $classes['Docs\\Api\\Database\\Mysql']['constants'][0]['value']);
        $this->assertEquals('integer', $classes['Docs\\Api\\Database\\Mysql']['constants'][0]['type']);
        $this->assertEquals('<p>Whether this MySQL query should be treated as safe</p>', $classes['Docs\\Api\\Database\\Mysql']['constants'][0]['docs']['summary']);

        // "MYSQL_STMT" constant
        $this->assertEquals('MYSQL_STMT', $classes['Docs\\Api\\Database\\Mysql']['constants'][1]['name']);
        $this->assertEquals('"stmt"', $classes['Docs\\Api\\Database\\Mysql']['constants'][1]['value']);
        $this->assertEquals('string', $classes['Docs\\Api\\Database\\Mysql']['constants'][1]['type']);
        $this->assertEquals('<p>Run query in a statement</p>', $classes['Docs\\Api\\Database\\Mysql']['constants'][1]['docs']['summary']);

        // Constructor method
        $this->assertEquals('__construct', $classes['Docs\\Api\\Database\\Mysql']['methods'][0]['name']);

        // --- Inspect the Mysqli class

        // This class is a normal, non-abstract, non-final class that extends the Mysql class only.
        $this->assertEquals('Mysql', $classes['Docs\\Api\\Database\\Mysql']['name']);
        $this->assertEquals('class', $classes['Docs\\Api\\Database\\Mysql']['type']);
        $this->assertEquals(false, $classes['Docs\\Api\\Database\\Mysql']['abstract']);
        $this->assertEquals(false, $classes['Docs\\Api\\Database\\Mysql']['final']);
        $this->assertEquals('Docs\\Api\\Database\\BaseDb', $classes['Docs\\Api\\Database\\Mysql']['extends']);
        $this->assertCount(0, $classes['Docs\\Api\\Database\\Mysqli']['implements']);

        // It should have no properties, constants and methods locally, and 2 properties, 2 constants and 4 methods inherited.
        $this->assertCount(0, $classes['Docs\\Api\\Database\\Mysqli']['properties']);
        $this->assertCount(0, $classes['Docs\\Api\\Database\\Mysqli']['constants']);
        $this->assertCount(0, $classes['Docs\\Api\\Database\\Mysqli']['methods']);
        $this->assertCount(2, $classes['Docs\\Api\\Database\\Mysqli']['inherited']['properties']);
        $this->assertCount(2, $classes['Docs\\Api\\Database\\Mysqli']['inherited']['constants']);
        $this->assertCount(4, $classes['Docs\\Api\\Database\\Mysqli']['inherited']['methods']);

        print_r($classes);
        die();
    }
}
