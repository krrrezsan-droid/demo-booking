<?php

namespace App\Models;

use Database\Factories\OfficeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Офис, определяющий местное время доступности переговорных.
 *
 * @property int $id Идентификатор записи
 * @property string $name Название
 * @property string $timezone Часовой пояс офиса в формате IANA
 * @property Carbon|null $created_at Дата и время создания записи
 * @property Carbon|null $updated_at Дата и время последнего изменения записи
 * @property-read Collection<int, Room> $rooms Переговорные офиса
 */
#[Fillable(['name', 'timezone'])]
class Office extends Model
{
    /** @use HasFactory<OfficeFactory> */
    use HasFactory;

    /**
     * Переговорные, расположенные в офисе.
     *
     * @return HasMany<Room, $this> Связь офиса с комнатами
     */
    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }
}
