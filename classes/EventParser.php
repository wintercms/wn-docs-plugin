<?php namespace Winter\Docs\Classes;

use File;

class EventParser
{
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
        $data = file_get_contents($file->getPathName());

        if (!preg_match_all('| +/\*\*\s+\* @event.+\*/|Us', $data, $match)) {
            return;
        }

        $segments = explode('/', $prefix . $file->getRelativePathName());
        $trigger = implode('\\', array_map('ucfirst', $segments));

        foreach ($match[0] as $doc) {
            if (!preg_match('|@event (.+?)$|m', $doc, $match)) {
                continue;
            }
            $eventName = $match[1];
            $description = static::getEventDescription($doc);

            $event = [
                'triggeredIn' => $trigger,
                'eventName' => $eventName,
                'description' => static::getEventDescription($doc),
            ];

            if (preg_match('|@since (.+?)$|m', $doc, $match)) {
                $event['since'] = $match[1];
            }

            array_set($events, $eventName, $event);
        }
    }

    protected static function getEventDescription($doc)
    {
        // filter out tags, the rest is our description
        $parts = preg_grep('/@(event|since)/', explode("\n", $doc), PREG_GREP_INVERT);

        // filter out open/close comment lines and '*' line prefix
        $result = implode("\n", preg_filter('|^\s+?\*\s?|', '', $parts));

        return $result;
    }
}
