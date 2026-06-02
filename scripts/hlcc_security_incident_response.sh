#!/usr/bin/env bash
set -euo pipefail

# HLCC security incident response (stop-bleed + deep audit)
# Usage:
#   bash hlcc_security_incident_response.sh --wp-path /www/wwwroot/x_handlock_xyz --allow-root
# Optional:
#   --skip-plugin all-in-one-wp-migration

WP_PATH="/www/wwwroot/x_handlock_xyz"
WP_ALLOW_ROOT=0
SKIP_PLUGIN=""

while [[ $# -gt 0 ]]; do
  case "$1" in
    --wp-path)
      WP_PATH="$2"
      shift 2
      ;;
    --allow-root)
      WP_ALLOW_ROOT=1
      shift
      ;;
    --skip-plugin)
      SKIP_PLUGIN="$2"
      shift 2
      ;;
    *)
      echo "Unknown arg: $1"
      exit 1
      ;;
  esac
done

if [[ ! -d "$WP_PATH" ]]; then
  echo "WP path not found: $WP_PATH"
  exit 1
fi

TS="$(date +%Y%m%d-%H%M%S)"
OUT_DIR="${WP_PATH}/wp-content/hlcc-incident-${TS}"
mkdir -p "$OUT_DIR"

echo "[INFO] incident workspace: $OUT_DIR"

WP_CMD=(wp --path="$WP_PATH")
if [[ $WP_ALLOW_ROOT -eq 1 ]]; then
  WP_CMD+=(--allow-root)
fi
if [[ -n "$SKIP_PLUGIN" ]]; then
  WP_CMD+=(--skip-plugins="$SKIP_PLUGIN")
fi

run_wp() {
  "${WP_CMD[@]}" "$@"
}

# 1) Evidence snapshot
if [[ -f "${WP_PATH}/wp-content/debug.log" ]]; then
  cp -f "${WP_PATH}/wp-content/debug.log" "$OUT_DIR/debug.log.snapshot"
fi

{
  echo "===== date ====="
  date -Iseconds
  echo "===== uname ====="
  uname -a || true
  echo "===== whoami ====="
  whoami || true
} > "$OUT_DIR/system.txt"

{
  echo "===== active plugins ====="
  run_wp plugin list --fields=name,status,version,update || true
  echo
  echo "===== administrators ====="
  run_wp user list --role=administrator --fields=ID,user_login,user_email,display_name,user_registered || true
} > "$OUT_DIR/wp_users_plugins.txt"

# Plugin inventory + hashes
(
  cd "$WP_PATH/wp-content"
  find plugins -maxdepth 2 -type f -print0 | sort -z | xargs -0 sha256sum > "$OUT_DIR/plugins.sha256" || true
  find mu-plugins -maxdepth 2 -type f -print0 2>/dev/null | sort -z | xargs -0 sha256sum > "$OUT_DIR/mu-plugins.sha256" || true
  find . -maxdepth 2 -type f \( -name advanced-cache.php -o -name object-cache.php -o -name db.php \) -print0 | sort -z | xargs -0 sha256sum > "$OUT_DIR/dropins.sha256" || true
)

# 2) Quarantine suspicious plugin path
SUSPECT_DIR="${WP_PATH}/wp-content/plugins/oxdbskrbld"
if [[ -e "$SUSPECT_DIR" ]]; then
  QUAR="${SUSPECT_DIR}.quarantine.${TS}"
  mv "$SUSPECT_DIR" "$QUAR"
  echo "[ACTION] quarantined: $SUSPECT_DIR -> $QUAR" | tee -a "$OUT_DIR/actions.log"
else
  echo "[INFO] suspect dir not found: $SUSPECT_DIR" | tee -a "$OUT_DIR/actions.log"
fi

# 3) Residual reference checks
{
  echo "===== references: oxdbskrbld ====="
  grep -RIn --binary-files=without-match "oxdbskrbld" "$WP_PATH/wp-content" || true
  echo
  echo "===== references: dex.php ====="
  grep -RIn --binary-files=without-match "dex.php" "$WP_PATH/wp-content" || true
} > "$OUT_DIR/suspect_refs.txt"

# 4) Deep suspicious code scan
if command -v rg >/dev/null 2>&1; then
  rg -n --hidden -S \
    "base64_decode\\(|eval\\(|assert\\(|gzinflate\\(|str_rot13\\(|shell_exec\\(|passthru\\(|system\\(|preg_replace\\s*\\(.*/e" \
    "$WP_PATH/wp-content" > "$OUT_DIR/suspicious_functions.txt" || true
else
  grep -RInE "base64_decode\\(|eval\\(|assert\\(|gzinflate\\(|str_rot13\\(|shell_exec\\(|passthru\\(|system\\(|preg_replace[^(]*\\(.*/e" \
    "$WP_PATH/wp-content" > "$OUT_DIR/suspicious_functions.txt" || true
fi

# 5) WP core integrity
run_wp core verify-checksums > "$OUT_DIR/wp_core_verify.txt" 2>&1 || true

# 6) DB audit: options + cron + admins
PREFIX="$(run_wp db prefix | tr -d '\r\n')"
{
  echo "===== active_plugins option ====="
  run_wp db query "SELECT option_name, LENGTH(option_value) AS len FROM ${PREFIX}options WHERE option_name='active_plugins';" || true
  echo
  echo "===== suspicious autoload options ====="
  run_wp db query "SELECT option_name, LENGTH(option_value) AS len FROM ${PREFIX}options WHERE autoload='yes' ORDER BY len DESC LIMIT 30;" || true
  echo
  echo "===== cron option size ====="
  run_wp db query "SELECT option_name, LENGTH(option_value) AS len FROM ${PREFIX}options WHERE option_name='cron';" || true
} > "$OUT_DIR/db_audit.txt"

run_wp cron event list > "$OUT_DIR/cron_events.txt" 2>&1 || true

# 7) File change timeline (last 7 days)
find "$WP_PATH/wp-content" -type f -mtime -7 -printf "%TY-%Tm-%Td %TH:%TM:%TS %p\n" | sort > "$OUT_DIR/recent_files_7d.txt" || true

# 8) Summary
{
  echo "incident_dir=$OUT_DIR"
  echo "wp_path=$WP_PATH"
  echo "completed_at=$(date -Iseconds)"
} > "$OUT_DIR/summary.txt"

echo "[DONE] Incident response artifacts written to: $OUT_DIR"
