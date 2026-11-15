# Logo exploration · Timetable

**Status:** PROPOSED for PO review · **Author:** A02 · **Date:** 2026-11-14
**Canvas:** [Webhook Trap · Timetable · Logo exploration](https://claude.ai/artifact/D579LRxyW9S96UXqmZQPCQ) (22 artboards: 4 concept rows × 5 boards, a comparison board, a wordmark board). The canvas is private until shared from its Share menu.
**SVG files for A04:** `claude-design/timetable-logos/svg/` (per concept: `*-mark-{light,dark}.svg`, `*-favicon-{light,dark}.svg`, `*-lockup-horizontal-{light,dark}.svg`, `*-lockup-stacked-{light,dark}.svg`; shared `wordmark-{light,dark}.svg`).

Nothing in the Timetable Design System or the Timetable screens canvas was changed.

## Shared rules

- Every mark is drawn on a 16-unit grid (1 unit = 1 px at favicon size), with 2u ink strokes. It uses only horizontal, vertical and 45° lines, the system's icon geometry.
- **Colour:** ink for the lines, and `conflict` red for one element only: the catch. No magenta and no amber in any mark.
- **Contrast on ground:** ink is 16.1:1 (light) and 15.0:1 (dark). Red is 5.5:1 (light) and 5.9:1 (dark). Every mark is above 3:1, and every text pair on the boards is above 4.5:1.
- **Lockup ratios (all concepts):**
  - Horizontal: the mark is 1.6 × the cap height, the gap is 0.6 × the cap height, and the mark is centred on the cap height.
  - Stacked: the mark's width is 0.28 × the wordmark's width.
- **Wordmark: constructed, not Archivo.** No Archivo font file or outlining tool (fontTools, Inkscape) was available, and installs were out of scope. I drew a custom geometric wordmark instead and exported it as filled outline paths.
  - Metrics: cap height 20u, x-height 14u, stroke 3u.
  - Bowls are chamfered rectangles (3u chamfers) instead of curves.
  - All four concepts share it. If the PO prefers Archivo 87.5/650 outlines, A04 can swap them in without changing any mark.

## 1 · Points (refined current mark)

- **Idea:** these are trap points. A bad path is diverted off the main line and held at a buffer stop before it fouls production. This is the territory's "Trap" story.
- **Construction:** the main line runs at y = 5 across the full width. A spur leaves it at x = 3, drops at 45° for 5u, then runs level for 3u. A 4 × 4u red stop sits at x 11–15, y 8–12.
- **What changed from the system's mark:**
  - The spur now levels off before the stop, so it reads as a siding rather than a tick or an arrow.
  - The stop grows from 3u to 4u so the red holds at 16 px.
- **Rationale:** it has the clearest story and is the most legible at 16 px of the three-shape marks. The main line and stop sit on whole pixels.
- **Risks:**
  - It says "catch", not "proof under real delivery conditions".
  - It is weakest for Horizon 2, because it pictures pre-production only.
- **Resemblance check:** I compared it with railway-operator marks (British Rail/National Rail double arrow, NS, SBB, DB, SNCF, Amtrak) and found no match. The closest generic shapes are a "turn right" road sign or an ↘ arrow; the levelled spur and the square stop move it away from both.

## 2 · Twice (new)

- **Idea:** the same event is delivered twice. D1 lands cleanly on the state rail. D2 runs parallel and lands on a red conflict square (state 1 → 2). It is the Delivery Graph's signature finding in four shapes.
- **Construction:**
  - The sent rail is at y = 3 and runs the full width.
  - The state rail is at y = 12 and stops at x = 12.
  - D1 starts at x = 1 and D2 at x = 6. Both run at 45°, 5u apart, leaving a 1.5u clear gap.
  - A 4u red square sits on D2's terminus.
- **Rationale:**
  - It is the only mark that is the product: graph geometry, the #1 failure mode, and red on the terminus exactly as in the product.
  - It grows naturally into the hero graph on the social card.
  - It carries into Horizon 2 unchanged (the same graph over production time).
- **Risks:**
  - At 16 px the two paths keep a 1.5 px gap. Below 14 px they merge, so the hard minimum size is 16 px.
  - It asks for some graph literacy: the 10-second comprehension test (A03) should include it.
  - It belongs to the "two rails plus diagonals" family of rail symbols.
- **Resemblance check:**
  - **British Rail double arrow:** two interlocked arrows across two parallel lines, 1965, Gerry Barney. This is the closest family. Twice avoids it with no arrowheads, paths that are parallel rather than zig-zag, and both rails starting at the same edge. A misuse board forbids adding arrowheads.
  - **NS (Dutch Railways):** search did not return a verified description of its mark. It is reportedly a double-arrow design. Flag it for a visual check.

## 3 · Second pass (new, letterform)

- **Idea:** a W for webhook, drawn as one continuous path that goes down, comes back up and goes down again. Providers deliver at least once, and the conflict square sits where the two passes meet.
- **Construction:** stems at x = 2 and x = 14 rise from y = 2, with 45° chamfers. A 45° V peaks at (8, 9). A 4u red keystone sits at x 6–10, y 6–10. It shares its strokes and chamfers with the constructed wordmark's W.
- **Rationale:** it is the only concept that is also an initial, which helps app icons and avatars. It gives the strongest wordmark/mark kinship.
- **Risks:**
  - The horizontal lockup reads "W Webhook", so the stacked lockup or the mark alone is preferred.
  - The idea (two passes) needs explaining.
  - The four short diagonals soften at 16 px.
- **Resemblance check:** I checked Webflow (an angular W), Westpac (a red W), Volkswagen (VW in a circle) and Wikipedia (a serif W). None has vertical stems with a central V and a square keystone.

## 4 · Proves (new, sign)

- **Idea:** the logician's turnstile ⊢ reads "proves": from these deliveries, this verdict follows. Drawn as track, it is a line meeting a fixed stop. The red bar is the stop.
- **Construction:** a red 3 × 12u bar at x 2–5, y 2–14, and a 2u ink line on y = 8 running to the right edge. There are no diagonals.
- **Rationale:**
  - It is pixel-exact at every size and survives down to 12 px.
  - It has a sharp "proof" meaning for a technical audience.
  - In the horizontal lockup it reads "⊢ Webhook Trap".
- **Risks:**
  - It is a standard glyph (U+22A2), which makes it weak as a trademark and dependent on its colour and proportion.
  - It has no time axis, so it extends poorly to Horizon 2.
  - It speaks "proof" more than "timetable".
- **Resemblance check:**
  - The ⊢/⊣ glyph itself, and generic "end of track" signage.
  - T-Mobile (a magenta T with squares) was checked because a rotated ⊢ is a T. The misuse board forbids rotating it and forbids magenta.
  - Cloudflare Turnstile shares only the word, which is why the concept is named "Proves".

## Resemblance checks: method and limits

- Web search confirmed the British Rail double arrow's construction (two interlocked arrows across two parallel lines, symbolising double track; Wikipedia, Logo Design Love).
- Search did **not** return usable descriptions of the Hookdeck, Svix or NS marks. My checks against Hookdeck, Svix, ngrok and Stripe come from memory:
  - Stripe is a wordmark, sometimes shown as an "S" tile.
  - ngrok is a wordmark.
  - Svix is a small geometric mark.
  - Hookdeck is a mark I recall as hook/deck themed.
  - None of the four concepts uses a hook, an S, a lowercase-wordmark-only identity, or rounded "webhook" iconography.
- The PO or legal should run a visual check against those three logos and NS before adoption, plus a trademark search on the chosen mark.

## Recommendation (PROPOSED: the PO decides)

**Recommended: 2 · Twice.**

- It is the only concept whose mark is the product's argument (webhooks fail in sequences), drawn in the product's own graph language.
- Its red sits exactly where the product puts red.
- It scales from the favicon to the landing hero, and it needs no rebrand for Horizon 2 monitoring.
- It reads less instantly than Points and costs 16 px headroom. I would accept that cost for a mark that says what we do.

**Runner-up: 1 · Points.** It is the safest at small sizes and keeps the "trap points" naming story. Its copy story can live on with Twice anyway: "trap points" can remain the explanation of the name in the About text.

**What would change this:**

1. **A03's comprehension test fails for Twice.** If developers do not read the parallel paths as "the same event, twice" within 10 seconds, I would switch to Points.
2. **Real-browser favicon tests fail.** If Chrome, Firefox or Safari tabs at 1× DPI show D1 and D2 merging at 16 px, I would switch to Points, or keep Twice with a simplified favicon.
3. **Legal or visual review finds Twice too close to a rail operator's symbol** (British Rail double arrow or NS). I would switch to Points.
4. **The PO wants a single initial for the app icon.** Second pass becomes the candidate.
