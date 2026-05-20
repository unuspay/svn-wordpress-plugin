<agents_md>

<project>
# UnusPay Crypto Payments for Easy Digital Downloads

WordPress plugin for accepting 1000+ crypto payments via UnusPay on Easy Digital Downloads stores. Single-file PHP plugin (870 lines), no build system, no dependencies.
</project>

<stack>
PHP 7.2+ | WordPress 6.0+ | Easy Digital Downloads 3.x | No build/runtime | No test framework
</stack>

<commands>
```bash
# Dev — no dev server, edit trunk/ files directly
# No build step — widgets.bundle.js is pre-built

# Test — no test framework exists
# Manual testing only: activate plugin in WP, process a test payment

# Deploy trunk (automatic)
git add trunk/ && git commit -m "fix: ..." && git push origin main
# → CI syncs trunk/ + assets/ to WordPress.org SVN

# Release to users (via skill)
release
# → or: gh workflow run release.yml

# Dry-run release
gh workflow run release.yml --field dry_run=true

# Check CI status
gh run list --workflow=sync-trunk.yml --limit 1

# Monitor release
RUN_ID=$(gh run list --workflow=release.yml --limit 1 --json databaseId --jq '.[0].databaseId')
gh run watch "$RUN_ID"
```
</commands>

<code_style>

### Files & Naming
- All plugin PHP logic lives in a single file: `trunk/unuspay-crypto-payments-for-easy-digital-downloads.php`
- Functions: `unuspay_edd_` prefix, snake_case — e.g. `unuspay_edd_process_payment()`
- Constants: `UNUSPAY_` prefix, UPPER_SNAKE — e.g. `UNUSPAY_GATEWAY_NAME`
- Globals: `$unuspay_edd_` prefix — e.g. `$unuspay_edd_payment_key`
- One exception: `getUnusPayOrder()` uses camelCase (legacy, don't replicate)
- Hooks registered with string callbacks (no closures): `add_action('edd_gateway_' . UNUSPAY_GATEWAY_NAME, 'unuspay_edd_process_payment');`

### Architecture
- Procedural only — no classes, no OOP, no namespaces
- WordPress hooks pattern: define function → register with `add_action`/`add_filter` immediately after
- REST API: `register_rest_route('unuspay/edd', '/checkouts/(?P<id>[\w-]+)', ...)` inside `rest_api_init`
- Custom DB tables via `dbDelta()`: `{$wpdb->prefix}edd_unuspay_checkouts`, `{$wpdb->prefix}edd_unuspay_transactions`
- All DB queries use `$wpdb->prepare()` — always parameterize

### Security
- Output: `esc_html__()`, `esc_url()`, `esc_attr()` for all user-facing strings
- Input: `wp_verify_nonce()` for form submissions, `sanitize_text_field()` for input
- HTTP: `wp_remote_post()` — never use `curl` directly

### Error Handling
```php
// Primary: try/catch → wp_die
try {
    // ... logic ...
} catch (Exception $e) {
    wp_die(
        esc_html__('Storing checkout failed', 'unuspay-crypto-payments-for-easy-digital-downloads'),
        esc_html__('Error', 'unuspay-crypto-payments-for-easy-digital-downloads'),
        array('response' => 403)
    );
}
```

### Assets
- CSS: `trunk/assets/css/` — enqueued via `wp_enqueue_style()`
- JS: `trunk/assets/js/` — enqueued via `wp_register_script()` + `plugin_dir_url(__FILE__)`
- `widgets.bundle.js` is pre-built (9MB) — no build step in repo, rebuild externally

### Where to Edit
- Plugin code: `trunk/unuspay-crypto-payments-for-easy-digital-downloads.php`
- Plugin assets: `trunk/assets/` (css, js, images)
- WordPress.org metadata: `trunk/readme.txt`
- WordPress.org banners/screenshots: `assets/` (top-level, NOT in trunk/)
- Version number: `trunk/unuspay-crypto-payments-for-easy-digital-downloads.php` line ` * Version: X.Y.Z`
- Stable tag: `trunk/readme.txt` line `Stable tag: X.Y.Z`
- NEVER edit `tags/` — created by CD pipeline via `svn cp`
</code_style>

<doc_context>
- **Skill (project context)**: `docs/skills/unuspay-wp-plugin.md` — full repo layout, CI/CD details, editing rules, versioning gotchas. **Load this first when working in this repo.**
- **Skill (release trigger)**: `docs/skills/release.md` — how to trigger the CD release pipeline via `gh workflow run`
- **Guide**: `docs/base/_guide/svn-ci-cd.md` — what was built, architecture decisions, troubleshooting
- **CI workflow**: `.github/workflows/sync-trunk.yml` — trunk sync on push to main
- **CD workflow**: `.github/workflows/release.yml` — release via manual dispatch
- **Release script**: `scripts/release.sh` — auto-versioning + SVN tag + commit logic
</doc_context>

<gotchas>
- **SVN mirror layout** — Git repo mirrors SVN structure (`trunk/`, `tags/`, `assets/` at root). Plugin code is NOT at repo root — it's inside `trunk/`. Edit `trunk/*.php`, not `*.php`.
- **CI only syncs trunk** — Pushing to `main` updates SVN trunk but users still download the tagged version. Users see changes ONLY after CD creates a new SVN tag + updates Stable tag.
- **CD pushes back to Git** — After release, CD updates `Version:` and `Stable tag:` in Git and pushes to `main`. This triggers CI once more, but CI finds no changes (Git and SVN match) and exits cleanly. This is expected, NOT an infinite loop.
- **Two `assets/` directories** — `trunk/assets/` (plugin CSS/JS/images shipped to users) vs `assets/` (WordPress.org banners/icons/screenshots shown on plugin directory page). They serve different purposes. Don't confuse them.
- **No SVN commits manually** — SVN is entirely managed by CI/CD. Always use Git. Never run `svn ci` directly.
- **Concurrency lock** — Both CI and CD share `concurrency: svn-pipeline`. They queue, never run simultaneously.
- **Version auto-increment caps at 10** — `1.0.10` → `1.1.0` (not `1.0.11`). Major version is always manual.
- **Empty catch block at line 293** — `catch (Exception $e) {}` silently swallows errors. Don't replicate this pattern.
- **GitHub secrets required** — `SVN_USERNAME` and `SVN_PASSWORD` must be set in repo settings. SVN password is generated separately at WordPress.org profile (NOT the account password).
</gotchas>

</agents_md>
