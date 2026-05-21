---
name: edd
description: Project context for the UnusPay EDD plugin. Covers paths, CI/CD, editing rules, and release commands.
---

# UnusPay EDD Plugin — Skill Reference

## Plugin Identity

| Field | Value |
|-------|-------|
| **Plugin** | UnusPay Crypto Payments for Easy Digital Downloads |
| **Directory** | `easy-digital-downloads/` |
| **SVN slug** | `unuspay-crypto-payments-for-easy-digital-downloads` |
| **SVN URL** | `https://plugins.svn.wordpress.org/unuspay-crypto-payments-for-easy-digital-downloads/` |
| **SVN user** | `unustech01` |
| **Main PHP file** | `easy-digital-downloads/trunk/unuspay-crypto-payments-for-easy-digital-downloads.php` |
| **Readme** | `easy-digital-downloads/trunk/readme.txt` |
| **Language** | PHP 7.2+ (procedural, no OOP/namespaces) |
| **CMS** | WordPress + Easy Digital Downloads 3.x |

## Directory Layout

```
easy-digital-downloads/
├── trunk/                           ← Edit HERE
│   ├── unuspay-crypto-payments-...php  ← Main plugin (870 lines, single file)
│   ├── assets/{css,js,images}/      ← Plugin CSS/JS/images
│   └── readme.txt                   ← WP.org metadata
├── assets/                          ← WP.org banners, icons, screenshots
├── tags/                            ← SVN release snapshots (DO NOT EDIT)
└── scripts/release.sh              ← Release logic
```

## Code Style

- Functions: `unuspay_edd_` prefix, snake_case
- Constants: `UNUSPAY_` prefix, UPPER_SNAKE
- Globals: `$unuspay_edd_` prefix
- Procedural only — no classes, no OOP, no namespaces
- Hooks registered with string callbacks (no closures)
- REST namespace: `unuspay/edd`

## Where to Edit

| What | Path |
|------|------|
| Plugin PHP | `easy-digital-downloads/trunk/unuspay-crypto-payments-for-easy-digital-downloads.php` |
| Plugin CSS | `easy-digital-downloads/trunk/assets/css/` |
| Plugin JS | `easy-digital-downloads/trunk/assets/js/` |
| WP.org metadata | `easy-digital-downloads/trunk/readme.txt` |
| WP.org banners | `easy-digital-downloads/assets/` |
| Version | `easy-digital-downloads/trunk/unuspay-crypto-payments-for-easy-digital-downloads.php` → ` * Version: X.Y.Z` |
| Stable tag | `easy-digital-downloads/trunk/readme.txt` → `Stable tag: X.Y.Z` |

## Release

```bash
# Trigger release
gh workflow run release-edd.yml --field dry_run=false

# Dry run
gh workflow run release-edd.yml --field dry_run=true

# Custom version
gh workflow run release-edd.yml --field version=2.0.0

# Monitor
RUN_ID=$(gh run list --workflow=release-edd.yml --limit 1 --json databaseId --jq '.[0].databaseId')
gh run watch "$RUN_ID"
```

## Gotchas

- `widgets.bundle.js` is pre-built (9MB) — no build step in repo
- Two `assets/` dirs: `trunk/assets/` (shipped to users) vs top-level `assets/` (WP.org directory page)
- Empty catch block at line 293 — silently swallows errors
- Version auto-increment caps at 10: `1.0.10` → `1.1.0`
