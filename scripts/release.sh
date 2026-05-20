#!/usr/bin/env bash
set -eo pipefail

# === Configuration ===
PLUGIN_SLUG="unuspay-crypto-payments-for-easy-digital-downloads"
SVN_URL="https://plugins.svn.wordpress.org/${PLUGIN_SLUG}/"
PLUGIN_FILE="trunk/unuspay-crypto-payments-for-easy-digital-downloads.php"
README_FILE="trunk/readme.txt"
SVN_DIR="${HOME}/svn-${PLUGIN_SLUG}"
SEMVER_REGEX='^[0-9]+\.[0-9]+\.[0-9]+$'

# === Portable sed ===
# Use temp-file approach to avoid GNU vs BSD sed differences
# Uses [[:space:]] instead of \s for POSIX compatibility
sed_inplace() {
  local pattern="$1"
  local file="$2"
  local tmp
  tmp=$(mktemp)
  sed -E "$pattern" "$file" > "$tmp" && mv "$tmp" "$file"
}

# === Parse arguments ===
DRY_RUN=false
FORCE_VERSION=""
while [[ $# -gt 0 ]]; do
  case $1 in
    --dry-run)
      DRY_RUN=true
      shift
      ;;
    --version)
      FORCE_VERSION="$2"
      shift 2
      ;;
    *)
      echo "Unknown argument: $1"
      echo "Usage: $0 [--dry-run] [--version X.Y.Z]"
      exit 1
      ;;
  esac
done

# === Validate environment ===
if [ -z "$SVN_USERNAME" ] || [ -z "$SVN_PASSWORD" ]; then
  echo "ERROR: SVN_USERNAME and SVN_PASSWORD environment variables are required."
  exit 1
fi

echo "==> Starting release process..."

# === Sparse SVN checkout ===
# Note: no conflict check needed here — fresh checkout cannot have conflicts
echo "==> Checking out SVN repository (sparse)..."
svn checkout --depth immediates "$SVN_URL" "$SVN_DIR"
cd "$SVN_DIR"
svn update --set-depth infinity trunk
svn update --set-depth infinity assets
svn update --set-depth immediates tags

# === Read current version ===
CURRENT_VERSION=$(grep -iE '^[[:space:]]*\*?[[:space:]]*Version:[[:space:]]*[0-9]' "$PLUGIN_FILE" | head -1 | sed -E 's/.*Version:[[:space:]]*//' | tr -d '[:space:]')

if [ -z "$CURRENT_VERSION" ]; then
  echo "ERROR: Could not read current version from $PLUGIN_FILE"
  exit 1
fi

if [[ ! "$CURRENT_VERSION" =~ $SEMVER_REGEX ]]; then
  echo "ERROR: Current version '$CURRENT_VERSION' is not valid semver (MAJOR.MINOR.PATCH)"
  exit 1
fi

echo "==> Current version: $CURRENT_VERSION"

# === Compute next version ===
if [ -n "$FORCE_VERSION" ]; then
  if [[ ! "$FORCE_VERSION" =~ $SEMVER_REGEX ]]; then
    echo "ERROR: Forced version '$FORCE_VERSION' is not valid semver (MAJOR.MINOR.PATCH)"
    exit 1
  fi
  NEXT_VERSION="$FORCE_VERSION"
  echo "==> Using forced version: $NEXT_VERSION"
else
  IFS='.' read -r MAJOR MINOR PATCH <<< "$CURRENT_VERSION"
  if [ "$PATCH" -lt 10 ]; then
    NEXT_VERSION="${MAJOR}.${MINOR}.$((PATCH + 1))"
  else
    NEXT_VERSION="${MAJOR}.$((MINOR + 1)).0"
  fi
  echo "==> Next version: $NEXT_VERSION"
fi

# === Validate tag doesn't exist ===
if [ -d "tags/$NEXT_VERSION" ]; then
  echo "ERROR: Version $NEXT_VERSION already exists in SVN tags. Aborting."
  exit 1
fi

# === Bump version in plugin file ===
echo "==> Updating Version in $PLUGIN_FILE..."

# Verify exactly one Version line exists before modifying
VERSION_COUNT=$(grep -cE '^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*[0-9]' "$PLUGIN_FILE" || true)
if [ "$VERSION_COUNT" -ne 1 ]; then
  echo "ERROR: Expected exactly 1 Version line, found $VERSION_COUNT in $PLUGIN_FILE"
  exit 1
fi

sed_inplace "s/^([[:space:]]*\*[[:space:]]*Version:[[:space:]]*).*/\1${NEXT_VERSION}/" "$PLUGIN_FILE"

# === Update Stable tag in readme.txt ===
echo "==> Updating Stable tag in $README_FILE..."

STABLE_COUNT=$(grep -cE '^Stable tag:[[:space:]]*[0-9]' "$README_FILE" || true)
if [ "$STABLE_COUNT" -ne 1 ]; then
  echo "ERROR: Expected exactly 1 Stable tag line, found $STABLE_COUNT in $README_FILE"
  exit 1
fi

sed_inplace "s/^Stable tag:[[:space:]]*.*/Stable tag: ${NEXT_VERSION}/" "$README_FILE"

# === Create SVN tag ===
echo "==> Creating SVN tag tags/$NEXT_VERSION..."
svn cp trunk "tags/$NEXT_VERSION"

# === Set MIME types on image assets ===
echo "==> Setting MIME types on image assets..."
if test -d assets && test -n "$(find assets -maxdepth 1 -name '*.png' -print -quit)"; then
  svn propset svn:mime-type image/png assets/*.png || true
fi
if test -d assets && test -n "$(find assets -maxdepth 1 -name '*.jpg' -print -quit)"; then
  svn propset svn:mime-type image/jpeg assets/*.jpg || true
fi
if test -d assets && test -n "$(find assets -maxdepth 1 -name '*.gif' -print -quit)"; then
  svn propset svn:mime-type image/gif assets/*.gif || true
fi
if test -d assets && test -n "$(find assets -maxdepth 1 -name '*.svg' -print -quit)"; then
  svn propset svn:mime-type image/svg+xml assets/*.svg || true
fi

# === Show status ===
echo "==> SVN status:"
svn status

# === Commit or dry-run ===
if [ "$DRY_RUN" = true ]; then
  echo ""
  echo "==> DRY RUN — no changes committed."
  echo "==> Version would be: $NEXT_VERSION"
  echo "==> Changes preview:"
  svn status
  # Export version for GitHub Actions output even in dry-run
  if [ -n "$GITHUB_OUTPUT" ]; then
    echo "released_version=${NEXT_VERSION}" >> "$GITHUB_OUTPUT"
  fi
  exit 0
fi

echo "==> Updating working copy..."
svn update

# Check for conflicts after update
if svn status | grep -q '^C'; then
  echo "ERROR: SVN conflict detected after update. Resolve manually."
  svn status | grep '^C'
  exit 1
fi

echo "==> Committing release $NEXT_VERSION..."
svn commit -m "Release $NEXT_VERSION" \
  --no-auth-cache \
  --non-interactive \
  --username "$SVN_USERNAME" \
  --password "$SVN_PASSWORD"

# Export version for GitHub Actions output
if [ -n "$GITHUB_OUTPUT" ]; then
  echo "released_version=${NEXT_VERSION}" >> "$GITHUB_OUTPUT"
fi

echo ""
echo "==> Release $NEXT_VERSION published to SVN successfully!"
echo "==> Users will see the update within a few hours."
