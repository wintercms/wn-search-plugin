<?php

namespace Winter\Search\Console;

use Laravel\Scout\Console\ImportCommand as BaseImportCommand;

class ImportCommand extends BaseImportCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'search:import
            {model : Class name of model to bulk import}
            {--c|chunk= : The number of records to import at a time (Defaults to configuration value: `search.chunk.searchable`)}';
}
