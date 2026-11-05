<?php

namespace Tests\Support;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use JsonException;

/**
 * Выполняет запрос бронирования внутри дочернего Laravel-приложения.
 *
 * Используется только тестом конкурентности: соединение и HTTP-контекст
 * независимы от родительского процесса, который удерживает блокировку комнаты.
 */
final class BookingRequestRunner
{
    /**
     * Создаёт исполнитель HTTP-запроса.
     *
     * @param  Kernel  $kernel  HTTP-ядро дочернего приложения
     */
    public function __construct(private readonly Kernel $kernel) {}

    /**
     * Выполняет запрос и возвращает ответ для родительского теста.
     *
     * @param  string  $inputJson  JSON с подключением к тестовой БД, маркером, токеном и телом запроса
     * @return string JSON со статусом HTTP и телом ответа API
     *
     * @throws JsonException Если вход, запрос или ответ невозможно закодировать либо разобрать как JSON
     * @throws QueryException Если не удалось настроить соединение с тестовой БД
     */
    public function run(string $inputJson): string
    {
        /** @var array{database: array<string, mixed>, marker: string, token: string, payload: array<string, mixed>} $input */
        $input = json_decode($inputJson, true, flags: JSON_THROW_ON_ERROR);
        $this->kernel->bootstrap();
        $this->configureConnection($input['database'], $input['marker']);
        $request = $this->createRequest($input['token'], $input['payload']);
        $response = $this->kernel->handle($request);

        try {
            return json_encode([
                'status' => $response->getStatusCode(),
                'body' => json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR),
            ], JSON_THROW_ON_ERROR);
        } finally {
            $this->kernel->terminate($request, $response);
        }
    }

    /**
     * Подключает тестовую БД и помечает соединение для наблюдения блокировок.
     *
     * Маркер виден в pg_stat_activity. Таймаут не даёт зависшему запросу
     * бесконечно удерживать дочерний процесс при ошибке теста.
     *
     * @param  array<string, mixed>  $database  Настройки PostgreSQL родительского теста
     * @param  string  $marker  Уникальное имя дочернего соединения
     *
     * @throws QueryException Если PostgreSQL отклонил настройку имени соединения или таймаута
     */
    private function configureConnection(array $database, string $marker): void
    {
        config([
            'database.default' => 'pgsql',
            'database.connections.pgsql' => $database,
        ]);
        DB::purge('pgsql');
        DB::select("select set_config('application_name', ?, false)", [$marker]);
        DB::statement("set lock_timeout = '10s'");
    }

    /**
     * Собирает JSON-запрос к API с отдельным токеном второго клиента.
     *
     * @param  string  $token  Токен Sanctum второго клиента
     * @param  array<string, mixed>  $payload  Параметры создания брони
     * @return Request Запрос, который пройдёт через маршрутизацию и middleware Laravel
     *
     * @throws JsonException Если тело запроса невозможно сериализовать
     */
    private function createRequest(string $token, array $payload): Request
    {
        return Request::create(
            uri: '/api/bookings',
            method: 'POST',
            server: [
                'HTTP_ACCEPT' => 'application/json',
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            ],
            content: json_encode($payload, JSON_THROW_ON_ERROR),
        );
    }
}
