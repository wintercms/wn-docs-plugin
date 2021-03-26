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

        // find all event docblocks in that file
        if (!preg_match_all('| +/\*\*\s+\* @event.+\*/|Us', $data, $matches)) {
            return;
        }

        $segments = explode('/', $prefix . $file->getRelativePathName());
        $trigger = implode('\\', array_map('ucfirst', $segments));

        foreach ($matches[0] as $doc) {
            // extract the event name
            if (!$eventName = static::getEventTag($doc)) {
                continue;
            }

            $event = [
                'triggeredIn' => $trigger,
                'eventName' => $eventName,
                'description' => static::getEventDescription($doc),
            ];

            if ($params = static::getParamTag($doc)) {
                $event['params'] = $params;
            }

            if ($since = static::getSinceTag($doc)) {
                $event['since'] = $since;
            }

            array_set($events, $eventName, $event);
        }
    }

    protected static function getEventTag($doc)
    {
        $result = null;

        if (preg_match('|@event (.+?)$|m', $doc, $match)) {
            $result = $match[1];
        }

        return $result;
    }

    protected static function getParamTag($doc)
    {
        $result = [];

        if (preg_match_all('|@param (.+?)$|m', $doc, $match)) {
            foreach ($match[1] as $param) {
                $result[] = $param;
            }
        }

        return $result;
    }

    protected static function getSinceTag($doc)
    {
        $result = null;

        if (preg_match('|@since (.+?)$|m', $doc, $match)) {
            $result = $match[1];
        }

        return $result;
    }

    protected static function getEventDescription($doc)
    {
        // filter out tags, the rest is our description
        $parts = preg_grep('/@\w+/', explode("\n", $doc), PREG_GREP_INVERT);

        // filter out open/close comment lines and '*' line prefix
        $result = implode("\n", preg_filter('/^\s+?\*(\s|$)/', '', $parts));

        return $result;
    }
}
