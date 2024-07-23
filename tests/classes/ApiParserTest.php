<?php namespace Winter\Docs\Tests\Classes;

use System\Tests\Bootstrap\TestCase;
use Winter\Docs\Classes\PHPApiParser;

/**
 * @covers \Winter\Docs\Classes\PHPApiParser
 * @testdox The API Parser class (\Winter\Docs\Classes\PHPApiParser)
 */
class ApiParserTest extends TestCase
{
    protected $apiParser;

    public function setUp(): void
    {
        parent::setUp();

        $this->apiParser = new PHPApiParser(dirname(__DIR__) . '/fixtures', [
            'api',
            'utilities',
        ]);
    }

    /**
     * @covers \Winter\Docs\Classes\PHPApiParser::getPaths()
     * @testdox can get the paths of all PHP files in a given codebase.
     */
    public function testGetPaths()
    {
        $this->assertCount(7, $this->apiParser->getPaths());

        $fixturePath = dirname(__DIR__) . '/fixtures/';
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

    /**
     * @covers \Winter\Docs\Classes\PHPApiParser::parse()
     * @testdox can parse all PHP files and present the schema in an array.
     */
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
        $this->assertEquals('Docs\\Api\\Database\\BaseDb', $classes['Docs\\Api\\Database\\Mysql']['extends']['class']);
        $this->assertEquals('Docs\\Api\\Contracts\\Db', $classes['Docs\\Api\\Database\\Mysql']['implements'][0]['class']);

        // It should have 2 properties, 2 constants and 3 methods locally, and 1 method inherited. It should also have one event
        $this->assertCount(2, $classes['Docs\\Api\\Database\\Mysql']['properties']);
        $this->assertCount(2, $classes['Docs\\Api\\Database\\Mysql']['constants']);
        $this->assertCount(4, $classes['Docs\\Api\\Database\\Mysql']['methods']);
        // @TODO: Change these to find the inherited method that should be found in the main methods definition array
        // $this->assertArrayNotHasKey('properties', $classes['Docs\\Api\\Database\\Mysql']['inherited']);
        // $this->assertArrayNotHasKey('constants', $classes['Docs\\Api\\Database\\Mysql']['inherited']);
        // $this->assertCount(1, $classes['Docs\\Api\\Database\\Mysql']['inherited']['methods']);
        $this->assertCount(1, $classes['Docs\\Api\\Database\\Mysql']['events']);

        // "queryLog" property
        $this->assertEquals('queryLog', $classes['Docs\\Api\\Database\\Mysql']['properties'][0]['name']);
        $this->assertEquals('scalar', $classes['Docs\\Api\\Database\\Mysql']['properties'][0]['type']['definition']);
        $this->assertEquals('mixed', $classes['Docs\\Api\\Database\\Mysql']['properties'][0]['type']['type']);
        $this->assertEquals('<p>Mismatched type in docs</p>', $classes['Docs\\Api\\Database\\Mysql']['properties'][0]['docs']['summary']);
        $this->assertEquals(33, $classes['Docs\\Api\\Database\\Mysql']['properties'][0]['line']);

        // "MYSQL_SAFE" constant
        $this->assertEquals('MYSQL_SAFE', $classes['Docs\\Api\\Database\\Mysql']['constants'][0]['name']);
        $this->assertEquals(1, $classes['Docs\\Api\\Database\\Mysql']['constants'][0]['value']);
        $this->assertEquals('scalar', $classes['Docs\\Api\\Database\\Mysql']['constants'][0]['type']['definition']);
        $this->assertEquals('integer', $classes['Docs\\Api\\Database\\Mysql']['constants'][0]['type']['type']);
        $this->assertEquals('<p>Whether this MySQL query should be treated as safe</p>', $classes['Docs\\Api\\Database\\Mysql']['constants'][0]['docs']['summary']);
        $this->assertEquals(20, $classes['Docs\\Api\\Database\\Mysql']['constants'][0]['line']);

        // "MYSQL_STMT" constant
        $this->assertEquals('MYSQL_STMT', $classes['Docs\\Api\\Database\\Mysql']['constants'][1]['name']);
        $this->assertEquals('"stmt"', $classes['Docs\\Api\\Database\\Mysql']['constants'][1]['value']);
        $this->assertEquals('scalar', $classes['Docs\\Api\\Database\\Mysql']['constants'][1]['type']['definition']);
        $this->assertEquals('string', $classes['Docs\\Api\\Database\\Mysql']['constants'][1]['type']['type']);
        $this->assertEquals('<p>Run query in a statement</p>', $classes['Docs\\Api\\Database\\Mysql']['constants'][1]['docs']['summary']);
        $this->assertEquals(23, $classes['Docs\\Api\\Database\\Mysql']['constants'][1]['line']);

        // Constructor method
        $this->assertEquals('__construct', $classes['Docs\\Api\\Database\\Mysql']['methods'][0]['name']);
        $this->assertFalse($classes['Docs\\Api\\Database\\Mysql']['methods'][0]['static']);
        $this->assertFalse($classes['Docs\\Api\\Database\\Mysql']['methods'][0]['final']);
        $this->assertEquals(['type' => ['definition' => 'scalar', 'type' => 'mixed'], 'summary' => null], $classes['Docs\\Api\\Database\\Mysql']['methods'][0]['returns']);
        $this->assertEquals('public', $classes['Docs\\Api\\Database\\Mysql']['methods'][0]['visibility']);
        $this->assertCount(0, $classes['Docs\\Api\\Database\\Mysql']['methods'][0]['params']);
        $this->assertEquals([38, 41], $classes['Docs\\Api\\Database\\Mysql']['methods'][0]['lines']);

        // Query method
        $this->assertEquals('query', $classes['Docs\\Api\\Database\\Mysql']['methods'][2]['name']);
        $this->assertFalse($classes['Docs\\Api\\Database\\Mysql']['methods'][2]['static']);
        $this->assertFalse($classes['Docs\\Api\\Database\\Mysql']['methods'][2]['final']);
        $this->assertEquals(['type' => ['definition' => 'scalar', 'type' => 'array'], 'summary' => '<p>An array of results</p>'], $classes['Docs\\Api\\Database\\Mysql']['methods'][2]['returns']);
        $this->assertEquals('public', $classes['Docs\\Api\\Database\\Mysql']['methods'][2]['visibility']);
        $this->assertEquals([46, 59], $classes['Docs\\Api\\Database\\Mysql']['methods'][2]['lines']);

        // Check query method params
        $this->assertCount(2, $classes['Docs\\Api\\Database\\Mysql']['methods'][2]['params']);
        $this->assertEquals('statement', $classes['Docs\\Api\\Database\\Mysql']['methods'][2]['params'][0]['name']);
        $this->assertEquals(['definition' => 'scalar', 'type' => 'string'], $classes['Docs\\Api\\Database\\Mysql']['methods'][2]['params'][0]['type']);
        $this->assertEquals('<p>Property type conflict</p>', $classes['Docs\\Api\\Database\\Mysql']['methods'][2]['params'][0]['summary']);
        $this->assertEquals('params', $classes['Docs\\Api\\Database\\Mysql']['methods'][2]['params'][1]['name']);
        $this->assertEquals(['definition' => 'scalar', 'type' => 'array'], $classes['Docs\\Api\\Database\\Mysql']['methods'][2]['params'][1]['type']);
        $this->assertNull($classes['Docs\\Api\\Database\\Mysql']['methods'][2]['params'][1]['summary']);

        // --- Inspect the Mysqli class

        // This class is a normal, non-abstract, non-final class that extends the Mysql class only.
        $this->assertEquals('Mysql', $classes['Docs\\Api\\Database\\Mysql']['name']);
        $this->assertEquals('class', $classes['Docs\\Api\\Database\\Mysql']['type']);
        $this->assertEquals(false, $classes['Docs\\Api\\Database\\Mysql']['abstract']);
        $this->assertEquals(false, $classes['Docs\\Api\\Database\\Mysql']['final']);
        $this->assertEquals('Docs\\Api\\Database\\BaseDb', $classes['Docs\\Api\\Database\\Mysql']['extends']['class']);
        $this->assertCount(1, $classes['Docs\\Api\\Database\\Mysqli']['implements']);

        // It should have 1 property, but no constants and methods locally, and 2 properties, 2 constants and 4 methods inherited.
        $this->assertCount(2, $classes['Docs\\Api\\Database\\Mysqli']['properties']);
        $this->assertCount(2, $classes['Docs\\Api\\Database\\Mysqli']['constants']);
        $this->assertCount(4, $classes['Docs\\Api\\Database\\Mysqli']['methods']);
        // @TODO: Change these to find the inherited definitions in the main definitions above
        // $this->assertCount(1, $classes['Docs\\Api\\Database\\Mysqli']['inherited']['properties']);
        // $this->assertCount(2, $classes['Docs\\Api\\Database\\Mysqli']['inherited']['constants']);
        // $this->assertCount(4, $classes['Docs\\Api\\Database\\Mysqli']['inherited']['methods']);
    }
}
