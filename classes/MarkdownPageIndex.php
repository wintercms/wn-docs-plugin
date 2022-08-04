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

    public function getRecords(): array
    {
        return array_map(function ($page) {
            $page->load();

            return [
                'slug' => Str::slug(str_replace('/', '-', $page->getPath())),
                'path' => $page->getPath(),
                'title' => $page->getTitle(),
                'content' => $this->processContent($page->getContent()),
            ];
        }, static::$pageList->getPages());
    }

    public function index()
    {
        static::all()->each(function ($item) {
            $item->save();
        });
    }

    /**
     * Processes the content into an indexable content block.
     *
     * This will strip code blocks, anchors and HTML tags from the content, and convert the content
     * to single-line content so that it's more easily indexed and excerpted.
     *
     * @param string $content
     * @return string
     */
    protected function processContent(string $content): string
    {
        // Ignore code blocks from indexed content
        $content = preg_replace('/<pre>\s*<code[^>]*>.*?<\/code>\s*<\/pre>/s', '', $content);

        // Strip table of contents
        $content = preg_replace('/<ul class="table-of-contents">.*?<\/ul>/s', '', $content);

        // Strip main title tag
        $content = preg_replace('/<h1 class="main-title">.*?<\/h1>/s', '', $content);

        // Strip anchors
        $content = preg_replace('/<a[^>]+>#<\/a>/s', '', $content);

        // Apply final tweaks
        $content = strip_tags($content);
        $content = preg_replace('/[\r\n ]+/', ' ', $content);

        return $content;
    }
}
