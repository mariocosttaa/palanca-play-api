<?php

namespace App\Console\Commands;

use App\Console\Interactive\ConsoleManager;
use Illuminate\Console\Command;

class AdminConsole extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'admin:manager';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Launch the interactive admin console for managing tenants and subscriptions';

    /**
     * Execute the console command.
     */
    public function handle(ConsoleManager $manager)
    {
        $manager->run();
    }
}
