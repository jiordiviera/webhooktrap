# Reset: one team with one active subscription matching the fixtures (fake test ids).
sqlite3 /work/db/database.sqlite "
delete from team_subscriptions where stripe_id='sub_1WtHarnessSub0001';
delete from teams where id=9001;
insert into teams (id,user_id,name,personal_team,created_at,updated_at,status) values (9001,1,'WT harness team',0,datetime('now'),datetime('now'),'active');
insert into team_subscriptions (team_id,stripe_id,stripe_status,stripe_price,quantity,created_at,updated_at,plan_key,current_period_ends_at) values (9001,'sub_1WtHarnessSub0001','active','price_WtHarnessMonthly',1,datetime('now'),datetime('now'),'monthly','2026-12-01 00:00:00');
" && echo "$(date -u +%H:%M:%S) reset" >> /wt/probe-readings.log
