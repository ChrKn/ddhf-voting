# DDHF MV Abstimmungssystem

Abstimmungstool für Versammlungen des Deutschen Dachverbands für historisches Fechten e.V.
Mitglieder (in der Regel Vereine) stimmen mit einer Stimmenzahl ab, die sich aus ihrer Mitgliederzahl ergibt.

## Stack

- Laravel 13, PHP 8.3+
- Filament (Verwaltung), Livewire (Wahlmaske, Versammlungsleitung)
- SQLite (WAL-Modus)

## Einrichtung

```sh
composer setup          # installiert Abhängigkeiten, legt .env und database/database.sqlite an, migriert
php artisan voting:create-admin
composer dev            # Server, Queue, Logs und Vite
```

Die App braucht weder Queue-Worker noch Cronjob: Alles, auch der Mailversand, läuft direkt im Request.
Auf Shared Hosting in der `.env` `QUEUE_CONNECTION=sync` setzen.

## E-Mail

Mails gehen nur in zwei Fällen raus: Einladung neuer Personen und „Passwort vergessen“ (dieselbe Mail,
Link zum Passwort setzen). Ohne SMTP-Einstellungen landen sie in `storage/logs/laravel.log`
(`MAIL_MAILER=log`).

Produktiv über Google Workspace mit dem Konto `abstimmung@ddhf.de`:

```dotenv
MAIL_MAILER=smtp
MAIL_SCHEME=null
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=abstimmung@ddhf.de
MAIL_PASSWORD="<App-Passwort>"
MAIL_FROM_ADDRESS="abstimmung@ddhf.de"
MAIL_FROM_NAME="DDHF MV Abstimmungssystem"

# Antworten der Empfänger:innen an eine Adresse, die gelesen wird
MAIL_REPLY_TO_ADDRESS="person@ddhf.de"
MAIL_REPLY_TO_NAME="DDHF"

# Unzustellbarkeitsmeldungen (Bounces)
MAIL_RETURN_PATH="person@ddhf.de"
```

- Zugangsdaten wie bei den anderen Systemen, die schon über `abstimmung@ddhf.de` verschicken
  (App-Passwort bzw. SMTP-Relay).
- **Reply-To** lässt Gmail unverändert, Antworten kommen also bei der angegebenen Adresse an.
- **Return-Path:** Gmail ersetzt den Envelope-Absender in der Regel durch das angemeldete Konto.
  Dann landen Bounces trotz `MAIL_RETURN_PATH` bei `abstimmung@ddhf.de`. Prüfen: Testmail über
  „Passwort vergessen?“ an sich selbst schicken, in Gmail „Original anzeigen“ und die Zeile
  `Return-Path:` ansehen.
- Falls dort `abstimmung@ddhf.de` steht, im Postfach `abstimmung@ddhf.de` einen Filter anlegen:
  Suche `from:(mailer-daemon) OR subject:("Delivery Status Notification")`, Aktion „Weiterleiten an“
  `person@ddhf.de`. Die Weiterleitungsadresse muss vorher unter *Einstellungen → Weiterleitung*
  bestätigt werden.
- Leere Werte für `MAIL_REPLY_TO_ADDRESS` / `MAIL_RETURN_PATH` bedeuten: nicht setzen.
- Nach Änderungen an der `.env` bei gecachter Konfiguration `php artisan config:cache` erneut ausführen.

**Wenn eine Mail partout nicht ankommt:** Systemadmin und Verwalter:innen können unter *Personen*
bei Vertreter:innen „Passwort festlegen“ wählen. Die App erzeugt ein vorläufiges Passwort
(z.B. `kmtp-4z9w-rxa3`) und zeigt es einmalig an; bitte telefonisch oder persönlich weitergeben.
Beim ersten Login muss die Person ein eigenes Passwort festlegen. Verwalter:innen sehen die
Personenliste, können aber nichts bearbeiten und nur Vertreter:innen einladen.

## Erstbefüllung

1. **Mitglieder** in der Verwaltung unter *Mitglieder → Importieren* hochladen
   (CSV oder XLSX mit Name und Anzahl Mitglieder). Umbenannte Mitglieder lassen sich
   dort einem vorhandenen Eintrag zuordnen.
2. **Personen** per Konsole anlegen und den Mitgliedern zuordnen:

   ```sh
   php artisan voting:import-people personen.xlsx --dry-run   # nur anzeigen
   php artisan voting:import-people personen.xlsx             # ausführen (mit Rückfrage)
   php artisan voting:import-people personen.xlsx --invite    # zusätzlich Einladungen verschicken
   ```

   Die Datei (CSV oder XLSX, erstes Tabellenblatt) braucht eine Kopfzeile mit genau diesen Spalten,
   Reihenfolge egal:

   | Mitglied         | E-Mail            | Name        |
   |------------------|-------------------|-------------|
   | Fechtschule Nord | anna@example.com  | Anna Muster |
   | Fechtgilde Ost   | anna@example.com  | Anna Muster |
   | Fechtgilde Ost   | bert@example.com  | Bert Beispiel |

   - *Mitglied* (alternativ *Verein*) muss wie in der Verwaltung heißen (Groß-/Kleinschreibung egal).
   - Eine Person in mehreren Zeilen vertritt mehrere Mitglieder.
   - Vorhandene Personen (gleiche E-Mail) werden nur zugeordnet, Name und Rolle bleiben unverändert.
   - Bei fehlerhaften Zeilen bricht der Befehl ab; `--skip-invalid` importiert nur die gültigen.
   - Ohne `--invite` gibt es keine Mails. Einladungen lassen sich später unter *Personen*
     (markieren → „Einladung senden“, Filter „Passwort gesetzt: Nein“) verschicken.

## SQLite

Konfiguriert über `.env`:

| Variable              | Standard    | Zweck                                                   |
|-----------------------|-------------|---------------------------------------------------------|
| `DB_JOURNAL_MODE`     | `wal`       | Lesen (Polling) blockiert Schreiben (Stimmabgabe) nicht |
| `DB_BUSY_TIMEOUT`     | `5000`      | Gleichzeitige Schreibzugriffe warten statt abzubrechen  |
| `DB_SYNCHRONOUS`      | `normal`    | Sicher in Kombination mit WAL                           |
| `DB_TRANSACTION_MODE` | `IMMEDIATE` | Schreibsperre direkt zu Beginn einer Transaktion        |
| `DB_SECURE_DELETE`    | `on`        | Gelöschte Daten werden mit Nullen überschrieben         |

### Geheime Abstimmungen und die Datenbankdatei

Wie ein Mitglied geheim abgestimmt hat, wird nirgends gespeichert. Es gibt nur Summen pro Option.
Solange eine Abstimmung läuft, steht in `database.sqlite-wal` aber noch die Reihenfolge der
Abgaben (Teilnahme und Zählerstand nacheinander). Wer diese Datei in dem Moment kopiert, könnte
Stimmen einzelnen Mitgliedern zuordnen.

Deshalb wird die WAL-Datei beim Beenden oder Zurücksetzen jeder Abstimmung in die Datenbank
übernommen und auf 0 Byte gekürzt; `DB_SECURE_DELETE` überschreibt freigegebene Bereiche. Danach
ist keine Reihenfolge mehr rekonstruierbar. Klappt das Leeren nicht (z.B. weil ein Prozess die
Datei blockiert), steht eine Warnung in `storage/logs/laravel.log`.

**Backups und Kopien der Datenbank daher nie während einer laufenden geheimen Abstimmung ziehen**,
sondern vor der Versammlung oder nachdem die Abstimmung beendet ist. Das gilt auch für automatische
Server-Backups: Diese für die Dauer der Versammlung aussetzen oder so legen, dass sie nicht in
eine Abstimmung fallen.

### Backup

`database/database.sqlite` vor jeder Versammlung sichern (siehe oben: nicht während einer geheimen
Abstimmung), am besten mit `sqlite3 database/database.sqlite ".backup backup.sqlite"`. Dabei
werden die `-wal`/`-shm`-Dateien automatisch berücksichtigt.

Der alte Plain-PHP-Stand liegt unter `legacy/` und wird nicht deployed.
