#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SOURCE_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
ARCHIVE="${1:-}"

detect_ampwebroot() {
    local conf
    for conf in /etc/amportal.conf /etc/freepbx.conf; do
        if [[ -f "${conf}" ]]; then
            local value
            value="$(awk -F= '/^AMPWEBROOT=/{gsub(/["'\'']/, "", $2); print $2; exit}' "${conf}")"
            if [[ -n "${value}" ]]; then
                echo "${value}"
                return 0
            fi
        fi
    done
    if [[ -d /var/www/html/admin ]]; then
        echo /var/www/html
        return 0
    fi
    return 1
}

resolve_path() {
    local path="$1"
    if [[ -L "${path}" ]]; then
        readlink -f "${path}"
    elif [[ -d "${path}" ]]; then
        cd "${path}" && pwd
    else
        echo "${path}"
    fi
}

if [[ -z "${ARCHIVE}" ]]; then
    ARCHIVE="$(ls -1t "${SOURCE_DIR}/dist/"sccp_manager-*.tgz 2>/dev/null | head -1 || true)"
fi

AMPWEBROOT="$(detect_ampwebroot || true)"
if [[ -z "${AMPWEBROOT}" ]]; then
    echo "AMPWEBROOT could not be detected." >&2
    exit 1
fi

TARGET="${AMPWEBROOT}/admin/modules/sccp_manager"
SOURCE_RESOLVED="$(resolve_path "${SOURCE_DIR}")"
TARGET_RESOLVED="$(resolve_path "${TARGET}" 2>/dev/null || true)"

echo "==> Source: ${SOURCE_DIR}"
echo "==> Target: ${TARGET}"

if [[ -n "${TARGET_RESOLVED}" && "${TARGET_RESOLVED}" == "${SOURCE_RESOLVED}" ]]; then
    echo "==> Module is already linked to this source tree; skipping file copy"
elif [[ -n "${ARCHIVE}" && -f "${ARCHIVE}" ]]; then
    TMP_DIR="$(mktemp -d)"
    trap 'rm -rf "${TMP_DIR}"' EXIT

    echo "==> Extracting ${ARCHIVE}"
    tar -xzf "${ARCHIVE}" -C "${TMP_DIR}"
    EXTRACTED="$(find "${TMP_DIR}" -mindepth 1 -maxdepth 1 -type d | head -1)"
    if [[ -z "${EXTRACTED}" || "$(basename "${EXTRACTED}")" != "sccp_manager" ]]; then
        echo "Archive must contain top-level directory sccp_manager/" >&2
        exit 1
    fi

    echo "==> Deploying to ${TARGET}"
    mkdir -p "${AMPWEBROOT}/admin/modules"
    if [[ -e "${TARGET}" ]]; then
        BACKUP_DIR="${SOURCE_DIR}/backups"
        mkdir -p "${BACKUP_DIR}"
        BACKUP="${BACKUP_DIR}/sccp_manager.bak.$(date +%Y%m%d%H%M%S)"
        echo "==> Backing up existing module to ${BACKUP}"
        mv "${TARGET}" "${BACKUP}"
    fi
    cp -a "${EXTRACTED}" "${TARGET}"
else
    echo "No archive provided and target is not linked to source." >&2
    echo "Build one first: ${SOURCE_DIR}/scripts/package-module.sh -u" >&2
    exit 1
fi

if command -v fwconsole >/dev/null 2>&1; then
    echo "==> Running module install"
    fwconsole ma install sccp_manager -f
    fwconsole reload
    echo "==> Done."
else
    echo "fwconsole not found. Run: fwconsole ma install sccp_manager -f"
fi
