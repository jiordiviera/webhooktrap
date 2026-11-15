<?php

declare(strict_types=1);

namespace App\Modules\Capture\Console;

use App\Modules\Capture\Application\CaptureRederivation;
use App\Modules\Capture\Jobs\RederiveProjectCaptures;
use App\Modules\Workspace\Application\Projects;
use Illuminate\Console\Command;

/**
 * `php artisan captures:rederive`: re-derive captures after a pack update
 * (domain §4). Stale captures by default (derived with another pack version,
 * or never derived); `--all` for every capture. One batched job per project
 * on `maintenance`, or in this process with `--now`. Scheduled hourly, so a
 * failed derivation job is caught up and a pack deploy re-derives by itself.
 */
final class RederiveCaptures extends Command
{
    protected $signature = 'captures:rederive
        {--all : Every capture, not only stale ones}
        {--provider=* : Only these providers (pack ids; `unknown` for captures no pack matched)}
        {--project= : Only this project (project key)}
        {--now : Run in this process instead of queueing one job per project}';

    protected $description = 'Re-derive capture provider, event type, object ids, groups and shapes from the current packs';

    public function handle(CaptureRederivation $rederivation, Projects $projects): int
    {
        $all = (bool) $this->option('all');
        $providers = array_values(array_filter(array_map('strval', (array) $this->option('provider')), fn (string $p): bool => $p !== ''));
        $providers = $providers === [] ? null : $providers;

        $projectId = null;
        $key = $this->option('project');
        if (is_string($key) && $key !== '') {
            $projectId = $projects->idOfKey($key);
            if ($projectId === null) {
                $this->error('No project with that key.');

                return self::FAILURE;
            }
        }

        $targets = $rederivation->projects($all, $providers, $projectId);
        if ($targets === []) {
            $this->info('Nothing to re-derive.');

            return self::SUCCESS;
        }

        foreach ($targets as $id => $count) {
            if ($this->option('now')) {
                $after = 0;
                do {
                    $after = $rederivation->run($id, $all, $providers, $after);
                } while ($after !== null);
            } else {
                RederiveProjectCaptures::dispatch($id, $all, $providers);
            }
        }

        $total = array_sum($targets);
        $verb = $this->option('now') ? 'Re-derived' : 'Queued';
        $this->info("{$verb} {$total} ".($total === 1 ? 'capture' : 'captures').' in '.count($targets).' '.(count($targets) === 1 ? 'project' : 'projects').'.');

        return self::SUCCESS;
    }
}
