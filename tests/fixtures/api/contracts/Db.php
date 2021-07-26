<?php namespace Docs\Api\Contracts;

interface Db
{
    /**
     * Query method.
     *
     * Executes a query.
     *
     * This is a test case to show a difference of typing between a property's strict type and the docblock.
     *
     * @param string|null $statement Property type conflict
     * @param array $params
     * @return string An array of results
     * @throws QueryException If the query cannot be run
     */
    public function query(string $statement, array $params = []): array;
}
