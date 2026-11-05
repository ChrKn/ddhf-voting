<?php

use App\Models\User;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Password;
use Symfony\Component\Mime\Email;

function sentInvitation(): Email
{
    $captured = null;
    Event::listen(MessageSending::class, function (MessageSending $event) use (&$captured) {
        $captured = $event->message;
    });

    Password::sendResetLink(['email' => User::factory()->create()->email]);

    return $captured;
}

it('setzt Reply-To und Return-Path aus der Konfiguration', function () {
    config([
        'mail.default' => 'array',
        'mail.from' => ['address' => 'noreply@ddhf.de', 'name' => 'DDHF'],
        'mail.reply_to' => ['address' => 'kontakt@ddhf.de', 'name' => 'DDHF Kontakt'],
        'mail.return_path' => ['address' => 'bounces@ddhf.de'],
    ]);

    $mail = sentInvitation();

    expect($mail->getFrom()[0]->getAddress())->toBe('noreply@ddhf.de')
        ->and($mail->getReplyTo()[0]->getAddress())->toBe('kontakt@ddhf.de')
        ->and($mail->getReturnPath()->getAddress())->toBe('bounces@ddhf.de');
});

it('lässt beides weg, wenn nichts konfiguriert ist', function () {
    config([
        'mail.default' => 'array',
        'mail.reply_to' => ['address' => null, 'name' => null],
        'mail.return_path' => ['address' => null],
    ]);

    $mail = sentInvitation();

    expect($mail->getReplyTo())->toBe([])
        ->and($mail->getReturnPath())->toBeNull();
});
