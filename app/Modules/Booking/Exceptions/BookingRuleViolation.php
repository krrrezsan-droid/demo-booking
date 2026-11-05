<?php

namespace App\Modules\Booking\Exceptions;

use DomainException;

/**
 * Нарушение правила бронирования без зависимости от HTTP и Laravel.
 *
 * Поле позволяет адаптеру API привязать сообщение к соответствующему вводу.
 */
final class BookingRuleViolation extends DomainException
{
    /**
     * Создаёт ошибку бизнес-правила с привязкой к полю ввода.
     *
     * @param  string  $field  Поле запроса, к которому относится нарушение
     * @param  string  $message  Пояснение нарушенного правила для клиента
     */
    public function __construct(public readonly string $field, string $message)
    {
        parent::__construct($message);
    }
}
