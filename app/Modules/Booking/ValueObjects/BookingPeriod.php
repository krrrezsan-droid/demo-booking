<?php

namespace App\Modules\Booking\ValueObjects;

use App\Modules\Booking\Exceptions\BookingRuleViolation;
use Carbon\CarbonImmutable;

/**
 * Неизменяемый интервал бронирования: границы хранятся в UTC.
 *
 * Длительность не зависит от смещения клиента. Сетка и рабочие часы
 * проверяются отдельно, когда известен часовой пояс офиса комнаты.
 */
final readonly class BookingPeriod
{
    private const int MIN_DURATION_SECONDS = 15 * 60;

    private const int MAX_DURATION_SECONDS = 4 * 60 * 60;

    private const int STEP_MINUTES = 15;

    private const int OPENING_HOUR = 9;

    private const int CLOSING_HOUR = 20;

    /**
     * @var CarbonImmutable Начало встречи в UTC
     */
    public CarbonImmutable $startsAt;

    /**
     * @var CarbonImmutable Окончание встречи в UTC
     */
    public CarbonImmutable $endsAt;

    /**
     * Нормализует моменты времени и проверяет допустимую длительность.
     *
     * @param  CarbonImmutable  $startsAt  Начало встречи с исходным часовым смещением
     * @param  CarbonImmutable  $endsAt  Окончание встречи с исходным часовым смещением
     *
     * @throws BookingRuleViolation Если интервал короче 15 минут или длиннее 4 часов
     */
    public function __construct(CarbonImmutable $startsAt, CarbonImmutable $endsAt)
    {
        $this->startsAt = $startsAt->utc();
        $this->endsAt = $endsAt->utc();
        $duration = $this->endsAt->getTimestamp() - $this->startsAt->getTimestamp();

        if ($duration < self::MIN_DURATION_SECONDS || $duration > self::MAX_DURATION_SECONDS) {
            throw new BookingRuleViolation('ends_at', 'Длительность брони должна быть от 15 минут до 4 часов.');
        }
    }

    /**
     * Проверяет обе границы по местному времени офиса, включая конец в 20:00.
     *
     * @param  string  $timezone  IANA-идентификатор, например Europe/Moscow.
     *
     * @throws BookingRuleViolation Если нарушены сетка или рабочие часы
     */
    public function assertBookableIn(string $timezone): void
    {
        $start = $this->startsAt->setTimezone($timezone);
        $end = $this->endsAt->setTimezone($timezone);

        $this->assertOnGrid($start, 'starts_at');
        $this->assertOnGrid($end, 'ends_at');

        if ($start->toDateString() !== $end->toDateString()
            || $start->hour < self::OPENING_HOUR
            || $end->greaterThan($start->setTime(self::CLOSING_HOUR, 0))) {
            throw new BookingRuleViolation('starts_at', 'Бронь должна целиком укладываться в 09:00–20:00 по времени офиса.');
        }
    }

    /**
     * Проверяет попадание границы встречи на пятнадцатиминутную сетку.
     *
     * @param  CarbonImmutable  $time  Граница интервала в местном времени офиса
     * @param  string  $field  Поле запроса для сообщения об ошибке
     *
     * @throws BookingRuleViolation Если граница не совпадает с четвертью часа
     */
    private function assertOnGrid(CarbonImmutable $time, string $field): void
    {
        if ($time->minute % self::STEP_MINUTES !== 0 || $time->second !== 0 || $time->micro !== 0) {
            throw new BookingRuleViolation($field, 'Время должно быть кратно 15 минутам, без секунд и долей секунды.');
        }
    }
}
