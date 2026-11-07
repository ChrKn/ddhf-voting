<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Entfernt Spuren der Abgabe-Reihenfolge aus SQLite.
 *
 * Im WAL-Modus hängt SQLite jede Transaktion an die -wal-Datei an. Eine
 * Stimmabgabe schreibt Teilnahme und Zähler gemeinsam, die Datei enthält
 * also bis zum nächsten Checkpoint die Abgaben in Reihenfolge. Nach dem
 * Beenden einer Abstimmung wird sie deshalb in die Hauptdatei übernommen und
 * auf null Byte gekürzt. Freigegebene Seiten überschreibt secure_delete
 * (config/database.php).
 */
class VoteTraceCleaner
{
    public function __construct(private readonly ?string $connection = null) {}

    public function flush(): void
    {
        $db = DB::connection($this->connection);

        if ($db->getDriverName() !== 'sqlite' || strtolower((string) $db->scalar('pragma journal_mode')) !== 'wal') {
            return;
        }

        // Wartet (busy_timeout) auf laufende Leser; busy = 1 heißt, es hat nicht geklappt
        $result = $db->selectOne('pragma wal_checkpoint(TRUNCATE)');

        if ((int) ($result->busy ?? 0) !== 0) {
            Log::warning('WAL-Datei konnte nicht geleert werden, sie enthält evtl. noch die Reihenfolge der Stimmabgaben.');
        }
    }
}
