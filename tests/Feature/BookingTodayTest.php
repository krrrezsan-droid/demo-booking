<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BookingFixtures;
use Tests\TestCase;

/**
 * Проверяет сохранение исходного контракта today после переноса чтения в сервис.
 */
class BookingTodayTest extends TestCase
{
    use BookingFixtures;
    use RefreshDatabase;

    /**
     * Сервис возвращает только встречи нужной комнаты за текущую дату приложения.
     */
    public function test_returns_room_bookings_for_current_application_date(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-02T12:00:00Z'));
        $room = $this->createRoom();
        $author = User::factory()->create(['name' => 'Анна']);
        $this->createMorningBooking($room, $author);
        $this->createMorningBooking($this->createRoom());
        Booking::factory()->for($room)->for($author)->create([
            'starts_at' => '2026-11-03 07:00:00',
            'ends_at' => '2026-11-03 08:00:00',
        ]);
        Sanctum::actingAs($author);

        $this->getJson('/api/rooms/'.$room->id.'/bookings/today')
            ->assertOk()
            ->assertExactJson([
                ['time' => '2026-11-02 07:00:00 – 2026-11-02 08:00:00', 'author' => 'Анна'],
            ]);
    }
}
