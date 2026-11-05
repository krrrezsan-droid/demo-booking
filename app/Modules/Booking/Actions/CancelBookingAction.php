<?php

namespace App\Modules\Booking\Actions;

use App\Models\Booking;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;

/**
 * Отменяет бронь с обязательной проверкой авторства для любого вызывающего кода.
 */
final class CancelBookingAction
{
    /**
     * Удаляет бронь, если отмену выполняет её автор.
     *
     * @param  Booking  $booking  Отменяемая бронь
     * @param  int  $authorId  Идентификатор пользователя, выполняющего отмену.
     *
     * @throws AuthorizationException Если пользователь не является автором
     * @throws QueryException Если не удалось удалить бронь из базы
     */
    public function execute(Booking $booking, int $authorId): void
    {
        if ($booking->user_id !== $authorId) {
            throw new AuthorizationException('Отменить бронь может только её автор.');
        }

        $booking->delete();
    }
}
