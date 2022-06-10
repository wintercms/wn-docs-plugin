<?php

namespace Winter\Docs\Classes;

use Winter\Storm\Database\Model;
use Winter\Storm\Database\Traits\ArraySource;
use Winter\Storm\Support\Str;

class MarkdownPageIndex extends Model
{
    use ArraySource;

    public $implement = [
        '@Winter.Search.Behaviors.Searchable'
    ];

    /**
     * The Markdown Documentation page list.
     *
     * @var MarkdownPageList
     */
    public MarkdownPageList $pageList;

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

    public function index()
    {
        foreach ($this->pageList->getPages() as $page) {
            $page->load();

            static::create([
                'slug' => Str::slug($page->getPath()),
                'path' => $page->getPath(),
                'title' => $page->getTitle(),
                'content' => $page->getContent(),
            ]);
        }
    }
}
