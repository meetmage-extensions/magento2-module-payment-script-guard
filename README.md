# Payment Script Guard for Magento 2

![Payment Script Guard for Magento 2](docs/images/hero.webp)

Keep an inventory of every script on your Magento checkout, with a written justification for each one
(**PCI DSS 4.0.1 requirement 6.4.3**), and get alerted when a script or the page's security headers change
(**requirement 11.6.1**). Catches injected card skimmers (Magecart). Works with Luma, Hyvä Checkout and
one-step checkout modules.

> Tested on Magento Open Source 2.4.9 with the Luma checkout. The reporter is plain JavaScript and is built to
> work with Hyvä Checkout and one-step checkouts, which have not been tested yet: please
> [open an issue](https://github.com/meetmage-extensions/magento2-module-payment-script-guard/issues) if something
> doesn't work.

[![CI](https://github.com/meetmage-extensions/magento2-module-payment-script-guard/actions/workflows/ci.yml/badge.svg)](https://github.com/meetmage-extensions/magento2-module-payment-script-guard/actions/workflows/ci.yml)
![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)

## Why

Since 31 March 2025 every store that takes card payments must, for the pages where card data is entered:

- **6.4.3:** keep an inventory of all scripts, confirm each one is authorized, and record why it is needed;
- **11.6.1:** detect unauthorized changes to those scripts and to the page's security-impacting HTTP headers,
  at least weekly.

This applies even when card fields are hosted by your payment provider. Magento does not do this for you, and the
usual answer is a paid monthly monitoring service. This module does it inside Magento.

## Features

### Script inventory (6.4.3)
- A small plain-JavaScript reporter runs **only on your payment pages** and lists every executable script: files
  and inline code, including scripts added after the page loaded, even if they remove themselves straight away
  (a common skimmer trick).
- **System → Payment Script Guard → Script Inventory**: authorize or reject each script with a business
  justification. Decisions are logged with the admin user and time.
- Script files from your own store, static or media domain (and CDN hosts you add) are authorized
  automatically, with an automatic justification, and still checked for changes.
- Inline scripts are fingerprinted by their *code*: per-visitor data (cart, addresses, form keys) and Magento's
  random variable names don't create new entries, while any new domain referenced by the script always does.
- Inline sources are uploaded once; after that the browser only sends SHA-256 hashes.

### Change and tamper detection (11.6.1)
- **Hourly integrity check** of script files against their approved **SHA-384** hash (cron, the
  "Check Integrity Now" button, or `bin/magento meetmage:psg:check`).
- Your store's files are read **from disk** (`pub/static`, `pub/media`), where skimmers are usually injected.
  A change that comes with a new static content deployment is accepted; a change **without** a deployment is
  reported as possible tampering (**critical**).
- Third-party files are checked once authorized. For vendors that update a file without changing its URL,
  tick "expected to change" to log changes instead of alerting.
- **Security headers:** `Content-Security-Policy` (report-only too), `Strict-Transport-Security`,
  `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy` and others are recorded per payment page; changes
  are reported. CSP nonces are ignored.
- A rejected script that still runs on a payment page raises a **critical** alert.

### Alerts and evidence
- Grouped **email alerts** (at most every 10 minutes), an **admin notice** while scripts need review, and an
  **activity log**.
- **Download PCI Report (CSV)**: every script with status, justification, who authorized it and when, hashes
  and last check, ready for your assessor or SAQ.

## Compatibility

| | Supported |
|---|---|
| Magento Open Source / Adobe Commerce | 2.4.6 – 2.4.9 |
| PHP | 8.1 – 8.5 |
| Checkout | Luma, Hyvä Checkout, one-step checkout modules (add their page, see below) |

## Installation

```bash
composer require meetmage/module-payment-script-guard
bin/magento module:enable MeetMage_PaymentScriptGuard
bin/magento setup:upgrade
bin/magento cache:flush
```

Magento cron must be running for integrity checks and email alerts.

## Getting started

1. Set **Send Alerts To** under **Stores → Configuration → MeetMage → Payment Script Guard → Alerts**.
2. Open your checkout once (place a product in the cart). The inventory fills in within seconds.
3. Go to **System → Payment Script Guard → Script Inventory** and review the scripts marked
   *Needs review*: open each one, write why it is needed (for example *"Stripe.js: collects card details in
   the Stripe payment fields"*) and authorize it, or reject it and remove it from your theme or module.
4. Download the CSV report for your PCI assessment.

## Configuration

**Stores → Configuration → MeetMage → Payment Script Guard**

| Setting | Default | Description |
|---|---|---|
| Enable | Yes | Turns the reporter on or off (per store view) |
| Payment Pages | `checkout_index_index`, multishipping billing and overview | Full action names of pages where card data is entered. Add your one-step checkout's page here. |
| Sampling (%) | 100 | Share of payment page visits that report scripts. Keep 100: skimmers may target only some visitors. |
| Auto-authorize Files From Your Own Domain | Yes | Authorize first-party script files automatically |
| Additional First-party Hosts | – | Your CDN host(s), one per line |
| Justification for Bulk Authorization | *Reviewed: required by the store theme or a checkout module.* | Recorded by the grid's bulk Authorize action |
| Check Scripts Every Hour | Yes | Hourly SHA-384 integrity check |
| Watch Security Headers | Yes | Record and compare payment page security headers |
| Send Email Alerts / Send Alerts To / Sender / Template | Yes / – / General / default | Alert emails |

## How it works and limits

- The reporter is client-side: it sees what actually runs in shoppers' browsers, including scripts injected by
  compromised third parties. A skimmer that disables the reporter before it runs would not be reported, so the
  hourly server-side integrity check of your files is the second line of defence.
- Headers added by your web server, load balancer or CDN are not visible to Magento; check those separately.
- The public report endpoint accepts reports only with a token from a payment page rendered by your store,
  limits sizes, and stops accepting new entries after 2,000 in 24 hours. Script URLs reported by browsers are
  never downloaded until you authorize them, and never from private network addresses.
- This module helps you meet requirements 6.4.3 and 11.6.1; whether your store is compliant is decided by your
  assessor (QSA) or your SAQ.

## Development

```bash
composer config repositories.mage-os composer https://mirror.mage-os.org/
composer install
vendor/bin/phpunit
vendor/bin/phpstan analyse
```

## Support

- Bugs and feature requests: [GitHub issues](https://github.com/meetmage-extensions/magento2-module-payment-script-guard/issues)
- Installation, PCI script review and custom Magento & Hyvä development: [meetshekhat.com](https://meetshekhat.com)

## License

[MIT](LICENSE) © 2026 Meet Shekhat
