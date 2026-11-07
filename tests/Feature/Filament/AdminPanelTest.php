<?php

use App\Enums\PollStatus;
use App\Enums\PollType;
use App\Enums\Role;
use App\Filament\Resources\Clubs\ClubResource;
use App\Filament\Resources\Clubs\Pages\CreateClub;
use App\Filament\Resources\Clubs\Pages\EditClub;
use App\Filament\Resources\Polls\Pages\CreatePoll;
use App\Filament\Resources\Polls\Pages\EditPoll;
use App\Filament\Resources\Polls\PollResource;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\UserResource;
use App\Models\Assembly;
use App\Models\Club;
use App\Models\Poll;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->systemAdmin()->create();
    $this->manager = User::factory()->manager()->create();
});

describe('Zugang', function () {
    it('leitet Gäste aus der Verwaltung zum gemeinsamen Login', function () {
        $this->get('/admin')->assertRedirect(route('login'));
    });

    it('sperrt Vereinsvertreter:innen aus der Verwaltung aus', function () {
        $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
    });

    it('lässt Systemadmin und Verwalter:innen hinein', function () {
        $this->actingAs($this->admin)->get('/admin')->assertOk();
        $this->actingAs($this->manager)->get('/admin')->assertOk();
    });

    it('lässt nur den Systemadmin Personen bearbeiten, Verwalter:innen nur ansehen', function () {
        $person = User::factory()->create();

        $this->actingAs($this->admin)->get(UserResource::getUrl('edit', ['record' => $person]))->assertOk();
        $this->actingAs($this->manager)->get(UserResource::getUrl())->assertOk();
        $this->actingAs($this->manager)->get(UserResource::getUrl('create'))->assertForbidden();
        $this->actingAs($this->manager)->get(UserResource::getUrl('edit', ['record' => $person]))->assertForbidden();
    });
});

describe('Vereine und Mitgliederzahl', function () {
    it('lässt den Systemadmin Vereine mit Mitgliederzahl anlegen', function () {
        $this->actingAs($this->admin);

        Livewire::test(CreateClub::class)
            ->fillForm(['name' => 'Fechtschule Nord', 'member_count' => 75, 'active' => true])
            ->call('create')
            ->assertHasNoFormErrors();

        $club = Club::firstWhere('name', 'Fechtschule Nord');
        expect($club->member_count)->toBe(75)->and($club->votes)->toBe(2);
    });

    it('lässt den Systemadmin die Mitgliederzahl ändern', function () {
        $club = Club::factory()->members(10)->create();
        $this->actingAs($this->admin);

        Livewire::test(EditClub::class, ['record' => $club->getRouteKey()])
            ->fillForm(['member_count' => 120])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($club->fresh()->member_count)->toBe(120);
    });

    it('zeigt Verwalter:innen die Vereinsliste, aber keine Bearbeitung', function () {
        $club = Club::factory()->create();
        $this->actingAs($this->manager);

        $this->get(ClubResource::getUrl())->assertOk()->assertSee($club->name);
        $this->get(ClubResource::getUrl('edit', ['record' => $club]))->assertForbidden();
        $this->get(ClubResource::getUrl('create'))->assertForbidden();
    });

    it('verhindert auch abseits der Oberfläche, dass Verwalter:innen die Mitgliederzahl ändern', function () {
        $club = Club::factory()->members(10)->create();
        $this->actingAs($this->manager);

        expect(fn () => $club->update(['member_count' => 500]))->toThrow(AuthorizationException::class);
        expect($club->fresh()->member_count)->toBe(10);

        // andere Felder bleiben für das Modell selbst unberührt
        $club->fresh()->update(['active' => false]);
        expect($club->fresh()->active)->toBeFalse();
    });
});

describe('Personen', function () {
    it('legt eine Person an, ordnet sie einem Verein zu und verschickt die Einladung', function () {
        Notification::fake();
        $club = Club::factory()->create();
        $this->actingAs($this->admin);

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Erika Muster',
                'email' => 'erika@example.com',
                'role' => Role::Member,
                'clubs' => [$club->getKey()],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $user = User::firstWhere('email', 'erika@example.com');
        expect($user->represents($club))->toBeTrue();
        Notification::assertSentTo($user, ResetPassword::class);
    });
});

describe('Abstimmungen', function () {
    it('legt einen Antrag mit Standardoptionen an', function () {
        $assembly = Assembly::factory()->create();
        $this->actingAs($this->manager);

        Livewire::test(CreatePoll::class)
            ->fillForm([
                'assembly_id' => $assembly->getKey(),
                'title' => 'Antrag auf Satzungsänderung',
                'type' => PollType::Motion,
                'mode' => 'secret',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $poll = Poll::firstWhere('title', 'Antrag auf Satzungsänderung')->load('positions.options');
        expect($poll->positions)->toHaveCount(1)
            ->and($poll->positions->first()->options->pluck('label')->all())->toBe(['Dafür', 'Dagegen', 'Enthaltung']);
    });

    it('legt eine Wahl mit Ämtern an und ergänzt die Enthaltung', function () {
        $assembly = Assembly::factory()->create();
        $this->actingAs($this->manager);

        Livewire::test(CreatePoll::class)
            ->fillForm([
                'assembly_id' => $assembly->getKey(),
                'title' => 'Präsidiumswahl',
                'type' => PollType::Election,
                'mode' => 'secret',
                'positions' => [
                    ['title' => 'Präsident:in', 'options' => [['label' => 'Anna', 'club_hint' => 'Nord'], ['label' => 'Bert']]],
                    ['title' => 'Jugendbeauftragte:r', 'options' => [['label' => 'Cem']]],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $poll = Poll::firstWhere('title', 'Präsidiumswahl')->load('positions.options');
        expect($poll->positions->pluck('title')->all())->toBe(['Präsident:in', 'Jugendbeauftragte:r'])
            ->and($poll->positions->first()->options->pluck('label')->all())->toBe(['Anna', 'Bert', 'Enthaltung'])
            ->and($poll->positions->last()->options->pluck('label')->all())->toBe(['Cem', 'Enthaltung']);
    });

    it('sperrt geöffnete Abstimmungen gegen Bearbeitung', function () {
        $poll = Poll::factory()->status(PollStatus::Open)->create();

        $this->actingAs($this->manager)
            ->get(PollResource::getUrl('edit', ['record' => $poll]))
            ->assertForbidden();
    });

    it('wandelt eine Wahl beim Umstellen auf Antrag in die Standardstruktur um', function () {
        $poll = Poll::factory()->election(['Amt' => ['X']])->create();
        $this->actingAs($this->manager);

        Livewire::test(EditPoll::class, ['record' => $poll->getRouteKey()])
            ->fillForm(['type' => PollType::Motion, 'title' => 'Doch ein Antrag'])
            ->call('save')
            ->assertHasNoFormErrors();

        $poll->refresh()->load('positions.options');
        expect($poll->positions)->toHaveCount(1)
            ->and($poll->positions->first()->title)->toBe('Doch ein Antrag')
            ->and($poll->positions->first()->options)->toHaveCount(3);
    });
});
