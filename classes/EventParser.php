<?php namespace Winter\Docs\Classes;

use File;

class EventParser {

    public static function getEvents()
    {
        $events = [];

        foreach (File::allFiles('/home/r4l/www/modules') as $file) {
            if ($fileEvents = static::getEvent($file)) {
                $events = array_merge($events, $fileEvents);
            }
        }

        foreach (File::allFiles('/home/r4l/www/vendor/winter/storm/src') as $file) {
            if ($fileEvents = static::getEvent($file, 'Winter/Storm/')) {
                $events = array_merge($events, $fileEvents);
            }
        }
        return $events;
    }

    public static function getEvent($file, $prefix=null)
    {
        $fileEvents = [];

        $segments = explode('/', $prefix . $file->getRelativePathName());
        $trigger = implode('\\', array_map('ucfirst', $segments));

        $data = file_get_contents($file->getPathName());

        if (!preg_match_all('| +/\*\*\s+\* @event.+\*/|Us', $data, $match)) {
            return null;
        }

        foreach ($match[0] as $ev) {
            $fileEvents[] = [
                'triggeredIn' => $trigger,
                'doc' => $ev
            ];
        }
        return $fileEvents;
    }
}
