<?php

use App\Models\Event;
use App\Models\TicketType;
use App\Models\User;
use Livewire\Livewire;

/**
 * 正しい入力値。データセットの上書き分だけ差し替えて使う。
 *
 * @return array<string, string>
 */
function validEventInput(): array
{
    return [
        'title' => '朝焼けトレッキング',
        'description' => '早朝に山頂を目指します。',
        'venue' => '岩手山 馬返し登山口',
        'starts_at' => '2030-05-01T05:00',
        'ends_at' => '2030-05-01T09:00',
    ];
}

/**
 * @return array<string, array{0: array<string, string>, 1: string, 2: string}>
 */
function invalidEventInputs(): array
{
    return [
        'title is empty' => [['title' => ''], 'title', 'required'],
        'title is too long' => [['title' => str_repeat('あ', 101)], 'title', 'max'],
        'description is empty' => [['description' => ''], 'description', 'required'],
        'description is too long' => [['description' => str_repeat('あ', 2001)], 'description', 'max'],
        'venue is empty' => [['venue' => ''], 'venue', 'required'],
        'venue is too long' => [['venue' => str_repeat('あ', 101)], 'venue', 'max'],
        'starts_at is empty' => [['starts_at' => ''], 'starts_at', 'required'],
        'starts_at is not a date' => [['starts_at' => 'not-a-date'], 'starts_at', 'date'],
        'ends_at is empty' => [['ends_at' => ''], 'ends_at', 'required'],
        'ends_at is not a date' => [['ends_at' => 'not-a-date'], 'ends_at', 'date'],
        'ends_at is before starts_at' => [['ends_at' => '2030-05-01T04:00'], 'ends_at', 'after'],
        'ends_at equals starts_at' => [['ends_at' => '2030-05-01T05:00'], 'ends_at', 'after'],
    ];
}

test('guest is redirected to login from the create and edit pages', function () {
    $event = Event::factory()->create();

    $this->get(route('events.create'))->assertRedirect(route('login'));
    $this->get(route('events.edit', $event))->assertRedirect(route('login'));
});

test('logged in user can open the create page', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('events.create'))
        ->assertOk();
});

test('user can create an event', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test('pages::events.create')
        ->set(validEventInput())
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('events.index'));

    $this->assertDatabaseHas('events', [
        'user_id' => $user->id,
        'title' => '朝焼けトレッキング',
        'venue' => '岩手山 馬返し登山口',
        'starts_at' => '2030-05-01 05:00:00',
        'ends_at' => '2030-05-01 09:00:00',
    ]);
    expect(session('status'))->toBe('イベントを登録しました。');
});

test('event is not created when the input is invalid', function (array $override, string $field, string $rule) {
    $this->actingAs(User::factory()->create());

    Livewire::test('pages::events.create')
        ->set(array_merge(validEventInput(), $override))
        ->call('save')
        ->assertHasErrors([$field => $rule])
        ->assertNoRedirect();

    $this->assertDatabaseCount('events', 0);
})->with(invalidEventInputs());

test('edit page shows the current values of the event', function () {
    $event = Event::factory()->create([
        'title' => '雪上ヨガ体験',
        'venue' => '安比高原',
        'starts_at' => '2030-02-03 10:00:00',
        'ends_at' => '2030-02-03 12:30:00',
    ]);

    $this->actingAs($event->user);

    Livewire::test('pages::events.edit', ['event' => $event])
        ->assertSet('title', '雪上ヨガ体験')
        ->assertSet('description', $event->description)
        ->assertSet('venue', '安比高原')
        ->assertSet('starts_at', '2030-02-03T10:00')
        ->assertSet('ends_at', '2030-02-03T12:30');
});

test('organizer can update their event', function () {
    $event = Event::factory()->create();

    $this->actingAs($event->user);

    Livewire::test('pages::events.edit', ['event' => $event])
        ->set(validEventInput())
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('events.index'));

    $event->refresh();
    expect($event->title)->toBe('朝焼けトレッキング')
        ->and($event->venue)->toBe('岩手山 馬返し登山口')
        ->and($event->starts_at->format('Y-m-d H:i'))->toBe('2030-05-01 05:00')
        ->and($event->ends_at->format('Y-m-d H:i'))->toBe('2030-05-01 09:00')
        ->and(session('status'))->toBe('イベントを更新しました。');
});

test('event is not updated when the input is invalid', function (array $override, string $field, string $rule) {
    $event = Event::factory()->create();
    $original = $event->only(['title', 'description', 'venue']);

    $this->actingAs($event->user);

    Livewire::test('pages::events.edit', ['event' => $event])
        ->set(array_merge(validEventInput(), $override))
        ->call('save')
        ->assertHasErrors([$field => $rule])
        ->assertNoRedirect();

    expect($event->refresh()->only(['title', 'description', 'venue']))->toBe($original);
})->with(invalidEventInputs());

test('organizer can delete their event', function () {
    $event = Event::factory()->create();

    $this->actingAs($event->user);

    Livewire::test('pages::events.edit', ['event' => $event])
        ->call('delete')
        ->assertRedirect(route('events.index'));

    $this->assertModelMissing($event);
    expect(session('status'))->toBe('イベントを削除しました。');
});

test('edit page is forbidden for a user who does not own the event', function () {
    $event = Event::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('events.edit', $event))
        ->assertForbidden();
});

test('event cannot be updated by a user who does not own the event', function () {
    $event = Event::factory()->create();
    $originalTitle = $event->title;

    $this->actingAs($event->user);
    $component = Livewire::test('pages::events.edit', ['event' => $event]);

    $this->actingAs(User::factory()->create());

    $component
        ->set(validEventInput())
        ->call('save')
        ->assertForbidden();

    expect($event->refresh()->title)->toBe($originalTitle);
});

test('event cannot be deleted by a user who does not own the event', function () {
    $event = Event::factory()->create();

    $this->actingAs($event->user);
    $component = Livewire::test('pages::events.edit', ['event' => $event]);

    $this->actingAs(User::factory()->create());

    $component
        ->call('delete')
        ->assertForbidden();

    $this->assertModelExists($event);
});

test('organizer can duplicate their event', function () {
    $event = Event::factory()->has(TicketType::factory()->count(2))->create([
        'title' => '朝焼けトレッキング',
        'starts_at' => '2030-05-01 05:00:00',
        'ends_at' => '2030-05-01 09:00:00',
    ]);

    $this->actingAs($event->user);

    $component = Livewire::test('pages::events.edit', ['event' => $event])
        ->call('duplicate');

    $this->assertDatabaseCount('events', 2);

    $copy = Event::query()->whereKeyNot($event->id)->sole();

    $component->assertRedirect(route('events.edit', $copy));

    expect($copy->title)->toBe('朝焼けトレッキング（複製）')
        ->and($copy->user_id)->toBe($event->user_id)
        ->and($copy->description)->toBe($event->description)
        ->and($copy->venue)->toBe($event->venue)
        ->and($copy->starts_at->format('Y-m-d H:i'))->toBe('2030-05-08 05:00')
        ->and($copy->ends_at->format('Y-m-d H:i'))->toBe('2030-05-08 09:00')
        ->and($copy->ticketTypes()->count())->toBe(0)
        ->and(session('status'))->toBe('イベントを複製しました。');

    $event->refresh();

    expect($event->title)->toBe('朝焼けトレッキング')
        ->and($event->starts_at->format('Y-m-d H:i'))->toBe('2030-05-01 05:00')
        ->and($event->ticketTypes()->count())->toBe(2);
});

test('duplicated title is shortened to fit within 100 characters', function () {
    $event = Event::factory()->create(['title' => str_repeat('あ', 100)]);

    $this->actingAs($event->user);

    Livewire::test('pages::events.edit', ['event' => $event])
        ->call('duplicate');

    $copy = Event::query()->whereKeyNot($event->id)->sole();

    expect($copy->title)->toBe(str_repeat('あ', 96).'（複製）')
        ->and(mb_strlen($copy->title))->toBe(100);
});

test('event cannot be duplicated by a user who does not own the event', function () {
    $event = Event::factory()->create();

    $this->actingAs($event->user);
    $component = Livewire::test('pages::events.edit', ['event' => $event]);

    $this->actingAs(User::factory()->create());

    $component
        ->call('duplicate')
        ->assertForbidden();

    $this->assertDatabaseCount('events', 1);
});

test('edit page shows the duplicate button and the status message', function () {
    $event = Event::factory()->create();

    $this->actingAs($event->user)
        ->withSession(['status' => 'イベントを複製しました。'])
        ->get(route('events.edit', $event))
        ->assertOk()
        ->assertSee('イベントを複製しました。')
        ->assertSeeHtml('wire:click="duplicate"');
});

test('unsaved form input is not carried over to the duplicated event', function () {
    $event = Event::factory()->create(['title' => '朝焼けトレッキング']);

    $this->actingAs($event->user);

    Livewire::test('pages::events.edit', ['event' => $event])
        ->set('title', '書きかけのタイトル')
        ->call('duplicate');

    $copy = Event::query()->whereKeyNot($event->id)->sole();

    expect($copy->title)->toBe('朝焼けトレッキング（複製）')
        ->and($event->refresh()->title)->toBe('朝焼けトレッキング');
});

test('event cannot be duplicated after the organizer logs out', function () {
    $event = Event::factory()->create();

    $this->actingAs($event->user);
    $component = Livewire::test('pages::events.edit', ['event' => $event]);

    auth()->logout();

    $component
        ->call('duplicate')
        ->assertForbidden();

    $this->assertDatabaseCount('events', 1);
});

test('duplicated event belongs to the organizer and is not editable by other users', function () {
    $event = Event::factory()->create();

    $this->actingAs($event->user);

    Livewire::test('pages::events.edit', ['event' => $event])
        ->call('duplicate');

    $copy = Event::query()->whereKeyNot($event->id)->sole();

    expect($copy->user->is($event->user))->toBeTrue();

    $this->get(route('events.edit', $copy))->assertOk();

    $this->actingAs(User::factory()->create())
        ->get(route('events.edit', $copy))
        ->assertForbidden();
});

test('event list can be filtered by title or venue', function () {
    Event::factory()->create(['title' => '朝焼けトレッキング', 'venue' => '岩手山']);
    Event::factory()->create(['title' => '雪上ヨガ体験', 'venue' => '安比高原']);
    Event::factory()->create(['title' => '夜のジャズ', 'venue' => '盛岡ホール']);

    Livewire::test('pages::events.index')
        ->assertSee(['朝焼けトレッキング', '雪上ヨガ体験', '夜のジャズ'])
        ->set('search', 'ヨガ')
        ->assertSee('雪上ヨガ体験')
        ->assertDontSee(['朝焼けトレッキング', '夜のジャズ'])
        ->set('search', '盛岡')
        ->assertSee('夜のジャズ')
        ->assertDontSee(['朝焼けトレッキング', '雪上ヨガ体験']);
});

test('event list shows a message when nothing matches the search', function () {
    Event::factory()->create(['title' => '朝焼けトレッキング']);

    Livewire::test('pages::events.index')
        ->set('search', '存在しない語')
        ->assertDontSee('朝焼けトレッキング')
        ->assertSee('条件に合うイベントが見つかりません。');
});

test('search treats percent and underscore as plain characters', function () {
    Event::factory()->create(['title' => '100%の力']);
    Event::factory()->create(['title' => 'ふつうの会']);

    Livewire::test('pages::events.index')
        ->set('search', '%')
        ->assertSee('100%の力')
        ->assertDontSee('ふつうの会');
});
