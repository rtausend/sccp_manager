# GitHub-Fork: sccp_manager mit eigenen Änderungen

Diese Anleitung beschreibt, wie du vom Original [chan-sccp/sccp_manager](https://github.com/chan-sccp/sccp_manager) einen **eigenen Fork** anlegst, deine lokalen Änderungen (Stand **14.6.0.1**) hochlädst und vorher auf persönliche Daten prüfst.

---

## 1. Vorbereitung: Repository bereinigen

### Sensibilitäts-Scan ausführen

```bash
cd /usr/src/sccp_manager
chmod +x scripts/scan-for-sensitive-data.sh
./scripts/scan-for-sensitive-data.sh
```

Der Scan prüft u.a.:

- verschachtelte Duplikat-Ordner (`sccp_manager/sccp_manager/`)
- lokale Pfade (`/usr/src/...`, `file:///...`)
- MAC-Adressen in Dokumentation
- E-Mail-Adressen, private IPs
- Private Keys / harte Passwörter

### Bekannte Bereinigungsschritte

| Problem | Aktion |
|---------|--------|
| Ordner `sccp_manager/sccp_manager/` | Versehentliches Duplikat — **löschen** vor dem Push |
| Ordner `backups/`, `dist/` | Nicht committen (steht in `.gitignore`) |
| Echte MAC in Doku | Durch Platzhalter ersetzen (`SEP001122334455`) |
| `sccpManagerUpdater.json` mit `file:///usr/src/...` | Auf `local-build` setzen (bereits angepasst) |

---

## 2. GitHub: Fork anlegen

### Variante A — Fork über GitHub-Web (empfohlen)

1. Auf https://github.com/chan-sccp/sccp_manager gehen
2. **Fork** klicken → in dein Konto (`DEIN-USER/sccp_manager`)
3. Lokal Remote umstellen:

```bash
cd /usr/src/sccp_manager
git remote rename origin upstream
git remote add origin https://github.com/DEIN-USER/sccp_manager.git
git fetch upstream
```

### Variante B — Neues leeres Repo

1. Auf GitHub: **New repository** → `sccp_manager` (ohne README)
2. Lokal:

```bash
cd /usr/src/sccp_manager
git remote rename origin upstream   # falls noch chan-sccp
git remote add origin https://github.com/DEIN-USER/sccp_manager.git
```

---

## 3. Branch & Commit-Struktur

Empfohlen: eigener Branch für deinen Fork, nicht direkt `develop` vom Upstream überschreiben.

```bash
git checkout -b fork/14.6.0.1-custom
```

### Sinnvolle Commit-Aufteilung (optional, aber übersichtlich)

1. `docs: add change overview and implementation plans`
2. `feat: firmware per phone assignment and bulk update`
3. `feat: SEP device swap/replace wizard`
4. `fix: PHP 8.2 / Asterisk 22 compatibility`
5. `build: local packaging scripts and module path helper`
6. `fix: installer array_diff_key and module.xml cos field lengths`

Oder **ein Squash-Commit** für den ersten Push:

```bash
git add -A
git status   # nochmal prüfen!
git commit -m "$(cat <<'EOF'
Fork sccp_manager 14.6.0.1 with local enhancements

Based on chan-sccp/sccp_manager develop @ 5752d9f.

Features:
- Firmware per phone (imageversion, bulk assign, lazy grid status)
- SEP swap/replace wizard
- Named groups, SCCP line editor, ehookEnable per device
- PHP 8.2 / Asterisk 22 fixes
- Local packaging (scripts/package-module.sh)
- Module-relative paths (sccp_manager_path.php)

See documentation/Aenderungsuebersicht_2026-06-30.md
EOF
)"
```

---

## 4. Push zu GitHub

```bash
git push -u origin fork/14.6.0.1-custom
```

Optional als Default-Branch auf GitHub setzen oder Pull Request gegen deinen eigenen `main` erstellen.

---

## 5. Dokumentation im Repo

| Datei | Inhalt |
|-------|--------|
| [Aenderungsuebersicht_2026-06-30.md](Aenderungsuebersicht_2026-06-30.md) | Gesamtübersicht aller Änderungen |
| [Implementierungsplan_Firmware_pro_Telefon.md](Implementierungsplan_Firmware_pro_Telefon.md) | Firmware-Feature |
| [Implementierungsplan_SEP_Tausch.md](Implementierungsplan_SEP_Tausch.md) | SEP-Tausch |
| [Implementierungsplan_ehookEnable_pro_Telefon.md](Implementierungsplan_ehookEnable_pro_Telefon.md) | ehookEnable |
| [../README.md](../README.md) | Installation + lokales Packaging |
| [../KONKRETE_CODEAENDERUNGEN_PHP82_AST22.md](../KONKRETE_CODEAENDERUNGEN_PHP82_AST22.md) | PHP/Asterisk-Fixes |

### README-Ergänzung für deinen Fork

Oben in `README.md` einen Hinweis einfügen:

```markdown
> **Fork-Hinweis:** Dieses Repository basiert auf [chan-sccp/sccp_manager](https://github.com/chan-sccp/sccp_manager).
> Version 14.6.0.1 enthält lokale Erweiterungen (Firmware pro Telefon, SEP-Tausch, PHP 8.2).
> Upstream-Updates: `git fetch upstream && git merge upstream/develop`
```

---

## 6. Upstream-Sync (später)

```bash
git fetch upstream
git checkout fork/14.6.0.1-custom
git merge upstream/develop
# Konflikte lösen, testen, committen
git push origin fork/14.6.0.1-custom
```

---

## 7. Was NICHT ins Repo gehört

- `dist/*.tgz` — Build-Artefakte
- `backups/` — alte Modul-Kopien
- `sccp_manager/sccp_manager/` — Duplikat-Ordner
- FreePBX-DB-Dumps, `sccp.conf` mit echten IPs
- TFTP-Dateien (`SEP*.cnf.xml`) aus `/tftpboot`
- API-Keys, AMI-Passwörter, `.env`

---

## 8. Checkliste vor dem ersten Push

- [ ] `./scripts/scan-for-sensitive-data.sh` ohne Issues
- [ ] Kein Ordner `sccp_manager/sccp_manager/`
- [ ] Keine echten MAC/Hostnamen in `documentation/`
- [ ] `module.xml` Version = 14.6.0.1
- [ ] `sccpManagerUpdater.json` ohne lokale `file://`-Pfade
- [ ] `git status` — nur gewollte Dateien
- [ ] GitHub-Repo erstellt, `origin` zeigt auf dein Konto

---

## 9. Installation aus deinem Fork

```bash
git clone https://github.com/DEIN-USER/sccp_manager.git
cd sccp_manager
./scripts/package-module.sh -u
./scripts/install-local.sh
```

Oder Symlink für Entwicklung:

```bash
ln -sfn /pfad/zu/sccp_manager /var/www/html/admin/modules/sccp_manager
fwconsole ma install sccp_manager -f
```
