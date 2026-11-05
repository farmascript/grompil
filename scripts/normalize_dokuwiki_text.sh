#!/usr/bin/env bash
set -euo pipefail

usage() {
    cat <<'USAGE'
Usage:
  bash normalize_dokuwiki_text.sh [--apply] <dokuwiki-directory>

Without --apply, lists text files and matching lines that would change.
With --apply, makes the replacements and creates a timestamped .bak file
beside each changed file.
USAGE
}

apply_changes=false
if [[ "${1:-}" == "--apply" ]]; then
    apply_changes=true
    shift
fi

if [[ $# -ne 1 || ! -d "$1" ]]; then
    usage >&2
    exit 2
fi

root=$(cd -- "$1" && pwd)
timestamp=$(date '+%Y%m%d-%H%M%S')
candidate_count=0
changed_count=0
file_list=$(mktemp)
trap 'rm -f -- "$file_list"' EXIT

find "$root" \
    -type d \( -name .git -o -name vendor -o -name cache -o -name attic -o -name media \) -prune -o \
    -type f -print0 > "$file_list"

while IFS= read -r -d '' file; do
    if ! grep -IqF -e 'not fully' -e 'translation.**\' -- "$file"; then
        continue
    fi

    candidate_count=$((candidate_count + 1))
    if ! $apply_changes; then
        printf '\n--- %s ---\n' "$file"
        grep -n -F -e 'not fully' -e 'translation.**\' -- "$file" || true
        continue
    fi

    if ! perl -0ne 'exit 0 if /(?<!or )not fully|translation\.\*\*\\(?!\\)/; exit 1' "$file"; then
        continue
    fi

    cp -p -- "$file" "$file.bak.$timestamp"
    perl -0pi -e 's/(?<!or )not fully/not, or not fully, /g; s/translation\.\*\*\\(?!\\)/translation.**\\\\/g' -- "$file"
    changed_count=$((changed_count + 1))
    printf 'Updated: %s (backup: %s.bak.%s)\n' "$file" "$file" "$timestamp"
done < "$file_list"

if $apply_changes; then
    printf '\nScanned candidates: %d; changed files: %d.\n' "$candidate_count" "$changed_count"
else
    printf '\nFiles to review: %d. Run again with --apply to make changes.\n' "$candidate_count"
fi
