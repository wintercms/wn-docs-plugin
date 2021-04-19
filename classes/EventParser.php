<?php namespace Winter\Docs\Classes;

use File;

class EventParser
{
    public static function getPathEvents($path, $prefix = null)
    {
        $events = [];

        foreach (File::allFiles($path) as $file) {
            if (ends_with($file->getFilename(), '.php')) {
                static::getFileEvents($events, $file, $prefix);
            }
        }

        return $events;
    }

    public static function getFileEvents(&$events, $file, $prefix = null)
    {
        $data = file_get_contents($file->getPathName());

        // find all event docblocks in that file
        if (!preg_match_all('/ +?\/\*\*\s+?\* @event.+?\*\//s', $data, $matches, PREG_OFFSET_CAPTURE)) {
            return;
        }

        $segments = explode('/', $prefix . $file->getRelativePathName());
        $trigger = implode('\\', array_map('ucfirst', $segments));

        $classPath = 'winter/tree/develop/modules/';
        if ($prefix === 'winter/storm/') {
            $classPath = 'storm/tree/develop/src/';
        }
        $classPath .= $file->getRelativePathName();

        foreach ($matches[0] as $match) {
            $doc = $match[0];
            $offset = $match[1];
            $startLine = substr_count(substr($data, 0, $offset), "\n") + 1;
            $endLine = $startLine + substr_count($doc, "\n");

            // extract the event name
            if (!$eventName = static::getEventTag($doc)) {
                continue;
            }

            $event = [
                'triggeredIn' => $trigger,
                'classPath' => $classPath . '#L' . $startLine . ',L' . ($endLine),
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

    public static function getEventDescription($doc)
    {
        // filter out opening/closing comment and tag names
        $result = preg_filter(['/^\s*?\/\*\*\s*?$/m', '/\s*\*\/$/s', '/@(event|since) .+$/m', '/@param [^@]+/s'], '', $doc);

        // filter out spaces and asterisk prefix
        $result = preg_filter(['/\s+?\*\s*?$/m', '/^ +?\* /m'], [PHP_EOL, ''], $result);

        // each note is a paragraph
        $result = preg_replace("/(>[^>]+)".PHP_EOL . "/s", "$1".PHP_EOL.PHP_EOL, $result);

        return trim($result);
    }

    public static function getEventTag($doc)
    {
        $result = null;

        if (preg_match('/@event (.+?)$/m', $doc, $match)) {
            $result = trim($match[1]);
        }

        return $result;
    }

    public static function getParamTag($doc)
    {
        $result = [];

        if (preg_match_all('/@param ([^@]+)/s', $doc, $matches)) {
            foreach ($matches[1] as $match) {
                $result[] = trim(preg_filter('/[\t ]+?\*(\s|\/)/', '', $match));
            }
        }

        return $result;
    }

    public static function getSinceTag($doc)
    {
        $result = null;

        if (preg_match('/@since (.+?)$/m', $doc, $match)) {
            $result = trim($match[1]);
        }

        return $result;
    }
}
