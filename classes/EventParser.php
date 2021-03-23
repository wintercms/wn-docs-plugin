<?php namespace Winter\Docs\Classes;

use File;

class EventParser {

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

        foreach ($match[0] as $doc) {
            if (!preg_match('|@event (.+?)$|m', $doc, $match)) {
                continue;
            }
            $eventName = $match[1];
            $description = static::getEventDescription($doc);

            $event = [
                'triggeredIn' => $trigger,
                'eventName' => $eventName,
                'description' => $description,
            ];

            array_set($events, $eventName, $event);
        }
    }

    protected static function getEventDescription($doc)
    {
        $parts = explode("\n", $doc);

        // remove comment opening and @event line
        array_splice($parts, 0, 2);

        // remove comment closing line
        array_splice($parts, -1, 1);

        return implode("\n", preg_replace('|\s+\*\s?|', '', $parts));
    }
}
