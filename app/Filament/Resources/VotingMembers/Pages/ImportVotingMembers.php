<?php

namespace App\Filament\Resources\VotingMembers\Pages;

use App\Exceptions\VotingException;
use App\Filament\Resources\VotingMembers\VotingMemberResource;
use App\Models\User;
use App\Models\VotingMember;
use App\Services\MemberImport;
use App\Services\VoteWeightCalculator;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Collection;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Import von Mitgliedern mit Zuordnungsschritt. Läuft synchron ohne Queue
 * (gedacht für einige hundert Zeilen). Nur für den Systemadmin, weil dabei
 * Mitgliederzahlen geändert werden.
 */
class ImportVotingMembers extends Page
{
    use WithFileUploads;

    protected static string $resource = VotingMemberResource::class;

    protected string $view = 'filament.resources.voting-members.pages.import-voting-members';

    protected static ?string $title = 'Mitglieder importieren';

    /** @var TemporaryUploadedFile|null */
    public $upload = null;

    /** @var list<list<string>> Rohdaten der Datei */
    public array $raw = [];

    public int $nameColumn = 0;

    public int $countColumn = 1;

    public bool $hasHeader = true;

    /** @var list<array{line: int, name: string, count: int|null, error: string|null, target: string, rename: bool, similar: string|null}> */
    public array $rows = [];

    public ?string $problems = null;

    /** @var array{created: int, updated: int, renamed: int, unchanged: int, skipped: int}|null */
    public ?array $summary = null;

    public static function canAccess(array $parameters = []): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && $user->can('create', VotingMember::class)
            && $user->can('updateVotes', VotingMember::class);
    }

    public function updatedUpload(): void
    {
        $this->validate([
            'upload' => ['required', 'file', 'max:2048', 'extensions:csv,txt,xlsx'],
        ], attributes: ['upload' => 'Datei']);

        $import = app(MemberImport::class);

        try {
            $this->raw = $import->read($this->upload->getRealPath(), $this->upload->getClientOriginalExtension());
        } catch (\Throwable $e) {
            report($e);
            $this->addError('upload', 'Die Datei konnte nicht gelesen werden.');

            return;
        }

        if ($this->raw === []) {
            $this->addError('upload', 'Die Datei enthält keine Daten.');

            return;
        }

        $guess = $import->guessColumns($this->raw);
        $this->nameColumn = $guess['name'];
        $this->countColumn = $guess['count'];
        $this->hasHeader = $guess['header'];
        $this->summary = null;
        $this->buildRows();
    }

    public function updatedNameColumn(): void
    {
        $this->buildRows();
    }

    public function updatedCountColumn(): void
    {
        $this->buildRows();
    }

    public function updatedHasHeader(): void
    {
        $this->buildRows();
    }

    public function updatedRows(): void
    {
        $this->problems = null;
    }

    public function import(): void
    {
        abort_unless(static::canAccess(), 403);

        try {
            $this->summary = app(MemberImport::class)->apply($this->rows);
        } catch (VotingException $e) {
            $this->problems = $e->getMessage();

            return;
        }

        Notification::make()->title('Import abgeschlossen')->success()->send();
        $this->resetImport();
    }

    public function resetImport(): void
    {
        $this->reset(['upload', 'raw', 'rows', 'problems']);
    }

    private function buildRows(): void
    {
        $this->problems = null;
        $this->rows = app(MemberImport::class)->rows($this->raw, $this->nameColumn, $this->countColumn, $this->hasHeader);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $votingMembers = VotingMember::query()->members()->orderBy('name')->get(['id', 'name', 'member_count', 'active'])->keyBy('id');
        $targeted = collect($this->rows)->pluck('target')->filter(fn ($t) => is_numeric($t))->map(fn ($t) => (int) $t);

        return [
            'votingMembers' => $votingMembers,
            'calculator' => app(VoteWeightCalculator::class),
            'columns' => $this->columnOptions(),
            'stats' => $this->stats($votingMembers),
            'missing' => $votingMembers->where('active', true)->except($targeted->all())->values(),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function columnOptions(): array
    {
        $width = max(array_map('count', $this->raw ?: [[]]));
        $first = $this->raw[0] ?? [];
        $options = [];

        for ($i = 0; $i < $width; $i++) {
            $label = 'Spalte '.($i + 1);
            $options[$i] = isset($first[$i]) && $first[$i] !== '' ? "{$label}: {$first[$i]}" : $label;
        }

        return $options;
    }

    /**
     * @param  Collection<int, VotingMember>  $votingMembers
     * @return array{new: int, update: int, unchanged: int, skip: int}
     */
    private function stats(Collection $votingMembers): array
    {
        $stats = ['new' => 0, 'update' => 0, 'unchanged' => 0, 'skip' => 0];

        foreach ($this->rows as $row) {
            if ($row['target'] === MemberImport::SKIP || $row['error'] !== null) {
                $stats['skip']++;
            } elseif ($row['target'] === MemberImport::NEW) {
                $stats['new']++;
            } else {
                $votingMember = $votingMembers->get((int) $row['target']);
                $changed = $votingMember === null
                    || $votingMember->member_count !== $row['count']
                    || ($row['rename'] && $votingMember->name !== $row['name']);
                $stats[$changed ? 'update' : 'unchanged']++;
            }
        }

        return $stats;
    }
}
