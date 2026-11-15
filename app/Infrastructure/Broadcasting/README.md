# Broadcasting (infrastructure adapter)

Reverb channels, broadcast auth endpoints and the per-principal connection cap / lifetime (decision 50).

Implemented (slice 4):

- `LiveUpdates::trigger($channel, $event, $payload)`: a Live Prop trigger, queued after commit on
  `broadcasts`, coalesced per (channel, event) by `LiveTrigger` (unique until processing). Payloads
  stay under 1 KiB. Failing to queue is reported and swallowed: persistence never depends on Reverb.
- `ChannelSigner`: the Pusher-protocol subscription signature with `channel_data.user_id = <type>:<id>`
  on private channels (event-catalogue §1 rule 6). Who may subscribe is decided by the browser auth
  endpoint (Identity, `POST /broadcasting/auth`) and the modules' `BrowserChannel` implementations;
  this adapter knows no module.

Not yet: the agent auth endpoint, `terminate_connections`, the per-principal cap and lifetime inside
the Reverb process.
