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
        'title',
        'type',
        'methods',
        'properties',
        'constants',
        'summary',
        'description',
    ];

    public $recordSchema = [
        'slug' => 'string',
        'title' => 'string',
        'type' => 'string',
        'methods' => 'text',
        'properties' => 'text',
        'constants' => 'text',
        'summary' => 'text',
        'description' => 'text',
    ];

    public $searchable = [
        'slug',
        'title',
        'type',
        'methods',
        'properties',
        'constants',
        'summary',
        'description',
    ];

    public function getRecords(): array
    {
        return array_map(function ($page) {
            $page->load();
            $frontMatter = $page->getFrontMatter();

            return [
                'slug' => Str::slug(str_replace('/', '-', $page->getPath())),
                'title' => $frontMatter['title'],
                'type' => $frontMatter['type'],
                'methods' => json_encode($frontMatter['methods'] ?? []),
                'properties' => json_encode($frontMatter['properties'] ?? []),
                'constants' => json_encode($frontMatter['constants'] ?? []),
                'summary' => strip_tags($frontMatter['summary'] ?? ''),
                'description' => strip_tags($frontMatter['description'] ?? ''),
            ];
        }, static::$pageList->getPages());
    }

    public function index()
    {
        static::all()->each(function ($item) {
            $item->save();
        });
    }
}
