#!/usr/bin/env python3
"""Exercise the isolated members demo. Not a live-system test.

Run from the repository root after the compose stack is up:

    python3 members-demo/test_demo.py
"""

from __future__ import annotations

import hashlib
import http.cookiejar
import re
import subprocess
import sys
import urllib.error
import urllib.parse
import urllib.request
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
BASE = "http://localhost:8080"
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
    for blocked in ("/app/bootstrap.php", "/app/db.php", "/storage/", "/.htaccess"):
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

    log = subprocess.run(
        ["sudo", "docker", "compose", "exec", "-T", "web", "php", "-d", "auto_prepend_file=", "-r",
         "echo file_get_contents('/var/www/html/storage/mail.log');"],
        cwd=ROOT / "members-demo",
        check=True,
        capture_output=True,
        text=True,
    ).stdout
    if "demo-member@example.test" not in log or "qabbalah.institute" in log:
        fail("mail log did not stay on the synthetic local driver")
    if "paypal.me" in log.lower():
        fail("mail log contained a payment link")

    ht_sha = subprocess.run(
        ["sudo", "docker", "compose", "exec", "-T", "web", "sha256sum", "/var/www/html/.htaccess"],
        cwd=ROOT / "members-demo",
        check=True,
        capture_output=True,
        text=True,
    ).stdout.split()[0]
    if ht_sha != "892055490f500709d23bf69cb440f7f35e9c786eb2648e3829a30c1c13694ba5":
        fail("container routing file is not the verified 662-byte members rules")
    missing_pledge = subprocess.run(
        ["sudo", "docker", "compose", "exec", "-T", "web", "test", "!", "-e", "/var/www/html/app/content/pledge.txt"],
        cwd=ROOT / "members-demo",
    )
    if missing_pledge.returncode != 0:
        fail("fresh image contains a pledge file")

    extra = Client()
    status, _, form = extra.open("/register")
    if status != 200 or SIGNUP_MARK in form:
        fail(f"register form returned {status} or showed the sign-up count")
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

    print("ok isolated members demo: login, admin sign-up count, facilitator, lesson, pdf, durable session, 662-byte routing")


if __name__ == "__main__":
    main()
