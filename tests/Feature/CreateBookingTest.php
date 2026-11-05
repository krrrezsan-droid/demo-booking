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
 * Создание брони и доверенные источники данных.
 */
class CreateBookingTest extends TestCase
{
    use BookingFixtures;
    use RefreshDatabase;

    /**
     * Сохраняет UTC и автора из токена, игнорируя подмену user_id и id.
     */
    public function test_creates_booking_in_utc_for_authenticated_author_despite_spoofed_fields(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $room = $this->createRoom();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/bookings', $this->bookingPayload($room, [
            'user_id' => $other->id,
            'id' => 987654,
        ]));

        $response->assertCreated()->assertJsonPath('user_id', $user->id)
            ->assertJsonPath('starts_at', '2026-11-02T07:00:00.000000Z');
        $this->assertDatabaseHas('bookings', [
            'id' => $response->json('id'),
            'room_id' => $room->id,
            'user_id' => $user->id,
            'starts_at' => '2026-11-02 07:00:00',
            'ends_at' => '2026-11-02 08:00:00',
            'title' => 'Планёрка',
        ]);
        $this->assertDatabaseMissing('bookings', ['id' => 987654]);
    }

    /**
     * Принимает допустимое время без дополнительных ограничений на календарные даты.
     */
    #[DataProviderExternal(BookingCases::class, 'validTimes')]
    public function test_accepts_valid_local_hours_and_unrestricted_dates(string $zone, string $start, string $end): void
    {
        $room = $this->createRoom($zone);
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/bookings', $this->bookingPayload($room, [
            'starts_at' => $start,
            'ends_at' => $end,
        ]))->assertCreated();

        $this->assertDatabaseCount('bookings', 1);
    }
}
