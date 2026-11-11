#!/usr/bin/env python3
"""Exercise the isolated members demo. Not a live-system test.

Run from the repository root after the compose stack is up:

    python3 members-demo/test_demo.py
"""

from __future__ import annotations

import hashlib
import http.cookiejar
import json
import re
import subprocess
import sys
import urllib.error
import urllib.parse
import urllib.request
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
BASE = "http://localhost:8080"
MAILPIT = "http://127.0.0.1:8025"
HOME_SHA = "d130153a74f4a2812a2f6f36b6ded42d50028d383d42c641726b741f8122756d"
ACCOUNTS = {
    "admin": ("demo-admin@example.test", "demo-admin-pass"),
    "member": ("demo-member@example.test", "demo-member-pass"),
    "facilitator": ("demo-facilitator@example.test", "demo-facilitator-pass"),
}


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


class Client:
    def __init__(self) -> None:
        self.jar = http.cookiejar.CookieJar()
        self.opener = urllib.request.build_opener(
            urllib.request.HTTPCookieProcessor(self.jar),
            NoRedirect(),
        )

    def open(self, path: str, data: dict | None = None, method: str | None = None):
        body = None
        headers = {}
        if data is not None:
            body = urllib.parse.urlencode(data).encode()
            headers["Content-Type"] = "application/x-www-form-urlencoded"
        req = urllib.request.Request(BASE + path, data=body, headers=headers, method=method)
        try:
            with self.opener.open(req, timeout=20) as res:
                return res.status, res.headers, res.read()
        except urllib.error.HTTPError as err:
            return err.code, err.headers, err.read()


def fail(message: str) -> None:
    print(f"FAIL: {message}")
    sys.exit(1)


def csrf(html: bytes) -> str:
    match = re.search(br'name="csrf" value="([^"]+)"', html)
    if not match:
        fail("CSRF field missing")
    return match.group(1).decode()


SIGNUP_MARK = b"Total sign-ups:"


def signup_value(html: bytes, label: str) -> int | None:
    match = re.search(rb"%s:\s*(?:<strong>)?(\d+)" % re.escape(label.encode()), html)
    return int(match.group(1)) if match else None


def assert_signup_counts(html: bytes, total: int, confirmed: int, unconfirmed: int, recent: int) -> None:
    got = (
        signup_value(html, "Total sign-ups"),
        signup_value(html, "Email confirmed"),
        signup_value(html, "Email not confirmed yet"),
        signup_value(html, "Signed up in the last 7 days"),
    )
    expect = (total, confirmed, unconfirmed, recent)
    if got != expect:
        fail(f"sign-up count was {got}, expected {expect}")


def assert_count_hidden(label: str, status: int, body: bytes, allowed: tuple[int, ...]) -> None:
    if status not in allowed:
        fail(f"{label} returned {status}, expected {allowed}")
    if SIGNUP_MARK in body:
        fail(f"{label} included the admin sign-up count")


def login(role: str) -> Client:
    client = Client()
    status, _, page = client.open("/login")
    if status != 200:
        fail(f"{role} login page status {status}")
    email, password = ACCOUNTS[role]
    status, headers, _ = client.open("/login", {"email": email, "password": password, "csrf": csrf(page)})
    if status not in (302, 303) or headers.get("Location") != "/dashboard":
        fail(f"{role} login did not reach the dashboard ({status} {headers.get('Location')})")
    return client


def cookie_names(client: Client) -> list[str]:
    return [cookie.name for cookie in client.jar]


def mailpit_messages(query: str) -> list[dict]:
    url = MAILPIT + "/api/v1/search?" + urllib.parse.urlencode({"query": query})
    with urllib.request.urlopen(url, timeout=20) as res:
        data = json.loads(res.read().decode())
    messages = data.get("messages") or []
    return messages if isinstance(messages, list) else []


def mailpit_text(message_id: str) -> str:
    with urllib.request.urlopen(MAILPIT + "/api/v1/message/" + urllib.parse.quote(message_id), timeout=20) as res:
        data = json.loads(res.read().decode())
    return str(data.get("Text") or "")


def mail_link(text: str, path: str) -> str:
    match = re.search(r"http://localhost:8080" + re.escape(path) + r"\?token=([0-9a-f]{64})", text)
    if not match:
        fail(f"mail did not contain a {path} link")
    return match.group(1)


def expire_token(raw: str) -> None:
    script = (
        'require "/var/www/html/app/bootstrap.php";'
        '$raw = getenv("TOKEN");'
        'q("UPDATE tokens SET expires_at = ? WHERE token_hash = ?", ["2000-01-01 00:00:00", hash("sha256", $raw)]);'
        'echo "expired\\n";'
    )
    result = subprocess.run(
        ["sudo", "docker", "compose", "exec", "-T", "-e", "TOKEN=" + raw, "web", "php", "-d", "auto_prepend_file=", "-r", script],
        cwd=ROOT / "members-demo",
        capture_output=True,
        text=True,
    )
    if result.returncode != 0 or "expired" not in result.stdout:
        fail("could not expire the synthetic token")


def main() -> None:
    home = (ROOT / "public-current/qabbalah.institute/index.html").read_bytes()
    preview = (ROOT / "preview/index.html").read_bytes()
    if hashlib.sha256(home).hexdigest() != HOME_SHA or preview != home:
        fail("public homepage bytes changed")
    diff = subprocess.run(
        ["git", "diff", "--exit-code", "--", "public-current", "preview", "members-source-only"],
        cwd=ROOT,
        capture_output=True,
        text=True,
    )
    if diff.returncode != 0:
        fail("homepage or imported members source has a working-tree diff")

    anon = Client()
    status, headers, page = anon.open("/")
    if status != 200 or b"Members" not in page:
        fail(f"home status {status}")
    if SIGNUP_MARK in page:
        fail("anonymous home showed the sign-up count")
    if b"paypal.com" in page.lower():
        fail("anonymous home offered a PayPal link")
    for blocked in ("/app/bootstrap.php", "/app/db.php", "/app/migrate-member-ids.php", "/storage/", "/.htaccess"):
        status, _, body = anon.open(blocked)
        if status != 403:
            fail(f"{blocked} returned {status}, expected 403")
        if b"RewriteRule" in body or b"app_secret" in body:
            fail(f"{blocked} returned protected file contents")
    status, _, missing = anon.open("/config.sample.php")
    if status not in (403, 404) or b"app_secret" in missing or b"CHANGE-ME" in missing:
        fail(f"config.sample.php was exposed ({status})")

    status, _, login_page = anon.open("/login")
    if status != 200:
        fail("login form unavailable")
    status, _, _ = anon.open("/login", {"email": ACCOUNTS["member"][0], "password": ACCOUNTS["member"][1]})
    if status != 403:
        fail(f"login without CSRF returned {status}")

    visitor = Client()
    status, headers, body = visitor.open("/admin")
    if status not in (302, 303) or headers.get("Location") != "/login":
        fail(f"logged-out /admin was not sent to sign-in ({status} {headers.get('Location')})")
    if SIGNUP_MARK in body:
        fail("logged-out /admin response included the sign-up count")
    status, _, login_after = visitor.open("/login")
    assert_count_hidden("login after a direct /admin request", status, login_after, (200,))
    status, headers, body = visitor.open("/admin/members")
    if status not in (302, 303) or headers.get("Location") != "/login" or SIGNUP_MARK in body:
        fail(f"logged-out /admin/members was not refused ({status} {headers.get('Location')})")
    status, _, missing_count = visitor.open("/admin/signups")
    assert_count_hidden("logged-out /admin/signups", status, missing_count, (404,))
    status, _, posted = visitor.open("/admin", {})
    assert_count_hidden("logged-out POST /admin", status, posted, (404,))

    status, _, _ = anon.open("/lesson/2/pdf")
    if status not in (302, 303):
        fail(f"anonymous lesson pdf returned {status}")
    status, _, draft = anon.open("/lesson/3")
    if status != 404:
        fail(f"draft lesson returned {status}")
    if b"Synthetic draft" in draft:
        fail("draft lesson body was visible")

    member = login("member")
    if "ismem" not in cookie_names(member):
        fail("session cookie was not named ismem")
    raw = next(cookie for cookie in member.jar if cookie.name == "ismem")
    flags = {key.lower() for key in raw._rest}
    if "httponly" not in flags or raw.secure:
        fail("session cookie must be HttpOnly and not Secure on this local http demo")
    status, _, dash = member.open("/dashboard")
    if status != 200 or b"Demo Member" not in dash:
        fail("member dashboard did not show the synthetic member")
    if SIGNUP_MARK in dash:
        fail("member dashboard showed the sign-up count")
    status, _, denied = member.open("/admin")
    if status != 403 or b"only for the site owner" not in denied or SIGNUP_MARK in denied:
        fail("member reached admin or saw the sign-up count")
    status, _, denied_list = member.open("/admin/members")
    assert_count_hidden("member /admin/members", status, denied_list, (403,))
    if b"only for the site owner" not in denied_list:
        fail("member members-list refusal was not the admin refusal")
    status, _, ghost = member.open("/admin/signups")
    assert_count_hidden("member /admin/signups", status, ghost, (404,))
    status, _, locked = member.open("/lesson/2")
    if status != 403 or b"Synthetic demo lesson" in locked:
        fail("lesson was readable before Part A was confirmed")

    status, _, degree = member.open("/degree/1")
    if status != 200 or b"paypal.com" in degree.lower() or b"not set up yet" not in degree:
        fail("Part A page did not keep PayPal disabled")
    status, headers, _ = member.open("/degree/1/claim", {"csrf": csrf(degree), "ref": "DEMO-RECEIPT"})
    if status not in (302, 303):
        fail(f"claim did not redirect ({status})")

    admin = login("admin")
    status, _, admin_home = admin.open("/admin")
    if status != 200 or b"Demo Member" not in admin_home:
        fail("admin did not see the synthetic claim")
    assert_signup_counts(admin_home, 4, 3, 1, 3)
    status, _, members_page = admin.open("/admin/members")
    if status != 200:
        fail(f"admin members page returned {status}")
    assert_signup_counts(members_page, 4, 3, 1, 3)
    status, _, searched = admin.open("/admin/members?q=no-such-synthetic-person")
    if status != 200 or b"No members found." not in searched:
        fail("admin search did not stay on the members page")
    assert_signup_counts(searched, 4, 3, 1, 3)
    status, _, member_admin = admin.open("/admin/member/3")
    if status != 200:
        fail("admin member page failed")
    status, headers, _ = admin.open(
        "/admin/member/3",
        {"csrf": csrf(member_admin), "degree": "1", "do": "a_confirm"},
    )
    if status not in (302, 303):
        fail(f"admin confirm did not redirect ({status})")

    status, _, lesson = member.open("/lesson/2")
    if status != 200 or b"Synthetic demo lesson. Not from the live site." not in lesson:
        fail("opened lesson text missing")
    status, pdf_headers, pdf = member.open("/lesson/2/pdf")
    if status != 200 or not pdf.startswith(b"%PDF") or "application/pdf" not in pdf_headers.get("Content-Type", ""):
        fail("protected pdf was not served to the member who opened the degree")
    status, _, _ = anon.open("/lesson/2/pdf")
    if status not in (302, 303):
        fail("anonymous pdf request was not sent to sign-in")

    status, _, locked_next = member.open("/degree/2")
    if status != 200 or b"Ready to open this Degree" in locked_next:
        fail("the next degree was ready before Part B was confirmed")
    status, _, still_draft = member.open("/lesson/3")
    if status != 404 or b"Synthetic draft" in still_draft:
        fail("the draft lesson unlocked with the degree")

    status, _, opened = member.open("/degree/1")
    if b"paypal.com" in opened.lower():
        fail("Part B page offered PayPal")
    status, _, _ = member.open("/degree/1/facilitator", {"csrf": csrf(opened), "facilitator": "2"})
    if status not in (302, 303):
        fail("choosing the synthetic facilitator failed")
    status, _, paying = member.open("/degree/1")
    if b"Synthetic demo note. No payment is collected" not in paying or b"paypal.com" in paying.lower():
        fail("facilitator instructions were not the synthetic no-payment note")
    status, _, _ = member.open("/degree/1/bclaim", {"csrf": csrf(paying), "ref": "DEMO-B"})
    if status not in (302, 303):
        fail("Part B claim failed")

    facilitator = login("facilitator")
    status, _, fac_page = facilitator.open("/facilitator")
    if status != 200 or b"Demo Member" not in fac_page:
        fail("facilitator did not see the synthetic member")
    if SIGNUP_MARK in fac_page:
        fail("facilitator page showed the sign-up count")
    status, _, fac_denied = facilitator.open("/admin")
    assert_count_hidden("facilitator /admin", status, fac_denied, (403,))
    if b"only for the site owner" not in fac_denied:
        fail("facilitator was not refused as a non-admin")
    status, _, _ = facilitator.open(
        "/facilitator/confirm",
        {"csrf": csrf(fac_page), "member": "3", "degree": "1", "do": "confirm"},
    )
    if status not in (302, 303):
        fail("facilitator confirm failed")
    status, _, done = member.open("/degree/1")
    if "Part B of 1° is confirmed".encode() not in done:
        fail("member did not see Part B confirmed")
    status, _, nxt = member.open("/degree/2")
    if status != 200 or b"Ready to open this Degree" not in nxt:
        fail("next synthetic degree did not become ready")

    subprocess.run(["sudo", "docker", "compose", "restart", "web"], cwd=ROOT / "members-demo", check=True)
    for _ in range(40):
        try:
            status, _, _ = member.open("/dashboard")
            if status == 200:
                break
        except Exception:
            status = 0
        import time
        time.sleep(1)
    else:
        fail("demo did not return after the web container restart")
    status, _, still = member.open("/dashboard")
    if status != 200 or b"Demo Member" not in still:
        fail("MySQL-backed session did not survive the web container restart")

    caught = mailpit_messages("to:demo-member@example.test")
    if not caught:
        fail("Mailpit did not catch the member notification")
    caught_text = "\n".join(mailpit_text(str(message["ID"])) for message in caught)
    if "qabbalah.institute" in caught_text or "paypal.me" in caught_text.lower():
        fail("caught mail pointed at the live site or a payment link")

    ht_sha = subprocess.run(
        ["sudo", "docker", "compose", "exec", "-T", "web", "sha256sum", "/var/www/html/.htaccess"],
        cwd=ROOT / "members-demo",
        check=True,
        capture_output=True,
        text=True,
    ).stdout.split()[0]
    if ht_sha != "892055490f500709d23bf69cb440f7f35e9c786eb2648e3829a30c1c13694ba5":
        fail("container routing file is not the verified 662-byte members rules")
    pledge_file = subprocess.run(
        ["sudo", "docker", "compose", "exec", "-T", "web", "php", "-d", "auto_prepend_file=", "-r",
         "echo file_get_contents('/var/www/html/app/content/pledge.txt');"],
        cwd=ROOT / "members-demo",
        capture_output=True,
        text=True,
    )
    if pledge_file.returncode != 0 or "PLACEHOLDER" not in pledge_file.stdout or "Ken's final wording" not in pledge_file.stdout:
        fail("pledge file is missing its placeholder wording")

    extra = Client()
    status, _, form = extra.open("/register")
    if status != 200 or SIGNUP_MARK in form or b"PLACEHOLDER" not in form or b"final wording" not in form:
        fail(f"register form returned {status}, hid the pledge, or showed the sign-up count")
    status, _, refused = extra.open(
        "/register",
        {
            "first_name": "No",
            "last_name": "Pledge",
            "pseudonym": "No Pledge",
            "email": "no-pledge@example.test",
            "password": "demo-extra-pass",
            "password2": "demo-extra-pass",
            "pledge_name": "No Pledge",
            "csrf": csrf(form),
        },
    )
    if status != 422 or b"Please tick the box" not in refused:
        fail(f"sign-up without the pledge was not refused ({status})")
    status, _, form = extra.open("/register")
    status, headers, body = extra.open(
        "/register",
        {
            "first_name": "Extra",
            "last_name": "Signup",
            "pseudonym": "Extra Signup",
            "email": "extra-signup@example.test",
            "password": "demo-extra-pass",
            "password2": "demo-extra-pass",
            "pledge": "1",
            "pledge_name": "Extra Signup",
            "csrf": csrf(form),
        },
    )
    if status not in (302, 303) or headers.get("Location") != "/dashboard":
        snippet = body[:500].decode("utf-8", "replace")
        fail(f"extra sign-up did not reach the dashboard ({status} {headers.get('Location')}): {snippet}")
    status, _, extra_dash = extra.open("/dashboard")
    if status != 200 or b"Extra Signup" not in extra_dash or SIGNUP_MARK in extra_dash:
        fail("new member saw the sign-up count or missed the dashboard")
    new_id = re.search(br"Your Member ID: <b>([^<]+)</b>", extra_dash)
    if not new_id or not re.fullmatch(br"QDI-\d+", new_id.group(1)) or new_id.group(1).startswith(b"EXT"):
        fail(f"new sign-up did not get a neutral Member ID ({extra_dash[:300]!r})")
    status, _, extra_denied = extra.open("/admin")
    assert_count_hidden("new member /admin", status, extra_denied, (403,))
    status, _, after = admin.open("/admin")
    if status != 200:
        fail(f"admin recount returned {status}")
    assert_signup_counts(after, 5, 4, 1, 4)
    status, _, after_members = admin.open("/admin/members")
    if status != 200:
        fail(f"admin members recount returned {status}")
    assert_signup_counts(after_members, 5, 4, 1, 4)

    def shown_member_id(html: bytes) -> str:
        match = re.search(br'name="member_id"[^>]*value="([^"]*)"', html)
        if not match:
            fail("Member ID field missing")
        return match.group(1).decode()

    def migrate(apply: bool) -> str:
        command = ["sudo", "docker", "compose", "exec", "-T", "web", "php", "-d", "auto_prepend_file=", "/var/www/html/app/migrate-member-ids.php"]
        if apply:
            command.append("--apply")
        result = subprocess.run(command, cwd=ROOT / "members-demo", check=True, capture_output=True, text=True)
        return result.stdout

    status, _, id_page = admin.open("/admin/member/3")
    if status != 200 or shown_member_id(id_page) != "DEMMEM-0003":
        fail("seeded member id was rewritten before Ken saved one")
    status, headers, saved = admin.open("/admin/member/3", {"csrf": csrf(id_page), "do": "set_id", "member_id": "LODGE-3"})
    if status not in (302, 303) or headers.get("Location") != "/admin/member/3":
        fail(f"admin save did not redirect ({status} {headers.get('Location')} {saved[:300]!r})")
    status, _, saved = admin.open("/admin/member/3")
    if status != 200 or shown_member_id(saved) != "LODGE-3" or b"Member ID saved." not in saved:
        fail("admin did not see the saved Member ID")
    status, _, listing = admin.open("/admin/members")
    if status != 200 or b"LODGE-3" not in listing or b"DEMADM-0001" not in listing or b"DEMFAC-0002" not in listing:
        fail("members list did not show the saved ID and the untouched IDs")

    status, _, other = admin.open("/admin/member/2")
    status, _, duplicate = admin.open("/admin/member/2", {"csrf": csrf(other), "do": "set_id", "member_id": "LODGE-3"})
    if status != 422 or b"already used" not in duplicate:
        fail(f"duplicate Member ID was not refused ({status})")
    status, _, other = admin.open("/admin/member/2")
    status, _, folded = admin.open("/admin/member/2", {"csrf": csrf(other), "do": "set_id", "member_id": "lodge-3"})
    if status != 422 or b"already used" not in folded:
        fail(f"case-variant Member ID was not refused ({status})")
    status, _, other = admin.open("/admin/member/2")
    if shown_member_id(other) != "DEMFAC-0002":
        fail("a refused duplicate changed the other member's ID")

    status, _, id_page = admin.open("/admin/member/3")
    status, _, bad = admin.open("/admin/member/3", {"csrf": csrf(id_page), "do": "set_id", "member_id": "John Smith"})
    if status != 422 or b"letters, digits, and hyphens" not in bad:
        fail(f"bad Member ID format was not refused ({status})")
    status, _, id_page = admin.open("/admin/member/3")
    status, _, too_long = admin.open("/admin/member/3", {"csrf": csrf(id_page), "do": "set_id", "member_id": "A" * 21})
    if status != 422 or b"up to 20 characters" not in too_long:
        fail(f"overlong Member ID was not refused ({status})")
    status, _, id_page = admin.open("/admin/member/3")
    if shown_member_id(id_page) != "LODGE-3":
        fail("a refused Member ID replaced the saved one")

    status, _, dash = member.open("/dashboard")
    status, _, member_post = member.open("/admin/member/3", {"csrf": csrf(dash), "do": "set_id", "member_id": "HACKED-1"})
    if status != 403 or b"only for the site owner" not in member_post:
        fail(f"member POST was not refused ({status})")
    status, _, account = member.open("/account")
    member.open("/account", {"csrf": csrf(account), "member_id": "HACKED-4", "current": "wrong-password", "password": "another-password", "password2": "another-password"})
    visitor = Client()
    status, _, login_page = visitor.open("/login")
    status, headers, visitor_post = visitor.open("/admin/member/3", {"csrf": csrf(login_page), "do": "set_id", "member_id": "HACKED-2"})
    if status not in (302, 303) or headers.get("Location") != "/login" or b"HACKED-2" in visitor_post:
        fail(f"logged-out POST with a token was not sent to sign-in ({status})")
    stranger = Client()
    status, _, stranger_post = stranger.open("/admin/member/3", {"do": "set_id", "member_id": "HACKED-3"})
    if status != 403 or b"HACKED-3" in stranger_post:
        fail(f"logged-out POST without a token was not refused ({status})")
    status, _, id_page = admin.open("/admin/member/3")
    if shown_member_id(id_page) != "LODGE-3":
        fail("a refused POST changed the Member ID")

    dry = migrate(False)
    if "Dry run. No Member IDs were changed." not in dry or "user 1: DEMADM-0001 -> QDI-0001" not in dry or "LODGE-3" in dry:
        fail(f"migration dry run was wrong:\n{dry}")
    if "does not run by itself" not in dry:
        fail("migration dry run did not say it does not run by itself")
    status, _, untouched = admin.open("/admin/member/1")
    if shown_member_id(untouched) != "DEMADM-0001":
        fail("migration dry run wrote a Member ID")
    applied = migrate(True)
    if "Updated " not in applied:
        fail(f"migration apply did not report an update:\n{applied}")
    status, _, migrated_admin = admin.open("/admin/member/1")
    status, _, kept = admin.open("/admin/member/3")
    status, _, migrated_pending = admin.open("/admin/member/4")
    status, _, extra_again = extra.open("/dashboard")
    if shown_member_id(migrated_admin) != "QDI-0001" or shown_member_id(migrated_pending) != "QDI-0004":
        fail("migration did not replace name-based IDs")
    if shown_member_id(kept) != "LODGE-3":
        fail("migration rewrote the ID Ken had saved")
    if b"QDI-" not in extra_again or b"EXTSIG" in extra_again:
        fail("migration changed the new sign-up ID into a name")
    status, _, listing = admin.open("/admin/members")
    if b"LODGE-3" not in listing or b"QDI-0001" not in listing or b"DEMMEM-0003" in listing:
        fail("members list did not show the IDs after the optional migration")

    resend = Client()
    status, _, login_page = resend.open("/login")
    resend.open("/resend", {"csrf": csrf(login_page), "email": "demo-pending@example.test"})
    signup_mail = mailpit_messages('to:demo-pending@example.test subject:"confirm your email"')
    if not signup_mail:
        fail("sign-up confirmation email was not caught")
    signup_text = mailpit_text(str(signup_mail[0]["ID"]))
    expired_token = mail_link(signup_text, "/verify")
    expire_token(expired_token)
    status, _, expired_page = resend.open("/verify?token=" + expired_token)
    if status != 200 or b"expired or was already used" not in expired_page:
        fail("expired sign-up link was still accepted")
    status, _, login_page = resend.open("/login")
    resend.open("/resend", {"csrf": csrf(login_page), "email": "demo-pending@example.test"})
    signup_mail = mailpit_messages('to:demo-pending@example.test subject:"confirm your email"')
    fresh = [message for message in signup_mail if mail_link(mailpit_text(str(message["ID"])), "/verify") != expired_token]
    if not fresh:
        fail("a fresh sign-up confirmation was not caught")
    verify_token = mail_link(mailpit_text(str(fresh[0]["ID"])), "/verify")
    status, _, verify_page = resend.open("/verify?token=" + verify_token)
    if status != 200 or b"Confirm my email" not in verify_page:
        fail("sign-up link did not open")
    status, headers, _ = resend.open("/verify", {"csrf": csrf(verify_page), "token": verify_token})
    if status not in (302, 303) or headers.get("Location") != "/login":
        fail("confirming the email did not finish")
    status, _, used_page = resend.open("/verify?token=" + verify_token)
    if status != 200 or b"expired or was already used" not in used_page:
        fail("sign-up link worked a second time")

    notice = Client()
    status, _, register_page = notice.open("/register")
    notice.open(
        "/register",
        {
            "first_name": "Mailbox",
            "last_name": "Check",
            "pseudonym": "Mailbox Check",
            "email": "demo-admin@example.test",
            "password": "mailbox-check-pass",
            "password2": "mailbox-check-pass",
            "pledge": "1",
            "pledge_name": "Mailbox Check",
            "csrf": csrf(register_page),
        },
    )
    already = mailpit_messages('to:demo-admin@example.test subject:"already have an account"')
    if not already or "paypal.me" in mailpit_text(str(already[0]["ID"])).lower():
        fail("existing-account sign-up email was not caught")

    resetter = Client()
    status, _, forgot_page = resetter.open("/forgot")
    resetter.open("/forgot", {"csrf": csrf(forgot_page), "email": "demo-member@example.test"})
    reset_mail = mailpit_messages('to:demo-member@example.test subject:"reset your password"')
    if not reset_mail:
        fail("password reset email was not caught")
    reset_text = mailpit_text(str(reset_mail[0]["ID"]))
    if "localhost:8080/reset?token=" not in reset_text:
        fail("password reset email did not contain a local link")
    reset_token = mail_link(reset_text, "/reset")
    status, _, reset_page = resetter.open("/reset?token=" + reset_token)
    if status != 200 or b"Save my new password" not in reset_page:
        fail("password reset link did not open")
    new_password = "demo-member-pass-2"
    status, headers, _ = resetter.open(
        "/reset",
        {"csrf": csrf(reset_page), "token": reset_token, "password": new_password, "password2": new_password},
    )
    if status not in (302, 303) or headers.get("Location") != "/login":
        fail("password reset did not finish")
    status, _, used_reset = resetter.open("/reset?token=" + reset_token)
    if status != 200 or b"expired or was already used" not in used_reset:
        fail("password reset link worked a second time")
    old_login = Client()
    status, _, old_page = old_login.open("/login")
    status, _, _ = old_login.open(
        "/login",
        {"csrf": csrf(old_page), "email": "demo-member@example.test", "password": "demo-member-pass"},
    )
    if status in (302, 303):
        fail("the old password still signed in")
    fresh_login = Client()
    status, _, fresh_page = fresh_login.open("/login")
    status, headers, _ = fresh_login.open(
        "/login",
        {"csrf": csrf(fresh_page), "email": "demo-member@example.test", "password": new_password},
    )
    if status not in (302, 303) or headers.get("Location") != "/dashboard":
        fail("the new password did not sign in")
    status, _, forgot_page = resetter.open("/forgot")
    resetter.open("/forgot", {"csrf": csrf(forgot_page), "email": "demo-member@example.test"})
    later = mailpit_messages('to:demo-member@example.test subject:"reset your password"')
    later_tokens = []
    for message in later:
        token = mail_link(mailpit_text(str(message["ID"])), "/reset")
        if token != reset_token:
            later_tokens.append(token)
    if not later_tokens:
        fail("a second reset email was not caught")
    expire_token(later_tokens[0])
    status, _, expired_reset = resetter.open("/reset?token=" + later_tokens[0])
    if status != 200 or b"expired or was already used" not in expired_reset:
        fail("expired password reset link was still accepted")

    print("ok isolated members demo: login, mail catcher, admin sign-up count, member ids, facilitator, lesson, pdf, durable session, 662-byte routing")


if __name__ == "__main__":
    main()
