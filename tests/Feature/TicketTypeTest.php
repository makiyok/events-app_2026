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
