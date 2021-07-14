<?php namespace Winter\Docs\Tests\Classes;

use TestCase;
use Winter\Docs\Classes\ApiParser;
use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;

class ApiParserTest extends TestCase
{
    use ArraySubsetAsserts;

    protected $apiParser;

    public function setUp(): void
    {
        parent::setUp();

        $this->apiParser = new ApiParser(dirname(dirname(__DIR__)) . '/fixtures/api');
    }

    public function testGetPaths()
    {
        $this->assertCount(5, $this->apiParser->getPaths());
        $this->assertArraySubset([
            [
                'fileName' => 'database/Mysql.php',
            ],
            [
                'fileName' => 'database/BaseDb.php',
            ],
            [
                'fileName' => 'contracts/Db.php',
            ],
            [
                'fileName' => 'utilities/StringUtility.php',
            ],
            [
                'fileName' => 'utilities/NumberUtility.php',
            ],
        ], $this->apiParser->getPaths(), true);
    }

    public function testParse()
    {
        $this->apiParser->parse();

        print_r($this->apiParser->getClasses());
        die();
    }
}
