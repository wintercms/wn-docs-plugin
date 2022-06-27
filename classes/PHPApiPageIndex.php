<?php

namespace Winter\Docs\Classes;

use Winter\Storm\Support\Str;

class PHPApiPageIndex extends BasePageIndex
{
    public $implement = [
        '@Winter.Search.Behaviors.Searchable',
    ];

    public $fillable = [
        'slug',
        'class',
        'methods',
        'properties',
        'constants',
        'description',
    ];

    public $recordSchema = [
        'slug' => 'string',
        'class' => 'string',
        'methods' => 'text',
        'properties' => 'text',
        'constants' => 'text',
        'description' => 'text',
    ];

    public $searchable = [
        'slug',
        'class',
        'methods',
        'properties',
        'constants',
        'description',
    ];

    public $jsonable = [
        'methods',
        'properties',
        'constants',
    ];

    public function index()
    {
        foreach (static::$pageList->getPages() as $page) {
            $page->load();
            $frontMatter = $page->getFrontMatter();

            $index = new static([
                'slug' => Str::slug(str_replace('/', '-', $page->getPath())),
                'class' => $frontMatter['title'],
                'methods' => $frontMatter['methods'],
                'properties' => $frontMatter['properties'],
                'constants' => $frontMatter['constants'],
                'summary' => $frontMatter['summary'],
                'description' => $frontMatter['description'],
            ]);
            $index->save();
        }
    }
}
