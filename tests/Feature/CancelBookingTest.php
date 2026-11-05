<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BookingFixtures;
use Tests\TestCase;

/**
 * Авторизация отмены и освобождение слота.
 */
class CancelBookingTest extends TestCase
{
    use BookingFixtures;
    use RefreshDatabase;

    /**
     * После отмены автором освобождённый интервал можно забронировать снова.
     */
    public function test_owner_can_cancel_and_rebook_slot(): void
    {
        $user = User::factory()->create();
        $room = $this->createRoom();
        $booking = $this->createMorningBooking($room, $user);
        Sanctum::actingAs($user);

        $this->deleteJson('/api/bookings/'.$booking->id)->assertNoContent();

        $this->assertModelMissing($booking);
        $this->postJson('/api/bookings', $this->bookingPayload($room))->assertCreated();
        $this->assertDatabaseCount('bookings', 1);
    }

    /**
     * Чужая бронь остаётся в базе после отказа в отмене.
     */
    public function test_returns_403_when_cancelling_another_authors_booking(): void
    {
        $booking = Booking::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $this->deleteJson('/api/bookings/'.$booking->id)->assertForbidden();

        $this->assertModelExists($booking);
    }

    /**
     * Несуществующая бронь возвращает 404.
     */
    public function test_returns_404_for_missing_booking(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->deleteJson('/api/bookings/999999')->assertNotFound();
    }

    /**
     * Без токена нельзя ни создать, ни отменить бронь.
     */
    public function test_requires_authentication_for_writes(): void
    {
        $booking = Booking::factory()->create();

        $this->postJson('/api/bookings', [])->assertUnauthorized();
        $this->deleteJson('/api/bookings/'.$booking->id)->assertUnauthorized();

        $this->assertModelExists($booking);
        $this->assertDatabaseCount('bookings', 1);
    }
}
