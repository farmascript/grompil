#!/usr/bin/env bash
# Download and import the latest RIZIV magistral reference workbook.
# https://dw.grompil.com/!saas/doku.php?id=broncode:scripts:xt_compounding_riziv_sync

set -Eeuo pipefail

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
PHP_BIN="${PHP_BIN:-php}"
PSQL_BIN="${PSQL_BIN:-psql}"
ROOT_DIR="${COMPOUNDING_ROOT_DIR:-${HOME:?HOME must be set}/sam/compounding}"
DOWNLOAD_DIR="${COMPOUNDING_DOWNLOAD_DIR:-$ROOT_DIR/download}"
TMP_DIR="${COMPOUNDING_TMP_DIR:-$ROOT_DIR/tmp}"
LOG_DIR="${COMPOUNDING_LOG_DIR:-$ROOT_DIR/logs}"
RESULTS_DIR="${COMPOUNDING_RESULTS_DIR:-$ROOT_DIR/results}"
APPLY=0
SOURCE_XLSX=""
RIZIV_XLSX_URL="https://www.riziv.fgov.be/SiteCollectionDocuments/magistrale_bereidingen_referentiebestand_preparation_magistrale_fichier_de_reference.xlsx"

usage() {
    cat <<'USAGE'
Usage:
  bash scripts/xt_compounding_riziv_sync.sh [--apply]
  bash scripts/xt_compounding_riziv_sync.sh --source-xlsx /path/to/reference.xlsx [--apply]

The default run downloads the current official RIZIV reference workbook,
extracts its reimbursable raw-material rows, stages them in PostgreSQL, checks
the identities/counts, then rolls back without changing permanent data.
Add --apply to publish the imported snapshot. Previous effective versions are
retained with valid_to dates; canonical materials are not deleted.

Set PGHOST, PGDATABASE and PGUSER, plus PGPASSFILE (or ~/.pgpass).
The recommended scheduler wrapper should explicitly set the database target.
USAGE
}

die() { printf 'ERROR: %s\n' "$*" >&2; exit 1; }

while (($#)); do
    case "$1" in
        --apply) APPLY=1; shift ;;
        --source-xlsx)
            (($# >= 2)) || die "--source-xlsx needs a file path."
            SOURCE_XLSX="$2"
            shift 2
            ;;
        -h|--help) usage; exit 0 ;;
        *) usage >&2; die "Unknown option: $1" ;;
    esac
done

command -v "$PHP_BIN" >/dev/null 2>&1 || die "PHP is unavailable: $PHP_BIN"
command -v "$PSQL_BIN" >/dev/null 2>&1 || die "psql is unavailable: $PSQL_BIN"
for command in curl unzip mktemp date wc tr basename mkdir flock sleep kill cp mv rm head sed cut sha256sum; do
    command -v "$command" >/dev/null 2>&1 || die "Required command is unavailable: $command"
done
[[ -n "${PGHOST:-}" && -n "${PGDATABASE:-}" && -n "${PGUSER:-}" ]] || die "Set PGHOST, PGDATABASE and PGUSER explicitly."
if [[ -n "${PGPASSFILE:-}" ]]; then
    [[ -r "$PGPASSFILE" ]] || die "PGPASSFILE is not readable: $PGPASSFILE"
elif [[ -n "${HOME:-}" && -r "$HOME/.pgpass" ]]; then
    PGPASSFILE="$HOME/.pgpass"
    export PGPASSFILE
else
    die "Set PGPASSFILE or create ~/.pgpass; interactive password prompts are disabled."
fi

mkdir -p -- "$DOWNLOAD_DIR" "$TMP_DIR" "$LOG_DIR" "$RESULTS_DIR"
exec 9>"$LOG_DIR/riziv-sync.lock"
flock -n 9 || die "Another RIZIV compounding sync run is already active."

RUN_ID="$(date -u '+%Y%m%dT%H%M%SZ')"
LOG_FILE="$LOG_DIR/riziv-compounding-sync-$RUN_ID.log"
WORK_DIR="$(mktemp -d "$TMP_DIR/grompil-riziv-sync.XXXXXXXX")"
DOWNLOAD_PART=""
exec 8>&1
cleanup() {
    local status=$?
    [[ -z "$DOWNLOAD_PART" ]] || rm -f -- "$DOWNLOAD_PART"
    [[ ! -d "$WORK_DIR" || "$WORK_DIR" != "$TMP_DIR"/grompil-riziv-sync.* ]] || rm -rf -- "$WORK_DIR"
    if ((status != 0)); then printf 'RIZIV sync failed (exit %s). Log: %s\n' "$status" "$LOG_FILE" >&8; fi
    [[ ! -f "$LOG_FILE" ]] || cat "$LOG_FILE" >&8
    exec 8>&-
    exit "$status"
}
trap cleanup EXIT
exec >>"$LOG_FILE" 2>&1

printf 'RIZIV magistral raw-material sync: %s\n' "$RUN_ID"
printf 'Database target: %s at %s (user %s)\n' "$PGDATABASE" "$PGHOST" "$PGUSER"
printf 'Mode: %s\n' "$([[ $APPLY -eq 1 ]] && printf APPLY || printf CHECK-ONLY)"
printf 'Temporary work directory: %s\n' "$WORK_DIR"

if [[ -z "$SOURCE_XLSX" ]]; then
    SOURCE_XLSX="$WORK_DIR/riziv-reference.xlsx"
    printf 'Downloading current RIZIV magistral reference workbook...\n'
    curl --fail --location --show-error --retry 3 --connect-timeout 30 --output "$SOURCE_XLSX" "$RIZIV_XLSX_URL"
else
    [[ -f "$SOURCE_XLSX" && -r "$SOURCE_XLSX" ]] || die "Source workbook is missing or unreadable: $SOURCE_XLSX"
    SOURCE_XLSX="$(cd -- "$(dirname -- "$SOURCE_XLSX")" && pwd)/$(basename -- "$SOURCE_XLSX")"
fi
[[ -s "$SOURCE_XLSX" ]] || die "Downloaded workbook is empty."
printf 'Workbook size: %s bytes\n' "$(wc -c < "$SOURCE_XLSX" | tr -d ' ')"

unzip -tq "$SOURCE_XLSX" || die "RIZIV workbook is not a valid XLSX archive."
EXTRACT_DIR="$WORK_DIR/workbook"
mkdir -p -- "$EXTRACT_DIR"
unzip -q "$SOURCE_XLSX" -d "$EXTRACT_DIR"
CSV_FILE="$WORK_DIR/riziv_compounding_reference.csv"
REPORT_FILE="$WORK_DIR/extract.log"
"$PHP_BIN" "$SCRIPT_DIR/xt_compounding_riziv_export.php" "$EXTRACT_DIR" "$CSV_FILE" >"$REPORT_FILE" 2>&1 || { cat "$REPORT_FILE" >&2; die "RIZIV workbook extraction failed."; }
cat "$REPORT_FILE"
[[ -s "$CSV_FILE" ]] || die "The extracted CSV is empty."
SOURCE_VERSION="$(sed -n 's/^Effective date: //p' "$REPORT_FILE" | head -n 1)"
[[ "$SOURCE_VERSION" =~ ^[0-9]{4}-[0-9]{2}-[0-9]{2}$ ]] || die "Extractor returned an invalid effective date."
[[ "${SOURCE_XLSX##*/}" =~ ^[A-Za-z0-9._-]+$ ]] || die "Workbook filename contains unexpected characters."
ARCHIVED_XLSX="$DOWNLOAD_DIR/riziv_magistral_reference_${SOURCE_VERSION}.xlsx"
ARCHIVED_CSV="$RESULTS_DIR/riziv_compounding_reference_${SOURCE_VERSION}.csv"
if [[ -e "$ARCHIVED_XLSX" ]]; then
    [[ "$(sha256sum "$ARCHIVED_XLSX" | cut -d ' ' -f 1)" == "$(sha256sum "$SOURCE_XLSX" | cut -d ' ' -f 1)" ]] || \
        die "A different workbook is already archived for effective date $SOURCE_VERSION. Review it manually before replacing anything."
else
    cp -- "$SOURCE_XLSX" "$ARCHIVED_XLSX"
fi
if [[ -e "$ARCHIVED_CSV" ]]; then
    if [[ "$(sha256sum "$ARCHIVED_CSV" | cut -d ' ' -f 1)" != "$(sha256sum "$CSV_FILE" | cut -d ' ' -f 1)" ]]; then
        # The archived source workbook was verified above. The CSV is a derived
        # artifact, so regenerate it when exporter serialization changes.
        cp -- "$CSV_FILE" "$ARCHIVED_CSV"
    fi
else
    cp -- "$CSV_FILE" "$ARCHIVED_CSV"
fi
SOURCE_FILE="${ARCHIVED_XLSX##*/}"
printf 'Archived workbook: %s\n' "$ARCHIVED_XLSX"
printf 'Archived extracted CSV: %s\n' "$ARCHIVED_CSV"

SQL_FILE="$WORK_DIR/import.sql"
cat > "$SQL_FILE" <<SQL
BEGIN;
CREATE TEMP TABLE riziv_stage (
    source_version TEXT NOT NULL,
    valid_from DATE NOT NULL,
    cnk VARCHAR(7) NOT NULL,
    chapter_code VARCHAR(8) NOT NULL,
    list_number VARCHAR(40) NOT NULL,
    name_fr TEXT NOT NULL,
    name_nl TEXT NOT NULL,
    synonyms_fr_json JSONB NOT NULL,
    synonyms_nl_json JSONB NOT NULL,
    source_payload_json JSONB NOT NULL
) ON COMMIT DROP;
\\copy riziv_stage FROM '$CSV_FILE' WITH (FORMAT csv, HEADER true, ENCODING 'UTF8')
DO \$body\$
BEGIN
    IF (SELECT count(*) FROM riziv_stage) = 0 THEN
        RAISE EXCEPTION 'RIZIV staging table is empty';
    END IF;
    IF (SELECT count(DISTINCT source_version) FROM riziv_stage) <> 1
       OR (SELECT min(source_version) FROM riziv_stage) <> '$SOURCE_VERSION' THEN
        RAISE EXCEPTION 'RIZIV staging rows contain inconsistent versions';
    END IF;
    IF EXISTS (SELECT 1 FROM riziv_stage WHERE cnk !~ '^[0-9]{7}$') THEN
        RAISE EXCEPTION 'RIZIV staging contains an invalid CNK';
    END IF;
    IF EXISTS (SELECT 1 FROM riziv_stage GROUP BY cnk, chapter_code, list_number HAVING count(*) > 1) THEN
        RAISE EXCEPTION 'RIZIV staging contains duplicate CNK/chapter/list identities';
    END IF;
    IF EXISTS (SELECT 1 FROM riziv_stage WHERE chapter_code NOT IN ('I','II','IV','V')) THEN
        RAISE EXCEPTION 'RIZIV staging contains an unsupported compounding chapter';
    END IF;
END
\$body\$;
SELECT '$SOURCE_VERSION' AS validated_source_version,
       count(*) AS reimbursement_entries,
       count(DISTINCT cnk) AS materials_to_load
FROM riziv_stage;
SQL

if ((APPLY)); then
    cat >> "$SQL_FILE" <<SQL
DO \$permissions\$
BEGIN
    IF NOT has_table_privilege(current_user, 'public.xt_compounding_raw_materials', 'SELECT')
       OR NOT has_table_privilege(current_user, 'public.xt_compounding_raw_materials', 'INSERT')
       OR NOT has_table_privilege(current_user, 'public.xt_compounding_raw_materials', 'UPDATE')
       OR NOT has_table_privilege(current_user, 'public.xt_compounding_raw_material_sources', 'SELECT')
       OR NOT has_table_privilege(current_user, 'public.xt_compounding_raw_material_sources', 'INSERT')
       OR NOT has_table_privilege(current_user, 'public.xt_compounding_raw_material_sources', 'UPDATE')
       OR NOT has_table_privilege(current_user, 'public.xt_compounding_reimbursement_codes', 'SELECT')
       OR NOT has_table_privilege(current_user, 'public.xt_compounding_reimbursement_codes', 'INSERT')
       OR NOT has_table_privilege(current_user, 'public.xt_compounding_reimbursement_codes', 'UPDATE') THEN
        RAISE EXCEPTION 'The import role needs SELECT, INSERT and UPDATE on all three xt_compounding tables';
    END IF;
    IF NOT has_sequence_privilege(current_user, 'public.xt_compounding_raw_materials_id_seq', 'USAGE')
       OR NOT has_sequence_privilege(current_user, 'public.xt_compounding_raw_material_sources_id_seq', 'USAGE')
       OR NOT has_sequence_privilege(current_user, 'public.xt_compounding_reimbursement_codes_id_seq', 'USAGE') THEN
        RAISE EXCEPTION 'The import role needs USAGE on the identity sequences for all three xt_compounding tables';
    END IF;
END
\$permissions\$;
CREATE TEMP TABLE riziv_material_map (cnk VARCHAR(7) PRIMARY KEY, raw_material_id BIGINT NOT NULL) ON COMMIT DROP;
INSERT INTO riziv_material_map (cnk, raw_material_id)
SELECT DISTINCT ON (s.cnk) s.cnk, src.raw_material_id
FROM (SELECT DISTINCT cnk FROM riziv_stage) s
JOIN public.xt_compounding_raw_material_sources src
  ON src.source_system = 'RIZIV' AND src.source_record_type = 'RIZIV_CNK' AND src.external_id = s.cnk
ORDER BY s.cnk, src.imported_at DESC, src.id DESC;

WITH new_materials AS (
    SELECT DISTINCT ON (cnk) cnk, name_fr, name_nl, synonyms_fr_json, synonyms_nl_json
    FROM riziv_stage
    ORDER BY cnk, chapter_code, list_number
), inserted AS (
    INSERT INTO public.xt_compounding_raw_materials
        (preferred_name_fr, preferred_name_nl, synonyms_fr, synonyms_nl, source_payload_json)
    SELECT name_fr, name_nl,
           ARRAY(SELECT jsonb_array_elements_text(synonyms_fr_json)),
           ARRAY(SELECT jsonb_array_elements_text(synonyms_nl_json)),
           jsonb_build_object('initial_source', 'RIZIV', 'cnk', cnk)
    FROM new_materials nm
    WHERE NOT EXISTS (SELECT 1 FROM riziv_material_map m WHERE m.cnk = nm.cnk)
    RETURNING id, source_payload_json->>'cnk' AS cnk
)
INSERT INTO riziv_material_map (cnk, raw_material_id)
SELECT cnk, id FROM inserted;

UPDATE public.xt_compounding_raw_material_sources
SET valid_to = ('$SOURCE_VERSION'::date - 1), imported_at = now()
WHERE source_system = 'RIZIV' AND source_record_type = 'RIZIV_CNK'
  AND valid_to IS NULL AND valid_from < '$SOURCE_VERSION'::date;
INSERT INTO public.xt_compounding_raw_material_sources
    (raw_material_id, source_system, source_record_type, external_id, source_version,
     source_file, source_status, valid_from, source_payload_json)
SELECT m.raw_material_id, 'RIZIV', 'RIZIV_CNK', m.cnk, '$SOURCE_VERSION',
       '$SOURCE_FILE', 'CURRENT', '$SOURCE_VERSION'::date,
       jsonb_build_object('cnk', m.cnk, 'source_version', '$SOURCE_VERSION')
FROM riziv_material_map m
ON CONFLICT (source_system, source_record_type, external_id, source_version)
DO UPDATE SET raw_material_id = EXCLUDED.raw_material_id, source_file = EXCLUDED.source_file,
              source_status = EXCLUDED.source_status, valid_from = EXCLUDED.valid_from,
              valid_to = NULL, source_payload_json = EXCLUDED.source_payload_json,
              imported_at = now();

UPDATE public.xt_compounding_reimbursement_codes
SET valid_to = ('$SOURCE_VERSION'::date - 1), imported_at = now()
WHERE source_file LIKE 'riziv_magistral_reference_%'
  AND valid_to IS NULL AND valid_from < '$SOURCE_VERSION'::date;
INSERT INTO public.xt_compounding_reimbursement_codes
    (raw_material_id, chapter_code, reimbursement_code, list_number,
     description_nl, description_fr, conditions_nl, conditions_fr,
     valid_from, source_version, source_file, source_payload_json)
SELECT m.raw_material_id, s.chapter_code, s.cnk, s.list_number,
       s.name_nl, s.name_fr,
       '', '',
       s.valid_from, s.source_version, '$SOURCE_FILE', s.source_payload_json
FROM riziv_stage s
JOIN riziv_material_map m USING (cnk)
ON CONFLICT (raw_material_id, chapter_code, reimbursement_code, list_number, valid_from)
DO UPDATE SET description_nl = EXCLUDED.description_nl,
              description_fr = EXCLUDED.description_fr,
              conditions_nl = EXCLUDED.conditions_nl,
              conditions_fr = EXCLUDED.conditions_fr,
              valid_to = NULL, source_version = EXCLUDED.source_version,
              source_file = EXCLUDED.source_file,
              source_payload_json = EXCLUDED.source_payload_json,
              imported_at = now();
COMMIT;
SELECT '$SOURCE_VERSION' AS published_source_version,
       (SELECT count(DISTINCT external_id) FROM public.xt_compounding_raw_material_sources
        WHERE source_system='RIZIV' AND source_record_type='RIZIV_CNK' AND source_version='$SOURCE_VERSION') AS linked_materials,
       (SELECT count(*) FROM public.xt_compounding_reimbursement_codes
        WHERE source_version='$SOURCE_VERSION' AND source_file='$SOURCE_FILE') AS published_entries;
SQL
    "$PSQL_BIN" -X --no-password --set=ON_ERROR_STOP=1 --file="$SQL_FILE"
    printf 'RIZIV snapshot published successfully.\n'
else
    printf 'ROLLBACK;\n' >> "$SQL_FILE"
    "$PSQL_BIN" -X --no-password --set=ON_ERROR_STOP=1 --file="$SQL_FILE"
    printf 'Check-only run complete: no permanent table was changed.\n'
fi

printf 'Run log: %s\n' "$LOG_FILE"
