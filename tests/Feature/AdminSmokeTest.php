<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders the admin dashboard with the occupancy widget', function () {
    $this->seed();

    $this->actingAs(User::factory()->create())
        ->get('/admin')
        ->assertOk();
});

it('renders the rooms list', function () {
    $this->seed();

    $this->actingAs(User::factory()->create())
        ->get('/admin/rooms')
        ->assertOk();
});

it('excludes maintenance rooms from the counted scope', function () {
    $group = \App\Models\Group::create(['name' => 'G', 'is_ours' => true]);
    $venue = \App\Models\Venue::create(['group_id' => $group->id, 'name' => 'V', 'timezone' => 'Asia/Dubai']);

    $open = \App\Models\Room::create(['venue_id' => $venue->id, 'name' => 'Open']);
    $closed = \App\Models\Room::create(['venue_id' => $venue->id, 'name' => 'Psycho', 'under_maintenance' => true]);

    $counted = \App\Models\Room::counted()->pluck('id');

    expect($counted)->toContain($open->id)
        ->and($counted)->not->toContain($closed->id);
});
