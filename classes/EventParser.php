<?php namespace Winter\Docs\Classes;

use File;

class EventParser {

    protected static $docBlockFactory = null;

    protected static function getDocBlockFactory()
    {
        if (empty(static::$docBlockFactory)) {
            static::$docBlockFactory = \phpDocumentor\Reflection\DocBlockFactory::createInstance();
        }

        return static::$docBlockFactory;
    }

    public static function getPathEvents($path, $prefix = null)
    {
        $events = [];

        foreach (File::allFiles($path) as $file) {
            static::getFileEvents($events, $file, $prefix);
        }

        return $events;
    }

    public static function getFileEvents(&$events, $file, $prefix = null)
    {
        $segments = explode('/', $prefix . $file->getRelativePathName());
        $trigger = implode('\\', array_map('ucfirst', $segments));

        $data = file_get_contents($file->getPathName());

        if (!preg_match_all('| +/\*\*\s+\* @event.+\*/|Us', $data, $match)) {
            return;
        }

        $factory = static::getDocBlockFactory();

        foreach ($match[0] as $doc) {
            $docblock = $factory->create(static::fixDocBlock($doc));

            $eventName = $docblock->getTagsByName('event')[0]->getDescription()->render();
            $event = [
                'triggeredIn' => $trigger,
                'eventName' => $eventName,
                'summary' => $docblock->getSummary(),
                'description' => $docblock->getDescription()->render(),
            ];

            array_set($events, $eventName, $event);
        }
    }

    protected static function fixDocBlock($doc)
    {
        $parts = explode("\n", $doc);

        // extract @event line
        $event = array_splice($parts, 1, 1);

        // insert @event before closing comment
        array_splice($parts, count($parts)-1, 0, (array)$event);

        return implode("\n", $parts);
    }
}
