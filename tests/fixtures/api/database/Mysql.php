<?php namespace Docs\Test\Database;

use BaseDb;
use Docs\Test\Contracts\Db as DbContract;
use Docs\Test\Utilities\{
    StringUtility,
    NumberUtility
};

/**
 * Mysql library.
 *
 * This is a test case for the API docs generation of the Docs plugin.
 *
 * @author Ben Thomson <git@alfreido.com>
 * @deprecated 1.0.1
 */
class Mysql extends BaseDb implements DbContract
{
    /**
     * Query cache.
     *
     * @var array
     */
    private $queryCache = [];

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->queryCache = [];
    }

    /**
     * @inheritDoc
     */
    public function query(string $statement, array $params = [])
    {
        return [];
    }
}
