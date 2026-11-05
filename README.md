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
