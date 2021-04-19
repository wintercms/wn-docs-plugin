<?php namespace Winter\Docs\Tests\Classes;

use TestCase;
use Winter\Docs\Classes\EventParser;

class EventParserTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        $this->eventDocBlock = file_get_contents(dirname(dirname(__DIR__)) . '/fixtures/event-doc-block.txt');
    }

    public function testGetEventDescription()
    {
        $expect =<<<EOS
Determines file changes between the specified build and the previous build.

Will return an array of added, modified and removed files.
EOS;
        $this->assertEquals(
            $expect,
            EventParser::getEventDescription($this->eventDocBlock)
        );
    }

    public function testGetEventTag()
    {
        $this->assertEquals(
            'my.test.event',
            EventParser::getEventTag($this->eventDocBlock)
        );
    }

    public function testGetSinceTag()
    {
        $this->assertEquals(
            'v1.1.2',
            EventParser::getSinceTag($this->eventDocBlock)
        );
    }

    public function testGetParamTag()
    {
        $params = EventParser::getParamTag($this->eventDocBlock);

        $this->assertEquals(
            'boolean $myParam This is my first param.',
            $params[0]
        );
        $this->assertEquals(
            "string|integer \$previous This is my second multi-line param,".PHP_EOL." it stretches on two lines.",
            $params[1]
        );
    }
}
