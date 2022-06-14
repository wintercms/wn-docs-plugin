<?php

namespace Winter\Docs\Classes;

use Winter\Docs\Classes\Contracts\PageList as PageListContact;

/**
 * PHP API Documentation instance.
 *
 * This class represents an entire instance of PHP API documentation.
 *
 * @author Ben Thomson <git@alfreido.com>
 * @copyright Winter CMS
 */
class PHPApiDocumentation extends BaseDocumentation
{
    /**
     * The page list instance.
     */
    protected ?PageListContact $pageList = null;

    /**
     * Paths to collate source APIs from.
     */
    protected array $sourcePaths = [];

    /**
     * @inheritDoc
     */
    public function __construct(string $identifier, array $config = [])
    {
        parent::__construct($identifier, $config);

        $this->sourcePaths = $config['sourcePaths'] ?? [];
    }

    /**
     * Gets the page list instance.
     *
     * The page list instance is used for navigation and searching documentation.
     *
     * @return PageListContact
     */
    public function getPageList(): PageListContact
    {

    }

    /**
     * Processes the documentation.
     *
     * This will get all PHP files within the documentation and convert it into HTML API documentation.
     *
     * @return void
     */
    public function process(): void
    {
        $apiParser = new ApiParser($this->sourcePaths);
        $apiParser->parse();

        print_r($apiParser->getNamespaces());
        die();
    }
}
