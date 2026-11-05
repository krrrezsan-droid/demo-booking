<?php

namespace Tests\Support;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use JsonException;
use Random\RandomException;
use RuntimeException;
use Symfony\Component\Process\Exception\LogicException as ProcessLogicException;
use Symfony\Component\Process\Exception\ProcessSignaledException;
use Symfony\Component\Process\Exception\ProcessStartFailedException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Exception\RuntimeException as ProcessRuntimeException;
use Symfony\Component\Process\Process;

/**
 * Дочерний клиент API и наблюдение его реальной блокировки в PostgreSQL.
 */
final class BookingWorker
{
    /**
     * @var Process Дочерний PHP-процесс, выполняющий конкурентный запрос к API
     */
    private readonly Process $process;

    /**
     * @var string Уникальный application_name соединения для поиска в pg_stat_activity
     */
    private readonly string $marker;

    /**
     * Подготавливает отдельного клиента с подключением к базе родительского теста.
     *
     * @param  string  $token  Токен Sanctum второго клиента
     * @param  array<string, mixed>  $payload  JSON-тело запроса создания брони
     *
     * @throws JsonException Если параметры дочернего клиента невозможно сериализовать
     * @throws RandomException Если не удалось сгенерировать уникальный маркер соединения
     * @throws ProcessLogicException Если в окружении недоступна функция proc_open
     */
    public function __construct(string $token, array $payload)
    {
        $this->marker = 'booking-concurrency-'.bin2hex(random_bytes(8));
        $this->process = new Process([PHP_BINARY, base_path('tests/Fixtures/create_booking.php')], base_path(), [
            'APP_ENV' => 'testing',
            'SESSION_DRIVER' => 'array',
            'CACHE_STORE' => 'array',
            'LOG_CHANNEL' => 'null',
        ]);
        $this->process->setInput(json_encode([
            'database' => DB::connection()->getConfig(),
            'token' => $token,
            'payload' => $payload,
            'marker' => $this->marker,
        ], JSON_THROW_ON_ERROR));
        $this->process->setTimeout(15);
    }

    /**
     * Запускает запрос асинхронно, пока родитель удерживает транзакцию.
     *
     * @throws ProcessStartFailedException Если операционная система не смогла запустить процесс
     * @throws ProcessRuntimeException Если процесс уже запущен
     */
    public function start(): void
    {
        $this->process->start();
    }

    /**
     * Возвращает SQL, который действительно ждёт указанное соединение.
     *
     * Снимок статистики сбрасывается на каждом опросе: родитель находится в транзакции.
     * Пауза только ограничивает частоту опроса; подтверждение ожидания берётся из PostgreSQL.
     *
     * @param  int  $parentPid  PID соединения PostgreSQL, удерживающего блокировку
     * @return string SQL-запрос дочернего клиента, ожидающий освобождения блокировки
     *
     * @throws RuntimeException Если процесс завершился до наблюдения блокировки или истекли 5 секунд ожидания
     * @throws QueryException Если не удалось прочитать состояние соединений PostgreSQL
     */
    public function waitUntilBlockedBy(int $parentPid): string
    {
        $deadline = microtime(true) + 5;
        do {
            DB::select('select pg_stat_clear_snapshot()');
            $waiting = DB::selectOne(
                "select query from pg_stat_activity where application_name = ? and wait_event_type = 'Lock' and ? = any(pg_blocking_pids(pid))",
                [$this->marker, $parentPid],
            );
            if ($waiting !== null) {
                return $waiting->query;
            }
            if (! $this->process->isRunning()) {
                throw new RuntimeException('Worker finished without waiting: '.$this->diagnostics());
            }
            usleep(20_000);
        } while (microtime(true) < $deadline);

        throw new RuntimeException('Worker did not wait within 5 seconds: '.$this->diagnostics());
    }

    /**
     * Дожидается HTTP-ответа; вызывается только после освобождения блокировки родителем.
     *
     * @return array{status: int, body: array<string, mixed>} Статус и JSON-тело ответа API
     *
     * @throws RuntimeException Если завершившийся процесс вернул ненулевой код выхода
     * @throws ProcessTimedOutException Если превышен таймаут выполнения процесса
     * @throws ProcessSignaledException Если процесс завершён сигналом
     * @throws ProcessLogicException Если ожидание вызвано до запуска процесса
     * @throws JsonException Если вывод дочернего процесса не является корректным JSON
     */
    public function response(): array
    {
        $this->process->wait();
        if (! $this->process->isSuccessful()) {
            throw new RuntimeException('Worker failed: '.$this->diagnostics());
        }

        return json_decode($this->process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * Останавливает незавершённый процесс при падении проверки или таймауте.
     */
    public function stop(): void
    {
        if ($this->process->isRunning()) {
            $this->process->stop(1);
        }
    }

    /**
     * Вывод процесса для диагностики упавшего сценария.
     *
     * @return string Объединённый стандартный вывод и поток ошибок процесса
     */
    private function diagnostics(): string
    {
        return $this->process->getOutput().$this->process->getErrorOutput();
    }
}
