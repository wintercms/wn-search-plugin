<?php

namespace Winter\Search\Console;

use Laravel\Scout\Console\IndexCommand as BaseIndexCommand;

class IndexCommand extends BaseIndexCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'search:index
            {name : The name of the index}
            {--k|key= : The name of the primary key}';
}
