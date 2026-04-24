#!/usr/bin/env bash
# check-privacy-leaks.sh — IPADP L2 Privacy Validation
#
# Scans all tracked repository files for patterns that should not appear
# in a public repository (private URLs, internal IPs, credentials).
#
# Patterns detected:
#   - gitlab.satware.com URLs
#   - git.satware.ai URLs
#   - Private IP ranges (10.x, 172.16-31.x, 192.168.x)
#   - Hardcoded API keys / tokens (generic patterns)
#
# Whitelist: .privacy-whitelist (one pattern per line, grep -E extended regex)
#
# Usage: ./check-privacy-leaks.sh [REPO_ROOT]
# Exit: 0 = clean, 1 = violations found
#
# Part of IPADP Phase 2: L2 Privacy Validation
# See: https://github.com/satwareAG/spec-kit/issues/9
set -euo pipefail

REPO_ROOT="${1:-.}"
WHITELIST="${REPO_ROOT}/.privacy-whitelist"
VIOLATIONS=0
REPORT=""

# --- Color helpers (nocolor fallback for CI) ---
if [[ -t 1 ]]; then
  RED='\033[0;31m'
  GREEN='\033[0;32m'
  YELLOW='\033[0;33m'
  NC='\033[0m'
else
  RED=''
  GREEN=''
  YELLOW=''
  NC=''
fi

info()  { echo -e "${GREEN}[INFO]${NC}  $*"; }
warn()  { echo -e "${YELLOW}[WARN]${NC}  $*"; }
fail()  { echo -e "${RED}[FAIL]${NC}  $*"; }

# --- Privacy patterns (extended regex) ---
# Each entry: PATTERN_REGEX<tab>CATEGORY_DESCRIPTION
PATTERNS=(
  $'gitlab\\.satware\\.com\tPrivate GitLab URL (gitlab.satware.com)'
  $'git\\.satware\\.ai\tPrivate Gitea URL (git.satware.ai)'
  $'10\\.[0-9]+\\.[0-9]+\\.[0-9]+\tPrivate IP (10.x.x.x)'
  $'172\\.(1[6-9]|2[0-9]|3[0-1])\\.[0-9]+\\.[0-9]+\tPrivate IP (172.16-31.x.x)'
  $'192\\.168\\.[0-9]+\\.[0-9]+\tPrivate IP (192.168.x.x)'
  $'(api[_-]?key|apikey|secret|token|password|passwd)\\s*[:=]\\s*['\''"][^'\''"]{8,}\tPotential hardcoded credential'
)

# --- Load whitelist exclusions ---
WHITELIST_ARGS=()
if [[ -f "${WHITELIST}" ]]; then
  info "Loaded whitelist: ${WHITELIST}"
  while IFS= read -r line; do
    [[ -z "${line}" || "${line}" =~ ^# ]] && continue
    WHITELIST_ARGS+=(--not --match="${line}")
  done < "${WHITELIST}"
fi

# --- Get tracked files ---
if git -C "${REPO_ROOT}" rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  mapfile -d '' TRACKED_FILES < <(git -C "${REPO_ROOT}" ls-files -z 2>/dev/null)
else
  warn "Not a git repo, scanning all files in ${REPO_ROOT}"
  mapfile -t TRACKED_FILES < <(find "${REPO_ROOT}" -type f -not -path '*/.git/*' 2>/dev/null)
fi

if [[ ${#TRACKED_FILES[@]} -eq 0 ]]; then
  info "No files to scan. Repository is clean."
  exit 0
fi

info "Scanning ${#TRACKED_FILES[@]} tracked files..."

# --- Check each pattern ---
for entry in "${PATTERNS[@]}"; do
  IFS=$'\t' read -r pattern category <<< "${entry}"

  # grep extended regex, fixed strings for file list
  while IFS= read -r match; do
    [[ -z "${match}" ]] && continue

    # Check whitelist — skip if line matches any whitelist pattern
    if [[ -f "${WHITELIST}" ]]; then
      skipped=false
      while IFS= read -r wl_line; do
        [[ -z "${wl_line}" || "${wl_line}" =~ ^# ]] && continue
        if echo "${match}" | grep -qE "${wl_line}" 2>/dev/null; then
          skipped=true
          break
        fi
      done < "${WHITELIST}"
      if [[ "${skipped}" == true ]]; then
        continue
      fi
    fi

    VIOLATIONS=$((VIOLATIONS + 1))
    REPORT+="[${category}] ${match}"$'\n'
  done < <(git -C "${REPO_ROOT}" grep -nE "${pattern}" -- ':!*.svg' ':!*.png' ':!*.jpg' ':!*.gif' ':!*.webp' 2>/dev/null || true)
done

# --- Report ---
if [[ ${VIOLATIONS} -gt 0 ]]; then
  fail "Found ${VIOLATIONS} privacy violation(s):"
  echo ""
  echo "${REPORT}" | sort -u
  echo ""
  echo "To suppress false positives, add patterns to .privacy-whitelist"
  exit 1
else
  info "No privacy violations detected. Repository is clean."
  exit 0
fi