# Probe (Level 2): state of the one subscription the fixtures refer to.
# Prints status | current_period_ends_at | ended flag. Logged for re-judging.
v=$(sqlite3 /work/db/database.sqlite "select stripe_status||'|'||coalesce(current_period_ends_at,'-')||'|'||(ends_at is not null) from team_subscriptions where stripe_id='sub_1WtHarnessSub0001';") || exit 1
[ -n "$v" ] || v="(no row)"
echo "$(date -u +%H:%M:%S.%N | cut -c1-12) $v" >> /wt/probe-readings.log
echo "$v"
