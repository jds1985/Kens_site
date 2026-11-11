# Mail settings for a later HostGator move

This is a note for the members app. It does not change qabbalah.institute, and it does not turn mail on for a live site.

The app has three drivers in `mail.driver`:

| Driver | What it does | When to use it |
| --- | --- | --- |
| `mail` | PHP `mail()`. The host sends the message. | HostGator's usual choice. This is already the default in `members-source-only/members/config.sample.php`. SMTP is not used. |
| `smtp` | The app connects to a mail server itself. | Only after a mailbox exists and its password is filled in. Leave this off until then. |
| `log` | Appends the message to `storage/mail.log`. Sends nothing. | A private test with no catcher. Never on the live site. |

## PHP mail() on HostGator

Set `mail.driver` to `mail`. Set `mail.from` to an address on the site's own domain, for example `members@YOURSITE.example`. HostGator must allow PHP `mail()` for that account. No SMTP host, username, or password is required. The sample config is already set up this way, so SMTP stays off until someone changes the driver.

## SMTP, off until a mailbox is ready

Set `mail.driver` to `smtp` and fill in `mail.smtp`:

- `host`: the HostGator mail host, often `mail.YOURSITE.example`
- `port`: `465` with `secure` set to `ssl`, or `587` with `secure` set to `tls`
- `user`: the full mailbox address
- `pass`: the mailbox password

If `user` and `pass` are both empty, the app does not try to log in. That is only for a local catcher. A HostGator mailbox needs both.

## What this demo does

The Docker demo sets `mail.driver` to `smtp` and points it at the `mailpit` container on port `1025`, with no username and no password. Mailpit keeps the messages on this machine. The demo guard refuses to boot if the SMTP host is anything else, or if a password is set. PHP `mail()` is disabled in the demo, so it cannot send through the host.

Open the catcher at `http://127.0.0.1:8025` while the demo is running. Nothing there is delivered to a real inbox.
