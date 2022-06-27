<?php

namespace Winter\Docs\Classes;

use Winter\Storm\Support\Str;

class MarkdownPageIndex extends BasePageIndex
{
    public $implement = [
        '@Winter.Search.Behaviors.Searchable',
    ];

    public $fillable = [
        'slug',
        'path',
        'title',
        'content',
    ];

    public $recordSchema = [
        'slug' => 'string',
        'path' => 'string',
        'title' => 'string',
        'content' => 'text',
    ];

    public $searchable = [
        'title',
        'slug',
        'path',
        'content',
    ];

    public function index()
    {
        foreach (static::$pageList->getPages() as $page) {
            $page->load();

            $index = new static([
                'pageList' => $this->pageList,
                'slug' => Str::slug(str_replace('/', '-', $page->getPath())),
                'path' => $page->getPath(),
                'title' => $page->getTitle(),
                'content' => strip_tags($page->getContent()),
            ]);
            $index->save();
        }
    }
}
