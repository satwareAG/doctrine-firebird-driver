#!/usr/bin/env bash
# morning-health-check.sh — IPADP L3 Ecosystem Health Check
#
# Standards: https://git.satware.ai/satware.ai/wiki/specs/rfc-interproject-agentic-development.md
# Part of Spec 006: Morning Start Protocol
set -euo pipefail

REPO_ROOT="${REPO_ROOT:-"$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"}"
METADATA="${REPO_ROOT}/specs/metadata.json"

# --- Color helpers ---
if [[ -t 1 ]]; then
  RED='\033[0;31m'
  GREEN='\033[0;32m'
  YELLOW='\033[0;33m'
  BLUE='\033[0;34m'
  NC='\033[0m'
else
  RED='' GREEN='' YELLOW='' BLUE='' NC=''
fi

info()  { echo -e "${GREEN}[INFO]${NC}  $*"; }
warn()  { echo -e "${YELLOW}[WARN]${NC}  $*"; }
fail()  { echo -e "${RED}[FAIL]${NC}  $*"; }
section() { echo -e "\n${BLUE}=== $* ===${NC}"; }

# --- Base Directory Enforcement ---
ensure_base_dirs() {
  for dir in "$HOME/external" "$HOME/internal"; do
    if [[ ! -d "$dir" ]]; then
      info "Creating directory: $dir"
      mkdir -p "$dir"
    fi
  done
}

section "IPADP Morning Health Check"
echo "Date: $(date '+%Y-%m-%d %H:%M')"
echo "Root: ${REPO_ROOT}"

# Ensure IPADP standard directory layout
ensure_base_dirs

# --- 1. Forge Connectivity ---
section "Forge Connectivity"
FORGES=("gh:GitHub" "glab:GitLab" "tea:Gitea")
for forge_entry in "${FORGES[@]}"; do
  cli="${forge_entry%%:*}"
  name="${forge_entry#*:}"
  if command -v "$cli" >/dev/null 2>&1; then
    if "$cli" auth status >/dev/null 2>&1 || [[ "$cli" == "tea" && "$(tea login ls >/dev/null 2>&1; echo $?)" == 0 ]]; then
      info "✅ $name ($cli) authenticated"
    else
      warn "⚠️ $name ($cli) NOT authenticated"
    fi
  else
    warn "❌ $name ($cli) NOT installed"
  fi
done

# --- 2. Local Health ---
section "Local Health"
if [[ -d "${REPO_ROOT}/.git" ]]; then
  info "Checking git status..."
  git -C "${REPO_ROOT}" fetch origin --quiet || warn "Failed to fetch origin"
  dirty="$(git -C "${REPO_ROOT}" status --short)"
  if [[ -n "$dirty" ]]; then
    warn "⚠️ Uncommitted changes detected:"
    echo "$dirty"
  else
    info "✅ Working directory clean"
  fi
else
  warn "Not a git repository"
fi

if command -v docker >/dev/null 2>&1; then
  info "Checking docker services..."
  if docker compose ps >/dev/null 2>&1; then
    docker compose ps --format "table {{.Service}}\t{{.Status}}"
  else
    info "No docker compose services defined/running"
  fi
fi

# --- 3. IPADP Spec Currency ---
section "IPADP Spec Currency"
if [[ -f "$METADATA" ]]; then
  info "Parsing metadata: $METADATA"
  
  # Extract upstream dependencies and check local paths
  mapfile -t dependencies < <(jq -r '.upstream | to_entries[] | "\(.key)|\(.value.local_path)"' "$METADATA" 2>/dev/null || true)
  
  if [[ ${#dependencies[@]} -gt 0 ]]; then
    for dep in "${dependencies[@]}"; do
      name="${dep%%|*}"
      path="${dep#*|}"
      
      # Expand ~ if present
      eval_path="${path//\~/$HOME}"
      
      if [[ -d "$eval_path" ]]; then
        info "✅ Upstream $name found at $path"
        # Check if upstream spec is newer (placeholder for version check)
        # In a full implementation, we would compare hashes or version fields
      else
        warn "❌ Upstream $name MISSING at $path"
        warn "   Action: cd $(dirname "$eval_path") && [forge_cli] clone ..."
      fi
    done
  else
    info "No upstream dependencies defined in metadata.json"
  fi
else
  warn "metadata.json missing at $METADATA"
fi

# --- 4. Privacy Check (Phase 2) ---
section "Privacy Validation"
PRIVACY_SCRIPT="${REPO_ROOT}/scripts/check-privacy-leaks.sh"
if [[ -f "$PRIVACY_SCRIPT" ]]; then
  if bash "$PRIVACY_SCRIPT" "${REPO_ROOT}"; then
    info "✅ Privacy validation passed"
  else
    warn "❌ Privacy violations detected!"
  fi
else
  info "Privacy check script not found, skipping."
fi

section "Morning Start Complete"
echo "Ready for deep work."
