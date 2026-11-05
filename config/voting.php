<?php

return [

    /*
    | Stimmen eines Vereins = floor(Mitglieder / votes_divisor) + votes_base
    | Entspricht der bisherigen Excel-Formel ABRUNDEN(Mitglieder / 50 + 1; 0).
    */
    'votes_divisor' => (int) env('VOTING_VOTES_DIVISOR', 50),
    'votes_base' => (int) env('VOTING_VOTES_BASE', 1),

    // Aktualisierungsintervall der Live-Ansichten (Livewire wire:poll)
    'poll_interval' => env('VOTING_POLL_INTERVAL', '3s'),

];
