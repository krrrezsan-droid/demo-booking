<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Бронь комнаты; starts_at и ends_at сохраняются как UTC.
 *
 * @property int $id Идентификатор записи
 * @property int $room_id Идентификатор переговорной
 * @property int $user_id Идентификатор автора брони
 * @property string $title Название встречи
 * @property Carbon $starts_at Начало встречи в UTC
 * @property Carbon $ends_at Окончание встречи в UTC
 * @property Carbon|null $created_at Дата и время создания записи
 * @property Carbon|null $updated_at Дата и время последнего изменения записи
 * @property-read Room $room Переговорная, в которой проходит встреча
 * @property-read User $user Автор брони
 */
#[Fillable(['room_id', 'user_id', 'starts_at', 'ends_at', 'title'])]
class Booking extends Model
{
    /** @use HasFactory<BookingFactory> */
    use HasFactory;

    /**
     * Определяет преобразование сохранённых дат в объекты Carbon.
     *
     * @return array<string, string> Типы преобразования атрибутов модели
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /**
     * Переговорная, в которой создана бронь.
     *
     * @return BelongsTo<Room, $this> Связь брони с комнатой
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * Автор брони.
     *
     * @return BelongsTo<User, $this> Связь брони с пользователем
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Ограничивает выборку бронями указанного пользователя.
     *
     * @param  Builder<Booking>  $query  Запрос бронирований
     * @param  int  $userId  Идентификатор автора брони
     * @return Builder<Booking> Запрос с фильтром по автору
     */
    #[Scope]
    protected function forUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Сортирует брони по убыванию времени начала.
     *
     * @param  Builder<Booking>  $query  Запрос бронирований
     * @return Builder<Booking> Запрос с порядком от поздних встреч к ранним
     */
    #[Scope]
    protected function latestFirst(Builder $query): Builder
    {
        return $query->orderByDesc('starts_at');
    }

    /**
     * Выбирает пересекающиеся интервалы; общая граница двух встреч допустима.
     *
     * @param  Builder<Booking>  $query  Запрос бронирований
     * @param  CarbonInterface  $startsAt  Начало проверяемого интервала в UTC
     * @param  CarbonInterface  $endsAt  Конец проверяемого интервала в UTC
     * @return Builder<Booking> Запрос пересекающихся броней
     */
    #[Scope]
    protected function overlapping(Builder $query, CarbonInterface $startsAt, CarbonInterface $endsAt): Builder
    {
        return $query->where('starts_at', '<', $endsAt)->where('ends_at', '>', $startsAt);
    }
}
