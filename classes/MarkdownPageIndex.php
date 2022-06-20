<?php

namespace Winter\Docs\Classes;

use Winter\Docs\Classes\Contracts\PageList;
use Winter\Storm\Database\Model;
use Winter\Storm\Database\Traits\ArraySource;
use Winter\Storm\Database\Traits\Purgeable;
use Winter\Storm\Support\Str;

class MarkdownPageIndex extends Model
{
    use ArraySource;
    use Purgeable;

    public $implement = [
        '@Winter.Search.Behaviors.Searchable',
    ];

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'slug';

    /**
     * The "type" of the primary key ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

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

    public $purgeable = [
        'pageList'
    ];

    /**
     * Page list instance.
     */
    protected static ?PageList $pageList = null;

    /**
     * Determines if the index needs to be updated.
     */
    protected static bool $needsUpdate = false;

    /**
     * Sets the page list instance.
     */
    public static function setPageList(?PageList $pageList): void
    {
        static::$pageList = $pageList;
    }

    /**
     * Tells the Array Source trait that the index needs updating.
     */
    public static function needsUpdate()
    {
        static::$needsUpdate = true;
    }

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

    /**
     * Sets the name of the search index. This is based off the docs name.
     *
     * @return void
     */
    public function searchableAs()
    {
        return Str::slug(str_replace('.', '-', 'docs-' . static::$pageList->getDocsIdentifier()));
    }

    /**
     * Make search index searchable by the slug.
     *
     * @return string
     */
    public function getSearchKey()
    {
        return 'slug';
    }

    /**
     * Determines if the stored array DB should be updated.
     */
    protected function arraySourceDbNeedsUpdate(): bool
    {
        if (static::$needsUpdate) {
            return true;
        }

        if (!$this->arraySourceCanStoreDb()) {
            return true;
        }

        if (!File::exists($this->arraySourceGetDbPath())) {
            return true;
        }

        $modelFile = (new ReflectionClass(static::class))->getFileName();

        if (File::lastModified($this->arraySourceGetDbPath()) < File::lastModified($modelFile)) {
            return true;
        }

        return false;
    }

    /**
     * Gets the path where the array database will be stored.
     */
    protected function arraySourceGetDbPath(): string
    {
        $class = str_replace('\\', '', static::class);
        return $this->arraySourceGetDbDir() . '/docs-' . Str::slug(str_replace('.', '-', static::$pageList->getDocsIdentifier())) . '.sqlite';
    }
}
