<?php

declare(strict_types=1);

return [

    // Deletion ledger outside the database (DL-3, compose-profile §2 notes):
    // one JSON line per deleted account (`usr_` id and deletion time, never the
    // address). `horizon`, where the purge job runs, mounts the `ledger` volume
    // writable; `backup` ships it off-server. Empty outside production keeps
    // the ledger in the database table only.
    'ledger_path' => env('LEDGER_PATH', env('APP_ENV') === 'production' ? '/ledger/deletions.jsonl' : null),

];
