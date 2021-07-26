<?php namespace Docs\Utilities\Traits;

/**
 * Utility traits.
 *
 * Provides helper functions for the utilities.
 *
 * @author Ben Thomson <git@alfreido.com>
 * @author Winter CMS
 */
trait IsUtility
{
    /** @var mixed Value for the utility */
    protected $value;

    public function __toString()
    {
        return (string) $this->value;
    }
}
