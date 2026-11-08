<?php

namespace App\Enums;

enum AssemblyEventType: string
{
    case AssemblyOpened = 'assembly_opened';
    case AssemblyClosed = 'assembly_closed';
    case CheckedIn = 'checked_in';
    case CheckedOut = 'checked_out';
    case VotesCorrected = 'votes_corrected';
    case PollOpened = 'poll_opened';
    case PollClosed = 'poll_closed';
    case PollPublished = 'poll_published';
    case PollReset = 'poll_reset';

    public function label(): string
    {
        return match ($this) {
            self::AssemblyOpened => 'Versammlung eröffnet',
            self::AssemblyClosed => 'Versammlung beendet',
            self::CheckedIn => 'Check-in',
            self::CheckedOut => 'Checkout',
            self::VotesCorrected => 'Stimmen korrigiert',
            self::PollOpened => 'Abstimmung gestartet',
            self::PollClosed => 'Abstimmung beendet',
            self::PollPublished => 'Ergebnis veröffentlicht',
            self::PollReset => 'Abstimmung zurückgesetzt',
        };
    }
}
