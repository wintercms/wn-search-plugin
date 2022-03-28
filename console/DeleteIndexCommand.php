<?php

namespace Winter\Search\Console;

use Laravel\Scout\Console\DeleteIndexCommand as BaseDeleteIndexCommand;

class DeleteIndexCommand extends BaseDeleteIndexCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'search:delete-index {name : The name of the index}';
}
