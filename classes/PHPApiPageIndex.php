<?php

namespace Winter\Docs\Classes;

use Winter\Storm\Support\Str;

class PHPApiPageIndex extends BasePageIndex
{
    public $implement = [
        '@Winter.Search.Behaviors.Searchable',
    ];

    public $fillable = [
        'group_1',
        'group_2',
        'group_3',
        'combined',
        'title',
        'slug',
        'path',
        'content',
    ];

    public $recordSchema = [
        'group_1' => 'string',
        'group_2' => 'string',
        'group_3' => 'string',
        'combined' => 'string',
        'title' => 'string',
        'slug' => 'string',
        'path' => 'string',
        'content' => 'text',
    ];

    public $searchable = [
        'group_3',
        'group_2',
        'group_1',
        'combined',
        'title',
        'content',
        'slug',
        'path',
    ];

    public function getRecords(): array
    {
        $records = [];

        foreach (static::$pageList->getPages() as $page) {
            $page->load();
            $frontMatter = $page->getFrontMatter();

            // Add record for class
            $records[] = [
                'group_1' => $frontMatter['namespace'] ?? '',
                'group_2' => $frontMatter['title'] ?? '',
                'group_3' => '',
                'combined' => trim(($frontMatter['namespace'] ?? '') . ' ' . ($frontMatter['title'] ?? '')),
                'title' => $frontMatter['title'],
                'content' => strip_tags($frontMatter['summary'] ?? ''),
                'slug' => Str::slug(str_replace('/', '-', $page->getPath())),
                'path' => $page->getPath(),
            ];
        }

        return $records;

        // return array_map(function ($page) {
        //     $page->load();
        //     $frontMatter = $page->getFrontMatter();

        //     return [
        //         'slug' => Str::slug(str_replace('/', '-', $page->getPath())),
        //         'title' => $frontMatter['title'],
        //         'type' => $frontMatter['type'],
        //         'methods' => json_encode($frontMatter['methods'] ?? []),
        //         'properties' => json_encode($frontMatter['properties'] ?? []),
        //         'constants' => json_encode($frontMatter['constants'] ?? []),
        //         'summary' => strip_tags($frontMatter['summary'] ?? ''),
        //         'description' => strip_tags($frontMatter['description'] ?? ''),
        //     ];
        // }, );
    }

    public function index()
    {
        static::all()->each(function ($item) {
            $item->save();
        });
    }
}
