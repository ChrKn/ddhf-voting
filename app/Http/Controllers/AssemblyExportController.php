<?php

namespace App\Http\Controllers;

use App\Enums\PollStatus;
use App\Models\Assembly;
use App\Services\PollResult;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Protokoll einer Versammlung als CSV (Semikolon, UTF-8 mit BOM, damit Excel
 * Umlaute korrekt anzeigt): Anwesenheit und alle durchgeführten Abstimmungen.
 */
class AssemblyExportController extends Controller
{
    public function __invoke(Assembly $assembly): StreamedResponse
    {
        Gate::authorize('view', $assembly);

        $filename = 'protokoll-'.Str::slug($assembly->title).'-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($assembly) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            $row = fn (array $fields) => fputcsv($out, $fields, ';', escape: '');

            $row(['Versammlung', $assembly->title]);
            $row(['Datum', $assembly->date?->format('d.m.Y') ?? '']);
            $row(['Status', $assembly->status->label()]);
            $row(['Exportiert am', now()->format('d.m.Y H:i')]);
            $row([]);

            $row(['Anwesenheit']);
            $row(['Mitglied', 'Stimmen', 'Eingecheckt um']);
            foreach ($assembly->clubs()->orderBy('name')->get() as $club) {
                $row([$club->name, $club->attendance?->votes, $club->attendance?->checked_in_at?->format('d.m.Y H:i')]);
            }
            $row(['Summe', $assembly->totalVotes(), '']);

            $polls = $assembly->polls()->whereIn('status', [PollStatus::Closed, PollStatus::Published])->get();

            foreach ($polls as $poll) {
                $result = PollResult::for($poll);
                $progress = $result->progress();

                $row([]);
                $row(['Abstimmung', $poll->title]);
                $row(['Art / Modus', $poll->type->label().' / '.$poll->mode->label()]);
                $row(['Zeitraum', $poll->opened_at?->format('H:i').' – '.$poll->closed_at?->format('H:i')]);
                $row(['Beteiligung', "{$progress['clubs_voted']} von {$progress['clubs_present']} Mitgliedern, {$progress['votes_cast']} von {$progress['votes_present']} Stimmen"]);
                $row(['Amt / Frage', 'Option', 'Stimmen', 'Anteil ohne Enthaltung (%)']);

                foreach ($result->positions() as $position) {
                    foreach ($position['options'] as $option) {
                        $row([
                            $position['title'],
                            $option['label'].($option['club_hint'] ? " ({$option['club_hint']})" : ''),
                            $option['votes'],
                            $option['is_abstention'] ? '' : number_format($option['share'], 1, ',', ''),
                        ]);
                    }
                }

                $details = $result->details();
                if ($details !== []) {
                    $row(['Detailergebnis (öffentlich)']);
                    $row(['Mitglied', 'Stimmen', 'Amt / Frage', 'Abgestimmt mit']);
                    foreach ($details as $detail) {
                        foreach ($detail['choices'] as $position => $label) {
                            $row([$detail['club'], $detail['votes'], $position, $label]);
                        }
                    }
                }
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
