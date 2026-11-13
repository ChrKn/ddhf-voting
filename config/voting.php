<?php

return [

    /*
    | Stimmen eines Mitglieds = ceil(Mitglieder / votes_divisor), nach der Satzung:
    | „Jedes Mitglied hat pro angefangene 50 seiner Einzelmitglieder eine Stimme.“
    | 1–50 Einzelmitglieder ergeben 1 Stimme, 51–100 ergeben 2 usw., 0 ergeben 0.
    | (Präsidiumsposten haben stattdessen eine feste Stimmenzahl am Posten.)
    */
    'votes_divisor' => (int) env('VOTING_VOTES_DIVISOR', 50),

    // Aktualisierungsintervall der Live-Ansichten (Livewire wire:poll)
    'poll_interval' => env('VOTING_POLL_INTERVAL', '3s'),

];
