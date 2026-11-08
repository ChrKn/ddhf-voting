<?php

namespace App\Filament\Resources\VotingMembers\Pages;

use App\Filament\Resources\VotingMembers\VotingMemberResource;
use Filament\Resources\Pages\CreateRecord;

class CreateVotingMember extends CreateRecord
{
    protected static string $resource = VotingMemberResource::class;
}
