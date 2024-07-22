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
        'title',
        'combined',
        'content',
        'slug',
        'path',
    ];

    public function getRecords()
    {
        foreach (static::$pageList->getPages() as $page) {
            $page->load();
            $frontMatter = $page->getFrontMatter();

            if ($frontMatter['type'] === 'class') {
                // Add record for class
                yield [
                    'group_1' => $frontMatter['title'] ?? '',
                    'group_2' => 'Class',
                    'group_3' => $frontMatter['namespace'] ?? '',
                    'combined' => trim(($frontMatter['namespace'] ?? '') . ' ' . ($frontMatter['title'] ?? '')),
                    'title' => $frontMatter['title'],
                    'content' => strip_tags($frontMatter['summary'] ?? ''),
                    'slug' => Str::slug(str_replace('/', '-', $page->getPath())),
                    'path' => $page->getPath(),
                ];

                // Add records for methods
                foreach ($frontMatter['methods'] ?? [] as $method) {
                    yield [
                        'group_1' => $frontMatter['title'] ?? '',
                        'group_2' => 'Method',
                        'group_3' => $frontMatter['namespace'] ?? '',
                        'combined' => trim(($frontMatter['namespace'] ?? '') . ' ' . ($frontMatter['title'] ?? '') . ' ' . $method['name']),
                        'title' => $method['name'] . '()',
                        'content' => strip_tags($method['summary'] ?? ''),
                        'slug' => Str::slug(str_replace('/', '-', $page->getPath()) . '-method-' . $method['name']),
                        'path' => $page->getPath() . '#method-' . Str::slug($method['name']),
                    ];
                }

                // Add records for properties
                foreach ($frontMatter['properties'] ?? [] as $property) {
                    yield [
                        'group_1' => $frontMatter['title'] ?? '',
                        'group_2' => 'Property',
                        'group_3' => $frontMatter['namespace'] ?? '',
                        'combined' => trim(($frontMatter['namespace'] ?? '') . ' ' . ($frontMatter['title'] ?? '') . ' ' . $method['name']),
                        'title' => '$' . $property['name'],
                        'content' => strip_tags($property['summary'] ?? ''),
                        'slug' => Str::slug(str_replace('/', '-', $page->getPath()) . '-prop-' . $property['name']),
                        'path' => $page->getPath() . '#prop-' . Str::slug($property['name']),
                    ];
                }

                // Add records for constants
                foreach ($frontMatter['constants'] ?? [] as $constant) {
                    yield [
                        'group_1' => $frontMatter['title'] ?? '',
                        'group_2' => 'Constant',
                        'group_3' => $frontMatter['namespace'] ?? '',
                        'combined' => trim(($frontMatter['namespace'] ?? '') . ' ' . ($frontMatter['title'] ?? '') . ' ' . $method['name']),
                        'title' => $constant['name'],
                        'content' => strip_tags($constant['summary'] ?? ''),
                        'slug' => Str::slug(str_replace('/', '-', $page->getPath()) . '-const-' . $constant['name']),
                        'path' => $page->getPath() . '#constants',
                    ];
                }
            } else {
                yield [
                    'group_1' => 'Events',
                    'group_2' => null,
                    'group_3' => null,
                    'combined' => 'event ' . $frontMatter['title'],
                    'title' => $frontMatter['title'],
                    'content' => strip_tags($frontMatter['summary'] ?? ''),
                    'slug' => Str::slug(str_replace('/', '-', $page->getPath())),
                    'path' => $page->getPath(),
                ];
            }
        }
    }

    public function index()
    {
        static::all()->each(function ($item) {
            $item->save();
        });
    }
}
