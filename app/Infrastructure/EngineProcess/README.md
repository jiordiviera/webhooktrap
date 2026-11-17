# EngineProcess (infrastructure adapter)

Calls the `engine` sandbox over a Unix socket for network-free `plan` and `verify` only; output is untrusted (ADR-010 §1).

Slice 1 implements `plan` (contracts §3.2 `Runs/Draft`, run `delivery_count`):

- `Engine` is the port (bound in `AppServiceProvider` like `Clock`); `SocketEngine` is the client of `webhooktrap serve-sandbox` at `config('engine.socket')` (`ENGINE_SOCKET`, default `/run/engine/engine.sock`). Wire format per `agent/internal/sandbox/protocol.go`: one call per connection, 4-byte big-endian length + JSON `{"op":"plan","input":PlanInput}` (≤ 6 MiB, refused locally above that as `frame_too_large`), write side half-closed, one NDJSON reply line ≤ 1 MiB. One deadline (`engine.timeout_ms`, 8 s; connect 1 s) covers connect, write and read.
- `ReplyDecoder` checks the reply like any untrusted input (S-11, TM-74): proto 1, op `plan` (or `""` for the server's own refusals), exact key sets, integer types, delivery count 0..50 and equal to the sum of the scenarios', `plan_hash` 64 hex, pack id equal to the request's, budget 1..600 000 ms, codes in `^[a-z0-9_]{1,64}$`, scenario names among the request's, event refs among the request's, event types in the hosted token charset. Anything else is `bad_reply`; engine text never leaves the adapter.
- `PlanResult` is a `PlanPreview` or a code: `engine_unavailable`, `timeout`, `busy`, `bad_reply`, `frame_too_large`, or the engine's own code. `transient()` marks call faults (never cached by callers).
- No state between calls (worker mode). Rate limiting (TM-18) and caching live in the caller (`Proving\Application\RunPlanner`) through the RateLimit and Cache adapters.

Tests: `tests/Unit/Infrastructure/EngineProcessTest.php` (fake socket server in `tests/Support/fake-engine.php`; the real `serve-sandbox` when `agent/bin/webhooktrap` exists, built with `CGO_ENABLED=0` so the per-call re-exec finds the binary itself under a musl loader).
