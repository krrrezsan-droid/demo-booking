<?php

namespace App\Modules\Booking\Actions;

use App\Models\Booking;
use App\Models\Room;
use App\Modules\Booking\Dto\CreateBookingData;
use App\Modules\Booking\Exceptions\BookingRuleViolation;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Создаёт бронь атомарно, сериализуя конкурирующие запросы одной комнаты.
 */
final class CreateBookingAction
{
    /**
     * Создаёт бронь после проверки периода и доступности комнаты.
     *
     * Блокировка комнаты защищает и пустое расписание: блокировать только брони недостаточно.
     * Все остальные пути записи должны соблюдать тот же протокол lock → check → insert.
     *
     * @param  CreateBookingData  $data  Проверенные параметры новой брони
     * @param  int  $authorId  Идентификатор аутентифицированного автора.
     * @return Booking Сохранённая бронь с границами в UTC
     *
     * @throws BookingRuleViolation Если время недопустимо или комната занята
     * @throws ModelNotFoundException<Room> Если комната больше не существует
     * @throws QueryException Если не удалось проверить доступность комнаты или сохранить бронь
     */
    public function execute(CreateBookingData $data, int $authorId): Booking
    {
        $booking = DB::transaction(function () use ($data, $authorId): Booking {
            $room = Room::query()->lockForUpdate()->findOrFail($data->roomId);
            $data->period->assertBookableIn($room->office->timezone);

            $hasOverlap = $room->bookings()
                ->overlapping($data->period->startsAt, $data->period->endsAt)
                ->exists();

            if ($hasOverlap) {
                throw new BookingRuleViolation('starts_at', 'Комната уже занята в выбранное время.');
            }

            return Booking::create([
                'room_id' => $room->id,
                'user_id' => $authorId,
                'title' => $data->title,
                'starts_at' => $data->period->startsAt,
                'ends_at' => $data->period->endsAt,
            ]);
        });

        DB::afterCommit(function () use ($booking): void {
            Log::channel('bookings')->info('Booking created', ['booking_id' => $booking->id]);
        });

        return $booking;
    }
}
