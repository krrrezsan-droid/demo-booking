<?php

namespace App\Modules\Booking\Dto;

use App\Modules\Booking\ValueObjects\BookingPeriod;

/**
 * Проверенные данные создания брони, независимые от HTTP-запроса.
 *
 * Автор передаётся в Action отдельно: клиент не может выбрать его через DTO.
 */
final readonly class CreateBookingData
{
    /**
     * Создаёт неизменяемый набор параметров для операции бронирования.
     *
     * @param  int  $roomId  Идентификатор выбранной переговорной
     * @param  string  $title  Название встречи
     * @param  BookingPeriod  $period  Интервал встречи с границами в UTC
     */
    public function __construct(
        public int $roomId,
        public string $title,
        public BookingPeriod $period,
    ) {}
}
