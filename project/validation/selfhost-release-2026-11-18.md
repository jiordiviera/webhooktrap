# Self-host release validation (issue #20), 2026-11-18

Run on a disposable Docker host (Docker Engine 29.1, Compose 2.40), linux/amd64, with images built
locally from two trees and tagged as two releases:

- `2.0.0-rc.1`: `github/v2` before this change (no `runner-health`, old `compose.yaml`);
- `2.0.0-rc.2`: this branch.

`install.sh` and every `docker compose` command ran as root (inside a `docker:29-cli` container
with the host socket), in a fresh directory, project name `wtst`, `--tls off --http-port 18080`.
Requests reached the app on `127.0.0.1:18080` with the `Host` of each name and `X-WT-Edge`, as a
reverse proxy would send them.

## Results

| Check | Result |
| ----- | ------ |
| Clean install, `install.sh --version 2.0.0-rc.1 ... --tls off --start` | All services healthy; `/up`, `/register`, `/health` 200; capture host answers `{"received":true}` |
| `install.sh` run a second time | Every secret and `.env` kept; stack started again |
| Account through the real `/register` flow | 302 to `/verify-email`; row in `users` |
| Backup | `backup-status` ok after the first full backup; WAL queue drained |
| Point-in-time restore | 2 accounts at T0, a 3rd created 4 s after T0. `restore --target-time=T0`, `recover`: "recovery stopping before commit of transaction 860, time T0+4s", new timeline 2. 2 accounts after restore |
| App refuses to start while `restore.pending` exists | `restore.pending present: run accounts:apply-ledger and restore-finish first` |
| `accounts:apply-ledger --allow-missing`, `restore-finish`, `up --wait` | Healthy; `/health` 200; full backup on the new timeline OK |
| Upgrade rc.1 → rc.2 (`backup-now`, version in `.env`, `up -d --wait`) | All services healthy on rc.2; data kept; `webhooktrap version` 2.0.0-rc.2 |
| Rollback rc.2 → rc.1 | 9 image services healthy on rc.1; `/health` 200; data kept |
| Runner (profile `runner`, rc.2) | Starts with a token; the app answers 404 on `/runner/v1/hello` (no runner API in 2.0); `runner-health` reports "the runner has not reached the app yet" |

## Defects found and fixed on this branch

1. `--tls off` still published `127.0.0.1:443`, which a host proxy already holds: the install
   failed to start `app`. The installer now sets `HTTPS_PORT` to a free loopback port.
2. `runner_token` as documented (`root:33 0440`) is unreadable by the runner image (`nonroot`,
   65532): "cannot read RUNNER_TOKEN_FILE". Now `root:65532`.
3. The app has no `/runner/v1` API, so a started runner retries forever. The runner is now behind
   the Compose profile `runner`, off by default.
4. The documented `docker compose run --rm backup restore` cannot work: `backup` mounts `pgdata`
   read-only, and `postgres` cannot fetch WAL during recovery (no repository, no cipher pass).
   New `restore` service (profile `restore`, pgdata read-write) and `wt-backup recover`.
5. `compose.yaml` did not mount `backup_s3_key`/`backup_s3_secret`, so `BACKUP_REPO_TYPE=s3` could
   not work on self-host. Mounted (empty files with posix).
6. The guide promised `sftp` backups, `BACKUP_BEFORE_MIGRATE`, `APP_HOST` and `REVERB_PUBLIC_HOST`;
   none exist. Removed from the guide.
7. `/secrets/` was not in `.gitignore`. Added.

## Not covered

- Built-in TLS (`--tls auto`): needs public DNS and ports 80/443; not run here.
- The two releases have the same migrations, so the upgrade and rollback ran no migration.
- The deletion-ledger part of the restore drill (delete an account, restore before it, apply the
  ledger) was not run; the ledger was empty. `ledger-fetch` with an age identity was not run.
- S3 backups (no bucket used).
- Images from GHCR: the repository is private until launch, so images were built locally.
