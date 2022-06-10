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
        'slug',
        'path',
        'title',
        'content',
    ];

    public function __construct(array $attributes = [])
    {
        print_r(array_keys($attributes));
        $this->pageList = $attributes['pageList'];
        unset($attributes['pageList']);

        parent::__construct($attributes);
    }

    public function index()
    {
        foreach ($this->pageList->getPages() as $page) {
            $page->load();

            $index = new static([
                'pageList' => $this->pageList,
                'slug' => Str::slug($page->getPath()),
                'path' => $page->getPath(),
                'title' => $page->getTitle(),
                'content' => $page->getContent(),
            ]);
            $index->save();
        }
    }

    public function searchableAs()
    {
        return Str::slug($this->pageList->getDocsIdentifier());
    }
}
