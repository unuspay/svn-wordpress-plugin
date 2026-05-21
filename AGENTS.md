<agents_md>

<project>
# UnusPay WordPress Plugins

Multi-plugin monorepo for UnusPay crypto payment gateway integrations. Each plugin lives in its own top-level directory with independent SVN mirror, release workflow, and skill reference.
</project>

<plugins>

### Easy Digital Downloads

- **Directory:** `easy-digital-downloads/`
- **Skill:** `docs/skills/edd.md` — load for EDD-specific paths, code style, release commands
- **SVN:** `unuspay-crypto-payments-for-easy-digital-downloads`
- **PHP:** 7.2+ procedural, single-file plugin (870 lines)
- **Release:** `gh workflow run release-edd.yml`

### WooCommerce

- **Directory:** `woocommerce/`
- **Skill:** `docs/skills/woocommerce.md` — load for Woo-specific paths, code style, release commands
- **SVN:** `unuspay-crypto-payments-for-woocommerce`
- **PHP:** 8.0+ OOP with strict types, multi-file with `includes/`
- **Release:** `gh workflow run release-woocommerce.yml`

</plugins>

<shared_rules>

### Repo Structure

- Each plugin directory contains: `trunk/` (plugin code), `assets/` (WP.org banners/screenshots), `tags/` (SVN snapshots, DO NOT EDIT), `scripts/release.sh`
- Shared files at repo root: `AGENTS.md`, `.github/workflows/`, `docs/skills/`, `.distignore`, `.gitignore`

### Editing

- Always edit in `<plugin>/trunk/`, never in `<plugin>/tags/`
- After editing: `git add <plugin>/trunk/ && git commit -m "fix: ..." && git push origin main`
- No auto-sync on push — changes reach users only via manual release

### Release Flow

1. Edit plugin files in `<plugin>/trunk/`
2. Commit and push to `main`
3. Trigger release: `gh workflow run release-<plugin>.yml`
4. Workflow: sync SVN trunk → bump version → create SVN tag → SVN commit → push version bump to Git → create Git tag → create GitHub Release
5. Users see the update within a few hours

### Version Locations

Each plugin has its version in TWO places (both updated automatically by release workflow):
- `<plugin>/trunk/<main-php-file>` → ` * Version: X.Y.Z`
- `<plugin>/trunk/readme.txt` → `Stable tag: X.Y.Z`
- WooCommerce has a third: `UNUSPAY_PAYMENTS_VERSION` constant

### Security

- SVN is entirely managed by CI/CD — never run `svn ci` manually
- GitHub secrets `SVN_USERNAME` and `SVN_PASSWORD` must be set
- SVN password is generated at WordPress.org profile (NOT account password)

</shared_rules>

<gotchas>
- **Two `assets/` directories per plugin** — `<plugin>/trunk/assets/` (shipped to users) vs `<plugin>/assets/` (WP.org directory page). Different purposes.
- **No auto-sync** — pushing to `main` does NOT update SVN trunk. Only the release workflow syncs.
- **CD pushes back to Git** — after release, the workflow pushes version bump to `main`.
- **Concurrency is per-plugin** — `svn-pipeline-edd` and `svn-pipeline-woocommerce` run independently.
- **Version auto-increment caps at 10** — `1.0.10` → `1.1.0` (not `1.0.11`). Major version is always manual.
- **GitHub Release tags** — EDD uses `v{version}-edd`, WooCommerce uses `v{version}-woo` to avoid tag collisions.
- **Git tags created on release** — the workflow tags the version bump commit and pushes the tag to origin. Tag creation is idempotent (skips if tag already exists).
- **WooCommerce `vendor/` is shipped** — do NOT add to `.distignore`.
</gotchas>

<doc_context>
- **Skill (EDD)**: `docs/skills/edd.md` — EDD-specific paths, code style, release commands
- **Skill (WooCommerce)**: `docs/skills/woocommerce.md` — Woo-specific paths, code style, release commands
- **Skill (release)**: `docs/skills/release.md` — how to trigger release workflows
- **EDD release workflow**: `.github/workflows/release-edd.yml`
- **Woo release workflow**: `.github/workflows/release-woocommerce.yml`
- **EDD release script**: `easy-digital-downloads/scripts/release.sh`
- **Woo release script**: `woocommerce/scripts/release.sh`
</doc_context>

</agents_md>
