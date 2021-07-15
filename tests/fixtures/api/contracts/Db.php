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
     * @return array An array of results
     */
    public function query(string $statement, array $params = []);
}
