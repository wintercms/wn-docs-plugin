<?php

namespace Winter\Docs\Classes;

use Winter\Storm\Support\Str;

class MarkdownPageIndex extends BasePageIndex
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

    public function getRecords()
    {
        foreach (static::$pageList->getPages() as $page) {
            $page->load();

            $contents = $this->processContent($page->getContent());

            // Split into sections
            $sections = $this->contentSections($contents, $page->getTitle());

            foreach ($sections as $section) {
                $slug = str_replace('/', '-', $page->getPath()) . ($section['hash'] ? '-' . $section['hash'] : '');
                $title = $section['titles'][count($section['titles']) - 1];

                yield [
                    'group_1' => $section['titles'][0],
                    'group_2' => $section['titles'][1] ?? null,
                    'group_3' => $section['titles'][2] ?? null,
                    'combined' => implode(' ', $section['titles']),
                    'slug' => Str::slug($slug),
                    'path' => $page->getPath() . ($section['hash'] ? '#' . $section['hash'] : ''),
                    'title' => $title,
                    'content' => $section['content'],
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

        return $content;
    }

    /**
     * Splits the content into sections based on heading tags.
     *
     * This allows us to group contents in the index based on section in the document
     * and provide more contextual search results.
     */
    protected function contentSections(string $content, string $title): array
    {
        $sections = preg_split('/(<h([1-3])[^>]*>.*?<\/h\\2>)/ms', $content, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        $processedSections = [];
        $currentDepth = 0;
        $currentHeadings = [$title];
        $currentHashbang = '';
        $currentContents = '';

        foreach ($sections as $section) {
            if (preg_match('/<h([1-3])[^>]*>(.*?)<\/h\\1>/s', $section, $matches)) {
                $depth = (int) $matches[1];
                $sectionTitle = trim(str_after(strip_tags($matches[2]), '#'));

                preg_match('/<a[^>]*href="#([^"]+)"[^>]*>(.*?)<\/a>/s', $matches[2], $titleMatches);
                $hashbang = trim($titleMatches[1] ?? '');

                // Save previous section
                if (!empty(trim($currentContents))) {
                    $processedSections[] = [
                        'titles' => $currentHeadings,
                        'hash' => $currentHashbang,
                        'content' => trim($currentContents),
                    ];
                }

                $currentContents = '';
                $currentHashbang = $hashbang;

                if ($depth === 2) {
                    if ($currentDepth === 3) {
                        array_pop($currentHeadings);
                        array_pop($currentHeadings);
                        array_push($currentHeadings, trim($sectionTitle));
                        $currentDepth = 2;
                    } elseif ($currentDepth === 0) {
                        array_push($currentHeadings, trim($sectionTitle));
                        $currentDepth = 2;
                    } else {
                        array_pop($currentHeadings);
                        array_push($currentHeadings, trim($sectionTitle));
                    }
                } elseif ($depth === 3) {
                    if ($currentDepth === 3) {
                        array_pop($currentHeadings);
                        array_push($currentHeadings, trim($sectionTitle));
                    } elseif ($currentDepth === 0) {
                        // If the first section encountered is a <h3>, consider it a <h2>
                        array_push($currentHeadings, trim($sectionTitle));
                        $currentDepth = 2;
                    } else {
                        array_push($currentHeadings, trim($sectionTitle));
                        $currentDepth = 3;
                    }
                }

                continue;
            } else {
                // Because we're capturing regex groups, we need to skip the captured depths.
                if (preg_match('/^[2-3]$/', $section)) {
                    continue;
                }

                // Apply final tweaks
                $content = strip_tags($section);
                $content = preg_replace('/[\r\n ]+/', ' ', $content);

                $currentContents .= $content;
            }
        }

        if (!empty(trim($currentContents))) {
            // Save previous section
            $processedSections[] = [
                'titles' => $currentHeadings,
                'hash' => $currentHashbang,
                'content' => trim($currentContents),
            ];
        }

        return $processedSections;
    }
}
