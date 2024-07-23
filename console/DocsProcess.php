<?php

namespace Winter\Docs\Console;

use Winter\Docs\Classes\DocsManager;
use Winter\Storm\Console\Command;

class DocsProcess extends Command
{
    /**
     * @inheritDoc
     */
    protected static $defaultName = 'docs:process';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'docs:process
        {id? : The identifier of the documentation to process}
        {--t|token= : An authorization token to use when downloading the documentation}
        {--m|memory-limit= : The memory limit to use when processing documentation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Processes and updates documentation.';

    /**
     * Command handler
     *
     * @return void
     */
    public function handle()
    {
        if ($this->option('memory-limit')) {
            ini_set('memory_limit', $this->option('memory-limit'));
        }

        $id = $this->argument('id');
        if (!empty($id)) {
            $ids = [$id];
        } else {
            $docsManager = DocsManager::instance();
            $docs = $docsManager->listDocumentation();

            if (!count($docs)) {
                $this->info('No documentation has been registered.');
                $this->line('');
                return;
            }

            foreach ($docs as $doc) {
                $ids[] = $doc['id'];
            }
        }

        foreach ($ids as $id) {
            $this->processDoc($id);
        }
    }

    public function processDoc(string $id)
    {
        $docsManager = DocsManager::instance();
        $doc = $docsManager->getDocumentation($id);

        $this->line('');

        if (is_null($doc)) {
            $this->error('No documentation by the given ID exists.');
            return;
        }

        $this->info('Processing ' . $id);

        // Download documentation
        if ($doc->isRemote()) {
            $this->line(' - Downloading documentation');
            $doc->download($this->option('token'));

            $this->line(' - Extracting documentation');
            $doc->extract();
        } else {
            $this->line(' - Documentation is locally available, skipping download');
        }

        // Process documentation
        $this->line(' - Processing documentation');
        $doc->process();
        $doc->resetState();

        $pageList = $doc->getPageList();
        $this->line(' - Processed ' . count($pageList) . ' page(s)');

        if ($pageList->isSearchable()) {
            $this->line(' - Indexing documentation');
            $pageList->index();
        }

        $this->line(' - Clean up downloaded files');
        $doc->cleanupDownload();
    }
}
