# Dunning email deliverability (SPF / DKIM / DMARC)

> **Operator-facing setup guide.** NeNe Clear records that a dunning notice was
> **sent**; it cannot guarantee it was **delivered**. Deliverability — whether
> the overdue reminder lands in the client's inbox instead of spam, or is
> rejected outright — is owned by the operator's sending domain and SMTP relay.
> This guide is the minimum setup so dunning email actually arrives.

Related: [`operator-guide.md`](./operator-guide.md) (dunning history),
[`adoption-review-2026-06.md`](./adoption-review-2026-06.md) (why this matters
for adoption), and the deployment guidance under #193.

## Why "sent" ≠ "delivered"

Clear writes a `dunning_notice` row (recipient, time, actor, outstanding at
send) and a `dunning_sent` audit event when it hands the message to the mailer.
That is an honest record of an **attempt**. New or misconfigured sending domains
are routinely filtered to spam or bounced by the recipient's mail server, so a
clean send log can still mean the client never saw the reminder. The three DNS
records below are what receiving servers check to decide.

## The three records

Publish these on the **domain in your From address** (`SMTP_FROM_ADDRESS`).

| Record | Purpose | Minimum |
| --- | --- | --- |
| **SPF** (TXT) | Authorizes which servers may send for your domain | `v=spf1 include:<your-relay> -all` (or your relay's published include) |
| **DKIM** (TXT) | Cryptographically signs the message so it can't be forged/altered | Enable DKIM signing on the relay; publish the relay's public key at `<selector>._domainkey.<domain>` |
| **DMARC** (TXT) | Tells receivers what to do when SPF/DKIM fail, and where to send reports | Start `v=DMARC1; p=none; rua=mailto:dmarc@<domain>`, review reports, then tighten to `p=quarantine` / `p=reject` |

**Alignment matters:** for DMARC to pass, the From-address domain must match the
domain authenticated by SPF and/or DKIM. Sending as `noreply@nene-clear.dev`
while authenticating a different domain will fail alignment even if SPF/DKIM
themselves pass.

## Use a real relay, not raw port 25

Configure Clear's SMTP to a reputable relay you control DKIM on
(your hosting provider's authenticated SMTP, or a transactional email service):

```
SMTP_HOST=<relay host>
SMTP_PORT=587            # submission (STARTTLS); avoid raw 25
SMTP_FROM_ADDRESS=billing@yourdomain.example   # a domain you own + can sign
SMTP_FROM_NAME="<Your company> 経理"
```

Leaving `SMTP_HOST` empty falls back to `LogOnlyDunningMailer` (records the
notice, sends nothing) — useful for testing, never for production dunning.

## Shared hosting caveat

Many Tier A shared hosts (ヘテムル / サクラ etc.) cannot meet this: outbound
port 25 is often blocked or rate-limited, and you usually cannot control DKIM
signing or DNS for a sending domain. For reliable dunning, send through an
authenticated relay (provider SMTP or a transactional service) and/or run on a
VPS — see the deployment guidance (#193).

## Bounces and complaints

Clear does not yet track bounces or opens. Until it does:

- Monitor the relay's bounce/complaint reports.
- Stop dunning an address that **hard-bounces** (the receivable still exists; you
  just have a bad address — fix it before sending again).
- Keep frequency reasonable (the dunning **minimum interval** guard in Settings
  already prevents over-sending).

## Checklist before turning on production dunning

- [ ] From address uses a domain you own (`SMTP_FROM_ADDRESS`).
- [ ] SPF record authorizes the relay.
- [ ] DKIM signing enabled on the relay + public key published.
- [ ] DMARC record published (`p=none` + `rua=` to start), reports reviewed.
- [ ] SMTP relay is authenticated submission (587/STARTTLS), not raw 25.
- [ ] A test send to your own external mailbox lands in the inbox (not spam).
