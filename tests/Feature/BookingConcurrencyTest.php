<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\Support\BookingFixtures;
use Tests\Support\BookingWorker;
use Tests\TestCase;

/**
 * Проверяет протокол lock → check → insert двумя независимыми клиентами PostgreSQL.
 */
class BookingConcurrencyTest extends TestCase
{
    use BookingFixtures;
    use DatabaseMigrations;

    /**
     * Второй клиент ждёт комнату, затем видит сохранённую бронь и получает 422.
     *
     * Внешняя транзакция удерживает блокировку первого запроса до наблюдения ожидания.
     * Разные токены исключают ложное ожидание обновления personal_access_tokens.
     */
    public function test_concurrent_request_waits_for_room_lock_then_returns_422(): void
    {
        $this->assertSame('pgsql', DB::connection()->getDriverName());
        $room = $this->createRoom();
        $user = User::factory()->create();
        $token = $user->createToken('first-client')->plainTextToken;
        $otherToken = User::factory()->create()->createToken('second-client')->plainTextToken;
        $payload = $this->bookingPayload($room);
        $worker = new BookingWorker($otherToken, $payload);
        $parentPid = DB::selectOne('select pg_backend_pid() as pid')->pid;

        DB::beginTransaction();
        try {
            $this->withToken($token)->postJson('/api/bookings', $payload)->assertCreated();
            $worker->start();
            $waitingQuery = $worker->waitUntilBlockedBy($parentPid);
            $this->assertStringContainsString('"rooms"', $waitingQuery);
            $this->assertStringContainsString('for update', strtolower($waitingQuery));

            DB::commit();
            $response = $worker->response();

            $this->assertSame(422, $response['status']);
            $this->assertSame('Комната уже занята в выбранное время.', $response['body']['errors']['starts_at'][0]);
            $this->assertDatabaseCount('bookings', 1);
            $this->assertDatabaseHas('bookings', [
                'room_id' => $room->id,
                'user_id' => $user->id,
                'starts_at' => '2026-11-02 07:00:00',
                'ends_at' => '2026-11-02 08:00:00',
            ]);
        } finally {
            while (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            $worker->stop();
        }
    }
}
