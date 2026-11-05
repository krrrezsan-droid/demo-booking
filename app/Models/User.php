<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;

/**
 * Пользователь API и автор бронирований переговорных.
 *
 * @property int $id Идентификатор пользователя
 * @property string $name Имя пользователя
 * @property string $email Электронная почта
 * @property string $password Хеш пароля
 * @property string|null $remember_token Токен запоминания входа
 * @property Carbon|null $email_verified_at Дата подтверждения почты
 * @property Carbon|null $created_at Дата создания пользователя
 * @property Carbon|null $updated_at Дата последнего изменения пользователя
 * @property-read Collection<int, Booking> $bookings Брони пользователя
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Определяет преобразования даты подтверждения почты и хеширование пароля.
     *
     * @return array<string, string> Типы преобразования атрибутов пользователя
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Возвращает связь пользователя с созданными им бронями.
     *
     * @return HasMany<Booking, $this> Связь пользователя с бронированиями
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
