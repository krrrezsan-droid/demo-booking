<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use Tests\Datasets\BookingCases;
use Tests\Support\BookingFixtures;
use Tests\TestCase;

/**
 * Полуоткрытые интервалы и независимость расписаний комнат.
 */
class BookingAvailabilityTest extends TestCase
{
    use BookingFixtures;
    use RefreshDatabase;

    /**
     * Отклоняет каждую форму пересечения, сохраняя только исходную бронь.
     */
    #[DataProviderExternal(BookingCases::class, 'overlaps')]
    public function test_returns_422_for_every_overlap_shape(string $start, string $end): void
    {
        $room = $this->createRoom();
        $this->createMorningBooking($room);
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/bookings', $this->bookingPayload($room, [
            'starts_at' => $start,
            'ends_at' => $end,
        ]))
            ->assertUnprocessable()->assertJsonPath('errors.starts_at.0', 'Комната уже занята в выбранное время.');

        $this->assertDatabaseCount('bookings', 1);
    }

    /**
     * Разрешает соседние встречи с общей границей.
     */
    #[DataProviderExternal(BookingCases::class, 'adjacentTimes')]
    public function test_allows_adjacent_bookings(string $start, string $end): void
    {
        $room = $this->createRoom();
        $this->createMorningBooking($room);
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/bookings', $this->bookingPayload($room, [
            'starts_at' => $start,
            'ends_at' => $end,
        ]))->assertCreated();

        $this->assertDatabaseCount('bookings', 2);
    }

    /**
     * Не смешивает расписания разных комнат.
     */
    public function test_allows_same_slot_in_another_room(): void
    {
        $this->createMorningBooking($this->createRoom());
        $room = $this->createRoom();
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/bookings', $this->bookingPayload($room))->assertCreated();

        $this->assertDatabaseCount('bookings', 2);
    }
}
