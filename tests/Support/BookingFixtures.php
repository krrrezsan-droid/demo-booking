<?php

namespace Tests\Support;

use App\Models\Booking;
use App\Models\Office;
use App\Models\Room;
use App\Models\User;

/**
 * Явные fixtures для сценариев API: 2 ноября 2026 года, Москва, 10:00–11:00.
 */
trait BookingFixtures
{
    /**
     * Создаёт комнату вместе с офисом в указанном часовом поясе.
     */
    protected function createRoom(string $timezone = 'Europe/Moscow'): Room
    {
        return Room::factory()->for(Office::factory()->state(['timezone' => $timezone]))->create();
    }

    /**
     * Возвращает валидный запрос; overrides позволяет явно показать отклонение в тесте.
     *
     * @param  array<string, mixed>  $overrides  В том числе намеренно некорректные значения.
     * @return array<string, mixed>
     */
    protected function bookingPayload(Room $room, array $overrides = []): array
    {
        return array_replace([
            'room_id' => $room->id,
            'title' => 'Планёрка',
            'starts_at' => '2026-11-02T10:00:00+03:00',
            'ends_at' => '2026-11-02T11:00:00+03:00',
        ], $overrides);
    }

    /**
     * Создаёт существующую бронь 10:00–11:00 МСК, сохраняя границы в UTC.
     */
    protected function createMorningBooking(Room $room, ?User $author = null): Booking
    {
        return Booking::factory()->for($room)->for($author ?? User::factory())->create([
            'starts_at' => '2026-11-02 07:00:00',
            'ends_at' => '2026-11-02 08:00:00',
        ]);
    }
}
