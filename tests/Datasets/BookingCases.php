<?php

namespace Tests\Datasets;

/** Именованные примеры API-контракта; ожидаемые значения не вычисляются кодом приложения. */
final class BookingCases
{
    /**
     * Допустимые интервалы, включая границы рабочих часов и разные смещения.
     *
     * @return array<string, array{zone: string, start: string, end: string}>
     */
    public static function validTimes(): array
    {
        return [
            'opening and minimum' => [
                'zone' => 'Europe/Moscow',
                'start' => '2026-11-02T09:00:00+03:00',
                'end' => '2026-11-02T09:15:00+03:00',
            ],
            'closing and maximum' => [
                'zone' => 'Europe/Moscow',
                'start' => '2026-11-02T16:00:00+03:00',
                'end' => '2026-11-02T20:00:00+03:00',
            ],
            'novosibirsk from utc' => [
                'zone' => 'Asia/Novosibirsk',
                'start' => '2026-11-02T02:00:00Z',
                'end' => '2026-11-02T02:15:00Z',
            ],
            'different offsets' => [
                'zone' => 'Asia/Novosibirsk',
                'start' => '2026-11-02T05:00:00+03:00',
                'end' => '2026-11-02T10:00:00+07:00',
            ],
            'negative fractional offset' => [
                'zone' => 'Europe/Moscow',
                'start' => '2026-11-02T02:30:00-03:30',
                'end' => '2026-11-02T03:30:00-03:30',
            ],
            'offset without colon' => [
                'zone' => 'Europe/Moscow',
                'start' => '2026-11-02T10:00+0300',
                'end' => '2026-11-02T11:00+0300',
            ],
            'weekend in past' => [
                'zone' => 'Europe/Moscow',
                'start' => '2020-01-04T10:00:00+03:00',
                'end' => '2020-01-04T11:00:00+03:00',
            ],
            'distant future' => [
                'zone' => 'Europe/Moscow',
                'start' => '2040-01-04T10:00:00+03:00',
                'end' => '2040-01-04T11:00:00+03:00',
            ],
        ];
    }

    /**
     * Нарушения правил времени и ожидаемые ошибки API.
     *
     * @return array<string, array{zone: string, start: string, end: string, field: string, message: string}>
     */
    public static function invalidTimes(): array
    {
        $duration = 'Длительность брони должна быть от 15 минут до 4 часов.';
        $step = 'Время должно быть кратно 15 минутам, без секунд и долей секунды.';
        $hours = 'Бронь должна целиком укладываться в 09:00–20:00 по времени офиса.';

        return [
            'short' => [
                'zone' => 'Europe/Moscow',
                'start' => '2026-11-02T10:00:00+03:00',
                'end' => '2026-11-02T10:10:00+03:00',
                'field' => 'ends_at',
                'message' => $duration,
            ],
            'long' => [
                'zone' => 'Europe/Moscow',
                'start' => '2026-11-02T10:00:00+03:00',
                'end' => '2026-11-02T14:15:00+03:00',
                'field' => 'ends_at',
                'message' => $duration,
            ],
            'zero' => [
                'zone' => 'Europe/Moscow',
                'start' => '2026-11-02T10:00:00+03:00',
                'end' => '2026-11-02T10:00:00+03:00',
                'field' => 'ends_at',
                'message' => $duration,
            ],
            'reversed' => [
                'zone' => 'Europe/Moscow',
                'start' => '2026-11-02T11:00:00+03:00',
                'end' => '2026-11-02T10:00:00+03:00',
                'field' => 'ends_at',
                'message' => $duration,
            ],
            'start step' => [
                'zone' => 'Europe/Moscow',
                'start' => '2026-11-02T10:05:00+03:00',
                'end' => '2026-11-02T11:00:00+03:00',
                'field' => 'starts_at',
                'message' => $step,
            ],
            'end step' => [
                'zone' => 'Europe/Moscow',
                'start' => '2026-11-02T10:00:00+03:00',
                'end' => '2026-11-02T11:05:00+03:00',
                'field' => 'ends_at',
                'message' => $step,
            ],
            'seconds' => [
                'zone' => 'Europe/Moscow',
                'start' => '2026-11-02T10:00:01+03:00',
                'end' => '2026-11-02T11:00:00+03:00',
                'field' => 'starts_at',
                'message' => $step,
            ],
            'fraction' => [
                'zone' => 'Europe/Moscow',
                'start' => '2026-11-02T10:00:00+03:00',
                'end' => '2026-11-02T11:00:00.000001+03:00',
                'field' => 'ends_at',
                'message' => $step,
            ],
            'before opening' => [
                'zone' => 'Europe/Moscow',
                'start' => '2026-11-02T08:45:00+03:00',
                'end' => '2026-11-02T09:15:00+03:00',
                'field' => 'starts_at',
                'message' => $hours,
            ],
            'after closing' => [
                'zone' => 'Europe/Moscow',
                'start' => '2026-11-02T19:45:00+03:00',
                'end' => '2026-11-02T20:15:00+03:00',
                'field' => 'starts_at',
                'message' => $hours,
            ],
            'starts at closing' => [
                'zone' => 'Europe/Moscow',
                'start' => '2026-11-02T20:00:00+03:00',
                'end' => '2026-11-02T20:15:00+03:00',
                'field' => 'starts_at',
                'message' => $hours,
            ],
            'cross midnight' => [
                'zone' => 'Europe/Moscow',
                'start' => '2026-11-02T23:00:00+03:00',
                'end' => '2026-11-03T01:00:00+03:00',
                'field' => 'starts_at',
                'message' => $hours,
            ],
            'novosibirsk office closing' => [
                'zone' => 'Asia/Novosibirsk',
                'start' => '2026-11-02T16:00:00+03:00',
                'end' => '2026-11-02T17:00:00+03:00',
                'field' => 'starts_at',
                'message' => $hours,
            ],
            'no offset' => [
                'zone' => 'Europe/Moscow',
                'start' => '2026-11-02T10:00:00',
                'end' => '2026-11-02T11:00:00+03:00',
                'field' => 'starts_at',
                'message' => 'Начало должно быть в формате ISO 8601 с часовым смещением.',
            ],
            'relative date' => [
                'zone' => 'Europe/Moscow',
                'start' => 'tomorrow',
                'end' => '2026-11-02T11:00:00+03:00',
                'field' => 'starts_at',
                'message' => 'Начало должно быть в формате ISO 8601 с часовым смещением.',
            ],
            'invalid day' => [
                'zone' => 'Europe/Moscow',
                'start' => '2026-02-30T10:00:00+03:00',
                'end' => '2026-03-02T11:00:00+03:00',
                'field' => 'starts_at',
                'message' => 'Начало содержит недопустимую дату.',
            ],
        ];
    }

    /**
     * Все формы пересечения с существующей бронью 10:00–11:00 МСК.
     *
     * @return array<string, array{start: string, end: string}>
     */
    public static function overlaps(): array
    {
        return [
            'same' => [
                'start' => '2026-11-02T10:00:00+03:00',
                'end' => '2026-11-02T11:00:00+03:00',
            ],
            'left' => [
                'start' => '2026-11-02T09:30:00+03:00',
                'end' => '2026-11-02T10:30:00+03:00',
            ],
            'right' => [
                'start' => '2026-11-02T10:30:00+03:00',
                'end' => '2026-11-02T11:30:00+03:00',
            ],
            'inside' => [
                'start' => '2026-11-02T10:15:00+03:00',
                'end' => '2026-11-02T10:45:00+03:00',
            ],
            'contains' => [
                'start' => '2026-11-02T09:30:00+03:00',
                'end' => '2026-11-02T11:30:00+03:00',
            ],
            'same in utc' => [
                'start' => '2026-11-02T07:00:00Z',
                'end' => '2026-11-02T08:00:00Z',
            ],
        ];
    }

    /**
     * Касание начала или конца существующей брони без пересечения.
     *
     * @return array<string, array{start: string, end: string}>
     */
    public static function adjacentTimes(): array
    {
        return [
            'before' => [
                'start' => '2026-11-02T09:00:00+03:00',
                'end' => '2026-11-02T10:00:00+03:00',
            ],
            'after' => [
                'start' => '2026-11-02T11:00:00+03:00',
                'end' => '2026-11-02T12:00:00+03:00',
            ],
        ];
    }

    /**
     * Ошибки транспортного контракта и попытка SQL-инъекции.
     *
     * @return array<string, array{string, mixed}>
     */
    public static function invalidFields(): array
    {
        return [
            'missing room' => ['room_id', 999999],
            'sql injection' => ['room_id', '1 OR 1=1'],
            'title type' => ['title', []],
            'title length' => ['title', str_repeat('a', 256)],
            'date type' => ['starts_at', []],
        ];
    }

    /**
     * JSON-значения, которые нельзя неявно приводить к целому id.
     *
     * @return array<string, array{mixed}>
     */
    public static function nonIntegerRoomIds(): array
    {
        return ['boolean' => [true], 'numeric string' => ['1'], 'float' => [1.0]];
    }
}
