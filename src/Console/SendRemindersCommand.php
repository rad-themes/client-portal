<?php

namespace Komalnakrani\ClientPortal\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Komalnakrani\ClientPortal\Notifications\DueDateReminder;
use Komalnakrani\ClientPortal\Portals;
use Statamic\Console\RunsInPlease;

class SendRemindersCommand extends Command
{
    use RunsInPlease;

    protected $signature = 'client-portal:send-reminders';

    protected $description = 'Email clients about modules that are due soon (run daily)';

    public function handle(): int
    {
        $days = (int) Portals::setting('reminder_days', 2);

        if ($days < 1) {
            $this->components->info('Reminders are turned off.');

            return self::SUCCESS;
        }

        $target = today()->addDays($days);
        $sent = 0;

        foreach (Portals::all() as $portal) {
            foreach (Portals::modules($portal) as ['module' => $module]) {
                if (Portals::status($module) !== 'active' || empty($module['due_date'])) {
                    continue;
                }

                $due = Carbon::parse($module['due_date']);

                if (! $due->isSameDay($target)) {
                    continue;
                }

                $clients = Portals::clients($portal);
                Notification::send($clients, new DueDateReminder($portal, $module['title'] ?? '', $due));
                $sent += $clients->count();
            }
        }

        $this->components->info("Sent {$sent} reminder(s).");

        return self::SUCCESS;
    }
}
