---
name: woocommerce
description: Project context for the UnusPay WooCommerce plugin. Covers paths, CI/CD, editing rules, and release commands.
---

# UnusPay WooCommerce Plugin — Skill Reference

## Plugin Identity

| Field | Value |
|-------|-------|
| **Plugin** | UnusPay Crypto Payments for WooCommerce |
| **Directory** | `woocommerce/` |
| **SVN slug** | `unuspay-crypto-payments-for-woocommerce` |
| **SVN URL** | `https://plugins.svn.wordpress.org/unuspay-crypto-payments-for-woocommerce/` |
| **SVN user** | `unustech01` |
| **Main PHP file** | `woocommerce/trunk/unuspay-payments.php` |
| **Readme** | `woocommerce/trunk/readme.txt` |
| **Language** | PHP 8.0+ (OOP with strict types) |
| **CMS** | WordPress + WooCommerce 8.0+ |

## Directory Layout

```
woocommerce/
├── trunk/                           ← Edit HERE
│   ├── unuspay-payments.php         ← Main plugin entry (165 lines)
│   ├── includes/                    ← OOP classes
│   │   ├── ApiClient.php
│   │   ├── BlocksIntegration.php
│   │   ├── WcGatewayUnuspay.php
│   │   └── WebhookHandler.php
│   ├── vendor/                      ← Composer deps (UnusPay SDK + autoloader)
│   ├── assets/{images,js}/          ← Plugin images and JS
│   ├── languages/                   ← i18n
│   ├── readme.txt                   ← WP.org metadata
│   └── uninstall.php               ← Cleanup on uninstall
├── assets/                          ← WP.org banners, icons, screenshots
├── tags/                            ← SVN release snapshots (DO NOT EDIT)
└── scripts/release.sh              ← Release logic
```

## Code Style

- PHP 8.0+ with `declare(strict_types=1)`
- OOP with classes in `includes/`
- Constants: `UNUSPAY_PAYMENTS_*` prefix
- Text domain: `unuspay-payments`
- WooCommerce hooks: `woocommerce_*` prefix
- HPOS compatible

## Where to Edit

| What | Path |
|------|------|
| Plugin entry | `woocommerce/trunk/unuspay-payments.php` |
| Gateway class | `woocommerce/trunk/includes/WcGatewayUnuspay.php` |
| API client | `woocommerce/trunk/includes/ApiClient.php` |
| Webhook handler | `woocommerce/trunk/includes/WebhookHandler.php` |
| Blocks integration | `woocommerce/trunk/includes/BlocksIntegration.php` |
| Plugin JS | `woocommerce/trunk/assets/js/` |
| Plugin images | `woocommerce/trunk/assets/images/` |
| WP.org metadata | `woocommerce/trunk/readme.txt` |
| WP.org banners | `woocommerce/assets/` |
| Version | `woocommerce/trunk/unuspay-payments.php` → ` * Version: X.Y.Z` + `UNUSPAY_PAYMENTS_VERSION` constant |
| Stable tag | `woocommerce/trunk/readme.txt` → `Stable tag: X.Y.Z` |

## Release

```bash
# Trigger release
gh workflow run release-woocommerce.yml --field dry_run=false

# Dry run
gh workflow run release-woocommerce.yml --field dry_run=true

# Custom version
gh workflow run release-woocommerce.yml --field version=2.0.0

# Monitor
RUN_ID=$(gh run list --workflow=release-woocommerce.yml --limit 1 --json databaseId --jq '.[0].databaseId')
gh run watch "$RUN_ID"
```

## Gotchas

- `vendor/` is shipped to users — do NOT exclude in `.distignore`
- Two version locations: `Version:` header AND `UNUSPAY_PAYMENTS_VERSION` constant — both must be updated
- JPG banners (not PNG) — MIME type handling must cover `image/jpeg`
- `Requires PHP: 8.0` — stricter than EDD's PHP 7.2+
- Existing SVN tags: `1.0.0`, `1.0.1`, `1.1.0` — next auto-increment would be `1.1.1`
