<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BookingIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_only_own_bookings(): void
    {
        $user = User::factory()->create();
        $own = Booking::factory()->for($user)->create();
        Booking::factory()->create();

        Sanctum::actingAs($user);

        $this->getJson('/api/bookings')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->id);
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/bookings')->assertUnauthorized();
    }

    /**
     * Сервис сохраняет сортировку по началу и размер страницы при чтении через скоупы.
     */
    public function test_orders_bookings_by_start_and_paginates_twenty_per_page(): void
    {
        $user = User::factory()->create();
        Booking::factory()->for($user)->count(21)->sequence(
            fn ($sequence): array => [
                'starts_at' => sprintf('2026-11-%02d 07:00:00', $sequence->index + 1),
                'ends_at' => sprintf('2026-11-%02d 08:00:00', $sequence->index + 1),
            ],
        )->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/bookings')
            ->assertOk()
            ->assertJsonCount(20, 'data')
            ->assertJsonPath('total', 21)
            ->assertJsonPath('data.0.starts_at', '2026-11-21T07:00:00.000000Z')
            ->assertJsonPath('data.19.starts_at', '2026-11-02T07:00:00.000000Z');

        $this->getJson('/api/bookings?page=2')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.starts_at', '2026-11-01T07:00:00.000000Z');
    }
}
