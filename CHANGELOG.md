# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added
- Payment page script reporter (plain JavaScript, Luma, Hyvä and one-step checkouts): lists script files and
  inline scripts, also scripts injected later and removed right away; inline sources are uploaded only once.
- Script inventory (PCI DSS 4.0.1 requirement 6.4.3): authorize or reject each script with a business
  justification; first-party files authorized automatically; bulk actions; review page with source and history.
- Inline script fingerprints that ignore per-visitor data and Magento's random variable names, but include every
  referenced domain.
- Hourly integrity check (requirement 11.6.1) against approved SHA-384 hashes: first-party files read from disk,
  deployment-aware (a change without a new static content version is reported as possible tampering);
  `bin/magento meetmage:psg:check`.
- Security header watch for payment pages (Content-Security-Policy and others, nonces ignored).
- Grouped email alerts, admin notice, activity log and CSV report for the PCI assessor.
- Abuse limits on the public report endpoint (signed page token, size caps, daily limit on new entries) and
  SSRF protection for integrity downloads.
