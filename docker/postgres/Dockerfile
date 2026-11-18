# syntax=docker/dockerfile:1
# Postgres image for the `postgres` and `backup` services (compose-profile.md §1; the private hosted deployment plan §5, option A):
# official postgres:18 + pgBackRest, plus age and rclone for the deletion-ledger copies (DL-3b).
# Published as ghcr.io/<owner>/webhooktrap-postgres.
FROM postgres:18-bookworm@sha256:ece55f7868da684e400f82f1030a050718adbdd65c23aa500c486f6e2950dc0f
RUN apt-get update \
    && apt-get install -y --no-install-recommends pgbackrest age rclone ca-certificates \
    && apt-get clean && find /var/lib/apt/lists -mindepth 1 -delete \
    && pgbackrest version && age --version && rclone version --check=false >/dev/null
COPY --chmod=755 wt-backup /usr/local/bin/wt-backup
# One script, called by name (wt-backup header). /status is created owned by postgres (999) in
# this image and in the app image, so the backup_status volume gets the same owner whichever
# container mounts it first; the backup service (uid 999) writes it, the app reads it.
RUN for n in pgbackrest-queue wt-pgbackrest backup-loop backup-now backup-status restore recover ledger-fetch restore-finish; do \
        ln -s wt-backup "/usr/local/bin/$n"; \
    done \
    && mkdir -p /status /var/lib/pgbackrest /var/spool/pgbackrest /tmp/pgbackrest \
    && chown postgres:postgres /status /var/lib/pgbackrest /var/spool/pgbackrest /tmp/pgbackrest
