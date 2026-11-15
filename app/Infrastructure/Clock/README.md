# Clock (infrastructure adapter)

The only place that reads the current time (`now()`, `Carbon::now()`); everything else asks the `Clock` contract (rule 4, D-10).
