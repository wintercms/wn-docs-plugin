<?php namespace Docs\Utilities\Utilities;

use Docs\Utilities\Traits\IsUtility;

class StringUtility
{
    use IsUtility;

    /**
     * Ensures that HTML entities are necoded
     *
     * @param string $string
     * @return string
     */
    public static function safe(string $string): string
    {
        return htmlentities($string, ENT_HTML5 | ENT_QUOTES, 'UTF-8');
    }
}
