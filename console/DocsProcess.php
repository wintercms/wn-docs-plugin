<?php

namespace Winter\Docs\Console;

use Illuminate\Support\Facades\Queue;
use Winter\Docs\Classes\DocsManager;
use Winter\Docs\Jobs\ProcessDoc;
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
        {--j|queue= : Add as a job to the queue}
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

        if (is_null($this->option('queue'))) {
            $queue = false;
        } else {
            $queue = $this->option('queue') ?: 'default';
        }

        foreach ($ids as $id) {
            if ($queue === false) {
                (new ProcessDoc())->processDoc($id, $this->option('token'), $this);
            } else {
                Queue::push(ProcessDoc::class, [
                    'id' => $id,
                    'token' => $this->option('token'),
                    'memory_limit' => $this->option('memory-limit'),
                ], $queue);
                $this->info('- Added documentation processing to queue "' . $queue . '": ' . $id);
            }
        }
    }
}
