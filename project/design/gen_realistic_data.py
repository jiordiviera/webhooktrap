"""Generate realistic-data.json and realistic-data.md for Webhook Trap 2.0 design work.

All ids, hashes and signatures are FAKE: derived from sha256 of fixed seeds.
They are correctly formatted but not computed from any secret.
"""
import base64, hashlib, json, os, random, sys
from datetime import datetime, timezone, timedelta

OUT_DIR = sys.argv[1] if len(sys.argv) > 1 else os.path.dirname(os.path.abspath(__file__))
rng = random.Random(20261111)
CROCK = "0123456789ABCDEFGHJKMNPQRSTVWXYZ"
ALNUM = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789"


def h(seed):
    return hashlib.sha256(seed.encode()).hexdigest()


def b64(seed):
    return base64.b64encode(hashlib.sha256(seed.encode()).digest()).decode()


def alnum(n):
    return "".join(rng.choice(ALNUM) for _ in range(n))


def ulid(dt):
    ms = int(dt.timestamp() * 1000)
    t = ""
    for _ in range(10):
        t = CROCK[ms % 32] + t
        ms //= 32
    return t + "".join(rng.choice(CROCK) for _ in range(16))


def uuid4():
    x = h(alnum(12))
    return f"{x[0:8]}-{x[8:12]}-4{x[13:16]}-a{x[17:20]}-{x[20:32]}"


def iso(dt):
    return dt.astimezone(timezone.utc).isoformat(timespec="milliseconds").replace("+00:00", "Z")


def unix(dt):
    return int(dt.timestamp())


T0 = datetime(2026, 11, 11, 13, 58, 4, 212000, tzinfo=timezone.utc)
HOST = "webhooktrap.dev"
PROJECT_INBOX = "Br7nDLk2Qm9x"
GUEST_INBOX = "k7Qm2xVb9TfL"

# ---------------------------------------------------------------- ids
acct = "acct_1Pz" + alnum(13)
cus_a = "cus_R" + alnum(13)
cus_b = "cus_R" + alnum(13)
sub_a = "sub_1QH" + alnum(21)
sub_b = "sub_1QG" + alnum(21)
in_a = "in_1QK" + alnum(21)
in_prev = "in_1Q9" + alnum(21)
pi_a = "pi_3QK" + alnum(21)
ch_a = "ch_3QK" + alnum(21)
pm_a = "pm_1PZ" + alnum(21)
price_a = "price_1PZ" + alnum(21)
si_a = "si_R" + alnum(13)
si_b = "si_Q" + alnum(13)
il_a = "il_1QK" + alnum(21)
evt = {k: "evt_1QK" + alnum(21) for k in ["ip", "pi", "su", "ips", "su_b"]}

period_end = datetime(2026, 12, 11, 13, 57, 31, tzinfo=timezone.utc)
period_start = datetime(2026, 11, 11, 13, 57, 31, tzinfo=timezone.utc)
prev_start = datetime(2026, 10, 11, 13, 57, 31, tzinfo=timezone.utc)

# ---------------------------------------------------------------- bodies
def stripe_event(eid, etype, created, obj, prev=None):
    data = {"object": obj}
    if prev is not None:
        data["previous_attributes"] = prev
    return {
        "id": eid,
        "object": "event",
        "api_version": "2024-06-20",
        "created": unix(created),
        "data": data,
        "livemode": False,
        "pending_webhooks": 1,
        "request": {"id": None, "idempotency_key": "in_auto_" + alnum(10)},
        "type": etype,
    }


def stripe_body(ev):
    return json.dumps(ev, indent=2)  # Stripe sends pretty-printed JSON


def compact(o):
    return json.dumps(o, separators=(",", ":"))


invoice_obj = {
    "id": in_a,
    "object": "invoice",
    "account_country": "US",
    "account_name": "Brindle Coffee Co.",
    "amount_due": 2400,
    "amount_paid": 2400,
    "amount_remaining": 0,
    "attempt_count": 1,
    "billing_reason": "subscription_cycle",
    "charge": ch_a,
    "collection_method": "charge_automatically",
    "created": unix(T0) - 2,
    "currency": "usd",
    "customer": cus_a,
    "customer_email": "jonah.reyes@example.com",
    "customer_name": "Jonah Reyes",
    "hosted_invoice_url": "https://invoice.stripe.com/i/" + acct + "/test_" + alnum(40),
    "lines": {
        "object": "list",
        "data": [{
            "id": il_a,
            "object": "line_item",
            "amount": 2400,
            "currency": "usd",
            "description": "1 × Brindle Monthly, Whole Bean (at $24.00 / month)",
            "period": {"end": unix(period_end), "start": unix(period_start)},
            "price": {"id": price_a, "object": "price", "unit_amount": 2400, "recurring": {"interval": "month", "interval_count": 1}},
            "quantity": 1,
            "subscription": sub_a,
            "type": "subscription",
        }],
        "has_more": False,
        "total_count": 1,
        "url": "/v1/invoices/" + in_a + "/lines",
    },
    "livemode": False,
    "number": "BRNDL-0042",
    "paid": True,
    "payment_intent": pi_a,
    "period_end": unix(period_start),
    "period_start": unix(prev_start),
    "status": "paid",
    "subscription": sub_a,
    "subtotal": 2400,
    "total": 2400,
}

pi_obj = {
    "id": pi_a,
    "object": "payment_intent",
    "amount": 2400,
    "amount_received": 2400,
    "capture_method": "automatic",
    "created": unix(T0) - 2,
    "currency": "usd",
    "customer": cus_a,
    "description": "Subscription update",
    "invoice": in_a,
    "latest_charge": ch_a,
    "livemode": False,
    "metadata": {},
    "payment_method": pm_a,
    "payment_method_types": ["card"],
    "status": "succeeded",
}

sub_obj = {
    "id": sub_a,
    "object": "subscription",
    "billing_cycle_anchor": unix(prev_start) - 86400 * 30,
    "cancel_at_period_end": False,
    "collection_method": "charge_automatically",
    "currency": "usd",
    "current_period_end": unix(period_end),
    "current_period_start": unix(period_start),
    "customer": cus_a,
    "items": {"object": "list", "data": [{"id": si_a, "object": "subscription_item", "price": {"id": price_a, "unit_amount": 2400, "recurring": {"interval": "month"}}, "quantity": 1}], "has_more": False, "total_count": 1},
    "latest_invoice": in_a,
    "livemode": False,
    "metadata": {"plan": "monthly-whole-bean"},
    "status": "active",
}

sub_b_obj = {
    "id": sub_b,
    "object": "subscription",
    "cancel_at": unix(datetime(2026, 11, 28, 9, 12, 5, tzinfo=timezone.utc)),
    "cancel_at_period_end": True,
    "canceled_at": unix(T0) + 300,
    "cancellation_details": {"comment": None, "feedback": "too_expensive", "reason": "cancellation_requested"},
    "current_period_end": unix(datetime(2026, 11, 28, 9, 12, 5, tzinfo=timezone.utc)),
    "current_period_start": unix(datetime(2026, 10, 28, 9, 12, 5, tzinfo=timezone.utc)),
    "customer": cus_b,
    "items": {"object": "list", "data": [{"id": si_b, "object": "subscription_item", "price": {"id": price_a, "unit_amount": 2400, "recurring": {"interval": "month"}}, "quantity": 1}], "has_more": False, "total_count": 1},
    "livemode": False,
    "status": "active",
}

shop_order_id = 5873201946743
shop_order_id_b = 5873204390011
cust_shop = 7421093380215


def shopify_order(oid, num, created_local, email, first, last, items, total, fin="paid"):
    return {
        "id": oid,
        "admin_graphql_api_id": f"gid://shopify/Order/{oid}",
        "confirmation_number": alnum(9).upper(),
        "contact_email": email,
        "created_at": created_local,
        "currency": "USD",
        "current_total_price": total,
        "email": email,
        "financial_status": fin,
        "fulfillment_status": None,
        "name": f"#{num}",
        "order_number": num,
        "processed_at": created_local,
        "subtotal_price": f"{float(total) - 5.0:.2f}",
        "total_price": total,
        "total_shipping_price_set": {"shop_money": {"amount": "5.00", "currency_code": "USD"}},
        "total_tax": "0.00",
        "customer": {"id": cust_shop if num == 1042 else cust_shop + 977, "email": email, "first_name": first, "last_name": last},
        "line_items": items,
        "shipping_lines": [{"code": "USPS Ground Advantage", "price": "5.00", "title": "Standard shipping"}],
    }


items_a = [
    {"id": 14820017561719, "price": "16.00", "product_id": 8840219623543, "quantity": 2, "sku": "BHB-340", "title": "Brindle House Blend, 340 g", "variant_title": "Whole bean"},
    {"id": 14820017594487, "price": "0.00", "product_id": 8840219656311, "quantity": 1, "sku": "STICKER-01", "title": "Sticker pack", "variant_title": None},
]
items_b = [{"id": 14820019331191, "price": "19.00", "product_id": 8840220016759, "quantity": 1, "sku": "ETH-YIR-250", "title": "Ethiopia Yirgacheffe, 250 g", "variant_title": "Filter grind"}]

gh_after = h("after")[:40]
gh_before = h("before")[:40]
gh_push = {
    "ref": "refs/heads/main",
    "before": gh_before,
    "after": gh_after,
    "repository": {"id": 873402155, "node_id": "R_kgDOM" + alnum(9), "name": "brindle-app", "full_name": "brindle/brindle-app", "private": True, "html_url": "https://github.com/brindle/brindle-app", "default_branch": "main"},
    "pusher": {"name": "maya-l", "email": "maya@brindlecoffee.example"},
    "sender": {"login": "maya-l", "id": 51882734, "type": "User"},
    "created": False, "deleted": False, "forced": False,
    "compare": f"https://github.com/brindle/brindle-app/compare/{gh_before[:12]}...{gh_after[:12]}",
    "commits": [{"id": gh_after, "tree_id": h("tree")[:40], "distinct": True, "message": "feat(shipping): create Parcelbird label on invoice.paid", "timestamp": "2026-11-11T08:59:41-05:00", "author": {"name": "Maya Lindqvist", "email": "maya@brindlecoffee.example", "username": "maya-l"}, "added": ["src/shipping/parcelbird.ts"], "removed": [], "modified": ["src/webhooks/stripe.ts", "package.json"]}],
    "head_commit": {"id": gh_after, "message": "feat(shipping): create Parcelbird label on invoice.paid", "timestamp": "2026-11-11T08:59:41-05:00", "author": {"name": "Maya Lindqvist", "email": "maya@brindlecoffee.example", "username": "maya-l"}},
}
gh_pr = {
    "action": "opened",
    "number": 87,
    "pull_request": {"id": 2190844731, "number": 87, "state": "open", "title": "Add subscription pause flow", "user": {"login": "dev-tobi", "id": 60219455}, "body": "Lets customers pause deliveries for up to 2 months.", "html_url": "https://github.com/brindle/brindle-app/pull/87", "head": {"ref": "feat/pause-subscription", "sha": h("prhead")[:40]}, "base": {"ref": "main", "sha": gh_after}, "draft": False, "commits": 3, "additions": 214, "deletions": 18, "changed_files": 7},
    "repository": {"id": 873402155, "name": "brindle-app", "full_name": "brindle/brindle-app", "private": True},
    "sender": {"login": "dev-tobi", "id": 60219455, "type": "User"},
}

pb_label = {"type": "shipment.label_created", "timestamp": "", "data": {"shipment_id": "shp_" + alnum(20), "order_ref": "#1042", "carrier": "usps", "service": "ground_advantage", "tracking_number": "9400111206215842706131", "label_url": "https://files.parcelbird.example/labels/" + alnum(24) + ".pdf", "rate": {"amount": "4.85", "currency": "USD"}}}
pb_transit = {"type": "shipment.in_transit", "timestamp": "", "data": {"shipment_id": pb_label["data"]["shipment_id"], "order_ref": "#1042", "carrier": "usps", "tracking_number": "9400111206215842706131", "status": "in_transit", "location": {"city": "Portland", "region": "OR", "country": "US"}}}

# ---------------------------------------------------------------- deliveries
deliveries = []


def add(offset_s, provider, event_type, provider_event_id, body, headers_fn, group, signature, decoded, shape, query="", content_type="application/json", source_ip=""):
    at = T0 + timedelta(seconds=offset_s)
    seq = len(deliveries) + 1
    did = "dlv_" + ulid(at)
    raw = body
    size = len(raw.encode("utf-8"))
    headers = headers_fn(at, size)
    deliveries.append({
        "id": did,
        "seq": seq,
        "label": f"#{seq:02d}",
        "received_at": iso(at),
        "method": "POST",
        "path": f"/c/{PROJECT_INBOX}",
        "raw_query": query,
        "provider": provider,
        "event_type": event_type,
        "provider_event_id": provider_event_id,
        "group": group,
        "size_bytes": size,
        "content_type": content_type,
        "source_ip": source_ip,
        "signature": signature,
        "shape": shape,
        "decoded_summary": decoded,
        "headers": headers,
        "body_raw": raw,
        "ingest_latency_ms": rng.randint(38, 140),
    })
    return did


def stripe_headers(sigseed):
    def f(at, size):
        return [
            ["Host", HOST],
            ["User-Agent", "Stripe/1.0 (+https://stripe.com/docs/webhooks)"],
            ["Content-Length", str(size)],
            ["Accept", "*/*; q=0.5, application/xml"],
            ["Cache-Control", "no-cache"],
            ["Content-Type", "application/json; charset=utf-8"],
            ["Stripe-Signature", f"t={unix(at)},v1={h(sigseed)}"],
            ["Accept-Encoding", "gzip"],
        ]
    return f


def shopify_headers(topic, sigseed):
    def f(at, size):
        return [
            ["Host", HOST],
            ["User-Agent", "Shopify-Captain-Hook"],
            ["Content-Length", str(size)],
            ["Accept", "*/*"],
            ["Accept-Encoding", "gzip;q=1.0,deflate;q=0.6,identity;q=0.3"],
            ["Content-Type", "application/json"],
            ["X-Shopify-Api-Version", "2025-10"],
            ["X-Shopify-Event-Id", uuid4()],
            ["X-Shopify-Hmac-Sha256", b64(sigseed)],
            ["X-Shopify-Shop-Domain", "brindle-coffee.myshopify.com"],
            ["X-Shopify-Topic", topic],
            ["X-Shopify-Triggered-At", (at - timedelta(milliseconds=611)).astimezone(timezone.utc).isoformat(timespec="microseconds").replace("+00:00", "Z")],
            ["X-Shopify-Webhook-Id", uuid4()],
        ]
    return f


def github_headers(event, sigseed):
    def f(at, size):
        return [
            ["Host", HOST],
            ["User-Agent", "GitHub-Hookshot/8a3f2c1"],
            ["Content-Length", str(size)],
            ["Accept", "*/*"],
            ["Content-Type", "application/json"],
            ["X-GitHub-Delivery", uuid4()],
            ["X-GitHub-Event", event],
            ["X-GitHub-Hook-ID", "512839104"],
            ["X-GitHub-Hook-Installation-Target-ID", "873402155"],
            ["X-GitHub-Hook-Installation-Target-Type", "repository"],
            ["X-Hub-Signature", "sha1=" + h(sigseed + "1")[:40]],
            ["X-Hub-Signature-256", "sha256=" + h(sigseed)],
            ["Accept-Encoding", "gzip"],
        ]
    return f


def sw_headers(msgid, sigseed):
    def f(at, size):
        return [
            ["host", HOST],
            ["user-agent", "Parcelbird-Webhooks/2.1"],
            ["content-type", "application/json"],
            ["content-length", str(size)],
            ["webhook-id", msgid],
            ["webhook-timestamp", str(unix(at))],
            ["webhook-signature", "v1," + b64(sigseed)],
            ["accept-encoding", "gzip"],
        ]
    return f


def curl_headers(at, size):
    return [["Host", HOST], ["User-Agent", "curl/8.7.1"], ["Accept", "*/*"], ["Content-Type", "application/json"], ["Content-Length", str(size)]]


SIG_VALID_STRIPE = {"state": "valid", "label": "Signature valid", "scheme": "Stripe v1 (HMAC-SHA256)", "detail": "v1 matched with secret STRIPE_WEBHOOK_SECRET on agent maya-mbp. Timestamp 0.2 s old, tolerance 300 s.", "verified_by": "agent maya-mbp"}
SIG_VALID_SHOPIFY = {"state": "valid", "label": "Signature valid", "scheme": "Shopify HMAC-SHA256 (base64)", "detail": "X-Shopify-Hmac-Sha256 matched with secret SHOPIFY_API_SECRET on agent maya-mbp.", "verified_by": "agent maya-mbp"}
SIG_VALID_GH = {"state": "valid", "label": "Signature valid", "scheme": "GitHub X-Hub-Signature-256", "detail": "sha256 matched with secret GITHUB_WEBHOOK_SECRET on agent maya-mbp.", "verified_by": "agent maya-mbp"}
SIG_INVALID_GH = {
    "state": "invalid",
    "label": "Signature invalid",
    "scheme": "GitHub X-Hub-Signature-256",
    "reason": "Signed with a different secret",
    "detail": "X-Hub-Signature-256 did not match. Checked 1 secret: GITHUB_WEBHOOK_SECRET on agent maya-mbp. The body is intact (7 of 7 checks: Content-Length matches, valid UTF-8, no BOM, no re-encoding detected, no signed timestamp (GitHub), header well-formed, scheme known), so the secret in this repository's webhook settings differs from yours.",
    "fix": "Copy the secret from GitHub › brindle/brindle-app › Settings › Webhooks › 512839104 into GITHUB_WEBHOOK_SECRET on your machine, then press Re-check.",
    "verified_by": "agent maya-mbp",
}
SIG_NO_SECRET = {"state": "no_secret", "label": "No secret", "scheme": "Standard Webhooks v1", "detail": "This delivery is signed (webhook-signature v1) but no secret is set for Parcelbird on agent maya-mbp, so it can't be checked.", "fix": "Add PARCELBIRD_WEBHOOK_SECRET (starts with whsec_) to the agent: `webhooktrap secrets set parcelbird`. Secrets stay on your machine.", "verified_by": None}
SIG_UNSIGNED = {"state": "unsigned", "label": "Unsigned", "scheme": None, "detail": "No signature header found and no known provider fingerprint (User-Agent curl/8.7.1). Treated as a generic request.", "verified_by": None}

g_sub = "grp_stripe_" + sub_a
g_sub_b = "grp_stripe_" + sub_b
g_order = f"grp_shopify_order_{shop_order_id}"
g_order_b = f"grp_shopify_order_{shop_order_id_b}"
g_repo = "grp_github_brindle/brindle-app"
g_ship = "grp_parcelbird_" + pb_label["data"]["shipment_id"]

invoice_ps = dict(invoice_obj)
d_ip = add(0.0, "Stripe", "invoice.paid", evt["ip"], stripe_body(stripe_event(evt["ip"], "invoice.paid", T0 - timedelta(seconds=1), invoice_obj)), stripe_headers("s1"), g_sub, SIG_VALID_STRIPE,
           ["Invoice BRNDL-0042 paid: $24.00 USD", "Customer cus_… Jonah Reyes (jonah.reyes@example.com)", f"Subscription {sub_a[:12]}… · billing_reason subscription_cycle", "Period Oct 11 → Nov 11, 2026"],
           {"state": "matches", "label": "Shape matches baseline", "detail": "invoice.paid · API 2024-06-20 · 41 fields, same as 6 earlier captures"})
d_ips = add(0.183, "Stripe", "invoice.payment_succeeded", evt["ips"], stripe_body(stripe_event(evt["ips"], "invoice.payment_succeeded", T0 - timedelta(seconds=1), invoice_obj)), stripe_headers("s2"), g_sub, SIG_VALID_STRIPE,
            ["Invoice BRNDL-0042 payment succeeded: $24.00 USD", "Same invoice as #01"], {"state": "matches", "label": "Shape matches baseline", "detail": "invoice.payment_succeeded · 41 fields"})
d_pi = add(0.611, "Stripe", "payment_intent.succeeded", evt["pi"], stripe_body(stripe_event(evt["pi"], "payment_intent.succeeded", T0 - timedelta(seconds=2), pi_obj)), stripe_headers("s3"), g_sub, SIG_VALID_STRIPE,
           [f"PaymentIntent {pi_a[:12]}… succeeded: $24.00 USD", "Card · invoice BRNDL-0042"], {"state": "matches", "label": "Shape matches baseline", "detail": "payment_intent.succeeded · 17 fields"})
d_su = add(1.204, "Stripe", "customer.subscription.updated", evt["su"], stripe_body(stripe_event(evt["su"], "customer.subscription.updated", T0 - timedelta(seconds=1), sub_obj, {"current_period_end": unix(period_start), "current_period_start": unix(prev_start), "latest_invoice": in_prev})), stripe_headers("s4"), g_sub, SIG_VALID_STRIPE,
           [f"Subscription {sub_a[:12]}… renewed", "current_period_end Nov 11 → Dec 11, 2026", "latest_invoice changed to BRNDL-0042"], {"state": "matches", "label": "Shape matches baseline", "detail": "customer.subscription.updated · 3 previous_attributes"})
d_sh1 = add(68.402, "Shopify", "orders/create", None, compact(shopify_order(shop_order_id, 1042, "2026-11-11T09:59:11-05:00", "ana.mbeki@example.com", "Ana", "Mbeki", items_a, "37.00")), shopify_headers("orders/create", "sh1"), g_order, SIG_VALID_SHOPIFY,
            ["Order #1042 · $37.00 USD · paid", "2 × Brindle House Blend 340 g, 1 × Sticker pack", "Customer Ana Mbeki (ana.mbeki@example.com)"], {"state": "new", "label": "New shape (first capture)", "detail": "orders/create · API 2025-10 · baseline created from this delivery"})
d_sh2 = add(69.017, "Shopify", "orders/paid", None, compact(shopify_order(shop_order_id, 1042, "2026-11-11T09:59:11-05:00", "ana.mbeki@example.com", "Ana", "Mbeki", items_a, "37.00")), shopify_headers("orders/paid", "sh2"), g_order, SIG_VALID_SHOPIFY,
            ["Order #1042 paid · $37.00 USD"], {"state": "new", "label": "New shape (first capture)", "detail": "orders/paid · API 2025-10"})
d_gh1 = add(131.877, "GitHub", "push", None, compact(gh_push), github_headers("push", "g1"), g_repo, SIG_VALID_GH,
            ["Push to brindle/brindle-app main by maya-l", "1 commit: feat(shipping): create Parcelbird label on invoice.paid", f"{gh_before[:7]} → {gh_after[:7]}"], {"state": "matches", "label": "Shape matches baseline", "detail": "push · 12 earlier captures"})
d_pb1 = add(187.230, "Parcelbird", "shipment.label_created", None, "", sw_headers("msg_2pQx" + alnum(23), "p1"), g_order, SIG_NO_SECRET,
            ["Label created for order #1042", "USPS Ground Advantage · 9400 1112 0621 5842 7061 31", "Rate $4.85"], {"state": "new", "label": "New shape (first capture)", "detail": "shipment.label_created · Standard Webhooks payload"})
d_gh2 = add(244.519, "GitHub", "pull_request", None, compact(gh_pr), github_headers("pull_request", "g2"), g_repo, SIG_INVALID_GH,
            ["PR #87 opened by dev-tobi: Add subscription pause flow", "feat/pause-subscription → main · 3 commits · +214 −18"], {"state": "matches", "label": "Shape matches baseline", "detail": "pull_request.opened · 4 earlier captures"})
d_sh3 = add(301.664, "Shopify", "orders/create", None, compact(shopify_order(shop_order_id_b, 1043, "2026-11-11T10:03:05-05:00", "lee.okonkwo@example.com", "Lee", "Okonkwo", items_b, "24.00")), shopify_headers("orders/create", "sh3"), g_order_b, SIG_VALID_SHOPIFY,
            ["Order #1043 · $24.00 USD · paid", "1 × Ethiopia Yirgacheffe 250 g, filter grind", "Customer Lee Okonkwo (lee.okonkwo@example.com)"], {"state": "matches", "label": "Shape matches baseline", "detail": "orders/create · matches #05"})
d_su_b = add(318.090, "Stripe", "customer.subscription.updated", evt["su_b"], stripe_body(stripe_event(evt["su_b"], "customer.subscription.updated", T0 + timedelta(seconds=317), sub_b_obj, {"cancel_at": None, "cancel_at_period_end": False, "canceled_at": None})), stripe_headers("s5"), g_sub_b, SIG_VALID_STRIPE,
              [f"Subscription {sub_b[:12]}… set to cancel at period end (Nov 28, 2026)", "Feedback: too_expensive"], {"state": "drift", "label": "Shape differs from baseline", "detail": "New field cancellation_details.feedback not in the saved fixture for customer.subscription.updated"})
d_pb2 = add(402.511, "Parcelbird", "shipment.in_transit", None, "", sw_headers("msg_2pR1" + alnum(23), "p2"), g_order, SIG_NO_SECRET,
            ["Order #1042 in transit · Portland, OR"], {"state": "new", "label": "New shape (first capture)", "detail": "shipment.in_transit"})
d_curl = add(515.007, "Unknown", "(generic POST)", None, compact({"ping": True, "from": "maya", "note": "testing the url"}), curl_headers, None, SIG_UNSIGNED,
             ["Generic JSON · 3 fields", "No provider detected"], {"state": "none", "label": "No baseline", "detail": "Unknown provider"}, query="source=curl&debug=1")

# fill Parcelbird bodies (need timestamp) and recompute size/content-length
for d in deliveries:
    if d["provider"] == "Parcelbird":
        src = pb_label if d["event_type"] == "shipment.label_created" else pb_transit
        src = json.loads(json.dumps(src))
        src["timestamp"] = d["received_at"]
        raw = compact(src)
        d["body_raw"] = raw
        d["size_bytes"] = len(raw.encode())
        for hdr in d["headers"]:
            if hdr[0] == "content-length":
                hdr[1] = str(d["size_bytes"])

by_id = {d["id"]: d for d in deliveries}
_gh = by_id[d_gh2]
_recv = dict(_gh["headers"])["X-Hub-Signature-256"].split("=")[1][:6]
_gh["signature"] = dict(_gh["signature"])
_gh["signature"]["detail"] = _gh["signature"]["detail"].replace("a07d3b", _recv)

groups = [
    {"id": g_sub, "provider": "Stripe", "title": f"Subscription {sub_a}", "short": f"sub_1QH…{sub_a[-4:]}", "object_ids": [sub_a, in_a, pi_a, cus_a], "subtitle": "Jonah Reyes · Monthly whole bean · renewal", "delivery_ids": [d_ip, d_ips, d_pi, d_su], "first_at": by_id[d_ip]["received_at"], "spread": "1.2 s", "prove_suggested": True},
    {"id": g_order, "provider": "Shopify", "title": f"Order #1042 ({shop_order_id})", "short": "Order #1042", "object_ids": [str(shop_order_id), "#1042"], "subtitle": "Ana Mbeki · $37.00 · paid", "delivery_ids": [d_sh1, d_sh2], "first_at": by_id[d_sh1]["received_at"], "spread": "0.6 s", "prove_suggested": False},
    {"id": g_ship, "provider": "Parcelbird", "title": f"Shipment {pb_label['data']['shipment_id']}", "short": pb_label["data"]["shipment_id"][:9] + "…" + pb_label["data"]["shipment_id"][-4:], "object_ids": [pb_label["data"]["shipment_id"]], "subtitle": "Order ref #1042 · label created, in transit", "delivery_ids": [d_pb1, d_pb2], "first_at": by_id[d_pb1]["received_at"], "spread": "3 min 35 s", "prove_suggested": False, "note": "No pack for Parcelbird: grouped on data.shipment_id (GRP-4). Not linked to Order #1042: cross-provider groups are post-MVP (GRP-5)."},
    {"id": g_repo, "provider": "GitHub", "title": "Repository brindle/brindle-app", "short": "brindle/brindle-app", "object_ids": ["873402155"], "subtitle": "push · pull_request", "delivery_ids": [d_gh1, d_gh2], "first_at": by_id[d_gh1]["received_at"], "spread": "1 min 53 s", "prove_suggested": False},
    {"id": g_order_b, "provider": "Shopify", "title": f"Order #1043 ({shop_order_id_b})", "short": "Order #1043", "object_ids": [str(shop_order_id_b)], "subtitle": "Lee Okonkwo · $24.00", "delivery_ids": [d_sh3], "first_at": by_id[d_sh3]["received_at"], "spread": "—", "prove_suggested": False},
    {"id": g_sub_b, "provider": "Stripe", "title": f"Subscription {sub_b}", "short": f"sub_1QG…{sub_b[-4:]}", "object_ids": [sub_b, cus_b], "subtitle": "Cancellation scheduled", "delivery_ids": [d_su_b], "first_at": by_id[d_su_b]["received_at"], "spread": "—", "prove_suggested": False},
    {"id": None, "provider": "Unknown", "title": "Ungrouped", "short": "Ungrouped", "object_ids": [], "subtitle": "Requests with no shared object", "delivery_ids": [d_curl], "first_at": by_id[d_curl]["received_at"], "spread": "—", "prove_suggested": False},
]
for d in deliveries:
    for g in groups:
        if d["id"] in g["delivery_ids"]:
            d["group"] = g["id"]

# ---------------------------------------------------------------- run
RUN_T0 = datetime(2026, 11, 11, 14, 9, 2, 118000, tzinfo=timezone.utc)
run_id = "run_" + ulid(RUN_T0)
BUDGET = 10000
OK = '{"received":true}'

scenarios = [
    {"key": "once", "name": "Once", "what": "Every selected event once, in capture order. Sets the baseline.", "why": "Every provider", "citation": None, "needs_secret": False, "deliveries_rule": "n"},
    {"key": "twice", "name": "Twice", "what": "The first event again, 500 ms after the first delivery completes: same event id, new signature timestamp", "why": "Stripe may deliver the same event more than once.", "citation": "Stripe docs › Webhooks › Handle duplicate events", "needs_secret": True, "deliveries_rule": "2"},
    {"key": "shuffled", "name": "Shuffled", "what": "Related events in reverse order", "why": "Stripe doesn't guarantee delivery order.", "citation": "Stripe docs › Webhooks › Event ordering", "needs_secret": True, "deliveries_rule": "n, needs 2+ related events"},
    {"key": "late", "name": "Late", "what": "Valid signature, timestamp 360 s old (tolerance 300 s)", "why": "Replay protection relies on the timestamp tolerance.", "citation": "Stripe docs › Webhooks › Prevent replay attacks", "needs_secret": True, "deliveries_rule": "1"},
    {"key": "retry", "name": "Retry after error", "what": "Sends the event, then the provider's retry 2 s after it completes. Providers retry when a delivery fails or takes too long; your handler can't tell which.", "why": "Stripe retries failed or timed-out deliveries with backoff for up to 3 days in live mode.", "citation": "Stripe docs › Webhooks › Retry behavior", "needs_secret": True, "deliveries_rule": "2"},
    {"key": "tampered", "name": "Tampered", "what": "amount_paid 2400 → 0 in the body, signed for the original body", "why": "A signature only protects you if a failed check stops the request.", "citation": "Stripe docs › Webhooks › Verify signatures", "needs_secret": True, "deliveries_rule": "1"},
    {"key": "unsigned", "name": "Unsigned", "what": "The event with every signature header removed", "why": "A handler that checks the signature only when the header is present accepts anything.", "citation": "Stripe docs › Webhooks › Verify signatures", "needs_secret": False, "deliveries_rule": "1"},
    {"key": "burst", "name": "Burst", "what": "Every selected event, released together (≤ 20 ms apart)", "why": "Stripe sends related events close together, often concurrently.", "citation": "Stripe docs › Webhooks › Event ordering", "needs_secret": True, "deliveries_rule": "max(n, 2)"},
    {"key": "slow", "name": "Slow budget", "what": "One more delivery after the others, measured against the budget: 10 s budget · Webhook Trap default", "why": "If Stripe times out waiting, the delivery counts as failed and is retried. Stripe doesn't publish the timeout.", "citation": "Stripe docs › Webhooks › Quickly return a 2xx response", "needs_secret": False, "deliveries_rule": "1"},
]

# Timing model for Run #3 (PLAN-3, PRB-6 to PRB-10). All values in ms from run start (agent accepted the run).
# A delivery is completed when its response headers are in and its probe reading is recorded (§1).
RESET_MS = 610          # npm run db:reset:webhooks
PROBE_MS = 30           # one orders.count reading
STABILITY_WAIT_MS = 500  # PRB-10
ASYNC_CHECK_MS = 1000    # PRB-7
GAP = {"once": 150, "shuffled": 150, "twice": 500, "retry": 2000}
BETWEEN = 150

# (scenario, source delivery, mode, status, latency, response body, before, after, expected, result, finding, signing)
# Probe orders.count counts orders for invoice in_a; reset empties it before every scenario.
PLAN = [
    ("once", d_ip, "normal", 200, 184, OK, 0, 1, None, "pass", None, "re-signed"),
    ("once", d_pi, "normal", 200, 121, OK, 1, 1, None, "pass", None, "re-signed"),
    ("once", d_su, "normal", 200, 143, OK, 1, 1, None, "pass", None, "re-signed"),
    ("twice", d_ip, "normal", 200, 212, OK, 0, 1, None, "pass", None, "re-signed"),
    ("twice", d_ip, "duplicate", 200, 196, OK, 1, 2, 1, "finding", "F2", "re-signed (same event id)"),
    ("shuffled", d_su, "normal", 200, 138, OK, 0, 0, None, "pass", None, "re-signed"),
    ("shuffled", d_pi, "normal", 200, 117, OK, 0, 0, None, "pass", None, "re-signed"),
    ("shuffled", d_ip, "normal", 200, 199, OK, 0, 1, 1, "pass", None, "re-signed"),
    ("late", d_ip, "late", 400, 41, '{"error":"Timestamp outside the tolerance zone"}', 0, 0, 0, "pass", None, "re-signed, t − 360 s"),
    ("retry", d_ip, "normal", 200, 11840, OK, 0, 1, None, "finding", "F3", "re-signed"),
    ("retry", d_ip, "retry", 200, 205, OK, 1, 2, 1, "finding", "F2", "re-signed (retry, same event id)"),
    ("tampered", d_ip, "tampered", 200, 167, OK, 0, 1, 0, "finding", "F1", "signed for the original body, fresh timestamp"),
    ("burst", d_ip, "normal", 200, 601, OK, None, None, None, "pass", None, "re-signed"),
    ("burst", d_pi, "normal", 200, 312, OK, None, None, None, "pass", None, "re-signed"),
    ("burst", d_su, "normal", 200, 448, OK, None, None, None, "pass", None, "re-signed"),
    ("slow", d_ip, "normal", 200, 10420, OK, 0, 1, None, "finding", "F3", "re-signed"),
]
BURST_DISPATCH = [0, 2, 3]   # first-byte offsets inside the burst; spread 3 ms (BURST-2: ≤ 20 ms)
BURST_STATE = {"before": 0, "final": 1, "expected": 1}

timeline = []          # (seq, sent_ms, completed_ms)
preflight = [("reset", 0, RESET_MS), ("probe stability check", RESET_MS, RESET_MS + PROBE_MS + STABILITY_WAIT_MS + PROBE_MS)]
t = preflight[-1][2]
prev_sc = None
seq = 0
burst_rows = []
for i, row in enumerate(PLAN):
    sc, lat = row[0], row[4]
    seq += 1
    if sc != prev_sc:
        if prev_sc is not None:
            t += ASYNC_CHECK_MS + PROBE_MS          # async check of the previous scenario
            t += RESET_MS + PROBE_MS                # reset + "before" reading
        t += BETWEEN
        prev_sc = sc
        if sc == "burst":
            burst_start = t
    else:
        t += GAP.get(sc, 0)
    if sc == "burst":
        k = len(burst_rows)
        sent = burst_start + BURST_DISPATCH[k]
        burst_rows.append((seq, sent, sent + lat))
        timeline.append((seq, sent, sent + lat))
        if k == len(BURST_DISPATCH) - 1:
            t = max(c for _, _, c in burst_rows) + PROBE_MS   # one final reading after every burst delivery
        continue
    sent = t
    t = sent + lat + PROBE_MS
    timeline.append((seq, sent, t))
RUN_END_MS = t + ASYNC_CHECK_MS + PROBE_MS

run_deliveries = []
for (sc, src, mode, status, lat, body, pb, pa, exp, verdict, fid, signing), (seq, off, done) in zip(PLAN, timeline):
    sent = RUN_T0 + timedelta(milliseconds=off)
    s = by_id[src]
    sig_t = unix(sent) - (360 if mode == "late" else 0)
    if mode == "tampered":
        note = "amount_paid edited in place 2400 → 0 (no re-serialisation, Content-Length recomputed); Stripe-Signature computed for the original body at a fresh timestamp"
    else:
        note = "Exact bytes of capture " + s["label"]
    if sc == "burst":
        digest = "— (read once after the burst: 0 → 1 final)"
    else:
        digest = f"orders.count {pb} → {pa}" + (f" · expected {exp}" if exp is not None and pa != exp else "")
    run_deliveries.append({
        "label": f"D{seq}",
        "seq": seq,
        "scenario": sc,
        "mode": mode,
        "event_type": s["event_type"],
        "provider_event_id": s["provider_event_id"],
        "source_delivery": s["id"],
        "source_label": s["label"],
        "sent_at": iso(sent),
        "sent_rel_ms": off,
        "completed_rel_ms": done,
        "signing": signing,
        "tamper": {"path": "data.object.amount_paid", "from": "2400", "to": "0"} if mode == "tampered" else None,
        "request": {
            "method": "POST",
            "url": "http://localhost:3000/webhooks/stripe",
            "headers": [["Content-Type", "application/json; charset=utf-8"], ["User-Agent", "Stripe/1.0 (+https://stripe.com/docs/webhooks)"], ["Stripe-Signature", f"t={sig_t},v1={h('run' + str(seq))}"], ["Webhooktrap-Run", run_id], ["Webhooktrap-Delivery", f"D{seq}"], ["Webhooktrap-Scenario", sc], ["Webhooktrap-Dry-Run", "1"]],
            "body_note": note,
            "size_bytes": s["size_bytes"] - (3 if mode == "tampered" else 0),
        },
        "response": {
            "status": status,
            "latency_ms": lat,
            "headers": [["Content-Type", "application/json; charset=utf-8"], ["X-Powered-By", "Express"], ["Content-Length", str(len(body))]],
            "body_snippet": body,
        },
        "budget_ms": BUDGET,
        "within_budget": lat <= BUDGET,
        "over_budget_counts": mode not in ("late", "tampered", "unsigned"),
        "probe": {"key": "orders.count", "before": pb, "after": pa, "expected": exp, "flagged": exp is not None and pa is not None and pa != exp, "digest": digest},
        "verdict": verdict,
        "finding": fid,
    })

findings = [
    {
        "id": "F1", "type": "accepts_tampered", "severity": "critical", "severity_label": "Critical", "level": 1, "level_label": "Level 1 · transport",
        "title": "Accepts tampered payload",
        "event_type": "invoice.paid",
        "scenarios": ["tampered"],
        "summary": "D12 changed amount_paid from 2400 to 0 and was signed for the original body. Your handler returned 200 in 167 ms and created an order: orders.count 0 → 1, expected a 4xx and no change.",
        "evidence": ["D12"],
        "evidence_roles": {"D12": "offending"},
        "measured": "HTTP 200 in 167 ms · orders.count 0 → 1 · expected: a 4xx and no change",
        "why": "Stripe signs the exact bytes it sends. If a failed check doesn't stop the request, anyone who can reach this URL can create paid orders.",
        "citation": "Stripe docs › Webhooks › Verify signatures",
        "cause_rule": "LC-1",
        "cause_hint": "Late (D9) was rejected, so the timestamp check runs; the signature check probably fails into a log line instead of stopping the request.",
        "fix_note": "Return 400 as soon as constructEvent throws, before any write. After the fix, D12 should get 400 and leave orders.count at 0.",
        "fix_framework": "Express (detected from X-Powered-By)",
        "fix": "app.post('/webhooks/stripe', express.raw({ type: 'application/json' }), (req, res) => {\n  let event;\n  try {\n    event = stripe.webhooks.constructEvent(req.body, req.get('stripe-signature'), endpointSecret);\n  } catch (err) {\n    return res.status(400).send('Invalid signature'); // stop here, don't log and continue\n  }\n  // ...handle event\n});",
    },
    {
        "id": "F2", "type": "duplicate_changed_state", "severity": "high", "severity_label": "High", "level": 2, "level_label": "Level 2 · judged on maya-mbp",
        "title": "Duplicate delivery created a second order",
        "event_type": "invoice.paid",
        "scenarios": ["twice", "retry"],
        "caption": "Same request, same response, a different state.",
        "summary": "D5 delivered the same invoice.paid event as D4. Both got 200 {\"received\":true}, but orders.count went 1 → 2, expected 1. The retry D11 did the same after D10. Only the probe could see this.",
        "evidence": ["D4", "D5", "D10", "D11"],
        "evidence_roles": {"D4": "reference", "D5": "offending", "D10": "reference", "D11": "offending"},
        "measured": "D5: HTTP 200 in 196 ms · orders.count 1 → 2, expected 1 · D11 (retry): HTTP 200 in 205 ms · orders.count 1 → 2, expected 1",
        "why": "Stripe may deliver the same event more than once, and retries failed or timed-out deliveries with backoff for up to 3 days in live mode. Each duplicate creates another order.",
        "citation": "Stripe docs › Webhooks › Handle duplicate events · Retry behavior",
        "cause_rule": "LC-8",
        "cause_hint": "The handler doesn't record the event id before it writes.",
        "fix_note": "Record event.id before creating the order and return 200 early when it is already recorded. After the fix, D5 and D11 should leave orders.count at 1.",
        "fix_framework": "Express (detected from X-Powered-By)",
        "fix": "// before creating the order\nconst { rowCount } = await db.query(\n  'INSERT INTO processed_events (id) VALUES ($1) ON CONFLICT DO NOTHING',\n  [event.id]\n);\nif (rowCount === 0) return res.sendStatus(200); // already handled",
    },
    {
        "id": "F3", "type": "over_budget", "severity": "medium", "severity_label": "Medium", "level": 1, "level_label": "Level 1 · transport",
        "title": "Over the 10 s budget for Stripe",
        "event_type": "invoice.paid",
        "scenarios": ["retry", "slow"],
        "summary": "2 of 8 valid invoice.paid deliveries took longer than the 10 s budget (Webhook Trap default): D10 (11 840 ms) and D16 (10 420 ms). Stripe doesn't publish its timeout, so these are over our default budget, not proven failures at Stripe.",
        "evidence": ["D10", "D16"],
        "evidence_roles": {"D10": "offending", "D16": "offending"},
        "measured": "D10 11 840 ms (+1 840 over) · D16 10 420 ms (+420 over) · median of the other 6 valid invoice.paid deliveries 202 ms",
        "why": "If Stripe times out waiting, the delivery counts as failed and is retried. Stripe doesn't publish the timeout. In this run, D10 went over our default budget, and the retry D11 created the duplicate order in F2.",
        "citation": "Stripe docs › Webhooks › Quickly return a 2xx response",
        "cause_rule": "LC-4",
        "cause_hint": "Only some deliveries of invoice.paid were slow, so the slow step is intermittent: usually an external call or a lock wait before the response.",
        "fix_note": "Acknowledge with 200 first, then queue the slow work. After the fix, D10 and D16 should respond within the budget.",
        "fix_framework": "Express (detected from X-Powered-By)",
        "fix": "res.sendStatus(200);            // acknowledge first\nawait queue.add('create-label', { invoiceId: event.data.object.id });",
    },
    {
        "id": "F4", "type": "probe_gap", "severity": "info", "severity_label": "Info", "level": 2, "level_label": "Level 2 · judged on maya-mbp",
        "title": "Probe doesn't see subscription state",
        "event_type": None,
        "scenarios": ["shuffled", "burst"],
        "summary": "Shuffled and Burst passed on orders.count. But customer.subscription.updated was delivered first in Shuffled (D6) and inside the burst (D15), and no probe reads subscription state.",
        "evidence": ["D6", "D15"],
        "evidence_roles": {"D6": "offending", "D15": "offending"},
        "measured": "orders.count 0 → 1 after Shuffled and after Burst, expected 1 · subscription state not measured",
        "why": "Out-of-order delivery usually breaks status fields, not counts.",
        "citation": "Stripe docs › Webhooks › Event ordering",
        "cause_rule": None,
        "cause_hint": None,
        "fix_note": "Add subscriptions.status to the probe in webhooktrap.yml. The next run then checks both.",
        "fix_framework": "webhooktrap.yml",
        "fix": "probe:\n  # keep your existing entries, then add:\n  - as: subscriptions.status\n    sql: select status from subscriptions where stripe_id = '{{subscription.id}}'",
        "fix_source": "Pack template (Stripe). The hosted page never shows your own probe definitions (PV-8); adjust table and column names.",
    },
]

run = {
    "id": run_id,
    "number": 3,
    "title": "Prove invoice.paid · renewal",
    "status": "findings",
    "status_label": "3 findings",
    "status_reason": None,
    "exit_code": 1,
    "fail_on": "high",
    "started_at": iso(RUN_T0),
    "finished_at": iso(RUN_T0 + timedelta(milliseconds=RUN_END_MS)),
    "duration_ms": RUN_END_MS,
    "preflight": [{"step": n, "start_ms": a, "end_ms": b} for n, a, b in preflight],
    "source_group": g_sub,
    "source_deliveries": [d_ip, d_pi, d_su],
    "target": {"url": "http://localhost:3000/webhooks/stripe", "agent": "maya-mbp", "framework_detected": "Express (detected from X-Powered-By)"},
    "provider": "Stripe",
    "budget_ms": BUDGET,
    "budget_source": "default",
    "budget_label": "10 s budget · Webhook Trap default",
    "budget_note": "Stripe publishes no timeout. 10 000 ms is Webhook Trap's default budget (provider-budgets.md, verified 2026-11-14).",
    "verdict_levels": ["Level 1 · transport", "Level 2 · state probe"],
    "probe": {"configured": True, "label": "orders.count", "source": "webhooktrap.yml", "reset_hook": "npm run db:reset:webhooks", "reset_between_scenarios": True, "display_values": "counts up to 9 999 sent as numbers, other values as codes (PV-2)", "entries": [{"name": "orders.count", "display_mode": "count"}]},
    "scenarios_not_selected": ["unsigned"],
    "counts": {"scenarios": 8, "deliveries": 16, "passed_scenarios": 4, "findings": 3, "critical": 1, "high": 1, "medium": 1, "info": 1},
    "burst": {"dispatch_spread_ms": BURST_DISPATCH[-1] - BURST_DISPATCH[0], "state": BURST_STATE},
    "scenario_results": [
        {"key": "once", "deliveries": ["D1", "D2", "D3"], "result": "pass", "reading": "0 → 1", "line": "200 × 3 · orders.count 0 → 1"},
        {"key": "twice", "deliveries": ["D4", "D5"], "result": "finding", "findings": ["F2"], "reading": "expected 1 · got 2", "line": "200, 200 · orders.count 1 → 2 on D5, expected 1"},
        {"key": "shuffled", "deliveries": ["D6", "D7", "D8"], "result": "pass", "findings": ["F4"], "reading": "○ F4 Info · 0 → 1", "line": "reversed · orders.count 0 → 1 · status not probed"},
        {"key": "late", "deliveries": ["D9"], "result": "pass", "reading": "400 · 41 ms", "line": "400 in 41 ms · rejected as expected"},
        {"key": "retry", "deliveries": ["D10", "D11"], "result": "finding", "findings": ["F2", "F3"], "reading": "△ F3 · 11 840 ms", "line": "D10 11 840 ms → retry D11 → orders.count 1 → 2"},
        {"key": "tampered", "deliveries": ["D12"], "result": "finding", "findings": ["F1"], "reading": "expected 4xx · got 200", "line": "200 in 167 ms · accepted a changed body"},
        {"key": "burst", "deliveries": ["D13", "D14", "D15"], "result": "pass", "findings": ["F4"], "reading": "○ F4 Info · 0 → 1 (final)", "line": "200 × 3 · 312–601 ms · orders.count 0 → 1 (final)"},
        {"key": "slow", "deliveries": ["D16"], "result": "finding", "findings": ["F3"], "reading": "10 420 ms · +420", "line": "10 420 ms · 420 ms over budget"},
    ],
    "deliveries": run_deliveries,
    "findings": findings,
    "not_checked": [],
    "fixtures": {"state": "not_saved", "files": ["webhooktrap/fixtures/stripe/invoice.paid.json", "webhooktrap/fixtures/stripe/payment_intent.succeeded.json", "webhooktrap/fixtures/stripe/customer.subscription.updated.json", "webhooktrap.yml"], "scrub": ["customer_email → customer1@example.test", "customer_name → Test Customer 1", "hosted_invoice_url → https://invoice.stripe.test/redacted"]},
    "share_url": f"https://{HOST}/s/q7Vf2LmX9pK4wT8nR3bZcD",
    "share": {"token": "q7Vf2LmX9pK4wT8nR3bZcD", "url": f"https://{HOST}/s/q7Vf2LmX9pK4wT8nR3bZcD", "rotated_url": f"https://{HOST}/s/Hc3mW8tZ1qN5rB7xK2vYpL", "shared_by": "Maya Lindqvist", "bodies_hidden": False, "include_probe_values": False, "owner_url": f"https://{HOST}/r/{run_id}"},
    "provenance": {"executor": "agent", "source": "web", "agent_name": "maya-mbp", "line": "Run by maya-mbp from the web app"},
}

run_history = [
    {"number": 1, "id": "run_" + ulid(RUN_T0 - timedelta(minutes=41)), "status": "inconclusive", "status_label": "Inconclusive", "status_reason": "needs_probe", "exit_code": 0, "started_at": iso(RUN_T0 - timedelta(minutes=41)), "events": ["#01"], "scenarios": ["Once", "Twice"], "deliveries": 3, "duration_ms": 1392, "line": "Responses identical, but we can't see your database. Add a probe to check for duplicate side effects.", "note": "One event selected, so Once sends 1 delivery and Twice 2. Exit 1 with --fail-on-inconclusive."},
    {"number": 2, "id": "run_" + ulid(RUN_T0 - timedelta(minutes=12)), "status": "aborted", "status_label": "Aborted", "status_reason": "agent_lost", "exit_code": 2, "started_at": iso(RUN_T0 - timedelta(minutes=12)), "events": ["#01", "#03", "#04"], "scenarios": ["Once", "Twice", "Shuffled", "Late", "Retry after error", "Tampered", "Burst", "Slow budget"], "deliveries": 2, "planned_deliveries": 16, "duration_ms": 2002, "line": "Agent maya-mbp disconnected at 13:57:04.120 during D3 of 16. D1–D2 and their results are kept."},
    {"number": 3, "id": run_id, "status": "findings", "status_label": "3 findings", "status_reason": None, "exit_code": 1, "started_at": iso(RUN_T0), "events": ["#01", "#03", "#04"], "scenarios": [s["name"] for s in scenarios if s["key"] != "unsigned"], "deliveries": 16, "duration_ms": RUN_END_MS, "line": "1 Critical · 1 High · 1 Medium · 1 Info"},
    {"number": 4, "id": "run_" + ulid(RUN_T0 + timedelta(minutes=23)), "status": "passed", "status_label": "Passed · 1 Info", "status_reason": None, "exit_code": 0, "started_at": iso(RUN_T0 + timedelta(minutes=23)), "events": ["#01", "#03", "#04"], "scenarios": [s["name"] for s in scenarios], "deliveries": 17, "duration_ms": 23400, "line": "9 of 9 scenarios passed at Level 1 + Level 2 (orders.count). Not checked: subscription state, which no probe reads · side effects outside your database, such as emails.", "info": ["F1 Info · Probe doesn't see subscription state"], "note": "Hypothetical rerun after the fixes for F1 to F3, with Unsigned added (17 deliveries, D13 is Unsigned). Use it for the Passed state and the 9-cell strip."},
]

# ---------------------------------------------------------------- copy
copy = {
    "capture": {
        "waiting": {"label": "Waiting for the first delivery", "helper": "Send any webhook to this URL. It appears here within a second.", "providers": {
            "Stripe": "Test mode › Developers › Webhooks › Add destination. Paste the URL, pick the events you handle, save, then trigger a test checkout. Stripe sends straight here, no CLI needed.",
            "GitHub": "Repository › Settings › Webhooks › Add webhook. Payload URL = this URL, Content type = application/json, set a secret.",
            "Shopify": "Settings › Notifications › Webhooks › Create webhook. Format JSON, URL = this URL, API version 2025-10.",
            "Standard Webhooks": "Paste the URL as the endpoint in your provider's webhook settings. We detect webhook-id / webhook-signature headers automatically.",
            "curl": "curl -X POST https://webhooktrap.dev/c/" + GUEST_INBOX + " -H 'Content-Type: application/json' -d '{\"hello\":\"world\"}'"}},
        "loading": {"label": "Loading deliveries…", "helper": None},
        "receiving": {"label": "Live", "helper": "New deliveries appear at the top."},
        "new_pill": {"label": "3 new deliveries", "helper": "Press N or click to jump to the newest."},
        "paused": {"label": "Catching up", "helper": "6 deliveries arrived while this tab was hidden. Showing them now."},
        "stream_lost": {"label": "Live updates interrupted", "helper": "Reconnecting… Deliveries are still being captured on our side and will appear when the connection returns. Last update 14:07:51."},
        "quota": {"label": "Guest limit reached: 100 of 100 deliveries", "helper": "New deliveries are still answered (your provider sees 200) but not stored. Save the ones you need as fixtures, or sign in to keep capturing."},
        "expired": {"label": "This guest URL expired", "helper": "Guest captures are kept for 24 hours. This one ended at 14:02 on Nov 12. Get a new URL, or sign in so URLs don't expire."},
        "body_not_kept": {"label": "Body not kept (12.4 MB, limit 5 MB)", "helper": "Headers and a SHA-256 of the body are stored. The body itself was not."},
        "binary": {"label": "Binary body (application/octet-stream, 48 KB)", "helper": "Shown as hex. Download the exact bytes."},
        "no_results": {"label": "No deliveries match “invoice.voided”", "helper": "Clear the filter to see all 13."},
    },
    "signature": {
        "valid": {"label": "Signature valid", "helper": "Checked on maya-mbp with STRIPE_WEBHOOK_SECRET."},
        "invalid": {"label": "Signature invalid", "helper": "Signed with a different secret. See the reason and fix."},
        "no_secret": {"label": "No secret", "helper": "Signed by Parcelbird, but no secret is set on your agent, so we can't check it."},
        "unknown": {"label": "Unknown scheme", "helper": "There is a signature-like header (X-Signature) but we don't recognise the scheme. Define it to check it."},
        "unsigned": {"label": "Unsigned", "helper": "No signature header. Nothing to check."},
        "no_agent": {"label": "Not checked", "helper": "Secrets live on your machine. Connect the agent to check signatures."},
    },
    "agent": {
        "not_installed": {"label": "No agent connected", "helper": "The agent runs on your machine, sends deliveries to localhost and keeps your signing secrets there.", "command": "npx @webhooktrap/agent@1 pair 7KQ2M-XR9TD"},
        "pairing": {"label": "Waiting for your agent…", "helper": "Run the command in a terminal. Code 7KQ2M-XR9TD expires in 9:41."},
        "awaiting_confirmation": {"label": "Confirm in your terminal", "helper": "maya-mbp is waiting for you to type y in the terminal where you ran the command. Check that it shows https://webhooktrap.dev first."},
        "needs_reconfirm": {"label": "Confirm in your terminal", "helper": "This session moved to your account, so maya-mbp asks you to confirm the new workspace in its terminal. It runs nothing until you do."},
        "pair_declined": {"label": "Pairing cancelled", "helper": "The agent was not paired: someone answered N in the terminal. Get a new code to try again."},
        "offline_finished_run": {"label": "maya-mbp is offline", "helper": "This run is complete and shown as the server stored it. Run again and Save as fixtures need maya-mbp. Last seen 14:31:07."},
        "signatures_checked": {"label": "Signatures checked", "helper": "Signatures checked: 4 of 4 valid with STRIPE_WEBHOOK_SECRET on maya-mbp."},
        "connected": {"label": "Connected · maya-mbp", "helper": "Target localhost:3000/webhooks/stripe · agent 0.9.2"},
        "unreachable_refused": {"label": "Target unreachable", "helper": "maya-mbp got 'connection refused' from localhost:3000. Is your app running?"},
        "unreachable_tls": {"label": "Target unreachable", "helper": "TLS error on https://localhost:3443: self-signed certificate. Use http:// for localhost or pass --insecure-localhost."},
        "lost_mid_run": {"label": "Agent disconnected", "helper": "maya-mbp stopped responding at 13:57:04.120 during D3 of 16. D1–D2 and their results are kept. Reconnect to run it again."},
        "too_old": {"label": "Agent update needed", "helper": "maya-mbp runs 0.7.4. This run needs 0.9 or later: npx @webhooktrap/agent@latest"},
        "replaced": {"label": "Agent replaced", "helper": "A new agent paired with this guest session, so maya-mbp was disconnected. A guest session has one agent at a time."},
    },
    "run": {
        "draft": {"label": "Draft", "helper": "9 scenarios selected · 17 deliveries · about 50 s"},
        "draft_run3": {"label": "Draft", "helper": "8 scenarios selected · 16 deliveries · about 45 s"},
        "draft_over_cap": {"label": "Too many deliveries", "helper": "This run would send 54 deliveries. The limit is 50."},
        "draft_two_providers": {"label": "Select events from one provider", "helper": "A run tests one provider at a time. Remove the Shopify events, or prove them in their own run."},
        "needs_secret": {"label": "Needs secret", "helper": "Stripe signs a timestamp, so nothing can be replayed without STRIPE_WEBHOOK_SECRET on maya-mbp.", "command": "webhooktrap secrets set stripe"},
        "side_effects": {"label": "This run repeats events", "helper": "This run sends the same events several times. If your handler sends emails or charges cards, honour the Webhooktrap-Dry-Run header first."},
        "queued": {"label": "Queued", "helper": "Waiting for maya-mbp to accept the run…"},
        "queued_confirm": {"label": "Queued", "helper": "Confirm in maya-mbp's terminal: it shows the probe and reset commands from webhooktrap.yml and this target before it runs them."},
        "checks_hosted": {"label": "What was checked", "helper": "Level 1 transport · Level 2 probe orders.count (count) · reset before every scenario, run on maya-mbp"},
        "running": {"label": "Running · D11 of 16", "helper": "Retry after error · 25.3 s elapsed"},
        "passed": {"label": "Passed · 1 Info", "helper": "9 of 9 scenarios passed at Level 1 + Level 2 (orders.count). Not checked: subscription state, which no probe reads · side effects outside your database, such as emails."},
        "findings": {"label": "3 findings", "helper": "1 Critical · 1 High · 1 Medium · 1 Info"},
        "aborted": {"label": "Aborted", "helper": "Agent lost during D3 of 16. D1–D2 are kept."},
        "aborted_target_lost": {"label": "Aborted", "helper": "3 deliveries in a row couldn't connect to localhost:3000 (D9 to D11). Completed deliveries and findings are kept."},
        "aborted_reset_failed": {"label": "Aborted", "helper": "The reset before Tampered failed on maya-mbp. D1–D11 and their findings are kept. The command and its output are in the agent's terminal.", "cli": "The reset before Tampered failed: npm run db:reset:webhooks exited with code 1. D1–D11 and their findings are kept."},
        "aborted_time_limit": {"label": "Aborted", "helper": "The run reached the 15-minute limit during Burst. Completed deliveries and findings are kept."},
        "aborted_user": {"label": "Aborted", "helper": "Stopped with Ctrl-C during D7. D1–D6 and their findings are kept."},
        "inconclusive": {"label": "Inconclusive", "helper": "Responses are identical, but we can't see your database. Add a probe to check for duplicate side effects."},
        "order_dependent": {"label": "Order-dependent", "helper": "No reset ran between scenarios, so results may depend on earlier ones. Add reset: to webhooktrap.yml."},
        "start_failed": {"label": "Couldn't start the run", "helper": "maya-mbp refused the job: target localhost:3000/webhooks/stripe isn't in its allow-list. Run: webhooktrap targets add localhost:3000", "reason": "target_not_allowed"},
        "couldnt_start_baseline_rejected": {"label": "Couldn't start the run", "helper": "Your handler rejected a correctly signed delivery (HTTP 401). Check the secret on maya-mbp and the target path.", "reason": "baseline_rejected"},
        "couldnt_start_target_unreachable": {"label": "Couldn't start the run", "helper": "maya-mbp couldn't connect to localhost:3000 for 5 s (connection refused). Nothing else was sent. Is your app running?", "reason": "target_unreachable"},
        "couldnt_start_probe_unstable": {"label": "Couldn't start the run", "helper": "Your probe isn't stable: its value changed with no delivery. Remove time-based values such as updated_at or now().", "reason": "probe_unstable"},
        "couldnt_start_reset_failed": {"label": "Couldn't start the run", "helper": "The reset failed on maya-mbp before the first delivery. Nothing was sent. The command and its output are in the agent's terminal.", "reason": "reset_failed", "cli": "The reset failed before the first delivery: npm run db:reset:webhooks exited with code 1. Nothing was sent."},
        "couldnt_start_needs_secret": {"label": "Couldn't start the run", "helper": "Stripe signs a timestamp, so nothing can be replayed without STRIPE_WEBHOOK_SECRET on maya-mbp.", "reason": "needs_secret"},
        "couldnt_start_agent_too_old": {"label": "Couldn't start the run", "helper": "maya-mbp runs 0.7.4. This run needs 0.9 or later: npx @webhooktrap/agent@latest", "reason": "agent_too_old"},
    },
    "scenario_reasons": {
        "needs_probe": {"label": "— Inconclusive", "helper": "Both responses were 2xx. Add a probe to see what the handler did to your state."},
        "needs_reset": {"label": "— Inconclusive", "helper": "Comparing with the baseline needs a reset between scenarios. Add reset: to webhooktrap.yml."},
        "probe_failed": {"label": "— Inconclusive", "helper": "Probe failed 3 times in a row; continuing at Level 1."},
        "not_simultaneous": {"label": "— Inconclusive", "helper": "The deliveries left 34 ms apart, more than the 20 ms a burst allows. Not judged at Level 2."},
        "redirected": {"label": "— Inconclusive", "helper": "The handler answered 302, so we can't tell whether it rejected the delivery."},
        "unsettled": {"label": "— Inconclusive", "helper": "orders.count changed 1 s after the last response. Your handler probably writes in a background job. Set settle: 2s in webhooktrap.yml."},
        "unreachable": {"label": "Unreachable", "helper": "maya-mbp couldn't connect to localhost:3000 for this delivery (connection refused). Not judged."},
        "na_one_event": {"label": "n/a", "helper": "Needs 2+ related events"},
        "na_no_timestamp": {"label": "n/a", "helper": "GitHub signs no timestamp"},
        "na_no_retries": {"label": "n/a", "helper": "GitHub doesn't retry automatically"},
        "na_not_json": {"label": "n/a", "helper": "Body isn't JSON"},
        "na_not_verified": {"label": "n/a", "helper": "Not verified for GitHub yet"},
        "na_unfaithful": {"label": "n/a", "helper": "Couldn't build a faithful request"},
        "not_selected": {"label": "Not selected", "helper": "Not part of this run"},
    },
    "info_findings": {
        "needs_probe": {"title": "Add a probe to see state", "helper": "Twice got 200 twice, but nothing reads your database, so a duplicate side effect can't be seen."},
        "order_dependent": {"title": "Results may depend on order", "helper": "No reset ran between scenarios. Add reset: to webhooktrap.yml."},
        "probe_gap": {"title": "Probe doesn't see subscription state", "helper": "customer.subscription.updated changes subscription state, which no probe reads."},
        "cold_start": {"title": "First delivery was slow", "helper": "D1 took 12 300 ms, later deliveries ≤ 238 ms. Likely a cold start, such as a dev server compiling the route. Not counted."},
        "async_write": {"title": "Handler writes after it responds", "helper": "orders.count changed 1 s after the last response. Your handler probably writes in a background job. Set settle: 2s in webhooktrap.yml."},
    },
    "guest": {
        "meter": {"label": "Guest session · expires in 23 h 49 m · 13/100 deliveries · 1/10 runs", "helper": "Sign in to keep"},
        "meter_waiting": {"label": "Guest session · expires in 23 h 59 m · 0/100 deliveries · 0/10 runs", "helper": "Sign in to keep"},
        "meter_first_four": {"label": "Guest session · expires in 23 h 57 m · 4/100 deliveries · 0/10 runs", "helper": "Sign in to keep"},
        "ending_soon": {"label": "Ends in 1 h", "helper": "This session ends in 1 h. Save fixtures or sign in to keep it."},
        "run_quota": {"label": "10 of 10 guest runs used", "helper": "Sign in to keep running. Runs that couldn't start don't count."},
        "run_too_late": {"label": "Session ends in 4 min", "helper": "A run can't start in the last 5 minutes of a guest session. Sign in to keep this session."},
        "expired_mid_run": {"label": "Guest session ended", "helper": "The session reached 24 hours during the run, so the run stopped and everything was deleted."},
        "ended": {"label": "Session ended", "helper": "Nothing was kept."},
        "claimed": {"label": "Guest session moved to your account", "helper": "Your deliveries, runs and agent are now in the project 'Stripe project'. The URLs didn't change. Share links were turned off; share again to get new ones. Confirm the new workspace in maya-mbp's terminal."},
    },
    "fixtures": {
        "not_saved": {"label": "Not saved", "helper": "Save these 3 events as scrubbed fixtures in your repo."},
        "saved": {"label": "Saved · in sync", "helper": "3 fixtures + webhooktrap.yml in brindle-app/webhooktrap/fixtures, written 14:12."},
        "drifted": {"label": "Shape drifted", "helper": "customer.subscription.updated now has cancellation_details.feedback, which isn't in your fixture."},
    },
    "probe_display": {
        "config_note": {"label": "Probe values", "helper": "Probe values stay on your machine. The web app shows counts up to 9 999 as numbers and other values as short codes such as #a1f3c0, which only show whether a value changed."},
        "tag_count": {"label": "count", "helper": "Shown as a number on the web."},
        "tag_code": {"label": "code", "helper": "Shown as a code on the web, such as #a1f3c0."},
        "tag_shown": {"label": "shown (show: value)", "helper": "Shown on your private run page. Shared links show a code unless you include probe values."},
        "code_tooltip": {"label": "#a1f3c0", "helper": "Code for a value that stays on your machine. Same code = same value within this run."},
        "invalid": {"label": "#invalid", "helper": "The server refused this value, so it isn't shown."},
        "guest_show_ignored": {"label": "show: ignored", "helper": "show: is ignored for guest sessions; sign in to use it"},
        "show_fallback": {"label": "Shown as a code", "helper": "customer.email is marked show: value, but its value looks like personal data, so the web app shows a code."},
        "lint_name": {"label": "Probe name", "helper": "Probe names are visible in the web app and on shared links."},
        "lint_unscoped": {"label": "Whole-table probe", "helper": "This probe reads the whole table. Its value can reveal your volume over time. Scope it with {{invoice.id}}."},
    },
    "share": {
        "title": {"label": "Share Run #3", "helper": "Shared as Maya Lindqvist"},
        "private": {"label": "Only people in this project", "helper": "The run page needs a sign-in."},
        "link": {"label": "Anyone with the link", "helper": "People with the link see probe names and counts. Other values show as codes, and signing secrets never leave your machine."},
        "hide_bodies": {"label": "Hide request and response bodies", "helper": "Fields your provider pack marks as personal are always hidden."},
        "include_probe_values": {"label": "Include probe values", "helper": "Also shows the values you marked show: in webhooktrap.yml: subscriptions.status."},
        "guest_note": {"label": "Guest link", "helper": "This link stops working when your guest session ends or when you sign in."},
        "new_link": {"label": "Create a new link", "helper": "The current link stops working at once."},
        "rotated": {"label": "New link ready", "helper": "The old link now shows Not found."},
        "stop": {"label": "Stop sharing", "helper": "The link now shows Not found."},
        "copied": {"label": "Copied", "helper": "Share link copied. Anyone with the link can see this run."},
        "ci_private": {"label": "Uploaded privately", "helper": "Uploaded runs are private. Add --share to get a link anyone can open."},
    },
    "provenance": {
        "agent_web": {"label": "Run by maya-mbp from the web app", "helper": "Results come from the machine that ran them. Webhook Trap re-checks status codes and timings only."},
        "agent_cli": {"label": "Run by maya-mbp from the CLI", "helper": None},
        "ci": {"label": "Uploaded from CI (token github-actions)", "helper": None},
        "server_public": {"label": "Run by the Webhook Trap server runner", "helper": None},
        "server_allowlist": {"label": "Run by the Webhook Trap server runner", "helper": "Self-host: private destinations allowed for this user (B′)."},
        "judged_on": {"label": "Level 2 · judged on maya-mbp", "helper": "Finding header for Level 2 findings (RUN-9); \"judged in CI\" for CI runs. Never \"verified\"."},
    },
    "errors": {
        "server": {"label": "Something went wrong on our side", "helper": "Your deliveries are safe. Retry, or check status.webhooktrap.dev. Ref 7F3K-21QX."},
        "offline": {"label": "You're offline", "helper": "Showing what was loaded at 14:07. Live updates resume when you're back."},
        "not_found": {"label": "Delivery not found", "helper": "It may have expired with its guest session, or the link is wrong."},
        "read_only": {"label": "Read-only shared run", "helper": "Shared by Maya Lindqvist. Sign in to run it again on your own agent."},
        "example": {"label": "Example run", "helper": "A real run against a deliberately buggy handler. Get a URL to prove your own."},
        "not_found_run": {"label": "Not found", "helper": "This run doesn't exist or isn't shared with you. Guest runs are kept for 24 hours."},
    },
}

# ---------------------------------------------------------------- account security (decision 44, A05 rev. 4 domain §2.1-2.4)
# Deterministic: derived from sha256 seeds only, never from rng, so every value above stays identical.
def crock_code(seed, n):
    v = int(h(seed), 16)
    out = ""
    for _ in range(n):
        out += CROCK[v % 32].lower()
        v //= 32
    return out


def recovery_code(i):
    c = crock_code(f"recovery-code-{i}", 10)   # 10 Crockford symbols = 50 bits (A09 R-K: >= 50 bits)
    return c[:5] + "-" + c[5:]


def b32_secret(seed):
    v = int(h(seed), 16)
    out = ""
    for _ in range(32):
        out += "ABCDEFGHIJKLMNOPQRSTUVWXYZ234567"[v % 32]
        v //= 32
    return out


def key_id(seed):
    # ULID-shaped, but the random part comes from the seed, not rng (keeps every other id unchanged)
    return "key_" + crock_code(seed + "-t", 10).upper() + crock_code(seed + "-r", 16).upper()


fmt_day = lambda dt: dt.strftime("%-d %b %Y")
fmt_full = lambda dt: dt.strftime("%-d %b %Y, %H:%M UTC")
TOTP_SECRET = b32_secret("totp-maya")
REC_REQ = T0 - timedelta(hours=4, minutes=46)                 # 11 Nov 09:12 UTC
REC_CONF = REC_REQ + timedelta(minutes=8)                     # 09:20 UTC
REC_RESET = T0 - timedelta(days=2, hours=19, minutes=18)      # password reset by email 2 days before (RC-2)
account_security = {
    "note": "Fixtures for the account-security screens (S-02, S-17A/B, S-24 to S-31). Fictional. Recovery codes and the TOTP secret are derived from sha256 seeds; they open nothing.",
    "maya": {
        "email": "maya@brindlecoffee.example",
        "has_password": True,
        "identities": [
            {"provider": "github", "label": "maya-l", "linked_at": iso(T0 - timedelta(days=9)), "linked_label": fmt_day(T0 - timedelta(days=9))},
        ],
        "identities_available": ["google"],
        "keys": [
            {"id": key_id("k-touchid"), "name": "MacBook Touch ID", "kind": "passkey", "synced": True, "aaguid_default": "iCloud Keychain",
             "created_at": iso(T0 - timedelta(days=2, hours=3)), "created_label": fmt_day(T0 - timedelta(days=2, hours=3)),
             "last_used_at": iso(T0 - timedelta(minutes=6)), "last_used_label": "Today, 13:52 UTC", "flagged": False},
            {"id": key_id("k-yubi"), "name": "YubiKey 5C NFC", "kind": "security_key", "synced": False, "aaguid_default": "YubiKey 5 Series",
             "created_at": iso(T0 - timedelta(days=1, hours=5)), "created_label": fmt_day(T0 - timedelta(days=1, hours=5)),
             "last_used_at": iso(T0 - timedelta(days=1, hours=5)), "last_used_label": fmt_day(T0 - timedelta(days=1, hours=5)), "flagged": False},
        ],
        "keys_states_sheet": [
            {"id": key_id("k-pixel"), "name": "Pixel 8", "kind": "passkey", "synced": True, "aaguid_default": "Google Password Manager",
             "created_at": iso(T0 - timedelta(days=4)), "created_label": fmt_day(T0 - timedelta(days=4)), "last_used_at": None, "last_used_label": "Never used", "flagged": False},
            {"id": key_id("k-old"), "name": "Old YubiKey", "kind": "security_key", "synced": False, "aaguid_default": "YubiKey 5 Series",
             "created_at": iso(T0 - timedelta(days=8)), "created_label": fmt_day(T0 - timedelta(days=8)),
             "last_used_at": iso(T0 - timedelta(hours=2, minutes=14)), "last_used_label": "Today, 11:44 UTC", "flagged": True,
             "flag_reason": "counter", "flag_note": "Blocked on 11 Nov 2026, 11:44 UTC: its use counter went backwards, which can mean the key was copied."},
        ],
        "totp": {"enabled": True, "enabled_label": fmt_day(T0 - timedelta(days=2, hours=3)),
                 "enrol_secret": TOTP_SECRET, "enrol_secret_grouped": " ".join(TOTP_SECRET[i:i + 4] for i in range(0, 32, 4)),
                 "otpauth_uri": f"otpauth://totp/Webhook%20Trap:maya%40brindlecoffee.example?secret={TOTP_SECRET}&issuer=Webhook%20Trap&period=30&digits=6",
                 "sample_code": "482 913"},
        "recovery_codes": [recovery_code(i) for i in range(10)],
        "recovery_codes_left": 8,
        "recovery_codes_file": "webhooktrap-recovery-codes.txt",
        "max_keys": 10,
        "second_factor_hold_until": "15:12 UTC",
    },
    "recovery": {
        "requested_at": iso(REC_REQ), "requested_label": fmt_full(REC_REQ),
        "confirmed_at": iso(REC_CONF), "confirmed_label": fmt_full(REC_CONF),
        "reminder_at": iso(REC_CONF + timedelta(hours=24)), "reminder_label": fmt_full(REC_CONF + timedelta(hours=24)),
        "completes_at": iso(REC_CONF + timedelta(hours=72)), "completes_label": fmt_full(REC_CONF + timedelta(hours=72)),
        "extended": {"password_reset_at": iso(REC_RESET), "password_reset_label": fmt_full(REC_RESET),
                     "completes_at": iso(REC_CONF + timedelta(days=7)), "completes_label": fmt_full(REC_CONF + timedelta(days=7)),
                     "note": "RC-2: a password reset by email in the 7 days before the request moves completion to 7 days from the later event (here the confirmation)."},
        "cooldown_until_label": fmt_day(REC_CONF + timedelta(days=30)),
        "lock_until_label": fmt_day(REC_CONF + timedelta(hours=72) + timedelta(days=7)),
        "agents_reconfirm": ["maya-mbp"],
        "ci_tokens_review": [{"name": "github-actions", "project": "Brindle Coffee", "last_used_label": fmt_day(T0 - timedelta(days=1))}],
        "security_log": [
            {"at": fmt_full(REC_REQ), "event": "Account recovery requested", "where": "Firefox on Windows · 203.0.113.0/24"},
            {"at": fmt_full(REC_CONF), "event": "Recovery link confirmed; 72-hour wait started", "where": "Firefox on Windows · 203.0.113.0/24"},
            {"at": fmt_full(REC_CONF + timedelta(hours=24)), "event": "Reminder email sent", "where": "—"},
            {"at": fmt_full(REC_CONF + timedelta(hours=72)), "event": "Recovery completed: passkeys, security keys, authenticator app and recovery codes removed; browser sessions and personal tokens revoked; maya-mbp needs confirmation", "where": "—"},
        ],
    },
    "imported_1x": {
        "note": "Three fictional stand-ins for the 3 imported 1.x accounts (decision 41). The real names and emails stay in the PO's export and never enter design files.",
        "accounts": [
            {"name": "Tomás Okafor", "email": "tomas@ferrymarket.example", "created_1x": "2025-03-02", "created_1x_label": "2 Mar 2025", "activation": "reset"},
            {"name": "Aiko Brandt", "email": "aiko.brandt@example.com", "created_1x": "2025-06-19", "created_1x_label": "19 Jun 2025", "activation": "github"},
            {"name": "Lena Vos", "email": "lena@vosworks.example", "created_1x": "2025-01-11", "created_1x_label": "11 Jan 2025", "activation": "not yet"},
        ],
        "notice_subject": "Webhook Trap 2.0 is here: set a password to sign in",
    },
    "sign_in_context": {"browser": "Firefox 133 on Windows", "network": "203.0.113.0/24", "new_device_example": "Safari 18 on iPhone"},
    "self_host_rp": {"instance": "hooks.ferrymarket.example", "rp_id": "hooks.ferrymarket.example", "http_example": "http://192.168.1.40:8080"},
}

# ---------------------------------------------------------------- account security, rev. 4 (A05 revision 5: domain §2.1, §2.2, §2.5-2.9; contracts §3.4)
# Additions only: every value above stays identical. Times derive from T0, never from rng.
SES_MAC = T0 - timedelta(minutes=6)                            # passkey sign-in, 11 Nov 13:52 (MacBook Touch ID last used)
SES_WIN = T0 - timedelta(days=1, hours=5, minutes=6)           # 10 Nov 08:52, password then authenticator app
SES_WIN_SEEN = T0 - timedelta(hours=20, minutes=27)            # 10 Nov 17:31
SES_IOS = T0 - timedelta(days=6, hours=18, minutes=53)         # 4 Nov 19:05, GitHub (2FA was not on yet)
SES_IOS_SEEN = T0 - timedelta(days=2, hours=6, minutes=18)     # 9 Nov 07:40
KEY_TOUCH = T0 - timedelta(days=2, hours=3)                    # 9 Nov 10:58 (created_at of MacBook Touch ID)
KEY_YUBI = T0 - timedelta(days=1, hours=5)                     # 10 Nov 08:58 (created_at of YubiKey 5C NFC)
LINKED_GH = T0 - timedelta(days=9)                             # 2 Nov 13:58, account created with GitHub
EMC_REQ = T0 - timedelta(minutes=2)                            # email change requested 13:56; link works 60 min
REC_DONE = REC_CONF + timedelta(hours=72)                      # 14 Nov 09:20 (recovery completed)

MAC = {"browser": "Safari 18 on macOS", "network": "198.51.100.0/24"}
WIN = {"browser": "Firefox 133 on Windows", "network": "203.0.113.0/24"}
IOS = {"browser": "Safari 18 on iPhone", "network": "2001:db8:4a1c::/48"}


def log_row(dt, code, where):
    return {"at": iso(dt), "at_label": fmt_full(dt), "action": code, "what": SECURITY_LOG_COPY[code].format(**where.get("args", {})),
            "browser": where.get("browser"), "network": where.get("network")}


# Display copy per security-log action code. Codes are A03 proposals for the allow-list in A05 schema §7 [pending A05 exact codes].
SECURITY_LOG_COPY = {
    "signed_in": "Signed in with {how}",
    "second_factor_hold": "Two-step sign-in paused after 20 wrong codes or keys",
    "key_added": "{kind} added: {name}",
    "key_removed": "{kind} removed: {name}",
    "key_blocked": "Key blocked: {name}",
    "key_unblocked": "Key unblocked: {name}",
    "totp_enabled": "Authenticator app turned on",
    "totp_disabled": "Authenticator app turned off",
    "recovery_codes_regenerated": "New recovery codes created",
    "password_set": "Password set",
    "password_changed": "Password changed; other browsers signed out",
    "password_reset": "Password reset by email; every browser signed out",
    "email_change_requested": "Email change requested to {masked}",
    "email_changed": "Email changed to {masked}; other browsers signed out",
    "identity_linked": "{provider} linked ({label})",
    "identity_unlinked": "{provider} disconnected ({label})",
    "session_revoked": "Signed out {browser_label}",
    "recovery_requested": "Account recovery requested",
    "recovery_confirmed": "Recovery link confirmed; 72-hour wait started",
    "recovery_extended": "Recovery wait extended to 7 days",
    "recovery_cancelled": "Account recovery cancelled",
    "recovery_completed": "Account recovered: passkeys, security keys, authenticator app and recovery codes removed; every browser and personal token signed out",
    "run_token_created": "Personal token that can start runs created: {name}",
}

account_security["maya"].update({
    "sessions": [
        {"id": "ses_" + crock_code("ses-mac-t", 10).upper() + crock_code("ses-mac-r", 16).upper(), "current": True, **MAC,
         "signed_in_at": iso(SES_MAC), "signed_in_label": fmt_full(SES_MAC), "last_seen_at": iso(T0), "last_seen_label": "Active now",
         "method": {"first": "passkey", "second": "passkey"}, "method_label": "Passkey"},
        {"id": "ses_" + crock_code("ses-win-t", 10).upper() + crock_code("ses-win-r", 16).upper(), "current": False, **WIN,
         "signed_in_at": iso(SES_WIN), "signed_in_label": fmt_full(SES_WIN), "last_seen_at": iso(SES_WIN_SEEN), "last_seen_label": fmt_full(SES_WIN_SEEN),
         "method": {"first": "password", "second": "totp"}, "method_label": "Password, then authenticator app"},
        {"id": "ses_" + crock_code("ses-ios-t", 10).upper() + crock_code("ses-ios-r", 16).upper(), "current": False, **IOS,
         "signed_in_at": iso(SES_IOS), "signed_in_label": fmt_full(SES_IOS), "last_seen_at": iso(SES_IOS_SEEN), "last_seen_label": fmt_full(SES_IOS_SEEN),
         "method": {"first": "github", "second": None}, "method_label": "GitHub"},
    ],
    "sessions_note": "Networks are stored truncated (/24 IPv4, /48 IPv6; A05 domain §2.7). Addresses are documentation ranges (RFC 5737, RFC 3849).",
    "method_labels": {"password": "Password", "github": "GitHub", "google": "Google", "passkey": "Passkey", "reset_link": "Password reset link",
                      "recovery_finish": "Account recovery", "second": {"security_key": "security key", "totp": "authenticator app", "recovery_code": "recovery code"},
                      "pattern": "{first}, then {second}", "passkey_rule": "A passkey sign-in shows \"Passkey\" alone: it is both steps."},
    "security_log": [
        log_row(SES_MAC, "signed_in", {**MAC, "args": {"how": "a passkey (MacBook Touch ID)"}}),
        log_row(KEY_YUBI, "key_added", {**WIN, "args": {"kind": "Security key", "name": "YubiKey 5C NFC"}}),
        log_row(SES_WIN, "signed_in", {**WIN, "args": {"how": "password, then authenticator app"}}),
        log_row(KEY_TOUCH + timedelta(minutes=4), "totp_enabled", MAC),
        log_row(KEY_TOUCH, "key_added", {**MAC, "args": {"kind": "Passkey", "name": "MacBook Touch ID"}}),
        log_row(SES_IOS, "signed_in", {**IOS, "args": {"how": "GitHub"}}),
        log_row(LINKED_GH + timedelta(minutes=7), "password_set", MAC),
        log_row(LINKED_GH, "identity_linked", {**MAC, "args": {"provider": "GitHub", "label": "maya-l"}}),
    ],
    "security_log_note": "Last 30 days, newest first, at most 50 rows (A05 SecurityView.securityLog).",
    "security_log_empty": "Nothing in the last 30 days.",
    "pending_email": {"new": "maya@brindleroasters.example", "masked": "m***@b***.example", "requested_at": iso(EMC_REQ), "requested_label": fmt_full(EMC_REQ),
                      "expires_at": iso(EMC_REQ + timedelta(minutes=60)), "expires_label": (EMC_REQ + timedelta(minutes=60)).strftime("%H:%M UTC"),
                      "taken_example": "tomas@ferrymarket.example"},
    "sign_in_throttled_until": (T0 + timedelta(hours=1)).strftime("%H:%M UTC"),
    "sign_in_throttled_note": "Illustrative: A05 names the sign-in throttle (per email + address, per address) without numbers; the screen prints the time from Retry-After. Example uses the 1-hour hold length (domain §2.1) [pending A05 value].",
    "password_policy": {"min": 12, "max": 128, "max_bcrypt_bytes": 72, "common_list": "bundled, about 100 000 entries, no network call"},
    "link_lifetimes": {"verify": "60 minutes", "email_change": "60 minutes", "reset": "60 minutes", "recovery_request": "60 minutes", "recovery_finish": "24 hours", "self_host_password_set": "24 hours"},
})
account_security["maya"]["keys_states_sheet"][1].update({
    "flag_count": 1, "can_unblock": True, "flag_cause": "counter",
    "flag_note_user_handle": "Blocked on 11 Nov 2026, 11:44 UTC: it sent sign-in details that don't belong to your account, which can mean it was tampered with.",
})
account_security["maya"]["keys_states_sheet"].append(
    {"id": key_id("k-spare"), "name": "Spare YubiKey", "kind": "security_key", "synced": False, "aaguid_default": "YubiKey 5 Series",
     "created_at": iso(T0 - timedelta(days=7)), "created_label": fmt_day(T0 - timedelta(days=7)),
     "last_used_at": iso(T0 - timedelta(hours=3, minutes=1)), "last_used_label": "Today, 10:57 UTC", "flagged": True,
     "flag_reason": "counter", "flag_cause": "counter", "flag_count": 2, "can_unblock": False,
     "flag_note": "Blocked on 11 Nov 2026, 10:57 UTC: its use counter went backwards, which can mean the key was copied.",
     "twice_note": "This key was blocked twice. Remove it and add it again."})

account_security["recovery"].update({
    "completed_at": iso(REC_DONE), "completed_label": fmt_full(REC_DONE),
    "lock_until_at": iso(REC_DONE + timedelta(days=7)), "lock_until_full_label": fmt_full(REC_DONE + timedelta(days=7)),
    "request_link_expires_label": fmt_full(REC_REQ + timedelta(minutes=60)),
    "finish_link_expires_label": fmt_full(REC_DONE + timedelta(hours=24)),
    "review": {
        "agents": [{"name": "maya-mbp", "needs_reconfirm": True}],
        "ci_tokens": [
            {"name": "github-actions", "project_key": "brindle", "project_name": "Brindle Coffee", "last_used_label": fmt_day(T0 - timedelta(days=1))},
            {"name": "github-actions", "project_key": "stripe", "project_name": "Stripe project", "last_used_label": "Never used"},
        ],
        "revoked_toast": "github-actions revoked in Stripe project.",
    },
})
# review log: the recovery rows (requested, confirmed, completed) plus the finish-link sign-in, newest first
account_security["recovery"]["review"]["log"] = [
    log_row(REC_DONE + timedelta(minutes=14), "signed_in", {**WIN, "args": {"how": "the recovery link"}}),
    log_row(REC_DONE + timedelta(minutes=14), "password_set", WIN),
    log_row(REC_DONE, "recovery_completed", {}),
    log_row(REC_CONF, "recovery_confirmed", WIN),
    log_row(REC_REQ, "recovery_requested", WIN),
]
account_security["recovery"]["decline"] = {"h1": "Recovery not started", "body": "Nothing changed. You can close this tab."}

account_security["account_deletion"] = {
    "pending": "A05 has no route yet (U-19); A03 UX drawn as [pending A05]",
    "counts": {"projects": 2, "runs": len(run_history), "agents": 1, "ci_tokens": 2, "personal_tokens": 1},
    "summary": f"2 projects (Brindle Coffee, Stripe project), {len(run_history)} runs, 1 agent (maya-mbp), 2 CI tokens and 1 personal token",
}

data = {
    "meta": {
        "status": "PROPOSED",
        "author": "A03",
        "date": "2026-11-14",
        "purpose": "One shared dataset every territory uses, so the PO compares design, not content.",
        "fake_notice": "Company, people, ids, hashes and signatures are fictional. Signatures are correctly formatted but derived from sha256 of fixed seeds, not from any secret. Emails use example.com / .example domains.",
        "timezone_note": "All times are UTC in data. The UI renders in the viewer's time zone; screens in this round use UTC to stay comparable.",
        "placeholders": "Domain webhooktrap.dev, the npm package @webhooktrap/agent and the command webhooktrap are provisional until Q12 (brand) settles (C-1, decision 35). Headers are Webhooktrap-*, the config file webhooktrap.yml.",
        "regenerated": "2026-11-14 for decision 35 (A01 scenarios-and-judge §16 changes 1 to 14; C-4: Run #3 has 16 deliveries), then for decisions 38 and 39 (PV-1 to PV-14; PO-A, PO-C to PO-F).",
    },
    "company": {"name": "Brindle Coffee Co.", "what": "A small specialty coffee roaster: Shopify storefront, Stripe for monthly bean subscriptions, Parcelbird (fictional, Standard Webhooks) for shipping labels, code on GitHub.", "domain": "brindlecoffee.example", "shop": "brindle-coffee.myshopify.com"},
    "user": {"name": "Maya Lindqvist", "handle": "maya-l", "email": "maya@brindlecoffee.example", "role": "Solo backend developer, uses a coding agent"},
    "project": {"id": "prj_" + ulid(T0 - timedelta(days=9)), "name": "Brindle Coffee", "slug": "brindle", "capture_url": f"https://{HOST}/c/{PROJECT_INBOX}", "created_at": iso(T0 - timedelta(days=9)), "deliveries_total": 13},
    "guest": {"capture_url": f"https://{HOST}/c/{GUEST_INBOX}", "created_at": iso(T0 - timedelta(minutes=3)), "expires_at": iso(T0 - timedelta(minutes=3) + timedelta(hours=24)), "expires_in_label": "23 h 49 m", "quota": {"deliveries_used": 13, "deliveries_limit": 100, "runs_used": 1, "runs_limit": 10}, "note": "Guest limits APPROVED (A-13, decision 35): 24 h from URL creation, 100 stored deliveries, 10 runs per session (a run counts once it gets past Once), 1 agent at a time (pairing a second replaces the first)."},
    "agent": {"name": "maya-mbp", "version": "0.9.2", "os": "macOS 15.4 (arm64)", "status": "connected", "connected_since": iso(T0 - timedelta(minutes=2, seconds=11)), "pairing_code": "7KQ2M-XR9TD", "pairing_code_spoken": "7 K Q 2 M, X R 9 T D", "install_command": "npx @webhooktrap/agent@1 pair 7KQ2M-XR9TD", "pairing_terminal": ["$ npx @webhooktrap/agent@1 pair 7KQ2M-XR9TD", "Server     https://webhooktrap.dev", "Workspace  Guest session · expires 2026-11-12 13:55 UTC", "Join? Only continue if you created this code in your own browser just now. [y/N] y", "Joined as maya-mbp. Leave this terminal open while you run."], "pairing_terminal_no": ["Join? Only continue if you created this code in your own browser just now. [y/N] n", "Not joined. This agent is not paired, and the code is used up."], "reconfirm_terminal": ["This agent's workspace changed. Check it before it accepts jobs.", "Server     https://webhooktrap.dev", "Workspace  Maya Lindqvist (maya@brindlecoffee.example)", "Continue with this workspace? [y/N] y", "Confirmed. Target approvals were cleared; the first run to each target asks again."], "target": "http://localhost:3000/webhooks/stripe", "allowed_targets": ["localhost:3000"], "secrets": [{"provider": "Stripe", "name": "STRIPE_WEBHOOK_SECRET", "state": "set"}, {"provider": "GitHub", "name": "GITHUB_WEBHOOK_SECRET", "state": "set"}, {"provider": "Shopify", "name": "SHOPIFY_API_SECRET", "state": "set"}, {"provider": "Parcelbird", "name": None, "state": "not set"}], "secrets_note": "Names only. The server never receives a secret value or any part of one, not even the last 4 characters (PO-D).", "repo": "~/code/brindle-app"},
    "providers": [
        {"name": "Stripe", "scheme": "Stripe-Signature v1", "budget_ms": 10000, "budget_source": "default", "budget_label": "10 s budget · Webhook Trap default", "tolerance_s": 300, "budget_note": "Stripe publishes no timeout. Webhook Trap default budget.", "deliveries": 5, "pack": "mvp"},
        {"name": "Shopify", "scheme": "X-Shopify-Hmac-Sha256", "budget_ms": 5000, "budget_source": "Shopify docs", "budget_label": "5 s · Shopify limit", "tolerance_s": None, "budget_note": "Documented: 5 s. Retry policy: 8 attempts in 4 h.", "deliveries": 3, "pack": "mvp"},
        {"name": "GitHub", "scheme": "X-Hub-Signature-256", "budget_ms": 10000, "budget_source": "GitHub docs", "budget_label": "10 s · GitHub limit", "tolerance_s": None, "budget_note": "Documented: 10 s. No automatic retries.", "deliveries": 2, "pack": "mvp"},
        {"name": "Parcelbird (Standard Webhooks)", "scheme": "webhook-signature v1", "budget_ms": 10000, "budget_source": "default", "budget_label": "10 s budget · Webhook Trap default", "tolerance_s": 300, "budget_note": "Fictional provider on the Standard Webhooks scheme pack. The spec's 15–30 s is guidance for senders, not a consumer limit (BUD-1).", "deliveries": 2, "pack": "mvp (scheme)"},
        {"name": "Unknown", "scheme": None, "budget_ms": 10000, "budget_source": "default", "budget_label": "10 s budget · Webhook Trap default", "tolerance_s": None, "budget_note": "Capture and replay only: sent as captured; Late, Tampered and Unsigned n/a (SIGN-2).", "deliveries": 1, "pack": "none"},
    ],
    "providers_coming": ["Twilio", "Slack"],
    "examples": {
        "public_target": "https://staging.brindlecoffee.example/webhooks/shopify",
        "public_target_note": "For server-side replay (P6): a public staging URL Maya owns. Used by FL-9 with group Order #1042 (#05, #06).",
        "private_target_refused": "http://10.0.4.12:3000/webhooks/shopify",
        "ci_token": {"name": "github-actions"},
        "second_project": "Stripe project",
        "shopify_server_draft": {
            "note": "FL-9: group Order #1042 (#05, #06), n = 2, server target, no secret. Citations wait for A01 (DUP-1, DUP-2): shown as [pending A01].",
            "rows": [
                {"key": "once", "deliveries": 2, "what": "Every selected event once, in capture order. Sets the baseline.", "why": "Every provider", "citation": None, "state": "locked"},
                {"key": "twice", "deliveries": None, "what": "The first event again, 500 ms after the first delivery completes: same body, same X-Shopify-Webhook-Id", "why": "Shopify may deliver the same webhook more than once.", "citation": "[pending A01]", "state": "n/a · Not verified for Shopify yet"},
                {"key": "shuffled", "deliveries": 2, "what": "Related events in reverse order", "why": "Shopify doesn't guarantee that webhooks arrive in the order they were created.", "citation": "[pending A01]", "state": "checked"},
                {"key": "late", "deliveries": None, "what": "Valid signature with an old timestamp", "why": None, "citation": None, "state": "n/a · Shopify signs no timestamp"},
                {"key": "retry", "deliveries": None, "what": "The first event again, 2 000 ms after the first attempt completes, as a retry", "why": "Shopify retries a delivery that fails or answers too slowly.", "citation": "[pending A01]", "state": "n/a · Not verified for Shopify yet"},
                {"key": "tampered", "deliveries": 1, "what": "One field changed in the body (line_items[0].quantity 2 → 0), sent with the original signature header", "why": "A signature only protects you if a failed check stops the request.", "citation": "[pending A01]", "state": "checked", "mutation": {"path": "line_items[0].quantity", "from": "2", "to": "0", "rule": "TAMP-1 rule 2 (no Shopify tamper_paths yet) [pending A01]"}},
                {"key": "unsigned", "deliveries": 1, "what": "The first event with X-Shopify-Hmac-Sha256 removed", "why": "A handler that checks the signature only when the header is present accepts anything.", "citation": "[pending A01]", "state": "checked"},
                {"key": "burst", "deliveries": 2, "what": "Every selected event, released together (≤ 20 ms apart)", "why": "Shopify can send related webhooks, such as orders/create and orders/paid, within a second of each other.", "citation": "[pending A01]", "state": "checked"},
                {"key": "slow", "deliveries": 1, "what": "One more delivery after the others, measured against the budget: 5 s · Shopify limit", "why": "Shopify counts a response slower than 5 s as failed and retries it.", "citation": "Shopify docs [page: pending A01]", "state": "checked"},
            ],
            "summary": "6 scenarios selected · 9 deliveries",
        },
        "code_reading": {
            "note": "Illustrative states-sheet example, not part of Run #3: a second probe entry subscriptions.status, judged on D4 of a run that proves customer.subscription.updated. Codes are PV-3 labels.",
            "name": "subscriptions.status",
            "owner_default": {"before": "#a1f3c0", "after": "#9c0254", "expected": "#a1f3c0", "line": "Expected #a1f3c0 · got #9c0254", "detail": "before #a1f3c0 → after #9c0254", "strip": "got #9c0254", "tag": "code"},
            "owner_opted": {"before": "active", "after": "past_due", "expected": "active", "line": "Expected active · got past_due", "detail": "before active → after past_due", "strip": "got past_due", "tag": "shown (show: value)"},
            "unchanged": {"line": "Expected #a1f3c0 · got #a1f3c0", "detail": "unchanged, as expected"},
            "widened": {"line": "Expected #a1f3c0d27e45 · got #9c0254b1f08a", "note": "PV-4: a collision widened every code of the run to 12 characters"},
            "invalid": {"line": "Expected #a1f3c0 · got #invalid"},
        },
    },
    "account_security": account_security,
    "groups": groups,
    "deliveries": deliveries,
    "scenarios": scenarios,
    "run": run,
    "run_history": run_history,
    "copy": copy,
}

with open(f"{OUT_DIR}/realistic-data.json", "w") as fh:
    json.dump(data, fh, indent=2, ensure_ascii=False)
    fh.write("\n")

# ---------------------------------------------------------------- markdown
L = []
w = L.append
fmt_n = lambda n: f"{n:,}".replace(",", " ") if isinstance(n, int) else str(n)


def hhmmss(s):
    return s[11:23]


w("# Realistic Data: Webhook Trap 2.0 design round")
w("")
w("**Status:** PROPOSED · **Author:** A03 (UX) · **Date:** 2026-11-11, regenerated 2026-11-14 (decision 35)")
w("**Machine-readable twin:** [`realistic-data.json`](realistic-data.json) holds exactly the same content (generated from one source, so values match byte for byte).")
w("")
w("Every territory (Timetable, Crash Lab, Notation) must use **this** dataset on every screen, so the PO compares design, not content. Don't invent other ids, numbers or copy; if a screen needs something missing here, ask A03 to add it.")
w("")
w("> " + data["meta"]["fake_notice"])
w("> ")
w("> " + data["meta"]["timezone_note"] + " " + data["meta"]["placeholders"])
w("> ")
w("> Regenerated " + data["meta"]["regenerated"] + " The change table is at the end of this file.")
w("")
w("## 1. The world")
w("")
w("| | |")
w("| - | - |")
w(f"| Company | **{data['company']['name']}**: {data['company']['what']} |")
w(f"| User | {data['user']['name']} (`{data['user']['handle']}`, {data['user']['email']}): {data['user']['role']} |")
w(f"| Project | **{data['project']['name']}** · `{data['project']['id']}` · capture URL `{data['project']['capture_url']}` |")
w(f"| Guest variant | capture URL `{data['guest']['capture_url']}` · expires in {data['guest']['expires_in_label']} · {data['guest']['quota']['deliveries_used']}/{data['guest']['quota']['deliveries_limit']} deliveries · {data['guest']['quota']['runs_used']}/{data['guest']['quota']['runs_limit']} runs. {data['guest']['note']} |")
a = data["agent"]
w(f"| Agent | **`{a['name']}`** · v{a['version']} · {a['os']} · {a['status']} since {hhmmss(a['connected_since'])} · target `{a['target']}` · repo `{a['repo']}` |")
w(f"| Pairing | code `{a['pairing_code']}` (read as \"{a['pairing_code_spoken']}\") · `{a['install_command']}` · terminal: " + " / ".join(f"`{x}`" for x in a["pairing_terminal"]) + " · on N: " + " / ".join(f"`{x}`" for x in a["pairing_terminal_no"]) + " · after a claim: " + " / ".join(f"`{x}`" for x in a["reconfirm_terminal"]) + " |")
ex = data["examples"]
w(f"| Examples | public target for server-side replay `{ex['public_target']}` · refused private target `{ex['private_target_refused']}` · CI upload token `{ex['ci_token']['name']}` (shown by name only) · claimed guest project '{ex['second_project']}' |")
w("| Secrets on the agent (names only, never values) | " + " · ".join(f"{s['provider']}: `{s['name']}` ({s['state']})" if s["name"] else f"{s['provider']}: not set" for s in a["secrets"]) + " |")
w("")
w("**Provider budgets used by the UI** (response-time budget drawn as the budget line/band/ruler/clock; every number carries its source tag):")
w("")
w("| Provider | Signature scheme | Budget | Source | UI label | Tolerance | Pack | Note | Deliveries in stream |")
w("| -------- | ---------------- | ------ | ------ | -------- | --------- | ---- | ---- | -------------------- |")
for p in data["providers"]:
    w(f"| {p['name']} | {p['scheme'] or '—'} | {fmt_n(p['budget_ms']) + ' ms' if p['budget_ms'] else '—'} | {p['budget_source'] or '—'} | {p['budget_label']} | {str(p['tolerance_s']) + ' s' if p['tolerance_s'] else 'none'} | {p['pack']} | {p['budget_note'] or '—'} | {p['deliveries']} |")
w("")
w("Coming (shown as names only, no budget anywhere in the product until their pack ships, A-18): " + ", ".join(data["providers_coming"]) + ".")
w("")
w(f"## 2. Capture stream: {len(deliveries)} deliveries in {len(groups)} groups")
w("")
w("Newest first is how the stream shows them; the table below is in arrival order. Times are `received_at` (UTC). Sizes are the exact byte length of the bodies in §3.")
w("")
w("| # | Received | Provider | Event type | Group | Size | Signature | Shape |")
w("| - | -------- | -------- | ---------- | ----- | ---- | --------- | ----- |")
gshort = {g["id"]: g["short"] for g in groups}
for d in deliveries:
    w(f"| {d['label']} | {hhmmss(d['received_at'])} | {d['provider']} | `{d['event_type']}` | {gshort.get(d['group'], '—')} | {fmt_n(d['size_bytes'])} B | {d['signature']['label']} | {d['shape']['label']} |")
w("")
w("### Groups (related object)")
w("")
w("| Group | Provider | Subtitle | Deliveries | Spread | Note |")
w("| ----- | -------- | -------- | ---------- | ------ | ---- |")
lab = {d["id"]: d["label"] for d in deliveries}
for g in groups:
    w(f"| **{g['short']}** (`{g['title']}`) | {g['provider']} | {g['subtitle']} | {', '.join(lab[i] for i in g['delivery_ids'])} | {g['spread']} | {g.get('note', '') or ('Suggested for Prove' if g['prove_suggested'] else '')} |")
w("")
w("### The signature-invalid delivery (#09)")
w("")
inv = by_id[d_gh2]["signature"]
w(f"- **Label:** {inv['label']} · **Reason:** {inv['reason']}")
w(f"- **Detail:** {inv['detail']}")
w(f"- **Fix:** {inv['fix']}")
w("")
w("Other signature states in the stream: **No secret** (#08, #12 Parcelbird: " + SIG_NO_SECRET["detail"] + ") and **Unsigned** (#13 curl: " + SIG_UNSIGNED["detail"] + "). *Unknown scheme* and *Not checked (no agent)* appear only as state copy in §6.")
w("")
w("## 3. Every delivery in full")
w("")
w("Headers are listed **in received order**, exactly as they should appear on the Raw tab. Bodies are the exact bytes (Stripe sends pretty-printed JSON; the others send compact JSON). Bodies are real-shaped but trimmed to the fields that matter.")
w("")
for d in deliveries:
    w(f"### {d['label']} · {d['provider']} · `{d['event_type']}`")
    w("")
    w(f"- **Delivery id:** `{d['id']}` · **received** `{d['received_at']}` · **ingest** {d['ingest_latency_ms']} ms (provider POST → on screen)")
    w(f"- **Request line:** `{d['method']} {d['path']}{'?' + d['raw_query'] if d['raw_query'] else ''}` · **raw query:** {'`' + d['raw_query'] + '`' if d['raw_query'] else '(none)'}")
    if d["provider_event_id"]:
        w(f"- **Provider event id:** `{d['provider_event_id']}`")
    w(f"- **Signature:** {d['signature']['label']}" + (f" · {d['signature']['scheme']}" if d['signature']['scheme'] else "") + f": {d['signature']['detail']}")
    w(f"- **Shape:** {d['shape']['label']}: {d['shape']['detail']}")
    w("- **Decoded summary:** " + " · ".join(d["decoded_summary"]))
    w("")
    w("```http")
    w(f"{d['method']} {d['path']}{'?' + d['raw_query'] if d['raw_query'] else ''} HTTP/1.1")
    for k, v in d["headers"]:
        w(f"{k}: {v}")
    w("")
    w(d["body_raw"])
    w("```")
    w("")
w("## 4. The Run: Run #3 · Prove invoice.paid · renewal")
w("")
w(f"- **Run id:** `{run['id']}` · **status:** {run['status_label']} ({run['counts']['critical']} Critical · {run['counts']['high']} High · {run['counts']['medium']} Medium · {run['counts']['info']} Info). Info doesn't count in \"3 findings\" (RUN-3). **Exit code** {run['exit_code']} with the default `--fail-on {run['fail_on']}`.")
w(f"- **Target:** `{run['target']['url']}` via agent **`{run['target']['agent']}`** · framework detected: {run['target']['framework_detected']}")
w(f"- **From:** group {gshort[g_sub]} (#01 invoice.paid, #03 payment_intent.succeeded, #04 customer.subscription.updated)")
w(f"- **Started** `{run['started_at']}` · **finished** `{run['finished_at']}` · **duration** {run['duration_ms']/1000:.1f} s · {run['counts']['scenarios']} scenarios · {run['counts']['deliveries']} deliveries. Unsigned was unchecked in this run's draft (Run #4 adds it).")
w("- **Preflight** (before D1, PRB-8): " + " · ".join(f"{x['step']} +{fmt_n(x['start_ms'])} → +{fmt_n(x['end_ms'])} ms" for x in run["preflight"]) + ".")
w(f"- **Provider budget:** {fmt_n(run['budget_ms'])} ms · source `{run['budget_source']}` · UI label `{run['budget_label']}`. {run['budget_note']}")
w(f"- **Verdict levels used:** {', '.join(run['verdict_levels'])} · **probe** `{run['probe']['label']}` from `{run['probe']['source']}` · **reset hook** `{run['probe']['reset_hook']}` between scenarios")
w(f"- **Share link:** `{run['share_url']}` (token `{run['share']['token']}`; after \"Create a new link\": `{run['share']['rotated_url']}`) · shared by {run['share']['shared_by']} · owner URL `{run['share']['owner_url']}` (owner only, never printed as a share link)")
w(f"- **Provenance line:** {run['provenance']['line']}")
w(f"- **Probe entries:** " + ", ".join(f"`{e['name']}` ({e['display_mode']})" for e in run['probe']['entries']) + f" · display: {run['probe']['display_values']}")
w("")
w("`webhooktrap.yml` (as the agent read it):")
w("")
w("```yaml")
w("target: http://localhost:3000/webhooks/stripe")
w("database: env:DATABASE_URL")
w("probe:")
w("  - as: orders.count")
w("    sql: select count(*) from orders where stripe_invoice_id = '{{invoice.id}}'")
w("reset: npm run db:reset:webhooks")
w("```")
w("")
w("### 4.1 Scenario rows (8)")
w("")
w("| Scenario | What we did | Deliveries | Result | Strip reading (line 3) | One line | Why providers do this |")
w("| -------- | ----------- | ---------- | ------ | ---------------------- | -------- | --------------------- |")
scn = {s["key"]: s for s in scenarios}
for sr in run["scenario_results"]:
    s = scn[sr["key"]]
    res = "Pass" if sr["result"] == "pass" else "Finding " + ", ".join(sr.get("findings", []))
    if sr["result"] == "pass" and sr.get("findings"):
        res = "Pass (+ Info " + ", ".join(sr["findings"]) + ")"
    w(f"| **{s['name']}** | {s['what']} | {', '.join(sr['deliveries'])} | {res} | `{sr['reading']}` | {sr['line']} | {s['why']} *({s['citation'] or 'all providers'})* |")
w("")
w("Not in this run: **Unsigned** (unchecked in the draft). The strip shows only the run's scenarios, so Run #3 has 8 cells; Run #4 has 9.")
w("")
w(f"### 4.2 Timeline: {len(run_deliveries)} deliveries")
w("")
w("`+ms` is the time the first request byte was written, since run start (the agent accepted the run at `" + run["started_at"] + "`). Gaps follow PLAN-3: 150 ms inside Once and Shuffled, 500 ms after Twice's first delivery completes, 2 000 ms after Retry's first attempt completes, and reset + 150 ms between scenarios. A delivery is completed when its response headers and its probe reading are in. Budget is 10 000 ms (`default`) for every row; Late and Tampered are never judged against it (INV-2). Probe digest is `orders.count before → after`; `expected` appears only where §8.5 sets one and the value differs, or on the row that is checked.")
w("")
w("| D | +ms | Scenario | Event | Mode | Signing | Status | Latency | Budget | Probe | Verdict |")
w("| - | --- | -------- | ----- | ---- | ------- | ------ | ------- | ------ | ----- | ------- |")
for r in run_deliveries:
    v = "pass" if r["verdict"] == "pass" else f"**{r['finding']}**"
    bud = "n/a (invalid delivery)" if not r["over_budget_counts"] else ("within" if r["within_budget"] else "**over**")
    w(f"| {r['label']} | {fmt_n(r['sent_rel_ms'])} | {scn[r['scenario']]['name']} | `{r['event_type']}` | {r['mode']} | {r['signing']} | {r['response']['status']} | {fmt_n(r['response']['latency_ms'])} ms | {bud} | {r['probe']['digest']} | {v} |")
w("")
w(f"Burst: released from a barrier, dispatch spread {run['burst']['dispatch_spread_ms']} ms (≤ 20 ms, BURST-2). The probe isn't read between Burst deliveries; it reads 0 before and 1 after all three, expected 1 (`B_final`).")
w("")
w("**The 1 → 2 moment** is **D5** (Twice): same `event.id` as D4, response identical (`200 {\"received\":true}`), and `orders.count 1 → 2`. D11 repeats it: the retry after D10, which went over the budget.")
w("")
w("### 4.3 Request / response side by side (all rows)")
w("")
for r in run_deliveries:
    rq, rs = r["request"], r["response"]
    w(f"**{r['label']}** · {scn[r['scenario']]['name']} · sent `{r['sent_at']}` · source capture {r['source_label']}")
    w("")
    w("| Request | Response |")
    w("| ------- | -------- |")
    w(f"| `{rq['method']} {rq['url']}` · {fmt_n(rq['size_bytes'])} B | **{rs['status']}** in {fmt_n(rs['latency_ms'])} ms |")
    w(f"| `Stripe-Signature: {rq['headers'][2][1][:34]}…` | `{rs['body_snippet']}` |")
    w(f"| {rq['body_note']} | Content-Type: application/json; X-Powered-By: Express |")
    w("")
w("Every request also carries `Webhooktrap-Run`, `Webhooktrap-Delivery: D<n>`, `Webhooktrap-Scenario` and `Webhooktrap-Dry-Run: 1` (SIGN-3, NAME-3).")
w("")
w(f"### 4.4 Findings ({len(findings)}: 3 that count, 1 Info)")
w("")
for f in findings:
    w(f"#### {f['id']} · {f['severity_label']} · {f['title']}")
    w("")
    roles = ", ".join(f"{d} ({f['evidence_roles'][d]})" for d in f["evidence"])
    w(f"- **Type:** `{f['type']}` · **Level:** {f['level_label']} · **Evidence:** {roles} · **Scenarios:** {', '.join(scn[k]['name'] for k in f['scenarios'])}")
    w(f"- **Summary:** {f['summary']}")
    w(f"- **Measured:** {f['measured']}")
    w(f"- **Why it matters:** {f['why']} *({f['citation']})*")
    if f["cause_hint"]:
        w(f"- **Likely cause ({f['cause_rule']}):** {f['cause_hint']}")
    w(f"- **Fix ({f['fix_framework']}):** {f['fix_note']}")
    w("")
    w("```" + ("yaml" if f["fix_framework"] == "webhooktrap.yml" else "js"))
    w(f["fix"])
    w("```")
    w("")
w("### 4.5 Save as fixtures (preview)")
w("")
w("State: **Not saved**. Files the agent would write: " + ", ".join(f"`{x}`" for x in run["fixtures"]["files"]) + ". Scrub rules shown in the preview: " + "; ".join(run["fixtures"]["scrub"]) + ".")
w("")
w("### 4.6 Run history (for the runs list and other run states)")
w("")
w("| Run | Status | Reason | Exit | Started | Duration | Events | Scenarios | Deliveries | Line |")
w("| --- | ------ | ------ | ---- | ------- | -------- | ------ | --------- | ---------- | ---- |")
for r in run_history:
    w(f"| #{r['number']} `{r['id'][:14]}…` | **{r['status_label']}** | {r['status_reason'] or '—'} | {r['exit_code']} | {hhmmss(r['started_at'])} | {r['duration_ms']/1000:.1f} s | {' '.join(r['events'])} | {len(r['scenarios'])} | {r['deliveries']}{' of ' + str(r['planned_deliveries']) if r.get('planned_deliveries') else ''} | {r['line']}{' (' + r['note'] + ')' if r.get('note') else ''} |")
w("")
w("## 5. Scenario catalogue copy (sheet and landing)")
w("")
w("Run order is fixed (PLAN-1). All 9 are MVP scenarios (A-7, decision 35) and are checked by default in a new draft. Level 3 and sequence packs are post-MVP and are not drawn.")
w("")
w("| Scenario | What it does | Deliveries (n = selected events) | Why providers do this | Citation | Needs secret |")
w("| -------- | ------------ | -------------------------------- | --------------------- | -------- | ------------ |")
for s in scenarios:
    w(f"| {s['name']} | {s['what']} | {s['deliveries_rule']} | {s['why']} | {s['citation'] or '—'} | {'yes' if s['needs_secret'] else 'no'} |")
w("")
w("Without a Stripe secret no Stripe run can start: every valid delivery must be re-signed (SIGN-2). For GitHub and Shopify, valid deliveries are sent with the original signature header, and Late is n/a.")
w("")
w("Citations are written as page paths, not URLs, until A01 supplies verified links.")
w("")
sd = data["examples"]["shopify_server_draft"]
w("### 5.1 Shopify server-run draft (FL-9, Order #1042)")
w("")
w(sd["note"] + " On an n/a row the why and citation are not shown.")
w("")
w("| Scenario | What | Why | Citation | Deliveries | State |")
w("| -------- | ---- | --- | -------- | ---------- | ----- |")
for r in sd["rows"]:
    w(f"| {scn[r['key']]['name']} | {r['what']} | {r['why'] or '—'} | {r['citation'] or '—'} | {r['deliveries'] if r['deliveries'] is not None else '—'} | {r['state']} |")
w("")
w(f"Summary: \"{sd['summary']}\". Tampered mutation: `{sd['rows'][5]['mutation']['path']}` {sd['rows'][5]['mutation']['from']} → {sd['rows'][5]['mutation']['to']} ({sd['rows'][5]['mutation']['rule']}).")
w("")
cr = data["examples"]["code_reading"]
w("### 5.2 State reading with codes (PV-2 to PV-7)")
w("")
w(cr["note"])
w("")
w("| Case | Line | Detail / strip |")
w("| ---- | ---- | -------------- |")
w(f"| Owner, default (`{cr['owner_default']['tag']}`) | {cr['owner_default']['line']} | {cr['owner_default']['detail']} · strip `{cr['owner_default']['strip']}` |")
w(f"| Owner, `show: value` (`{cr['owner_opted']['tag']}`) | {cr['owner_opted']['line']} | {cr['owner_opted']['detail']} · strip `{cr['owner_opted']['strip']}` |")
w(f"| Unchanged | {cr['unchanged']['line']} | {cr['unchanged']['detail']} |")
w(f"| Widened (PV-4) | {cr['widened']['line']} | {cr['widened']['note']} |")
w(f"| Refused by the server (PV-11) | {cr['invalid']['line']} | — |")
w("")
w("## 6. State copy (plain labels; territories add flavour only in helper text)")
w("")
AREA_TITLES = {"scenario_reasons": "Scenario results: inconclusive, unreachable and n/a reasons", "info_findings": "Info findings (never counted in \"N findings\")", "guest": "Guest session (A-13 values)", "probe_display": "Probe display (PV-2, PV-5, PV-9, PV-10, PV-14)", "share": "Share dialog (PO-A, PO-C, PV-7, PV-14)", "provenance": "Provenance line (TM-17, every run view)"}
for area, states in copy.items():
    w(f"### {AREA_TITLES.get(area, area.capitalize())}")
    w("")
    w("| State | Label | Helper |")
    w("| ----- | ----- | ------ |")
    for k, v in states.items():
        if "title" in v and "label" not in v:
            v = dict(v, label=v["title"])
        helper = (v.get("helper") or "") + (f" Reason code: `{v['reason']}`." if v.get("reason") else "")
        if "command" in v:
            helper += f" Command: `{v['command']}`"
        if "cli" in v:
            helper += f" CLI (local only): {v['cli']}"
        if "providers" in v:
            helper += " Provider instructions: " + " / ".join(f"**{p}:** {t}" for p, t in v["providers"].items())
        w(f"| `{k}` | {v['label']} | {helper.replace('|', '\\|')} |")
    w("")

w("## 7. Account security fixtures (decision 44)")
w("")
w(account_security["note"])
w("")
m = account_security["maya"]
w("| Credential | Kind | Sync | Added | Last used | State |")
w("| ---------- | ---- | ---- | ----- | --------- | ----- |")
for k in m["keys"] + m["keys_states_sheet"]:
    st = ("Blocked twice (can't unblock)" if not k.get("can_unblock", True) else "Blocked") if k["flagged"] else "OK"
    w(f"| {k['name']} | {'Passkey' if k['kind'] == 'passkey' else 'Security key'} | {'Synced' if k['synced'] else 'This device only'} | {k['created_label']} | {k['last_used_label']} | {st} |")
w("")
w(f"Linked: GitHub `{m['identities'][0]['label']}` (since {m['identities'][0]['linked_label']}); Google not linked. Authenticator app on since {m['totp']['enabled_label']}. Recovery codes left: {m['recovery_codes_left']} of 10.")
w("")
w(f"Recovery codes (shown once; fake): `{'`, `'.join(m['recovery_codes'])}`. TOTP setup key (fake): `{m['totp']['enrol_secret_grouped']}`.")
w("")
r = account_security["recovery"]
w(f"Recovery request: requested {r['requested_label']}, confirmed {r['confirmed_label']}, reminder {r['reminder_label']}, completes {r['completes_label']}. Extended (RC-2, password reset {r['extended']['password_reset_label']}): completes {r['extended']['completes_label']}. Cool-down after a cancel: until {r['cooldown_until_label']}. Lock (RC-3) until {r['lock_until_label']}.")
w("")
w("Sessions (\"Where you're signed in\", A05 `SessionRow`; this browser first):")
w("")
w("| Browser | Network | Signed in | Last seen | How |")
w("| ------- | ------- | --------- | --------- | --- |")
for s_ in m["sessions"]:
    w(f"| {s_['browser']}{' (this browser)' if s_['current'] else ''} | `{s_['network']}` | {s_['signed_in_label']} | {s_['last_seen_label']} | {s_['method_label']} |")
w("")
w(m["sessions_note"])
w("")
w("Security log (S-17A, last 30 days; codes are A03 proposals, [pending A05 exact codes]):")
w("")
w("| When | What | Browser · network |")
w("| ---- | ---- | ----------------- |")
for e in m["security_log"]:
    w(f"| {e['at_label']} | {e['what']} | {(e['browser'] or '—') + (' · ' + e['network'] if e['network'] else '')} |")
w("")
pe = m["pending_email"]
w(f"Email change (pending): to `{pe['new']}`, shown masked as `{pe['masked']}`, requested {pe['requested_label']}, link works until {pe['expires_label']}. Taken-address example: `{pe['taken_example']}`. Sign-in throttle example: try again after {m['sign_in_throttled_until']} ({m['sign_in_throttled_note']}) Password: {m['password_policy']['min']} to {m['password_policy']['max']} characters ({m['password_policy']['max_bcrypt_bytes']} bytes on a bcrypt fallback). Link lifetimes: " + ", ".join(f"{k_.replace('_', ' ')} {v_}" for k_, v_ in m["link_lifetimes"].items()) + ".")
w("")
rv = r["review"]
w(f"Post-recovery review (S-30, A05 `RecoveryReview`): completed {r['completed_label']}, lock until {r['lock_until_full_label']}; finish link works until {r['finish_link_expires_label']}. Agents: " + ", ".join(a_["name"] for a_ in rv["agents"]) + ". CI tokens: " + "; ".join(f"{t_['name']} · {t_['project_name']} · {t_['last_used_label']}" for t_ in rv["ci_tokens"]) + ".")
w("")
w("| When | What | Browser · network |")
w("| ---- | ---- | ----------------- |")
for e in rv["log"]:
    w(f"| {e['at_label']} | {e['what']} | {(e['browser'] or '—') + (' · ' + e['network'] if e['network'] else '')} |")
w("")
w(f"Account deletion ({account_security['account_deletion']['pending']}): {account_security['account_deletion']['summary']}.")
w("")
w("| 1.x stand-in | Email | 1.x since | Activation |")
w("| ------------ | ----- | --------- | ---------- |")
for a in account_security["imported_1x"]["accounts"]:
    w(f"| {a['name']} | {a['email']} | {a['created_1x_label']} | {a['activation']} |")
w("")
w(account_security["imported_1x"]["note"])
w("")

w("## 8. Changes (2026-11-14, decision 35)")
w("")
w("| # | Source | Change |")
w("| - | ------ | ------ |")
for n, src, ch in [
    (1, "A01 §16.1, BUD-1", "Parcelbird (Standard Webhooks) budget 15 000 ms → 10 000 ms `default`. Provider table gains UI label, tolerance and pack columns; Unknown gets the generic 10 000 ms default."),
    (2, "A01 §16.2, C-4", "Run #3 regenerated under PLAN-1: Once sends #01 #03 #04, so 16 deliveries (Once D1–D3, Twice D4–D5, Shuffled D6–D8, Late D9, Retry D10–D11, Tampered D12, Burst D13–D15, Slow budget D16). F1 = D12, F2 = D4 D5 · D10 D11, F3 = D10 · D16, F4 = D6–D8 (corrected to D6 · D15 in change 26). Drafts and Run #2 say \"of 16\"."),
    (3, "A01 §16.3, PLAN-3", "`+ms` recomputed from a timing model: gaps after completion, reset and async check between scenarios, preflight before D1. Duration 41.0 s → 43.2 s."),
    (4, "A01 §16.4, TAMP-2", "Tampered is \"signed for the original body\" with a fresh timestamp; the body diff is recorded as `tamper {path, from, to}`."),
    (5, "A01 §16.5, BURST-4", "Burst rows show state `—`; the scenario reading is `0 → 1 (final)`; dispatch spread 3 ms recorded."),
    (6, "A01 §16.6, RUN-3", "\"4 findings\" → \"3 findings\" with the counts line \"1 Critical · 1 High · 1 Medium · 1 Info\". Exit codes added to every run."),
    (7, "A01 §16.7, LC-4, INV-2", "F3 likely cause from LC-4 (the commit-message cause is removed); measured line counts only the 8 valid `invoice.paid` deliveries. F3 title \"Over the 10 s budget for Stripe\" (A01 §16.22). F1 cause from LC-1, F2 gains LC-8."),
    (8, "A01 §16.8, SIG-2, SIG-3", "#09: \"Hook ID 512839104 was edited at 13:31 today\" removed; the 7 checks listed in SIG-2 order."),
    (9, "A01 §16.9, GRP-4, GRP-5", "Order #1042 is Shopify only (#05 #06). Parcelbird #08 #12 form group `shp_1c263…ztsP`. 7 groups."),
    (10, "A01 §16.10, RUN-1", "Run copy adds every Couldn't start reason and the Aborted reasons `target_lost`, `reset_failed`, `time_limit`, `user_abort`."),
    (11, "A01 §16.11, SIGN-2", "`needs_secret` no longer offers \"run only Once and Slow budget\"."),
    (12, "A01 §16.12, NAME-4", "Side-effects copy applies to every run beyond Once."),
    (13, "A01 §16.13, NAME-1 to 3", "`trap` → `webhooktrap`, `trap.yml` → `webhooktrap.yml`, `X-Trap-*` → `Webhooktrap-*` (adds `Webhooktrap-Scenario`), fixtures under `webhooktrap/fixtures/`."),
    (14, "A01 §16.14, A-7", "Catalogue copy per CE changes (Once every event, Twice after completion, Burst released together, Retry RETRY-6) and **Unsigned** added (9 scenarios). Run #4 adds Unsigned: 17 deliveries, \"Passed · 1 Info\" per RUN-7."),
    (15, "A-13, A-18", "Guest quota 3 → 10 runs, counting rule in the note. Twilio and Slack listed as coming, with no budget."),
    (16, "A03 (consistency)", "Run #2's disconnect time 13:57:40.912 → 13:57:04.120: under the timing model D3 of Run #2 is sent about 2 s after start, not 38 s. Durations added to the run history (Run #1 1.4 s, Run #2 2.0 s, Run #4 23.4 s, estimated with the same model)."),
    (17, "A03", "New copy groups for screens: scenario reasons (inconclusive, unreachable, n/a), Info findings, guest session states, draft refusals (cap, two providers), example run and run not found."),
    (18, "A03", "`examples`: a public staging target for server-side replay (P6), a refused private target, a CI token name and the claimed guest project name."),
    (19, "Decision 39, PO-D", "Every signing-secret last-4 hint removed (`…3f9a`, `…7c4d`, `…b21e`): signature details, `copy.signature.valid`, agent secrets (now `state: set / not set`). CI token last 4 stays (it is a token we issue, not a secret you hold)."),
    (20, "Decision 39, PO-E, PO-F", "Pairing code `HXR-4K7` → `7KQ2M-XR9TD` (10 symbols); command pins `@webhooktrap/agent@1`; terminal transcripts for pairing (`[y/N]`, origin first), decline and reconfirmation after a claim; agent states `awaiting_confirmation`, `needs_reconfirm`, `pair_declined`."),
    (21, "Decision 39, PO-A, PO-C", "Share link `/r/{runId}` → `/s/q7Vf2LmX9pK4wT8nR3bZcD` (a separate token; a reshare rotates it to `/s/Hc3mW8tZ1qN5rB7xK2vYpL`). `/r/{runId}` is owner-only. New `copy.share` group; CI uploads private unless `--share`."),
    (22, "Decision 38, PV-2 to PV-14", "Probe display: Run #3's `orders.count` is a count (PV-2). New `copy.probe_display` group with the PV-14 wording, and `examples.code_reading` for code-valued readings (owner default, owner with `show: value`, unchanged, widened, invalid)."),
    (23, "Decision 38, PV-8", "Hosted copy never names the reset command or shows your probe definitions: `aborted_reset_failed` and `couldnt_start_reset_failed` keep the command only in a `cli` variant; F4's fix is the pack template; new `run.checks_hosted`."),
    (24, "Decision 39, TM-17", "Run provenance line (`run.provenance`, `copy.provenance`) for agent, CLI, CI and server-runner runs."),
    (25, "QA v3 M4, M7; owner offline", "`examples.shopify_server_draft` (Shopify why, citation and mutation copy; citations [pending A01]); guest meter values for the waiting and first-four moments; `copy.agent.offline_finished_run`; claim toast says share links were turned off."),
    (26, "A01 rev. 2 §16 rows 32–38", "#09 detail drops \"Expected sha256=… received sha256=…\" (SIG-3). F4 evidence D6 · D15, scenarios Shuffled and Burst (PRB-12a); Burst strip reading `○ F4 Info · 0 → 1 (final)`. Level 2 findings read \"Level 2 · judged on maya-mbp\" (RUN-9). Provenance copy from RUN-8. CI token by name only (no `…9f2c`)."),
    (27, "Decision 44; A05 rev. 4 domain §2.1–2.4; A09 §12", "New `account_security` block: Maya's passkey and security key (plus a never-used passkey and a blocked key for the states sheet), GitHub link, authenticator app, 10 recovery codes of 50 bits, a recovery request with its 72-hour and RC-2 dates, the post-recovery security log, and three fictional stand-ins for the imported 1.x accounts. New §7; Changes renumbered §8."),
    (28, "A05 revision 5 (U-10 to U-19); domain §2.1, §2.2, §2.5–2.9; contracts §3.4", "`account_security` additions only (every earlier value unchanged): Maya's three sign-in sessions with truncated networks, the 30-day security log with action codes and display copy, a pending email change (masked), the sign-in throttle example time, password policy 12–128 and link lifetimes, Old YubiKey flag count and user-handle note, a new Spare YubiKey blocked twice (can't unblock), the post-recovery review (completion, lock end, agents, CI tokens across two projects, review log, decline page) and the account-deletion summary [pending A05]."),
]:
    w(f"| {n} | {src} | {ch} |")
w("")

with open(f"{OUT_DIR}/realistic-data.md", "w") as fh:
    fh.write("\n".join(L) + "\n")
print("ok", len(deliveries), "deliveries", len(run_deliveries), "run rows")
