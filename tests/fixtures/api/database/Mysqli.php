<?php namespace Docs\Api\Database;

/**
 * Mysqli library.
 *
 * This is a test case for the API docs generation of the Docs plugin.
 *
 * @author Ben Thomson <git@alfreido.com>
 */
class Mysqli extends Mysql
{
    /**
     * @inheritDoc
     */
    private $queryCache = [];
}
