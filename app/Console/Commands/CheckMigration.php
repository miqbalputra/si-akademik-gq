<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('check:migration')]
#[Description('Command description')]
class CheckMigration extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        //
    }
}
