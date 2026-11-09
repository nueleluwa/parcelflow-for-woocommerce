import re, sys, urllib.request, urllib.parse, http.cookiejar

BASE = "http://127.0.0.1:8099"


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *a, **k):
        return None


class S:
    def __init__(self):
        self.cj = http.cookiejar.CookieJar()
        self.op = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.cj), NoRedirect)

    def req(self, path, data=None, follow=True):
        url = path if path.startswith("http") else BASE + path
        body = urllib.parse.urlencode(data, doseq=True).encode() if data is not None else None
        for _ in range(6):
            try:
                r = self.op.open(urllib.request.Request(url, data=body), timeout=60)
                return r.status, r.read().decode("utf8", "replace"), r.headers
            except urllib.error.HTTPError as e:
                if e.code in (301, 302, 303, 307) and follow:
                    url = urllib.parse.urljoin(url, e.headers.get("Location"))
                    body = None
                    continue
                return e.code, e.read().decode("utf8", "replace"), e.headers
        return 0, "", {}

    def login(self, u, p):
        self.req("/wp-login.php")  # test cookie
        self.cj.set_cookie(http.cookiejar.Cookie(0, "wordpress_test_cookie", "WP Cookie check", None, False, "127.0.0.1", False, False, "/", True, False, None, False, None, None, {}))
        s, b, h = self.req("/wp-login.php", {"log": u, "pwd": p, "wp-submit": "Log In", "redirect_to": BASE + "/wp-admin/", "testcookie": "1"}, follow=False)
        return any("wordpress_logged_in" in c.name for c in self.cj)


results = []


def check(name, cond, detail=""):
    results.append(cond)
    print(("PASS  " if cond else "FAIL  ") + name + (("   [" + detail + "]") if detail and not cond else ""))


def nonce_from(html, action_field="_wpnonce"):
    m = re.search(r'name="_wpnonce" value="([a-f0-9]+)"', html)
    return m.group(1) if m else None


ORDER, ORDER_B, KEY = sys.argv[1], sys.argv[2], sys.argv[3]

# ---------- Access control ----------
anon = S()
s, b, _ = anon.req("/wp-admin/admin-post.php?action=parcelflow_wc_book&order_id=" + ORDER, follow=False)
check("anon: admin-post book is not reachable/handled (no nopriv hook)", s in (302, 400, 403) or "Shipment booked" not in b, str(s))
s, b, _ = anon.req("/wp-admin/admin-post.php", {"action": "parcelflow_wc_save_settings"}, follow=False)
check("anon: save_settings not handled", s in (302, 400, 403))

sub = S(); assert sub.login("sub", "pass1234"), "subscriber login"
s, b, _ = sub.req("/wp-admin/admin.php?page=parcelflow-shipments")
check("subscriber: Shipments page denied", s == 403 or "permission" in b.lower(), str(s))
s, b, _ = sub.req("/wp-admin/admin.php?page=parcelflow-settings")
check("subscriber: Settings page denied", s == 403 or "permission" in b.lower(), str(s))
s, b, _ = sub.req("/wp-admin/admin-post.php", {"action": "parcelflow_wc_save_settings", "tab": "general", "pf[base_url]": "https://evil.example/"}, follow=False)
check("subscriber: save_settings refused (no cap, no nonce)", s in (403,) or "permission" in b.lower() or "expired" in b.lower(), str(s))
s, b, _ = sub.req("/wp-admin/admin-post.php?action=parcelflow_wc_export_csv", follow=False)
check("subscriber: CSV export refused", s == 403 or "permission" in b.lower() or "expired" in b.lower(), str(s))

mgr = S(); assert mgr.login("mgr", "pass1234"), "manager login"
s, b, _ = mgr.req("/wp-admin/admin-post.php", {"action": "parcelflow_wc_save_settings", "tab": "general", "pf[base_url]": "https://evil.example/"}, follow=False)
check("shop manager: save_settings without nonce refused", s == 403 or "expired" in b.lower(), str(s))
s, b, _ = mgr.req("/wp-admin/admin-post.php?action=parcelflow_wc_book&order_id=" + ORDER, follow=False)
check("shop manager: booking via GET without nonce refused", s == 403 or "expired" in b.lower(), str(s))

# nonce bound to a different order must not authorise booking another order
s, b, _ = mgr.req("/wp-admin/admin.php?page=parcelflow-shipments")
n_any = re.search(r"parcelflow_wc_refresh_tracking&(?:amp;|#038;)?order_id=(\d+)&(?:amp;|#038;)?_wpnonce=([a-f0-9]+)", b)
if n_any:
    s2, b2, _ = mgr.req("/wp-admin/admin-post.php?action=parcelflow_wc_refresh_tracking&order_id=" + ORDER_B + "&_wpnonce=" + n_any.group(2), follow=False)
    check("nonce for order A cannot be replayed against order B", s2 == 403 or "expired" in b2.lower(), str(s2))
else:
    print("SKIP  nonce replay (no refresh link rendered)")

# ---------- Privilege escalation: shop manager vs. API token destination ----------
s, b, _ = mgr.req("/wp-admin/admin.php?page=parcelflow-settings")
can_open = s == 200 and "pf-settings" in b
nonce = nonce_from(b)
check("PRIVILEGE: shop manager must NOT be able to open API settings", not can_open, "manager can open Settings (token destination editable)")
if nonce:
    s, b2, _ = mgr.req("/wp-admin/admin-post.php", {"action": "parcelflow_wc_save_settings", "tab": "general", "_wpnonce": nonce, "pf[base_url]": "https://attacker.example/", "pf[enabled]": "yes"}, follow=False)
    check("PRIVILEGE: shop manager must NOT be able to change API base URL", s == 403, "save accepted, status " + str(s))

# ---------- Output escaping (stored XSS from API-derived data) ----------
adm = S(); assert adm.login("admin", "admin"), "admin login"
s, b, _ = adm.req("/wp-admin/admin.php?page=parcelflow-shipments")
check("admin list: no raw <script>/<img onerror>/<svg onload> from stored data", not re.search(r"<script>alert|<img src=x|<svg onload", b))
m = re.search(r"page=parcelflow-shipments&(?:amp;|#038;)?view=(\d+)", b)
if m:
    s, d, _ = adm.req("/wp-admin/admin.php?page=parcelflow-shipments&view=" + m.group(1))
    check("admin detail: stored XSS escaped", not re.search(r"<script>alert|<img src=x|<svg onload", d))
    check("admin detail: javascript: label URL is not linked", 'href="javascript:' not in d.lower())
s, b, _ = adm.req("/wp-admin/admin.php?page=parcelflow-logs")
check("logs list: stored XSS escaped", not re.search(r"<script>alert|<img src=x", b))
lid = re.search(r"page=parcelflow-logs&(?:amp;|#038;)?log=(\d+)", b)
if lid:
    s, d, _ = adm.req("/wp-admin/admin.php?page=parcelflow-logs&log=" + lid.group(1))
    check("log detail: XSS escaped", not re.search(r"<script>alert|<img src=x", d))
    check("log detail: token not present", "SECRET_TOKEN_VALUE_123" not in d)
    check("log detail: e-mail address redacted", "bob@evil.co" not in d)
s, b, _ = adm.req("/wp-admin/admin.php?page=parcelflow-settings")
check("settings page never contains the stored token", "SECRET_TOKEN_VALUE_123" not in b)
s, b, _ = adm.req("/wp-admin/admin.php?page=parcelflow-help")
check("help page never contains the stored token", "SECRET_TOKEN_VALUE_123" not in b)
s, b, _ = adm.req("/wp-admin/post.php?post=" + ORDER + "&action=edit")
if s != 200 or "parcelflow" not in b.lower():
    s, b, _ = adm.req("/wp-admin/admin.php?page=wc-orders&action=edit&id=" + ORDER)
check("order screen: carrier-name XSS escaped, no token", not re.search(r"<script>alert\(1\)", b) and "SECRET_TOKEN_VALUE_123" not in b)

# ---------- Customer isolation ----------
ca = S(); assert ca.login("custA", "pass1234"), "custA login"
cb = S(); assert cb.login("custB", "pass1234"), "custB login"
pa, _, _ = ca.req("/?page_id=8")
s, b, _ = ca.req("/?page_id=8&view-order=" + ORDER)
check("customer A sees own order's ParcelFlow section", "Shipment Information" in b and "PF-TEST-1" in b, str(s))
check("customer A: stored XSS in shipment data escaped", not re.search(r"<script>alert|<img src=x|<svg onload", b))
s, b, _ = cb.req("/?page_id=8&view-order=" + ORDER)
check("customer B cannot see A's shipment (number absent)", "PF-TEST-1" not in b and "TRK" not in b)
s, b, _ = anon.req("/?page_id=8&view-order=" + ORDER)
check("anonymous cannot see A's shipment", "PF-TEST-1" not in b)
s, b, _ = anon.req("/?page_id=7&order-received=" + ORDER)
check("order-received without key shows no shipment data", "PF-TEST-1" not in b)
s, b, _ = anon.req("/?page_id=7&order-received=" + ORDER + "&key=" + KEY)
print("INFO  order-received WITH valid key shows shipment section:", "PF-TEST-1" in b)
s, b, _ = anon.req("/?page_id=7&order-received=" + ORDER + "&key=wc_order_wrongkey")
check("order-received with WRONG key shows nothing", "PF-TEST-1" not in b)

# ---------- Public tracking endpoint ----------
t = S()
for payload, name in [('"><script>alert(1)</script>', "script tag"), ("' OR '1'='1", "sql quote"), ("PF-TEST-1' --", "sql comment")]:
    s, b, _ = t.req("/?page_id=10&pf_track=" + urllib.parse.quote(payload))
    check("tracking: %s input is inert/escaped" % name, "<script>alert(1)" not in b and s == 200)
s, b, _ = t.req("/?page_id=10&pf_track=NOPE-123")
check("tracking: unknown number => not found (no API call needed)", "could not find a shipment" in b.lower())
s, b, _ = t.req("/?page_id=10&pf_track=" + "A" * 500)
check("tracking: oversized input rejected", "not valid" in b.lower())
s, b, _ = t.req("/?page_id=10&pf_track=PF-TEST-1")
i = b.find('<div class="parcelflow-tracking">')
blk = b[i:b.find("</main>", i)] if i >= 0 else ""
txt = re.sub(r"\s+", " ", re.sub(r"<[^>]+>", " ", blk))
check("tracking: no addresses / phone / e-mail in the plugin output", i >= 0 and "@" not in txt and "address" not in txt.lower() and "+" not in txt)
check("tracking: stored carrier/status/event strings are escaped", not re.search(r"<script|<svg|<img src=x", blk, re.I))
thr = None
for i in range(25):
    s, b, _ = t.req("/?page_id=10&pf_track=NOPE-%d" % i)
    if "too many lookups" in b.lower():
        thr = i
        break
check("tracking: per-IP throttle kicks in", thr is not None and thr <= 16, str(thr))

print("\n%d/%d passed" % (sum(results), len(results)))
sys.exit(0 if all(results) else 1)

