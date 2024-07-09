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

        $this->table(
            [
                'ID',
                'Name',
                'Type',
                'Plugin',
                'Downloaded?',
                'Processed?',
            ],
            array_map(function ($doc) {
                return [
                    $doc['id'],
                    $doc['name'],
                    ($doc['type'] === 'php') ? 'PHP API' : 'Markdown',
                    $doc['plugin'],
                    ($doc['instance']->isDownloaded() ? '<info>Yes</info>' : 'No'),
                    ($doc['instance']->isProcessed() ? '<info>Yes</info>' : 'No'),
                ];
            }, $docs)
        );
    }
}
