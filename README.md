# DDHF Wahl-O-Mat

Abstimmungstool für Versammlungen des Deutschen Dachverbands für historisches Fechten e.V.
Vereine stimmen mit einer Stimmenzahl ab, die sich aus ihrer Mitgliederzahl ergibt.

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

Backup: `database/database.sqlite` (inkl. `-wal`/`-shm`-Dateien) vor jeder Versammlung sichern,
am besten mit `sqlite3 database/database.sqlite ".backup backup.sqlite"`.

Der alte Plain-PHP-Stand liegt unter `legacy/` und wird nicht deployed.
