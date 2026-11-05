<?php

namespace App\Filament\Resources\Polls\Pages;

use App\Filament\Resources\Polls\PollResource;
use App\Models\Poll;
use App\Services\PollStructure;
use Filament\Resources\Pages\CreateRecord;

class CreatePoll extends CreateRecord
{
    protected static string $resource = PollResource::class;

    protected function afterCreate(): void
    {
        /** @var Poll $poll */
        $poll = $this->record;

        app(PollStructure::class)->sync($poll);
    }
}
