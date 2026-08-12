<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\GradeSeeder;
use Database\Seeders\SettingSeeder;

beforeEach(function () {
    $this->seed([GradeSeeder::class, SettingSeeder::class]);
});

it('sends the yard attendant straight to the yard, not to a page of revenue figures', function () {
    $attendant = User::factory()->attendant()->create();

    $this->actingAs($attendant)
        ->get('/admin')
        ->assertRedirect('/admin/yard');
});

it('lets the yard attendant work the yard', function () {
    $attendant = User::factory()->attendant()->create();

    $this->actingAs($attendant)->get('/admin/yard')->assertOk();

    $this->actingAs($attendant)
        ->post('/admin/yard', ['vehicle_reg' => 'ND 44 221', 'bay' => 'A1'])
        ->assertRedirect();

    $this->assertDatabaseHas('yard_bookings', ['vehicle_reg' => 'ND 44 221']);
});

it('keeps the yard attendant out of customers and settings', function () {
    $attendant = User::factory()->attendant()->create();

    $this->actingAs($attendant)->get('/admin/customers')->assertForbidden();
    $this->actingAs($attendant)->get('/admin/settings')->assertForbidden();
});

it('keeps an admin out of settings but lets them work documents', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get('/admin/settings')->assertForbidden();
    $this->actingAs($admin)->patch('/admin/settings', ['business.phone' => '000'])->assertForbidden();

    $this->actingAs($admin)->get('/admin/documents')->assertOk();
    $this->actingAs($admin)->get('/admin/customers')->assertOk();
});

it('lets the owner reach everything', function () {
    $owner = User::factory()->owner()->create();

    foreach (['/admin', '/admin/pricing', '/admin/documents', '/admin/customers', '/admin/yard', '/admin/settings'] as $path) {
        $this->actingAs($owner)->get($path)->assertOk();
    }
});

it('tells the React sidebar exactly what each role may reach', function () {
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)
        ->get('/admin')
        ->assertInertia(fn ($page) => $page
            ->where('auth.can.publishPrices', true)
            ->where('auth.can.viewSettings', true)
            ->where('auth.can.viewDocuments', true));

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get('/admin')
        ->assertInertia(fn ($page) => $page
            // Hidden in the sidebar and refused by the controller — both.
            ->where('auth.can.publishPrices', false)
            ->where('auth.can.viewSettings', false)
            ->where('auth.can.viewDocuments', true));
});

it('turns nobody away who is not signed in, except from the admin console', function () {
    $this->get('/admin')->assertRedirect('/login');
    $this->get('/admin/documents')->assertRedirect('/login');
    $this->get('/')->assertOk();
});

it('has no public registration route', function () {
    $this->get('/register')->assertNotFound();
    $this->post('/register', [])->assertNotFound();
});
