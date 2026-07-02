#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SOURCE_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
OUT_DIR="${SOURCE_DIR}/dist"
VERSION=""
UPDATE_JSON=0

usage() {
    cat <<'EOF'
Usage: scripts/package-module.sh [options]

Create a FreePBX-installable sccp_manager archive from the local source tree.

Options:
  -v VERSION   Set module version (updates module.xml and sccpManagerUpdater.json)
  -o DIR       Output directory (default: dist/)
  -u           Refresh sccpManagerUpdater.json md5sum/packaged fields
  -h           Show this help

Examples:
  scripts/package-module.sh
  scripts/package-module.sh -v 14.6.0.1 -u
  ./scripts/install-local.sh
EOF
}

while getopts ":v:o:uh" opt; do
    case "${opt}" in
        v) VERSION="${OPTARG}" ;;
        o) OUT_DIR="${OPTARG}" ;;
        u) UPDATE_JSON=1 ;;
        h)
            usage
            exit 0
            ;;
        *)
            usage
            exit 1
            ;;
    esac
done

if [[ -z "${VERSION}" ]]; then
    VERSION="$(sed -n 's:.*<version>\([^<]*\)</version>.*:\1:p' "${SOURCE_DIR}/module.xml" | head -1)"
fi

if [[ -z "${VERSION}" ]]; then
    echo "Unable to determine module version from module.xml" >&2
    exit 1
fi

PKG_DIR_NAME="sccp_manager"
ARCHIVE_NAME="sccp_manager-${VERSION}"
STAGING_ROOT="${OUT_DIR}/.staging"
STAGING_DIR="${STAGING_ROOT}/${PKG_DIR_NAME}"
ARCHIVE="${OUT_DIR}/${ARCHIVE_NAME}.tgz"

rm -rf "${STAGING_DIR}"
mkdir -p "${STAGING_DIR}" "${OUT_DIR}"

if [[ "${UPDATE_JSON}" -eq 1 || "${VERSION}" != "$(sed -n 's:.*<version>\([^<]*\)</version>.*:\1:p' "${SOURCE_DIR}/module.xml" | head -1)" ]]; then
    sed -i "s:<version>[^<]*</version>:<version>${VERSION}</version>:" "${SOURCE_DIR}/module.xml"
fi

rsync -a \
    --delete \
    --exclude='.git/' \
    --exclude='dist/' \
    --exclude='*.tgz' \
    --exclude='*~' \
    --exclude='.staging/' \
    "${SOURCE_DIR}/" "${STAGING_DIR}/"

tar -czf "${ARCHIVE}" -C "${STAGING_ROOT}" "${PKG_DIR_NAME}"
MD5SUM="$(md5sum "${ARCHIVE}" | awk '{print $1}')"
PACKAGED="$(date +%s)"

if [[ "${UPDATE_JSON}" -eq 1 ]]; then
    python3 - "${SOURCE_DIR}/sccpManagerUpdater.json" "${VERSION}" "${ARCHIVE}" "${MD5SUM}" "${PACKAGED}" <<'PY'
import json
import sys

path, version, archive, md5sum, packaged = sys.argv[1:6]
with open(path, "r", encoding="utf-8") as handle:
    data = json.load(handle)
data["version"] = version
data["location"] = "local-build"
data["md5sum"] = ""
data["packaged"] = packaged
with open(path, "w", encoding="utf-8") as handle:
    json.dump(data, handle, indent=4, ensure_ascii=False)
    handle.write("\n")
PY
fi

cat <<EOF
Created installable module archive:
  ${ARCHIVE}
Version: ${VERSION}
MD5:     ${MD5SUM}

Install (do NOT use "fwconsole ma install <path>.tgz"):
  ${SOURCE_DIR}/scripts/install-local.sh ${ARCHIVE}

Or manually:
  tar -xzf ${ARCHIVE} -C /tmp
  cp -a /tmp/sccp_manager /var/www/html/admin/modules/
  fwconsole ma install sccp_manager -f
  fwconsole reload
EOF
