<?php

namespace App\Modules\Booking\Services;

use App\Models\Booking;
use App\Models\Room;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Сервис чтения расписания и пользовательских списков бронирований.
 */
final class BookingService
{
    /**
     * Возвращает страницу броней пользователя от поздних встреч к ранним.
     *
     * @param  int  $userId  Идентификатор пользователя
     * @return LengthAwarePaginator<int, Booking> Страница из 20 броней пользователя
     */
    public function paginateForUser(int $userId): LengthAwarePaginator
    {
        return Booking::query()
            ->forUser($userId)
            ->latestFirst()
            ->paginate(20);
    }

    /**
     * Возвращает расписание комнаты за текущую дату приложения.
     *
     * Сохраняет исходный контракт today; ограничения часового пояса описаны в REVIEW.md.
     *
     * @param  Room  $room  Комната, для которой запрашивается расписание
     * @return Collection<int, array{time: string, author: string}> Интервалы встреч и имена авторов
     */
    public function todayForRoom(Room $room): Collection
    {
        return $room->bookings()
            ->whereDate('starts_at', today())
            ->get()
            ->map(fn (Booking $booking): array => [
                'time' => $booking->starts_at.' – '.$booking->ends_at,
                'author' => $booking->user->name,
            ]);
    }
}
