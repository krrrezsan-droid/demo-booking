<?php

namespace App\Http\Requests;

use App\Modules\Booking\Dto\CreateBookingData;
use App\Modules\Booking\Exceptions\BookingRuleViolation;
use App\Modules\Booking\ValueObjects\BookingPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Проверяет транспортный контракт; правила периода принадлежат BookingPeriod.
 */
class StoreBookingRequest extends FormRequest
{
    /**
     * ISO 8601 с обязательным смещением; календарную дату дополнительно проверяет date.
     */
    private const string TIMESTAMP_PATTERN = <<<'REGEX'
~\A
    \d{4}-\d{2}-\d{2}T
    (?:[01]\d|2[0-3]):[0-5]\d
    (?::[0-5]\d(?:\.\d{1,6})?)?
    (?:Z|[+-](?:[01]\d|2[0-3]):?[0-5]\d)
\z~x
REGEX;

    /**
     * Создаёт типизированный DTO только из валидированных полей.
     *
     * @return CreateBookingData Данные создания с нормализованным UTC-интервалом
     *
     * @throws BookingRuleViolation Если длительность встречи недопустима
     */
    public function toData(): CreateBookingData
    {
        /** @var array{room_id: int, title: string, starts_at: string, ends_at: string} $data */
        $data = $this->validated();

        return new CreateBookingData(
            roomId: $data['room_id'],
            title: $data['title'],
            period: new BookingPeriod(
                CarbonImmutable::parse($data['starts_at']),
                CarbonImmutable::parse($data['ends_at']),
            ),
        );
    }

    /**
     * Разрешает обработку запроса после аутентификации middleware auth:sanctum.
     *
     * @return bool Признак разрешения обработки запроса
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Определяет обязательные поля, их типы и формат дат со смещением.
     *
     * @return array<string, array<int, string>> Правила валидации по именам полей
     */
    public function rules(): array
    {
        $timestamp = ['bail', 'required', 'string', 'regex:'.self::TIMESTAMP_PATTERN, 'date'];

        return [
            'room_id' => ['bail', 'required', 'integer:strict', 'exists:rooms,id'],
            'title' => ['required', 'string', 'max:255'],
            'starts_at' => $timestamp,
            'ends_at' => $timestamp,
        ];
    }

    /**
     * Возвращает понятные сообщения об ошибках входных данных.
     *
     * @return array<string, string> Сообщения по именам полей и правил валидации
     */
    public function messages(): array
    {
        return [
            'required' => 'Поле :attribute обязательно.',
            'string' => 'Поле :attribute должно быть строкой.',
            'room_id.integer' => 'Идентификатор комнаты должен быть целым числом.',
            'room_id.exists' => 'Комната не найдена.',
            'title.max' => 'Название не должно превышать 255 символов.',
            'starts_at.regex' => 'Начало должно быть в формате ISO 8601 с часовым смещением.',
            'ends_at.regex' => 'Окончание должно быть в формате ISO 8601 с часовым смещением.',
            'starts_at.date' => 'Начало содержит недопустимую дату.',
            'ends_at.date' => 'Окончание содержит недопустимую дату.',
        ];
    }
}
