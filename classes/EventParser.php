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

    public static function getEvents($path, $prefix = null)
    {
        $events = [];

        foreach (File::allFiles($path) as $file) {
            if ($fileEvents = static::getEvent($file, $prefix)) {
                $events = array_merge($events, $fileEvents);
            }
        }

        return $events;
    }

    public static function getEvent($file, $prefix = null)
    {
        $events = [];

        $segments = explode('/', $prefix . $file->getRelativePathName());
        $trigger = implode('\\', array_map('ucfirst', $segments));

        $data = file_get_contents($file->getPathName());

        if (!preg_match_all('| +/\*\*\s+\* @event.+\*/|Us', $data, $match)) {
            return null;
        }

        $factory = static::getDocBlockFactory();

        foreach ($match[0] as $doc) {
            $docblock = $factory->create(static::fixDocBlock($doc));

            $events[] = [
                'triggeredIn' => $trigger,
                'eventName' => $docblock->getTagsByName('event')[0]->getDescription()->render(),
                'summary' => $docblock->getSummary(),
                'description' => $docblock->getDescription()->render(),
            ];
        }

        return $events;
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
