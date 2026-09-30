<?php

namespace App\Console\Commands;

use App\Models\BotUser;
use App\Models\Subscriptions;
use App\Models\UserStat;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SnapshotUserStats extends Command
{
    protected $signature = 'app:snapshot-user-stats
        {--backfill=0 : Also fill this many past days that have no snapshot, estimated from the rows that still exist}';

    protected $description = 'Records today\'s totals of bot users, subscriptions and street filters';

    public function handle(): void
    {
        $days = (int)$this->option('backfill');
        for ($day = now()->subDays($days)->startOfDay(); $day->lt(today()); $day->addDay()) {
            if (!UserStat::query()->whereKey($day->toDateString())->exists()) {
                $this->store($day, $day->copy()->addDay());
            }
        }

        // Rerunning during the day overwrites the row, so it ends up with the day's last state.
        $this->store(today(), null);
    }

    /**
     * Counts rows created before $until (everything when null). A filter has no
     * history, so it counts from its last edit (only /filter touches updated_at).
     */
    private function store(Carbon $day, ?Carbon $until): void
    {
        $before = fn($query, string $column) => $query->when($until, fn($query) => $query->where($column, '<', $until));

        UserStat::query()->updateOrCreate(['date' => $day->toDateString()], [
            'users' => $before(BotUser::query()->whereNot('is_bot'), 'created_at')->count(),
            'subscriptions' => $before(Subscriptions::query(), 'created_at')->count(),
            'filtered' => $before(Subscriptions::query()->whereNotNull('street_filter'), 'updated_at')->count(),
        ]);
    }
}
