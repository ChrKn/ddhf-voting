# DDHF MV Abstimmungssystem

Abstimmungstool für Versammlungen des Deutschen Dachverbands für historisches Fechten e.V.
Stimmberechtigt sind die Mitglieder (in der Regel Vereine) mit einer Stimmenzahl, die sich aus ihrer
Mitgliederzahl ergibt, und die Präsidiumsposten mit einer festen Stimmenzahl je Posten.

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

## Deployment (Shared Hosting ohne Node)

Auf dem Server gibt es kein npm, `composer setup` bricht dort beim `npm install` ab. Node wird aber
nur zum **Bauen** von CSS und JS gebraucht. Zur Laufzeit liegen die fertigen Dateien in
`public/build/` und werden als statische Dateien ausgeliefert. Fehlt dieser Ordner, antwortet jede
Seite mit Fehler 500 (`ViteManifestNotFoundException`). Darum wird lokal gebaut und hochgeladen.

Vorher prüfen, dass auf der Konsole PHP 8.3 oder neuer läuft (`php -v`). Manche Hoster bieten
neuere Versionen nur unter eigenem Namen an, z. B. `php83`. Diesen dann in allen Befehlen unten
statt `php` verwenden.

### Erstinstallation

Auf dem Server, im Ordner, der den Projektordner enthalten soll:

```sh
git clone \<git-archive\> \<folder\>  
cd \<folder\>
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --force
```

In der `.env` mindestens setzen:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=<https://example.com>
QUEUE_CONNECTION=sync
```

Dazu die Mail-Einstellungen, siehe [E-Mail](#e-mail).

Lokal, auf **demselben Commit** wie der Server, bauen und hochladen. Tailwind erzeugt nur die
Klassen, die in den Blade-Dateien des gebauten Stands vorkommen. Ein Build von einem anderen Stand
kann deshalb Styles vermissen lassen.

```sh
git checkout main && git pull
npm ci
npm run build
scp -r public/build <ssh-user>@<host>:<pfad>/\<folder\>/public/
```

`scp` gibt es unter Windows in PowerShell und Git Bash.

Zurück auf dem Server:

```sh
php artisan voting:create-admin
php artisan optimize          # Config, Routen und Views cachen
```

Im Hosting-Panel die Domain auf den Unterordner **`public/`** zeigen lassen, nicht auf den
Projektordner. Sonst wären `.env` und die Datenbankdatei über das Web erreichbar.

### Update

Nicht während einer laufenden geheimen Abstimmung, und vorher die Datenbank sichern
(siehe [Backup](#backup)).

Auf dem Server:

```sh
php artisan down
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
```

Lokal auf dem neuen Stand `npm ci && npm run build` ausführen und `public/build` wie oben
hochladen. Am einfachsten bei jedem Update, nicht nur wenn sich CSS geändert hat, denn auch
geänderte Blade-Dateien können neue Klassen brauchen. Alte Dateien in `public/build/assets` stören
nicht, das Manifest verweist nur auf die aktuellen.

Danach auf dem Server:

```sh
php artisan optimize
php artisan up
```

`php artisan optimize` cacht auch die Konfiguration. Nach jeder Änderung an der `.env` muss es
deshalb erneut laufen, sonst gelten die alten Werte weiter.

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
Beim ersten Login muss die Person ein eigenes Passwort festlegen.

**Verwalter:innen** können einzelne Stimmberechtigte (mit Mitgliederzahl bzw. festen Stimmen) und
Vertreter:innen anlegen und miteinander verknüpfen, auch nachträglich. Die Stimmen eines
bestehenden Eintrags ändern, importieren, löschen und Rollen vergeben kann nur der Systemadmin;
Admin- und Verwalterkonten können Verwalter:innen weder bearbeiten noch einladen.

## Erstbefüllung

1. **Mitglieder** in der Verwaltung unter *Stimmberechtigte → Importieren* hochladen
   (CSV oder XLSX mit Name und Anzahl Mitglieder). Umbenannte Mitglieder lassen sich
   dort einem vorhandenen Eintrag zuordnen. Präsidiumsposten berührt der Import nicht.
2. **Präsidiumsposten** sind schon da. Die acht Posten nach § 19 der Satzung legt
   `php artisan migrate` mit je einer Stimme an. Ändert sich die Satzung, die Posten unter
   *Stimmberechtigte* anpassen. Die Stimmen hängen am Posten: Wechselt die Person, wird nur die
   Zuordnung geändert.
3. **Personen** per Konsole anlegen und den Mitgliedern bzw. Posten zuordnen:

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

   - *Mitglied* (alternativ *Verein*, *Stimmberechtigte* oder *Posten*) muss wie in der Verwaltung
     heißen (Groß-/Kleinschreibung egal). Präsidiumsposten werden genauso über ihren Namen gefunden.
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
