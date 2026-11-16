#!/usr/bin/env python3
"""Compose policy for the hosted profile (project/operations/ci-cd.md §4.2, rules P-1 to P-20).

deploy.sh runs it on the box, as root, with `python3 -I`, on the resolved configuration of the
candidate compose.production.yaml (`docker compose ... config --format json`). It is installed at
C3 and never read from the image. Standard library only.

Usage:
    compose-policy.py --config resolved.json --app-ref REPO@sha256:... --pg-ref REPO@sha256:...
                      --app-dir APP_DIR --app-port APP_PORT [--cgroupfs]

Exit 0 when every rule holds, 1 otherwise. P-11: on failure it prints only "rule service" pairs,
never a value from the configuration (the JSON inlines .env.production).
"""

from __future__ import annotations

import argparse
import json
import os
import re
import sys

APP_IMAGE_SERVICES = {"app", "ingest", "haproxy-config", "reverb", "horizon", "scheduler", "engine"}
PG_IMAGE_SERVICES = {"postgres", "backup"}
SERVICES = APP_IMAGE_SERVICES | PG_IMAGE_SERVICES | {"valkey", "haproxy"}
LARAVEL = {"app", "ingest", "reverb", "horizon", "scheduler"}
DIGEST = re.compile(r"@sha256:[0-9a-f]{64}$")

SECRET_FILES = {
    "valkey_password", "postgres_password", "db_password", "backup_cipher_pass", "backup_s3_key",
    "backup_s3_secret", "edge_proxy_secret", "agent_pairing_hmac_key", "app_key",
    "reverb_app_secret", "mail_password", "pulsesend_api_key", "github_client_secret",
    "google_client_secret", "error_tracker_dsn", "identity_email_hmac_key",
    "identity_recovery_code_key",
}
SECRETS_PER_SERVICE = {
    "app": {"app_key", "db_password", "valkey_password", "reverb_app_secret",
            "github_client_secret", "google_client_secret", "error_tracker_dsn",
            "identity_email_hmac_key", "identity_recovery_code_key", "agent_pairing_hmac_key",
            "edge_proxy_secret", "pulsesend_api_key"},
    "ingest": {"app_key", "db_password", "valkey_password", "error_tracker_dsn",
               "edge_proxy_secret"},
    "reverb": {"app_key", "valkey_password", "reverb_app_secret", "error_tracker_dsn"},
    "horizon": {"app_key", "db_password", "valkey_password", "reverb_app_secret",
                "mail_password", "pulsesend_api_key", "error_tracker_dsn"},
    "scheduler": {"app_key", "db_password", "valkey_password", "error_tracker_dsn"},
    "haproxy": {"edge_proxy_secret"},
    "valkey": {"valkey_password"},
    "postgres": {"postgres_password"},
    "backup": {"backup_cipher_pass", "backup_s3_key", "backup_s3_secret"},
    "engine": set(),
    "haproxy-config": set(),
}
NETWORKS_PER_SERVICE = {
    "app": {"edge", "internal", "egress"},
    "ingest": {"internal"},
    "haproxy": {"internal"},
    "reverb": {"edge", "internal"},
    "horizon": {"internal", "egress"},
    "scheduler": {"internal"},
    "valkey": {"internal"},
    "postgres": {"internal"},
    "backup": {"internal", "backup_egress"},
}
NO_NETWORK = {"engine", "haproxy-config"}
USERS = {"engine": "10001:10001", "backup": "postgres", "haproxy-config": "0:0"}
GROUP_ADD = {"app": ["10001"], "haproxy": ["33"]}
CAP_ADD = {
    "valkey": {"SETUID", "SETGID"},
    "postgres": {"CHOWN", "DAC_OVERRIDE", "FOWNER", "SETUID", "SETGID"},
}
FORBIDDEN_KEYS = ("privileged", "devices", "device_cgroup_rules", "pid", "ipc", "uts",
                  "userns_mode", "cgroup", "runtime", "gpus", "volumes_from", "sysctls", "build")
FORBIDDEN_BIND = ("/", "/proc", "/sys", "/dev", "/etc", "/var/lib/docker", "/var/run/docker.sock",
                  "/run/docker.sock")  # anything outside APP_DIR is refused anyway (P-5)
READ_ONLY = {"app", "ingest", "haproxy", "haproxy-config", "reverb", "horizon", "scheduler", "engine"}
HAPROXY_ENV = {"WT_CAPTURE_MAX_CONN", "WT_CAPTURE_MAX_RATE", "WT_CAPTURE_MAX_STREAM",
               "WT_CAPTURE_STREAM_SLOTS"}
HAPROXY_CONFIG_ENTRYPOINT = ["cp", "/etc/webhooktrap/haproxy.cfg",
                             "/etc/webhooktrap/received-200.http", "/wt-haproxy/"]
INGEST_COMMAND = ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile.ingest",
                  "--adapter", "caddyfile"]
HAPROXY_COMMAND = ["haproxy", "-f", "/wt-haproxy/haproxy.cfg"]
PLAIN_SECRETS = ("APP_KEY", "APP_PREVIOUS_KEYS", "DB_PASSWORD", "REDIS_PASSWORD",
                 "REVERB_APP_SECRET", "MAIL_PASSWORD", "PULSESEND_API_KEY",
                 "GITHUB_CLIENT_SECRET", "GOOGLE_CLIENT_SECRET", "ERROR_TRACKER_DSN",
                 "IDENTITY_EMAIL_HMAC_KEY", "IDENTITY_RECOVERY_CODE_KEY", "AGENT_PAIRING_HMAC_KEY",
                 "POSTGRES_PASSWORD")
POOLERS = ("pgbouncer", "pgcat", "odyssey", "supavisor")
FALSY = {"", "0", "false", "False", "FALSE", "(false)"}


class Policy:
    def __init__(self, cfg: dict, args: argparse.Namespace):
        self.cfg = cfg
        self.args = args
        self.failures: list[tuple[str, str]] = []
        self.app_dir = os.path.realpath(args.app_dir)

    def fail(self, rule: str, where: str = "-") -> None:
        self.failures.append((rule, where))

    def services(self) -> dict:
        return self.cfg.get("services") or {}

    @staticmethod
    def env(svc: dict) -> dict:
        env = svc.get("environment") or {}
        if isinstance(env, list):
            env = dict(item.split("=", 1) if "=" in item else (item, None) for item in env)
        return env

    def under(self, path: str, base: str) -> bool:
        real = os.path.realpath(path)
        base = os.path.realpath(base)
        return real == base or real.startswith(base + os.sep)

    # P-1
    def p1(self) -> None:
        if self.cfg.get("name") != "webhooktrap":
            self.fail("P-1", "name")
        names = set(self.services())
        for extra in sorted(names - SERVICES):
            self.fail("P-1", extra)
        for missing in sorted(SERVICES - names):
            self.fail("P-1", missing)

    # P-2
    def p2(self) -> None:
        app_digest = self.args.app_ref.split("@", 1)[1]
        for name, svc in self.services().items():
            image = svc.get("image") or ""
            if name in APP_IMAGE_SERVICES and image != self.args.app_ref:
                self.fail("P-2", name)
            elif name in PG_IMAGE_SERVICES and image != self.args.pg_ref:
                self.fail("P-2", name)
            elif name == "valkey" and not (image.startswith("valkey/valkey:") and DIGEST.search(image)):
                self.fail("P-2", name)
            elif name == "haproxy":
                if not (re.match(r"^(docker\.io/library/)?haproxy:[0-9][^@]*@", image) and DIGEST.search(image)):
                    self.fail("P-2", name)
                if (svc.get("labels") or {}).get("webhooktrap.config-from") != app_digest:
                    self.fail("P-2", name + " label")

    # P-3, P-4
    def p3_p4(self) -> None:
        for name, svc in self.services().items():
            for key in FORBIDDEN_KEYS:
                if svc.get(key) not in (None, False, [], {}):
                    self.fail("P-3", name)
            mode = svc.get("network_mode")
            if mode is not None and not (mode == "none" and name in NO_NETWORK):
                self.fail("P-3", name)
            if svc.get("security_opt") != ["no-new-privileges:true"]:
                self.fail("P-3", name)
            if svc.get("user") != USERS.get(name):
                self.fail("P-3", name + " user")
            if [str(g) for g in (svc.get("group_add") or [])] != GROUP_ADD.get(name, []):
                self.fail("P-3", name + " group_add")
            if svc.get("cap_drop") != ["ALL"]:
                self.fail("P-4", name)
            if set(svc.get("cap_add") or []) - CAP_ADD.get(name, set()):
                self.fail("P-4", name)

    # P-5, P-13
    def p5_p13(self) -> None:
        top_volumes = self.cfg.get("volumes") or {}
        for vname, vol in top_volumes.items():
            vol = vol or {}
            if vol.get("external") or vol.get("driver_opts") or vol.get("driver") not in (None, "local"):
                self.fail("P-5", "volume " + vname)
        if self.cfg.get("configs"):
            self.fail("P-5", "configs")
        secrets_dir = os.path.join(self.app_dir, "secrets")
        top_secrets = self.cfg.get("secrets") or {}
        for sname, sec in top_secrets.items():
            sec = sec or {}
            path = sec.get("file") or ""
            if sname not in SECRET_FILES or set(sec) - {"file", "name"}:
                self.fail("P-5", "secret " + sname)
            elif os.path.realpath(path) != os.path.join(os.path.realpath(secrets_dir), sname):
                self.fail("P-5", "secret " + sname)
        for name, svc in self.services().items():
            if svc.get("configs"):
                self.fail("P-5", name)
            for mount in svc.get("volumes") or []:
                kind = mount.get("type")
                source = mount.get("source") or ""
                if kind == "volume":
                    if source not in top_volumes:
                        self.fail("P-5", name)
                    if source == "haproxy_config":
                        ok = (name == "haproxy-config" and not mount.get("read_only")) or \
                             (name == "haproxy" and mount.get("read_only"))
                        if not ok or mount.get("target") != "/wt-haproxy":
                            self.fail("P-13", name)
                    elif name == "haproxy":
                        self.fail("P-13", name)
                elif kind == "bind":
                    real = os.path.realpath(source)
                    if not self.under(real, self.app_dir) or real in FORBIDDEN_BIND or "docker.sock" in real:
                        self.fail("P-5", name)
                elif kind != "tmpfs":
                    self.fail("P-5", name)
        users = [n for n, s in self.services().items()
                 if any((m.get("source") == "haproxy_config") for m in s.get("volumes") or [])]
        if sorted(users) != ["haproxy", "haproxy-config"]:
            self.fail("P-13", "haproxy_config")

    # P-6
    def p6(self) -> None:
        for name, svc in self.services().items():
            ports = svc.get("ports") or []
            if name != "app":
                if ports:
                    self.fail("P-6", name)
                continue
            if len(ports) != 1:
                self.fail("P-6", name)
                continue
            port = ports[0]
            if port.get("host_ip") != "127.0.0.1" or int(port.get("target", 0)) != 8080 \
                    or str(port.get("published")) != str(self.args.app_port):
                self.fail("P-6", name)

    # P-7, P-15
    def p7_p15(self) -> None:
        nets = self.cfg.get("networks") or {}
        for nname, net in nets.items():
            net = net or {}
            if net.get("external") or net.get("driver") in ("host", "macvlan", "ipvlan"):
                self.fail("P-7", "network " + nname)
        if not (nets.get("internal") or {}).get("internal"):
            self.fail("P-7", "network internal")
        for name, svc in self.services().items():
            have = set((svc.get("networks") or {}).keys())
            if name in NO_NETWORK:
                if have or svc.get("network_mode") != "none":
                    self.fail("P-15", name)
            elif have != NETWORKS_PER_SERVICE.get(name, set()):
                self.fail("P-15", name)

    # P-8, P-10
    def p8_p10(self) -> None:
        for name, svc in self.services().items():
            if not self.args.cgroupfs and svc.get("cgroup_parent") != "webhooktrap.slice":
                self.fail("P-8", name)
            if not svc.get("mem_limit") or not svc.get("pids_limit"):
                self.fail("P-8", name)
            # `config` omits the default 0, so absent means 0; what matters is no negative value.
            if int(svc.get("oom_score_adj") or 0) < 0:
                self.fail("P-10", name)

    # P-9: env_file is inlined by `config`; a remaining entry must be .env.production.
    def p9(self) -> None:
        want = os.path.join(self.app_dir, ".env.production")
        for name, svc in self.services().items():
            for entry in svc.get("env_file") or []:
                path = entry.get("path") if isinstance(entry, dict) else entry
                if os.path.realpath(path or "") != os.path.realpath(want):
                    self.fail("P-9", name)

    # P-12
    def p12(self) -> None:
        svc = self.services().get("haproxy-config") or {}
        if svc.get("entrypoint") != HAPROXY_CONFIG_ENTRYPOINT:
            self.fail("P-12", "haproxy-config entrypoint")
        mounts = [(m.get("type"), m.get("source"), m.get("target")) for m in svc.get("volumes") or []]
        if mounts != [("volume", "haproxy_config", "/wt-haproxy")]:
            self.fail("P-12", "haproxy-config volumes")
        for key in ("secrets", "env_file", "environment", "command"):
            if svc.get(key):
                self.fail("P-12", "haproxy-config " + key)
        if svc.get("read_only") is not True or svc.get("restart") != "no":
            self.fail("P-12", "haproxy-config")

    # P-14
    def p14(self) -> None:
        for name, svc in self.services().items():
            have = {s.get("source") for s in svc.get("secrets") or []}
            if have != SECRETS_PER_SERVICE.get(name, set()):
                self.fail("P-14", name)

    # P-16
    def p16(self) -> None:
        for name in READ_ONLY:
            if (self.services().get(name) or {}).get("read_only") is not True:
                self.fail("P-16", name)
        env = self.env(self.services().get("haproxy") or {})
        if set(env) - HAPROXY_ENV:
            self.fail("P-16", "haproxy environment")
        for key in HAPROXY_ENV:
            if not re.fullmatch(r"[0-9]+", str(env.get(key) or "")):
                self.fail("P-16", "haproxy " + key)

    # P-17
    def p17(self) -> None:
        svcs = self.services()
        ingest = svcs.get("ingest") or {}
        env = self.env(ingest)
        if env.get("PROXY_TRUST_MODE") != "secret" or env.get("WT_ROLE") != "ingest" \
                or ingest.get("command") != INGEST_COMMAND:
            self.fail("P-17", "ingest")
        app = self.env(svcs.get("app") or {})
        if app.get("PROXY_TRUST_MODE") != "secret" or app.get("WT_ROLE") != "app" \
                or app.get("INGEST_MODE") != "proxy" or app.get("INGEST_UPSTREAM") != "haproxy:8080":
            self.fail("P-17", "app")
        threads = str(app.get("APP_NUM_THREADS") or "")
        if not re.fullmatch(r"[0-9]+", threads) or int(threads) < 4 \
                or str(app.get("LONGPOLL_HTTP_WORKERS")) != threads:
            self.fail("P-17", "app threads")
        if (svcs.get("haproxy") or {}).get("command") != HAPROXY_COMMAND:
            self.fail("P-17", "haproxy")

    # P-18, P-19, P-20
    def p18_to_p20(self) -> None:
        for name, svc in self.services().items():
            env = self.env(svc)
            if str(env.get("AGENT_ALLOW_DEV_BUILDS") or "") not in FALSY:
                self.fail("P-18", name)
            if name in LARAVEL and env.get("APP_ENV") != "production":
                self.fail("P-18", name)
            if name in LARAVEL and env.get("DB_HOST") != "postgres":
                self.fail("P-19", name)
            image = (svc.get("image") or "").lower()
            if any(p in image for p in POOLERS):
                mode = env.get("PGBOUNCER_POOL_MODE") or env.get("POOL_MODE") or env.get("pool_mode")
                if mode != "session":
                    self.fail("P-19", name)
            for key in PLAIN_SECRETS:
                if env.get(key) not in (None, ""):
                    self.fail("P-20", name)
                file_value = env.get(key + "_FILE")
                if file_value not in (None, "") and not str(file_value).startswith("/run/secrets/"):
                    self.fail("P-20", name)

    def run(self) -> list[tuple[str, str]]:
        for check in (self.p1, self.p2, self.p3_p4, self.p5_p13, self.p6, self.p7_p15,
                      self.p8_p10, self.p9, self.p12, self.p14, self.p16, self.p17, self.p18_to_p20):
            check()
        return self.failures


def main(argv: list[str]) -> int:
    parser = argparse.ArgumentParser(description=__doc__.split("\n")[0])
    parser.add_argument("--config", required=True)
    parser.add_argument("--app-ref", required=True)
    parser.add_argument("--pg-ref", required=True)
    parser.add_argument("--app-dir", required=True)
    parser.add_argument("--app-port", required=True)
    parser.add_argument("--cgroupfs", action="store_true", help="P-8 variant without cgroup_parent")
    args = parser.parse_args(argv)
    for ref in (args.app_ref, args.pg_ref):
        if not DIGEST.search(ref):
            print("policy: references must be pinned by digest", file=sys.stderr)
            return 1
    try:
        with open(args.config, encoding="utf-8") as handle:
            cfg = json.load(handle)
    except (OSError, ValueError):
        print("policy: cannot read the resolved configuration", file=sys.stderr)
        return 1
    failures = Policy(cfg, args).run()
    for rule, where in failures:
        print(f"policy: {rule} {where}", file=sys.stderr)
    if failures:
        return 1
    print("policy: ok")
    return 0


if __name__ == "__main__":
    sys.exit(main(sys.argv[1:]))
