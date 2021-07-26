<?php namespace Docs\Api\Database;

abstract class BaseDb
{
    /** @var string Mismatched type in docs */
    protected $queryLog = [];

    /**
     * Runs a statement.
     *
     * This should be done **most** of the time, as it affords speed benefits and security.
     *
     * @param array $queries The queries to run.
     * @return void
     */
    public function runStatement($queries = [])
    {

    }

    /**
     * Closes the connection.
     *
     * @return void
     */
    public function close()
    {

    }
}
