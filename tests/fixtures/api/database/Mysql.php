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

    /** @inheritDoc */
    protected $queryLog = [];

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
        foreach ($params as $key => $value) {
            $params[$key] = StringUtility::safe($value);
        }

        $this->queryCache[] = [
            'query' => $statement,
            'params' => $params,
            'result' => []
        ];

        return [];
    }

    /**
     * Closes the MySQL connection.
     *
     * @param bool $force If the connection should be force closed.
     * @return void
     */
    public function close($force = false): void
    {
        /**
         * @event mysql.close
         *
         * Fired when the MySQL connection is closed.
         *
         * @param \Docs\Api\Database\Mysql $mysql The MySQL instance.
         * @param bool $force If the connection is being forced-closed.
         */
        $this->fireEvent('mysql.close', [$this, $force]);
    }
}
