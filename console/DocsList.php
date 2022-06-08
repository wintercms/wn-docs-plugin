<?php

namespace Winter\Docs\Console;

use Winter\Docs\Classes\DocsManager;
use Winter\Storm\Console\Command;

class DocsList extends Command
{
    /**
     * @inheritDoc
     */
    protected static $defaultName = 'docs:list';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'docs:list';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Lists available documentation.';

    /**
     * Command handler
     *
     * @return void
     */
    public function handle()
    {
        $docsManager = DocsManager::instance();
        $docs = $docsManager->listDocumentation();

        if (!count($docs)) {
            $this->info('No documentation has been registered.');
            $this->line('');
            return;
        }

        foreach ($docs as $doc) {
            $this->line('');
            $this->info($doc['id']);
            $this->line('    Name:           ' . $doc['name']);
            $this->line('    Type:           ' . $doc['type']);
            $this->line('    Plugin:         ' . $doc['plugin']);
            $this->line('    Is downloaded?: ' . ($doc['instance']->isDownloaded() ? 'Yes' : 'No'));
            $this->line('    Is processed?:  ' . ($doc['instance']->isProcessed() ? 'Yes' : 'No'));
        }

        $this->line('');
    }
}
