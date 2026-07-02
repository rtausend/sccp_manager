#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${ROOT}"

RED='\033[0;31m'
YEL='\033[1;33m'
GRN='\033[0;32m'
NC='\033[0m'

issues=0
warnings=0

section() {
    echo
    echo "=== $1 ==="
}

hit_issue() {
    echo -e "${RED}[ISSUE]${NC} $1"
    issues=$((issues + 1))
}

hit_warn() {
    echo -e "${YEL}[WARN]${NC} $1"
    warnings=$((warnings + 1))
}

hit_ok() {
    echo -e "${GRN}[OK]${NC} $1"
}

section "Struktur"
if [[ -d "${ROOT}/sccp_manager" ]]; then
    hit_issue "Verschachtelter Ordner ${ROOT}/sccp_manager/ gefunden (Duplikat). Vor GitHub-Upload löschen: rm -rf sccp_manager/"
fi
if [[ -d "${ROOT}/backups" ]]; then
    hit_warn "Ordner backups/ vorhanden (sollte nicht ins Repo). In .gitignore: /backups/"
fi
if [[ -d "${ROOT}/dist" ]]; then
    hit_warn "Ordner dist/ vorhanden (Build-Artefakte nicht committen)."
fi

section "Lokale Pfade / Hosts"
while IFS= read -r line; do
    hit_warn "${line}"
done < <(grep -RIn --exclude-dir=.git --exclude-dir=dist --exclude-dir=backups --exclude-dir=sccp_manager \
    -E '/usr/src/sccp_manager|file:///usr/src|/var/www/html/admin/modules/sccp_manager\.bak' . 2>/dev/null || true)

section "MAC-Adressen (SEP/ATA/VG)"
while IFS= read -r line; do
    hit_warn "Prüfen ob echte Geräte-MAC: ${line}"
done < <(grep -RIn --exclude-dir=.git --exclude-dir=dist --exclude-dir=backups --exclude-dir=sccp_manager \
    -E 'SEP[0-9A-Fa-f]{12}|ATA[0-9A-Fa-f]{12}|VG[0-9A-Fa-f]{12}' . 2>/dev/null | grep -v 'SEP001122334455\|SEPAABBCCDDEEFF\|SEPFFEEDDCCBBAA\|SEP0000000000' || true)

section "E-Mail-Adressen"
while IFS= read -r line; do
    hit_warn "${line}"
done < <(grep -RIn --exclude-dir=.git --exclude-dir=dist --exclude-dir=backups --exclude-dir=sccp_manager \
    -E '[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}' . 2>/dev/null | grep -v 'example\.com\|@ext-local\|@from-internal' || true)

section "Private IPs (nicht Standard-Beispiele)"
while IFS= read -r line; do
    hit_warn "${line}"
done < <(grep -RIn --exclude-dir=.git --exclude-dir=dist --exclude-dir=backups --exclude-dir=sccp_manager \
    -E '\b(10\.|172\.(1[6-9]|2[0-9]|3[01])\.|192\.168\.)[0-9]{1,3}\.[0-9]{1,3}\b' . 2>/dev/null || true)

section "Credentials / Secrets"
while IFS= read -r line; do
    hit_issue "${line}"
done < <(grep -RIn --exclude-dir=.git --exclude-dir=dist --exclude-dir=backups --exclude-dir=sccp_manager \
    -E 'BEGIN (RSA |OPENSSH |EC )?PRIVATE KEY|api[_-]?key\s*=\s*["'"'"'][^"'"'"']+["'"'"']' . 2>/dev/null || true)
while IFS= read -r line; do
    hit_warn "Hardcoded password pattern (prüfen): ${line}"
done < <(grep -RIn --exclude-dir=.git --exclude-dir=dist --exclude-dir=backups --exclude-dir=sccp_manager \
    -E "password\s*=\s*['\"][^'\"]{3,}['\"]" . 2>/dev/null | grep -v '\$pass\|REGEN\|cisco\|1234\|MyPassword\|AUTHORIZED' || true)

section "Git-Metadaten"
if git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
    echo "Remote origin: $(git remote get-url origin 2>/dev/null || echo 'keiner')"
    echo "Branch: $(git branch --show-current 2>/dev/null || echo '?')"
    if git status --porcelain | grep -q '^'; then
        hit_ok "Uncommitted changes vorhanden (normal vor erstem Fork-Push)"
        git status --short | head -20
    else
        hit_ok "Working tree clean"
    fi
else
    hit_warn "Kein Git-Repository initialisiert"
fi

section "Zusammenfassung"
echo "Issues:   ${issues}"
echo "Warnings: ${warnings}"
if [[ ${issues} -gt 0 ]]; then
    echo -e "${RED}Bitte Issues beheben vor dem GitHub-Push.${NC}"
    exit 1
fi
if [[ ${warnings} -gt 0 ]]; then
    echo -e "${YEL}Warnings manuell prüfen.${NC}"
    exit 0
fi
echo -e "${GRN}Keine kritischen Funde.${NC}"
