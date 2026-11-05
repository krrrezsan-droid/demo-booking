<?php

namespace App\Models;

use Database\Factories\RoomFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Переговорная с рабочим часовым поясом своего офиса.
 *
 * @property int $id Идентификатор записи
 * @property int $office_id Идентификатор офиса
 * @property string $name Название
 * @property int $capacity Количество мест в переговорной
 * @property Carbon|null $created_at Дата и время создания записи
 * @property Carbon|null $updated_at Дата и время последнего изменения записи
 * @property-read Office $office Офис комнаты и его часовой пояс
 * @property-read Collection<int, Booking> $bookings Брони
 */
#[Fillable(['office_id', 'name', 'capacity'])]
class Room extends Model
{
    /** @use HasFactory<RoomFactory> */
    use HasFactory;

    /**
     * Офис, которому принадлежит комната.
     *
     * @return BelongsTo<Office, $this> Связь комнаты с офисом
     */
    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    /**
     * Брони переговорной.
     *
     * @return HasMany<Booking, $this> Связь комнаты с её бронированиями
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
