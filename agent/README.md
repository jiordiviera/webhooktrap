# webhooktrap engine (Go)

One binary for the local agent, CI runs, the server runner and the network-free
sandbox (ADR-010). Toolchain pinned in `go.mod`; standard library plus `gopkg.in/yaml.v3` (decision 64).

| Package | Role |
| ------- | ---- |
| `plan` | pure: selection + scenarios + pack + seed → Plan (PLAN-1..4, TAMP-1, PRB-4) |
| `execute` | the only stage on the network: transport, gaps, burst barrier, probes, reset (Clock, Scheduler, Transport, Prober, Resetter ports) |
| `guard` | `local` mode target allow-list, resolve once and pin (TGT-1..5, TM-55) |
| `signing` | Stripe, GitHub, Shopify, Standard Webhooks; secrets never printable |
| `judge` | pure: Plan + Observations → Verdict (SJ §3–§7) |
| `render` | pure: run document (local profile) and terminal text |
| `scrub` | PV-2 display values, secret masking, fixture scrubbing (SCR-1..4) |
| `pack` | pack types and loader; data comes from the shared `packs/` (embedded copy in `internal/packdata`) |

```bash
mise exec go@1.27.4 -- go test ./...
mise exec go@1.27.4 -- go test -race ./...
mise exec go@1.27.4 -- go generate ./internal/packdata   # after any change to ../packs
UPDATE_GOLDEN=1 mise exec go@1.27.4 -- go test ./cmd/webhooktrap -run Golden   # reviewed golden changes only
mise exec go@1.27.4 -- go run ./cmd/webhooktrap test --help
```

`webhooktrap test` reads `webhooktrap.yml` (target, `secret: env:NAME`,
fixtures, scenarios, budget, probe with `cmd:` or `url:`, settle, reset),
runs plan → execute → judge, prints the run, writes the run document to
`.webhooktrap/runs/<run id>.json` and exits per `--fail-on` (EXIT-1).
`webhooktrap rejudge run.json` recomputes the verdict from the file (JSON-4).

### Probe templates (`{{T.id}}`)

A probe can name an object of the selected events: `{{invoice.id}}` is the id
of the first `invoice` among them (PRB-4). These values come from captured
bodies, which anyone who knows a capture URL can write, so:

- A value is used only if it is a plain id: 1 to 128 characters from
  `[A-Za-z0-9_-]`, and it matches the pack's `id_pattern` when the pack has
  one. Both lookups (the pack object's id field, and a field named `invoice`)
  apply the rule. Anything else is refused: `webhooktrap test` exits 3,
  `webhooktrap agent` aborts the run with `capture_verification_failed`.
- A `cmd:` probe never receives the value inside its command text. The value
  is passed in the environment as `WT_<TYPE>_ID` (`{{invoice.id}}` →
  `WT_INVOICE_ID`), and the template is replaced by a reference to that
  variable written for where it sits: `"${WT_INVOICE_ID}"` unquoted,
  `${WT_INVOICE_ID}` inside double quotes, `'"${WT_INVOICE_ID}"'` inside
  single quotes. The shell expands it after parsing, so it is one word and
  never shell syntax. You can also write the reference yourself.
- On Windows the reference is `!WT_INVOICE_ID!` and commands run with
  `cmd.exe /D /V:ON /S /C`, so it is expanded after cmd.exe has parsed the
  line. Never write `%WT_INVOICE_ID%` (expanded before parsing). With delayed
  expansion on, a literal `!` in a command must be escaped (`^!`).
- `url:` probes get the value query-escaped, and the URL still passes the
  target guard.

```yaml
probe:
  # The template sits inside the double-quoted SQL: it becomes
  # '${WT_INVOICE_ID}', expanded by the shell. The SQL quotes hold because
  # the value can only be [A-Za-z0-9_-].
  - as: orders.status
    cmd: psql "$DATABASE_URL" -Atc "select status from orders where stripe_invoice_id = '{{invoice.id}}'"
  # The same probe with the variable written out:
  - as: orders.count
    cmd: psql "$DATABASE_URL" -Atc "select count(*) from orders where stripe_invoice_id = '$WT_INVOICE_ID'"
  # To let psql quote the value itself, use a psql variable. psql does not
  # interpolate variables in -c, so the SQL goes through stdin:
  - as: orders.paid
    cmd: echo "select paid from orders where stripe_invoice_id = :'id'" | psql "$DATABASE_URL" -At -v id="$WT_INVOICE_ID"
  - as: ledger
    url: http://localhost:3000/_debug/ledger?invoice={{invoice.id}}
```

Goldens: `testdata/golden/` (RD Run #3 against the buggy reference handler in
`internal/refhandler`, and its fixed twin).

## Connected mode (`/agent/v1`, contracts §5)

```bash
webhooktrap pair 7KQ2M-XR9TD --server https://webhooktrap.dev   # asks [y/N] before joining
webhooktrap agent                     # hello every 20 s, long-poll jobs/next, run jobs
webhooktrap agent --once              # exit after the first run job
webhooktrap agent unpair              # DELETE /agent/v1/agent and delete the local key
```

- One Ed25519 key per server origin, `0600` in a `0700` directory under the OS
  config directory (`$WEBHOOKTRAP_CONFIG_DIR` overrides it); a key readable by
  others is refused. `agent.json` holds the agent id, origin and key path;
  tokens are never written or printed.
- The pairing lives in `agents/<slug>-<hash>/`, where the hash is the first
  128 bits of SHA-256 over the exact origin, so two origins never share a
  directory or key. On Windows the key's access list isn't checked (no ACL API
  in the standard library); the agent says so on `pair` and on every start.
  OS keychain storage (Keychain, Secret Service, Credential Manager) is a
  later proposal; until then the file-only key is an accepted MVP deviation,
  limited by origin-bound signatures.
- `https://` is required and verified against the system roots. Plain
  `http://` is accepted only with `--insecure-dev-origin` and only for a local
  development origin (`localhost`, `*.localhost`, `*.test`, loopback). It is
  refused when the server reports itself as hosted (pair's `server.kind`, or
  hello's optional `server.kind`), and a warning is printed on every start.
  A dev origin never goes through `HTTP_PROXY`. Redirects are never followed.
- `webhooktrap agent pair <CODE>` is an alias of `webhooktrap pair <CODE>`.
- `pair` stores and prints the name the server kept (`maya-mbp-2` when
  `maya-mbp` is taken). Before the `[y/N]` it prints the server, workspace,
  agent name and this key's fingerprint (`SHA256:…`, as the browser shows
  it). When the code replaces an agent, the pair answer's optional
  `replaces: {agent_id, name, key_fingerprint}` is shown too, and the
  question is always asked, even with `--yes`.
- A run from the browser is accepted only for the target declared in
  `webhooktrap.yml`, approved once in this terminal (with the side-effect
  acknowledgement) per (origin, agent, scheme, host, port), with probe and
  reset commands confirmed per file hash. Without a TTY the agent declines
  with `needs_confirmation`, prints one line for that job, and waits 5 s,
  10 s, 20 s, … up to 1 min before it checks again.
- A job's `target_ref` must be printable ASCII without spaces.
- Captures are checked against the job's `body_sha256`, header count and JSON
  depth before use. Observations stream per delivery; the final document is
  the hosted profile (`render.Hosted`: no prose, display values only). Event
  types and provider event ids outside the schema charset are sent as `""`.
  Every upload (the plan, each observation batch, the final document) is
  checked for the signing secret and refused if it contains it.
- A local failure after accept calls `POST /agent/v1/jobs/{id}/abort`:
  `capture_verification_failed` (a capture failed its checks, or a probe
  value is not a plain id), `interrupted` (Ctrl-C), `internal_error` (engine
  error, refused final document).
- Everything printed that came from a capture, the server, a pack or a file
  goes through one cleaner (`scrub.Terminal`): controls become `?`, tab and
  line breaks (including U+2028/U+2029) a space, and format characters
  (bidi, zero-width, soft hyphen, tags) are dropped.
- Realtime nudges (`private-agent.{id}`) are not used yet: long-poll only.
  Verify jobs are left pending.
