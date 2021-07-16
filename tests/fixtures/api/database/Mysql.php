<?php namespace Docs\Api\Database;

use Docs\Api\Contracts\Db as DbContract;
use Docs\Utilities\Utilities\{
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
    /** Whether this MySQL query should be treated as safe */
    const MYSQL_SAFE = 1;

    /** Run query in a statement */
    const MYSQL_STMT = 'stmt';

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
    public function query(string $statement, array $params = []): array
    {
        return [];
    }

    /**
     * Closes the MySQL connection.
     *
     * @return void
     */
    public function close(): void
    {

    }
}
