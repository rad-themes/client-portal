<?php

namespace RadThemes\ClientPortal\Console;

use Illuminate\Console\Command;
use RadThemes\ClientPortal\Activity;
use Statamic\Console\RunsInPlease;

class SendDigestCommand extends Command
{
    use RunsInPlease;

    protected $signature = 'client-portal:send-digest';

    protected $description = 'Email admins the queued client activity digest (run daily)';

    public function handle(): int
    {
        $count = Activity::sendDigest();

        $this->components->info("Sent a digest with {$count} update(s).");

        return self::SUCCESS;
    }
}
