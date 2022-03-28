<?php

namespace Winter\Search\Console;

use Laravel\Scout\Console\FlushCommand as BaseFlushCommand;

class FlushCommand extends BaseFlushCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'search:flush {model}';
}
