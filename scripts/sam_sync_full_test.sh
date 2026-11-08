#!/usr/bin/env bash
# Full SAM snapshot test runner: download/unpack, extract, validate, and optionally publish.
# https://dw.grompil.com/!saas/doku.php?id=broncode:scripts:sam_sync_full_test

set -Eeuo pipefail

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd -- "$SCRIPT_DIR/.." && pwd)"
PHP_BIN="${PHP_BIN:-php}"
PSQL_BIN="${PSQL_BIN:-psql}"
SAM_LOG_DIR="${SAM_LOG_DIR:-${HOME:?HOME must be set}/sam/logs}"
SAM_TMP_DIR="${SAM_TMP_DIR:-$HOME/sam/tmp}"
SOURCE_DIR=""
ARCHIVE_FILE=""
DOWNLOAD_URL=""
APPLY=0

usage() {
    cat <<'USAGE'
Usage:
  bash scripts/sam_sync_full_test.sh --source-dir /path/to/full-sam-files [--apply]
  bash scripts/sam_sync_full_test.sh --archive /path/to/full-export.zip [--apply]
  bash scripts/sam_sync_full_test.sh --download-url https://.../full-export.zip [--apply]

The source must contain exactly one AMP-*.xml and one REF-*.xml full export.
By default the script imports into temporary staging tables, validates the data,
and rolls back. Add --apply to replace the SAM product and ATC snapshots.

Database connection is read from standard libpq environment variables and
.pgpass/PGPASSFILE (the script never prompts for a password).
USAGE
}

die() {
    printf 'ERROR: %s\n' "$*" >&2
    exit 1
}

while (($#)); do
    case "$1" in
        --source-dir)
            (($# >= 2)) || die "--source-dir needs a directory argument."
            SOURCE_DIR="$2"
            shift 2
            ;;
        --download-url)
            (($# >= 2)) || die "--download-url needs a direct URL to a full export ZIP."
            DOWNLOAD_URL="$2"
            shift 2
            ;;
        --archive)
            (($# >= 2)) || die "--archive needs a path to an existing full export ZIP."
            ARCHIVE_FILE="$2"
            shift 2
            ;;
        --apply)
            APPLY=1
            shift
            ;;
        -h|--help)
            usage
            exit 0
            ;;
        *)
            usage >&2
            die "Unknown option: $1"
            ;;
    esac
done

SOURCE_COUNT=0
[[ -z "$SOURCE_DIR" ]] || SOURCE_COUNT=$((SOURCE_COUNT + 1))
[[ -z "$ARCHIVE_FILE" ]] || SOURCE_COUNT=$((SOURCE_COUNT + 1))
[[ -z "$DOWNLOAD_URL" ]] || SOURCE_COUNT=$((SOURCE_COUNT + 1))
((SOURCE_COUNT == 1)) || { usage >&2; die "Provide exactly one of --source-dir, --archive, or --download-url."; }

for command in "$PHP_BIN" "$PSQL_BIN" find sort wc grep sed mktemp date head tr tee cat mkdir basename; do
    command -v "$command" >/dev/null 2>&1 || die "Required command is unavailable: $command"
done

if [[ -n "$DOWNLOAD_URL" ]]; then
    command -v curl >/dev/null 2>&1 || die "curl is required for --download-url."
fi
if [[ -n "$DOWNLOAD_URL" || -n "$ARCHIVE_FILE" ]]; then
    command -v unzip >/dev/null 2>&1 || die "unzip is required for ZIP input."
fi

[[ -n "${PGHOST:-}" && -n "${PGDATABASE:-}" && -n "${PGUSER:-}" ]] || \
    die "Set PGHOST, PGDATABASE, and PGUSER for the test database."
if [[ -n "${PGPASSFILE:-}" ]]; then
    [[ -r "$PGPASSFILE" ]] || die "PGPASSFILE is not readable: $PGPASSFILE"
elif [[ -n "${HOME:-}" && -r "$HOME/.pgpass" ]]; then
    PGPASSFILE="$HOME/.pgpass"
    export PGPASSFILE
else
    die "Set PGPASSFILE or create a readable ~/.pgpass; interactive prompts are disabled."
fi

mkdir -p -- "$SAM_LOG_DIR" "$SAM_TMP_DIR"
command -v flock >/dev/null 2>&1 || die "flock is required to prevent overlapping SAM sync runs."
exec 9>"$SAM_LOG_DIR/sam-sync.lock"
flock -n 9 || die "Another SAM sync run is already active."

RUN_ID="$(date -u '+%Y%m%dT%H%M%SZ')"
LOG_FILE="$SAM_LOG_DIR/sam-full-sync-$RUN_ID.log"
WORK_DIR="$(mktemp -d "$SAM_TMP_DIR/grompil-sam-sync.XXXXXXXX")"
cleanup() {
    local status=$?
    if [[ -n "${WORK_DIR:-}" && -d "$WORK_DIR" && "$WORK_DIR" == "$SAM_TMP_DIR"/grompil-sam-sync.* ]]; then
        rm -rf -- "$WORK_DIR"
    fi
    if ((status != 0)); then
        printf 'SAM sync failed (exit %s). Log: %s\n' "$status" "$LOG_FILE" >&2
    fi
    exit "$status"
}
trap cleanup EXIT

exec > >(tee -a "$LOG_FILE") 2>&1
printf 'SAM full snapshot run: %s\n' "$RUN_ID"
printf 'Database target: %s at %s (user %s)\n' "$PGDATABASE" "$PGHOST" "$PGUSER"
printf 'Mode: %s\n' "$([[ $APPLY -eq 1 ]] && printf APPLY || printf CHECK-ONLY)"
printf 'Temporary work directory: %s\n' "$WORK_DIR"

EXPORT_DIR="$WORK_DIR/export"
mkdir -p -- "$EXPORT_DIR"
if [[ -n "$DOWNLOAD_URL" ]]; then
    ARCHIVE="$WORK_DIR/sam-full-export.zip"
    printf 'Downloading SAM full export archive...\n'
    curl --fail --location --retry 2 --connect-timeout 30 --output "$ARCHIVE" "$DOWNLOAD_URL"
    [[ -s "$ARCHIVE" ]] || die "Downloaded archive is empty."
    printf 'Downloaded archive size: %s bytes\n' "$(wc -c < "$ARCHIVE" | tr -d ' ')"
    unzip -tq "$ARCHIVE" || die "Downloaded ZIP failed its integrity check."
    unzip -q "$ARCHIVE" -d "$EXPORT_DIR"
elif [[ -n "$ARCHIVE_FILE" ]]; then
    [[ -f "$ARCHIVE_FILE" && -r "$ARCHIVE_FILE" ]] || die "Archive is missing or unreadable: $ARCHIVE_FILE"
    ARCHIVE_FILE="$(cd -- "$(dirname -- "$ARCHIVE_FILE")" && pwd)/$(basename -- "$ARCHIVE_FILE")"
    printf 'Using existing SAM full export archive: %s (%s bytes)\n' "$ARCHIVE_FILE" "$(wc -c < "$ARCHIVE_FILE" | tr -d ' ')"
    unzip -tq "$ARCHIVE_FILE" || die "Existing ZIP failed its integrity check."
    unzip -q "$ARCHIVE_FILE" -d "$EXPORT_DIR"
else
    [[ -d "$SOURCE_DIR" && -r "$SOURCE_DIR" ]] || die "Source directory is missing or unreadable: $SOURCE_DIR"
    EXPORT_DIR="$(cd -- "$SOURCE_DIR" && pwd)"
fi

find_one_export() {
    local prefix="$1"
    local -a matches=()
    mapfile -t matches < <(find "$EXPORT_DIR" -type f -name "${prefix}-*.xml" -print | sort)
    ((${#matches[@]} == 1)) || die "Expected exactly one ${prefix}-*.xml full export under $EXPORT_DIR; found ${#matches[@]}."
    printf '%s\n' "${matches[0]}"
}

AMP_XML="$(find_one_export AMP)"
REF_XML="$(find_one_export REF)"
PRODUCT_CSV="$WORK_DIR/sam_public_catalog.csv"
ATC_CSV="$WORK_DIR/sam_atc_classifications.csv"
AMP_REPORT="$WORK_DIR/amp-extract.log"
REF_REPORT="$WORK_DIR/ref-extract.log"

printf 'AMP source: %s (%s bytes)\n' "$(basename -- "$AMP_XML")" "$(wc -c < "$AMP_XML" | tr -d ' ')"
printf 'REF source: %s (%s bytes)\n' "$(basename -- "$REF_XML")" "$(wc -c < "$REF_XML" | tr -d ' ')"

"$PHP_BIN" "$SCRIPT_DIR/sam_public_catalog_export.php" "$AMP_XML" "$PRODUCT_CSV" >"$AMP_REPORT" 2>&1 || {
    cat "$AMP_REPORT" >&2
    die "Product CSV extraction failed."
}
"$PHP_BIN" "$SCRIPT_DIR/sam_atc_classifications_export.php" "$REF_XML" "$ATC_CSV" >"$REF_REPORT" 2>&1 || {
    cat "$REF_REPORT" >&2
    die "ATC CSV extraction failed."
}
cat "$AMP_REPORT"
cat "$REF_REPORT"

grep -Fq 'XML parsing warnings: 0' "$AMP_REPORT" || die "AMP XML reported parsing warnings; refusing import."
[[ -s "$PRODUCT_CSV" && -s "$ATC_CSV" ]] || die "One of the extracted CSV files is empty."
AMP_SAM_ID="$(sed -n 's/^SAM ID: //p' "$AMP_REPORT" | head -n 1)"
REF_SAM_ID="$(sed -n 's/^SAM ID: //p' "$REF_REPORT" | head -n 1)"
[[ -n "$AMP_SAM_ID" && "$AMP_SAM_ID" != '(onbekend)' ]] || die "AMP export has no SAM ID."
[[ "$AMP_SAM_ID" == "$REF_SAM_ID" ]] || die "AMP and REF exports do not share a SAM ID ($AMP_SAM_ID vs $REF_SAM_ID)."
[[ "$AMP_SAM_ID" =~ ^[A-Za-z0-9._-]+$ ]] || die "SAM ID contains unexpected characters."

printf 'Extracted product CSV size: %s bytes\n' "$(wc -c < "$PRODUCT_CSV" | tr -d ' ')"
printf 'Extracted ATC CSV size: %s bytes\n' "$(wc -c < "$ATC_CSV" | tr -d ' ')"
printf 'Validated matching SAM ID: %s\n' "$AMP_SAM_ID"

PSQL_ARGS=(-X --no-password --set=ON_ERROR_STOP=1)
PSQL_FILE="$WORK_DIR/import.sql"
cat > "$PSQL_FILE" <<SQL
BEGIN;
CREATE TEMP TABLE sam_stage_products (LIKE public.xt_sam_public_products INCLUDING DEFAULTS);
CREATE TEMP TABLE sam_stage_atc (LIKE public.xt_sam_atc_classifications INCLUDING DEFAULTS);
\\copy sam_stage_products (sam_export_version, sam_id, validity_date, amp_code, ampp_code, cnk, delivery_environment, cnk_code_type, amp_status, ampp_status, official_name, name_nl, name_fr, prescription_name_nl, prescription_name_fr, medicine_type, delivery_modus_codes_json, delivery_modus_specification_codes_json, amp_data_json, ampp_data_json, dmpp_data_json, pack_display_nl, pack_display_fr) FROM '$PRODUCT_CSV' WITH (FORMAT csv, HEADER true, ENCODING 'UTF8')
\\copy sam_stage_atc (atc_code, description) FROM '$ATC_CSV' WITH (FORMAT csv, HEADER true, ENCODING 'UTF8')
DO \$body\$
BEGIN
    IF (SELECT count(*) FROM sam_stage_products) = 0 THEN
        RAISE EXCEPTION 'SAM product staging table is empty';
    END IF;
    IF (SELECT count(DISTINCT sam_id) FROM sam_stage_products) <> 1
       OR (SELECT min(sam_id) FROM sam_stage_products) <> '$AMP_SAM_ID' THEN
        RAISE EXCEPTION 'SAM product CSV contains inconsistent or unexpected SAM IDs';
    END IF;
    IF EXISTS (
        SELECT 1 FROM sam_stage_products
        GROUP BY amp_code, ampp_code, cnk HAVING count(*) > 1
    ) THEN
        RAISE EXCEPTION 'SAM product staging table has duplicate source identities';
    END IF;
    IF EXISTS (
        SELECT 1 FROM sam_stage_products
        WHERE cnk IS NULL OR amp_code IS NULL OR ampp_code IS NULL
    ) THEN
        RAISE EXCEPTION 'SAM product staging table has missing key fields';
    END IF;
    IF (SELECT count(*) FROM sam_stage_atc) = 0 THEN
        RAISE EXCEPTION 'SAM ATC staging table is empty';
    END IF;
    IF EXISTS (
        SELECT 1 FROM sam_stage_atc GROUP BY atc_code HAVING count(*) > 1
    ) THEN
        RAISE EXCEPTION 'SAM ATC staging table has duplicate codes';
    END IF;
END
\$body\$;

SELECT '$AMP_SAM_ID' AS validated_sam_id,
       (SELECT count(*) FROM sam_stage_products) AS products_to_load,
       (SELECT count(*) FROM sam_stage_atc) AS atc_rows_to_load;
SQL

if ((APPLY)); then
    cat >> "$PSQL_FILE" <<SQL
DELETE FROM public.xt_sam_public_products;
INSERT INTO public.xt_sam_public_products (
    sam_export_version, sam_id, validity_date, amp_code, ampp_code, cnk,
    delivery_environment, cnk_code_type, amp_status, ampp_status, official_name,
    name_nl, name_fr, prescription_name_nl, prescription_name_fr, medicine_type,
    delivery_modus_codes_json, delivery_modus_specification_codes_json,
    amp_data_json, ampp_data_json, dmpp_data_json, pack_display_nl, pack_display_fr
)
SELECT sam_export_version, sam_id, validity_date, amp_code, ampp_code, cnk,
       delivery_environment, cnk_code_type, amp_status, ampp_status, official_name,
       name_nl, name_fr, prescription_name_nl, prescription_name_fr, medicine_type,
       delivery_modus_codes_json, delivery_modus_specification_codes_json,
       amp_data_json, ampp_data_json, dmpp_data_json, pack_display_nl, pack_display_fr
FROM sam_stage_products;
DELETE FROM public.xt_sam_atc_classifications;
INSERT INTO public.xt_sam_atc_classifications (atc_code, description)
SELECT atc_code, description FROM sam_stage_atc;
COMMIT;
SELECT '$AMP_SAM_ID' AS published_sam_id,
       (SELECT count(*) FROM public.xt_sam_public_products) AS published_products,
       (SELECT count(*) FROM public.xt_sam_atc_classifications) AS published_atc_rows;
SQL
    "$PSQL_BIN" "${PSQL_ARGS[@]}" --file="$PSQL_FILE"
    printf 'Snapshot published successfully.\n'
else
    printf 'ROLLBACK;\n' >> "$PSQL_FILE"
    "$PSQL_BIN" "${PSQL_ARGS[@]}" --file="$PSQL_FILE"
    printf 'Check-only run complete: no permanent table was changed.\n'
fi

printf 'Run log: %s\n' "$LOG_FILE"
