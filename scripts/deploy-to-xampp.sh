#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"
PLUGIN_SLUG="custom-form-handler"
DEFAULT_XAMPP_WORDPRESS_ROOT="/Applications/XAMPP/xamppfiles/htdocs/wordpress"
DEFAULT_TARGET_DIR="${DEFAULT_XAMPP_WORDPRESS_ROOT}/wp-content/plugins/${PLUGIN_SLUG}"

DRY_RUN=0
TARGET_DIR="${CFH_XAMPP_PLUGIN_DIR:-${DEFAULT_TARGET_DIR}}"

print_usage() {
    cat <<'EOF'
Usage: ./scripts/deploy-to-xampp.sh [--dry-run] [--target-dir PATH]

Options:
  --dry-run          Preview changes without copying files.
  --target-dir PATH  Override the XAMPP plugin target directory.
  --help             Show this help message.

Environment:
  CFH_XAMPP_PLUGIN_DIR  Alternative target directory for the deployed plugin.
EOF
}

while [[ $# -gt 0 ]]; do
    case "$1" in
        --dry-run)
            DRY_RUN=1
            shift
            ;;
        --target-dir)
            if [[ $# -lt 2 ]]; then
                echo "Missing value for --target-dir" >&2
                exit 1
            fi
            TARGET_DIR="$2"
            shift 2
            ;;
        --help)
            print_usage
            exit 0
            ;;
        *)
            echo "Unknown argument: $1" >&2
            print_usage >&2
            exit 1
            ;;
    esac
done

if ! command -v rsync >/dev/null 2>&1; then
    echo "rsync is required for deployment but was not found in PATH." >&2
    exit 1
fi

mkdir -p "${TARGET_DIR}"

RSYNC_ARGS=(
    -av
    --delete
    --exclude=.git/
    --exclude=.github/
    --exclude=.agents/
    --exclude=.codex/
    --exclude=.DS_Store
    --exclude=githooks/
    --exclude=scripts/
    --exclude=README.md
    --exclude=RELEASING.md
)

if [[ "${DRY_RUN}" -eq 1 ]]; then
    RSYNC_ARGS+=(--dry-run)
fi

echo "Deploying plugin from:"
echo "  ${REPO_ROOT}"
echo "to:"
echo "  ${TARGET_DIR}"

rsync "${RSYNC_ARGS[@]}" "${REPO_ROOT}/" "${TARGET_DIR}/"

if [[ "${DRY_RUN}" -eq 1 ]]; then
    echo "Dry run completed."
else
    echo "Deployment completed successfully."
fi
