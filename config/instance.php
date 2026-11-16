<?php

declare(strict_types=1);

return [

    // `/health` (compose-profile §6, hosted plan §5, §6.6, §6.8).
    'health' => [
        // Status file written by the `backup` service (`wt-backup`), read-only
        // in the app image through the `backup_status` volume. Missing file:
        // backup fields are null and do not fail the check (no backup service).
        'backup_status_path' => env('BACKUP_STATUS_PATH', '/status/backup.json'),
        'scheduler_max_age' => 120,
        'backup_max_full_age' => 26 * 3600,
        'ledger_max_ship_age' => 900,
    ],

];
