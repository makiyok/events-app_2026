<?php

use App\Models\Event;
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
