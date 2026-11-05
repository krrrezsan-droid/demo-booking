<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\Sanctum;
use Monolog\Handler\TestHandler;
use Tests\Support\BookingFixtures;
use Tests\TestCase;

/**
 * Согласованность лога создания с окончательным результатом транзакции.
 */
class BookingLoggingTest extends TestCase
{
    use BookingFixtures;
    use DatabaseMigrations;

    /**
     * Запись с booking_id появляется только после коммита внешней транзакции.
     */
    public function test_logs_booking_id_only_after_commit(): void
    {
        $room = $this->createRoom();
        Sanctum::actingAs(User::factory()->create());
        $log = $this->captureLog('bookings');

        DB::beginTransaction();
        $response = $this->postJson('/api/bookings', $this->bookingPayload($room))->assertCreated();
        $this->assertSame([], $log->getRecords());
        DB::commit();

        $records = $log->getRecords();
        $this->assertCount(1, $records);
        $this->assertSame('Booking created', $records[0]->message);
        $this->assertSame($response->json('id'), $records[0]->context['booking_id']);
        $this->assertDatabaseHas('bookings', ['id' => $response->json('id')]);
    }

    /**
     * Откат не оставляет ни брони, ни ложного сообщения об успешном создании.
     */
    public function test_does_not_log_rolled_back_booking(): void
    {
        $room = $this->createRoom();
        Sanctum::actingAs(User::factory()->create());
        $log = $this->captureLog('bookings');

        DB::beginTransaction();
        $this->postJson('/api/bookings', $this->bookingPayload($room))->assertCreated();
        $this->assertSame([], $log->getRecords());
        DB::rollBack();

        $this->assertSame([], $log->getRecords());
        $this->assertDatabaseCount('bookings', 0);
    }

    /**
     * Ожидаемый конфликт расписания не записывается как ошибка приложения.
     */
    public function test_does_not_report_booking_conflict_as_application_error(): void
    {
        $room = $this->createRoom();
        $this->createMorningBooking($room);
        Sanctum::actingAs(User::factory()->create());
        $log = $this->captureLog(config('logging.default'));

        $this->postJson('/api/bookings', $this->bookingPayload($room))
            ->assertUnprocessable()
            ->assertJsonPath('errors.starts_at.0', 'Комната уже занята в выбранное время.');

        $this->assertSame([], $log->getRecords());
        $this->assertDatabaseCount('bookings', 1);
    }

    /**
     * Подключает обработчик к настоящему лог-каналу и снимает его после теста.
     */
    private function captureLog(string $channel): TestHandler
    {
        $handler = new TestHandler;
        $logger = Log::channel($channel)->getLogger();
        $logger->pushHandler($handler);
        $this->beforeApplicationDestroyed(fn () => $logger->popHandler());

        return $handler;
    }

    /**
     * Закрывает транзакцию до очистки схемы DatabaseMigrations, в том числе при ошибке.
     */
    protected function tearDown(): void
    {
        try {
            while (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
        } finally {
            parent::tearDown();
        }
    }
}
