<?php

namespace Winter\Docs\Jobs;

use Winter\Docs\Classes\DocsManager;
use Winter\Storm\Console\Command;
use Winter\Storm\Exception\ApplicationException;

class ProcessDoc
{
    /**
     * The command instance, if run through the console.
     */
    protected ?Command $command = null;

    /**
     * Execute the job.
     */
    public function fire($job, $data)
    {
        if ($data['memory_limit']) {
            ini_set('memory_limit', $data['memory_limit']);
        }

        $this->processDoc($data['id'], $data['token']);
        $job->delete();
    }

    /**
     * Processes a documentation by the given ID.
     *
     * You may optionally provide a token to use when downloading the documentation as the second argument.
     *
     * This can either be run through the `docs:process` command, or as a queued job. If run through the command, the
     * command instance should be passed as the third argument.
     */
    public function processDoc(string $id, ?string $token = null, ?Command $command = null)
    {
        $this->command = $command;

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
            $doc->download($token);

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

    protected function line($string): void
    {
        if ($this->command) {
            $this->command->line($string);
        }
    }

    protected function info($string): void
    {
        if ($this->command) {
            $this->command->info($string);
        }
    }

    protected function error($string): void
    {
        if ($this->command) {
            $this->command->error($string);
        } else {
            throw new ApplicationException($string);
        }
    }
}
