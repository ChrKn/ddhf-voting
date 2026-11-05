<?php

// Deutsche Validierungsmeldungen für die in der App verwendeten Regeln.
// Filament bringt eigene Übersetzungen für seine Oberfläche mit.

return [
    'accepted' => ':attribute muss akzeptiert werden.',
    'array' => ':attribute muss eine Liste sein.',
    'boolean' => ':attribute muss wahr oder falsch sein.',
    'confirmed' => 'Die Bestätigung von :attribute stimmt nicht überein.',
    'date' => ':attribute muss ein gültiges Datum sein.',
    'email' => ':attribute muss eine gültige E-Mail-Adresse sein.',
    'exists' => 'Die Auswahl für :attribute ist ungültig.',
    'in' => 'Die Auswahl für :attribute ist ungültig.',
    'integer' => ':attribute muss eine ganze Zahl sein.',
    'max' => [
        'numeric' => ':attribute darf höchstens :max sein.',
        'string' => ':attribute darf höchstens :max Zeichen lang sein.',
        'array' => ':attribute darf höchstens :max Einträge haben.',
    ],
    'min' => [
        'numeric' => ':attribute muss mindestens :min sein.',
        'string' => ':attribute muss mindestens :min Zeichen lang sein.',
        'array' => ':attribute muss mindestens :min Einträge haben.',
    ],
    'numeric' => ':attribute muss eine Zahl sein.',
    'password' => [
        'letters' => ':attribute muss mindestens einen Buchstaben enthalten.',
        'mixed' => ':attribute muss Groß- und Kleinbuchstaben enthalten.',
        'numbers' => ':attribute muss mindestens eine Zahl enthalten.',
        'symbols' => ':attribute muss mindestens ein Sonderzeichen enthalten.',
        'uncompromised' => ':attribute ist in einem Datenleck aufgetaucht. Bitte ein anderes wählen.',
    ],
    'required' => ':attribute muss ausgefüllt werden.',
    'string' => ':attribute muss ein Text sein.',
    'unique' => ':attribute ist bereits vergeben.',

    'attributes' => [
        'email' => 'E-Mail-Adresse',
        'name' => 'Name',
        'password' => 'Passwort',
        'member_count' => 'Mitgliederzahl',
        'title' => 'Titel',
    ],
];
