<?php

use App\Models\Event;
use App\Models\TicketType;
use App\Models\User;
use Livewire\Livewire;

test('organizer can add a ticket type to their event', function () {
    $event = Event::factory()->create();

    $this->actingAs($event->user);

    Livewire::test('pages::events.edit', ['event' => $event])
        ->set('ticketTypeName', '一般')
        ->set('ticketTypePrice', '3000')
        ->set('ticketTypeCapacity', '30')
        ->call('addTicketType')
        ->assertHasNoErrors()
        ->assertSet('ticketTypeName', '')
        ->assertSee('一般')
        ->assertSee('3,000円')
        ->assertSee('30名');

    $this->assertDatabaseHas('ticket_types', [
        'event_id' => $event->id,
        'name' => '一般',
        'price' => 3000,
        'capacity' => 30,
    ]);
});

test('edit page lists only the ticket types of the event', function () {
    $event = Event::factory()->create();
    TicketType::factory()->for($event)->create(['name' => '学生', 'price' => 1500, 'capacity' => 20]);
    TicketType::factory()->create(['name' => '別イベントの券種']);

    $this->actingAs($event->user);

    Livewire::test('pages::events.edit', ['event' => $event])
        ->assertSee('学生')
        ->assertSee('1,500円')
        ->assertSee('20名')
        ->assertDontSee('別イベントの券種');
});

test('ticket type is rejected when the input is invalid', function (string $field, string $value, string $rule) {
    $event = Event::factory()->create();

    $this->actingAs($event->user);

    Livewire::test('pages::events.edit', ['event' => $event])
        ->set('ticketTypeName', '一般')
        ->set('ticketTypePrice', '3000')
        ->set('ticketTypeCapacity', '30')
        ->set($field, $value)
        ->call('addTicketType')
        ->assertHasErrors([$field => $rule]);

    $this->assertDatabaseCount('ticket_types', 0);
})->with([
    'name is empty' => ['ticketTypeName', '', 'required'],
    'name is too long' => ['ticketTypeName', str_repeat('あ', 51), 'max'],
    'price is negative' => ['ticketTypePrice', '-1', 'min'],
    'price is not an integer' => ['ticketTypePrice', '1000.5', 'integer'],
    'capacity is zero' => ['ticketTypeCapacity', '0', 'min'],
]);

test('event can be updated while the ticket type inputs are empty', function () {
    $event = Event::factory()->create();

    $this->actingAs($event->user);

    Livewire::test('pages::events.edit', ['event' => $event])
        ->set('title', '更新後のタイトル')
        ->call('save')
        ->assertHasNoErrors();

    expect($event->refresh()->title)->toBe('更新後のタイトル');
});

test('ticket type cannot be added by a user who does not own the event', function () {
    $event = Event::factory()->create();

    $this->actingAs($event->user);
    $component = Livewire::test('pages::events.edit', ['event' => $event]);

    $this->actingAs(User::factory()->create());

    $component
        ->set('ticketTypeName', '一般')
        ->set('ticketTypePrice', '3000')
        ->set('ticketTypeCapacity', '30')
        ->call('addTicketType')
        ->assertForbidden();

    $this->assertDatabaseCount('ticket_types', 0);
});

test('ticket types are deleted together with their event', function () {
    $event = Event::factory()->has(TicketType::factory()->count(2))->create();

    $event->delete();

    $this->assertDatabaseCount('ticket_types', 0);
});

test('organizer can delete a ticket type of their event', function () {
    $event = Event::factory()->create();
    $ticketType = TicketType::factory()->for($event)->create(['name' => '学生']);

    $this->actingAs($event->user);

    Livewire::test('pages::events.edit', ['event' => $event])
        ->assertSee('学生')
        ->call('deleteTicketType', $ticketType->id)
        ->assertDontSee('学生');

    $this->assertModelMissing($ticketType);
});

test('ticket type of another event cannot be deleted from the edit page', function () {
    $event = Event::factory()->create();
    $otherTicketType = TicketType::factory()->create();

    $this->actingAs($event->user);

    Livewire::test('pages::events.edit', ['event' => $event])
        ->call('deleteTicketType', $otherTicketType->id)
        ->assertNotFound();

    $this->assertModelExists($otherTicketType);
});

test('ticket type cannot be deleted by a user who does not own the event', function () {
    $event = Event::factory()->create();
    $ticketType = TicketType::factory()->for($event)->create();

    $this->actingAs($event->user);
    $component = Livewire::test('pages::events.edit', ['event' => $event]);

    $this->actingAs(User::factory()->create());

    $component
        ->call('deleteTicketType', $ticketType->id)
        ->assertForbidden();

    $this->assertModelExists($ticketType);
});

test('event list shows the price label of each event', function (array $prices, string $label) {
    $event = Event::factory()->create();

    foreach ($prices as $price) {
        TicketType::factory()->for($event)->create(['price' => $price]);
    }

    Livewire::test('pages::events.index')
        ->assertSeeInOrder([$event->venue, $label, $event->user->name]);
})->with([
    'cheapest ticket type' => [[3000, 1500], '1,500円〜'],
    'free ticket type' => [[2000, 0], '無料'],
    'no ticket types' => [[], '─'],
]);
