<?php

namespace Nawasara\Cloudflare\Commands;

use Illuminate\Console\Command;

class CloudflareCommand extends Command
{
    public $signature = 'cloudflare';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
