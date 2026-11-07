<?php

use App\Models\Assembly;
use App\Models\Poll;
use App\Services\AssemblyLifecycle;
use App\Services\PollLifecycle;
use App\Services\VoteTraceCleaner;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->file = storage_path('framework/testing/wal-'.uniqid().'.sqlite');
    @mkdir(dirname($this->file), 0777, true);
    touch($this->file);

    config(['database.connections.walfile' => [
        ...config('database.connections.sqlite'),
        'database' => $this->file,
        'journal_mode' => 'wal',
    ]]);
});

afterEach(function () {
    DB::purge('walfile');
    foreach (['', '-wal', '-shm'] as $suffix) {
        @unlink($this->file.$suffix);
    }
});

it('übernimmt die WAL-Datei in die Datenbank und leert sie', function () {
    $db = DB::connection('walfile');
    $db->statement('create table tally (votes integer)');
    $db->insert('insert into tally values (3)');
    $db->update('update tally set votes = votes + 5');

    clearstatcache();
    expect(filesize($this->file.'-wal'))->toBeGreaterThan(0);

    (new VoteTraceCleaner('walfile'))->flush();

    clearstatcache();
    expect(filesize($this->file.'-wal'))->toBe(0)
        ->and($db->scalar('select votes from tally'))->toBe(8);
});

it('überschreibt gelöschte Daten (secure_delete)', function () {
    expect((int) DB::connection('walfile')->scalar('pragma secure_delete'))->toBe(1);
});

it('macht ohne WAL-Modus nichts', function () {
    // Tests laufen mit :memory:, dort gibt es keine WAL-Datei
    (new VoteTraceCleaner)->flush();
})->throwsNoExceptions();

it('räumt beim Beenden und Zurücksetzen einer Abstimmung auf', function () {
    $cleaner = Mockery::mock(VoteTraceCleaner::class);
    $cleaner->shouldReceive('flush')->twice();
    app()->instance(VoteTraceCleaner::class, $cleaner);

    $assembly = Assembly::factory()->create();
    app(AssemblyLifecycle::class)->open($assembly);
    $poll = Poll::factory()->create(['assembly_id' => $assembly->id]);

    $polls = app(PollLifecycle::class);
    $polls->open($poll);
    $polls->close($poll);
    $polls->reset($poll);
});
