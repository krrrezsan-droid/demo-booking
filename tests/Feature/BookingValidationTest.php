<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use Tests\Datasets\BookingCases;
use Tests\Support\BookingFixtures;
use Tests\TestCase;

/**
 * Проверка входного JSON и бизнес-правил времени через HTTP.
 */
class BookingValidationTest extends TestCase
{
    use BookingFixtures;
    use RefreshDatabase;

    /**
     * Возвращает сообщение по нужному полю и не сохраняет некорректный интервал.
     */
    #[DataProviderExternal(BookingCases::class, 'invalidTimes')]
    public function test_returns_422_without_saving_invalid_times(
        string $zone,
        string $start,
        string $end,
        string $field,
        string $message,
    ): void {
        $room = $this->createRoom($zone);
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/bookings', $this->bookingPayload($room, [
            'starts_at' => $start,
            'ends_at' => $end,
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$field])
            ->assertJsonPath("errors.$field.0", $message);

        $this->assertDatabaseCount('bookings', 0);
    }

    /**
     * Требует все обязательные поля запроса.
     */
    public function test_returns_422_for_missing_required_fields(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/bookings', [])->assertUnprocessable()
            ->assertJsonValidationErrors(['room_id', 'title', 'starts_at', 'ends_at']);

        $this->assertDatabaseCount('bookings', 0);
    }

    /**
     * Отбрасывает некорректные поля до обращения к операции создания.
     */
    #[DataProviderExternal(BookingCases::class, 'invalidFields')]
    public function test_returns_422_for_invalid_fields(string $field, mixed $value): void
    {
        $room = $this->createRoom();
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/bookings', $this->bookingPayload($room, [$field => $value]))
            ->assertUnprocessable()->assertJsonValidationErrors([$field]);

        $this->assertDatabaseCount('bookings', 0);
    }

    /**
     * Сохраняет JSON-тип значения при отправке, чтобы отличить 1.0 от 1.
     */
    #[DataProviderExternal(BookingCases::class, 'nonIntegerRoomIds')]
    public function test_returns_422_for_non_integer_json_room_id(mixed $roomId): void
    {
        $room = Room::factory()->for(Office::factory()->state(['timezone' => 'Europe/Moscow']))->create(['id' => 1]);
        Sanctum::actingAs(User::factory()->create());
        $payload = $this->bookingPayload($room, ['room_id' => $roomId]);

        $this->call('POST', '/api/bookings', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], json_encode($payload, JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR))
            ->assertUnprocessable()
            ->assertJsonPath('errors.room_id.0', 'Идентификатор комнаты должен быть целым числом.');

        $this->assertDatabaseCount('bookings', 0);
    }
}
