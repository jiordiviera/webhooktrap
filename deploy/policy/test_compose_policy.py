"""TM-T146: the Compose policy passes the real hosted file and refuses each breaking fixture.

Run from the repository root (CI `infra` job):
    python3 -I deploy/policy/test_compose_policy.py RESOLVED_JSON APP_DIR

RESOLVED_JSON is `docker compose --project-directory APP_DIR --env-file .env.deploy
-f compose.production.yaml config --format json` with test values; every fixture below is a
copy of it with one change, and must fail with the named rule.
"""

from __future__ import annotations

import copy
import importlib.util
import json
import os
import sys
import unittest
from argparse import Namespace

HERE = os.path.dirname(os.path.abspath(__file__))
spec = importlib.util.spec_from_file_location("compose_policy", os.path.join(HERE, "compose-policy.py"))
policy = importlib.util.module_from_spec(spec)
spec.loader.exec_module(policy)

RESOLVED, APP_DIR = sys.argv[1], sys.argv[2]
with open(RESOLVED, encoding="utf-8") as handle:
    BASE = json.load(handle)
APP_REF = BASE["services"]["app"]["image"]
PG_REF = BASE["services"]["postgres"]["image"]
APP_PORT = BASE["services"]["app"]["ports"][0]["published"]


def rules(cfg: dict) -> set[str]:
    args = Namespace(app_ref=APP_REF, pg_ref=PG_REF, app_dir=APP_DIR, app_port=APP_PORT, cgroupfs=False)
    return {rule for rule, _ in policy.Policy(cfg, args).run()}


def mutate(fn) -> dict:
    cfg = copy.deepcopy(BASE)
    fn(cfg)
    return cfg


S = lambda cfg, name: cfg["services"][name]  # noqa: E731

FIXTURES = {
    # rule: (description, mutation)
    "privileged": ("P-3", lambda c: S(c, "horizon").update(privileged=True)),
    "host bind of /": ("P-5", lambda c: S(c, "horizon")["volumes"].append({"type": "bind", "source": "/", "target": "/host"})),
    "docker socket": ("P-5", lambda c: S(c, "app")["volumes"].append({"type": "bind", "source": "/var/run/docker.sock", "target": "/var/run/docker.sock"})),
    "port on all interfaces": ("P-6", lambda c: S(c, "app")["ports"][0].update(host_ip="0.0.0.0")),
    "extra cap_add": ("P-4", lambda c: S(c, "app").update(cap_add=["NET_ADMIN"])),
    "volume with driver_opts bind of /": ("P-5", lambda c: c["volumes"]["storage"].update(driver="local", driver_opts={"type": "none", "o": "bind", "device": "/"})),
    "configs entry": ("P-5", lambda c: c.update(configs={"x": {"file": "/etc/shadow"}})),
    "secret outside secrets/": ("P-5", lambda c: c["secrets"]["app_key"].update(file="/etc/shadow")),
    "negative oom_score_adj": ("P-10", lambda c: S(c, "app").update(oom_score_adj=-100)),
    "missing mem_limit": ("P-8", lambda c: S(c, "reverb").pop("mem_limit")),
    "extra service": ("P-1", lambda c: c["services"].update(shell=copy.deepcopy(S(c, "scheduler")))),
    "unpinned app image": ("P-2", lambda c: S(c, "app").update(image="ghcr.io/someone/else:latest")),
    "haproxy label from another digest": ("P-2", lambda c: S(c, "haproxy")["labels"].update({"webhooktrap.config-from": "sha256:" + "0" * 64})),
    "user on reverb": ("P-3", lambda c: S(c, "reverb").update(user="0:0")),
    "network_mode host": ("P-3", lambda c: S(c, "app").update(network_mode="host")),
    "haproxy-config gets a secret": ("P-12", lambda c: S(c, "haproxy-config").update(secrets=[{"source": "app_key", "target": "/run/secrets/app_key"}])),
    "haproxy-config other entrypoint": ("P-12", lambda c: S(c, "haproxy-config").update(entrypoint=["sh", "-c", "id"])),
    "haproxy_config volume on app": ("P-13", lambda c: S(c, "app")["volumes"].append({"type": "volume", "source": "haproxy_config", "target": "/wt-haproxy"})),
    "haproxy mounts it read-write": ("P-13", lambda c: S(c, "haproxy")["volumes"][0].update(read_only=False)),
    "edge secret on reverb": ("P-14", lambda c: S(c, "reverb")["secrets"].append({"source": "edge_proxy_secret", "target": "/run/secrets/edge_proxy_secret"})),
    "identity key on ingest": ("P-14", lambda c: S(c, "ingest")["secrets"].append({"source": "identity_email_hmac_key", "target": "/run/secrets/identity_email_hmac_key"})),
    "ingest on egress": ("P-15", lambda c: S(c, "ingest")["networks"].update(egress=None)),
    "ingest writable root": ("P-16", lambda c: S(c, "ingest").update(read_only=False)),
    "haproxy extra environment": ("P-16", lambda c: S(c, "haproxy")["environment"].update(DEBUG="1")),
    "empty haproxy limit": ("P-16", lambda c: S(c, "haproxy")["environment"].update(WT_CAPTURE_MAX_CONN="")),
    "ingest without WT_ROLE": ("P-17", lambda c: S(c, "ingest")["environment"].pop("WT_ROLE")),
    "app bypasses haproxy": ("P-17", lambda c: S(c, "app")["environment"].update(INGEST_UPSTREAM="ingest:8080")),
    "long-poll workers apart from threads": ("P-17", lambda c: S(c, "app")["environment"].update(LONGPOLL_HTTP_WORKERS="16")),
    "dev agent builds": ("P-18", lambda c: S(c, "app")["environment"].update(AGENT_ALLOW_DEV_BUILDS="1")),
    "APP_ENV local": ("P-18", lambda c: S(c, "horizon")["environment"].update(APP_ENV="local")),
    "transaction pooler": ("P-19", lambda c: c["services"].update(pgbouncer={**copy.deepcopy(S(c, "valkey")), "image": "edoburu/pgbouncer@sha256:" + "1" * 64, "environment": {"POOL_MODE": "transaction"}})),
    "DB_HOST elsewhere": ("P-19", lambda c: S(c, "app")["environment"].update(DB_HOST="pgbouncer")),
    "plain APP_KEY": ("P-20", lambda c: S(c, "app")["environment"].update(APP_KEY="base64:plain")),
    "_FILE outside /run/secrets": ("P-20", lambda c: S(c, "horizon")["environment"].update(MAIL_PASSWORD_FILE="/app/.env")),
    "ssr gets a secret": ("P-14", lambda c: S(c, "ssr").update(secrets=[{"source": "app_key", "target": "/run/secrets/app_key"}])),
    "ssr on internal": ("P-15", lambda c: S(c, "ssr")["networks"].update(internal=None)),
    "ssr on egress": ("P-15", lambda c: S(c, "ssr")["networks"].update(egress=None)),
    "ssr network not internal": ("P-7", lambda c: c["networks"]["ssr"].pop("internal")),
    "ssr as root": ("P-3", lambda c: S(c, "ssr").update(user="0:0")),
    "ssr writable root": ("P-16", lambda c: S(c, "ssr").update(read_only=False)),
    "ssr published port": ("P-6", lambda c: S(c, "ssr").update(ports=[{"host_ip": "127.0.0.1", "target": 13714, "published": "13714"}])),
    "ssr keeps capabilities": ("P-4", lambda c: S(c, "ssr").pop("cap_drop")),
    "ssr other entrypoint": ("P-21", lambda c: S(c, "ssr").update(entrypoint=["sh", "-c", "id"])),
    "ssr reads the env file": ("P-21", lambda c: S(c, "ssr").update(env_file=[os.path.join(APP_DIR, ".env.production")])),
    "ssr extra environment": ("P-21", lambda c: S(c, "ssr")["environment"].update(DB_PASSWORD_FILE="/run/secrets/db_password")),
    "ssr storage volume": ("P-21", lambda c: S(c, "ssr").update(volumes=[{"type": "volume", "source": "storage", "target": "/app/storage/app"}])),
    "ssr without healthcheck": ("P-21", lambda c: S(c, "ssr").pop("healthcheck")),
    "ssr memory above budget": ("P-21", lambda c: S(c, "ssr").update(mem_limit="1073741824")),
    "app SSR URL elsewhere": ("P-21", lambda c: S(c, "app")["environment"].update(INERTIA_SSR_URL="http://evil.example:13714")),
}


class PolicyTest(unittest.TestCase):
    def test_real_file_passes(self) -> None:
        self.assertEqual(rules(BASE), set())

    def test_each_fixture_fails_with_its_rule(self) -> None:
        for name, (rule, fn) in FIXTURES.items():
            with self.subTest(fixture=name):
                self.assertIn(rule, rules(mutate(fn)))

    def test_output_never_carries_a_value(self) -> None:
        cfg = mutate(lambda c: S(c, "app")["environment"].update(APP_KEY="base64:CANARY-VALUE"))
        args = Namespace(app_ref=APP_REF, pg_ref=PG_REF, app_dir=APP_DIR, app_port=APP_PORT, cgroupfs=False)
        printed = " ".join(f"{r} {w}" for r, w in policy.Policy(cfg, args).run())
        self.assertNotIn("CANARY", printed)


if __name__ == "__main__":
    unittest.main(argv=[sys.argv[0]], verbosity=1)
