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
        {id : The identifier of the documentation to process}
        {--t|token= : An authorization token to use when downloading the documentation}';

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
        $docsManager = DocsManager::instance();
        $id = $this->argument('id');
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
