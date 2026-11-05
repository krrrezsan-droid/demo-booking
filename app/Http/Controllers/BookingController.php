<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBookingRequest;
use App\Models\Booking;
use App\Models\Room;
use App\Modules\Booking\Actions\CancelBookingAction;
use App\Modules\Booking\Actions\CreateBookingAction;
use App\Modules\Booking\Services\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

/**
 * HTTP-адаптер API бронирования переговорных.
 */
class BookingController extends Controller
{
    /**
     * Возвращает страницу броней текущего пользователя.
     *
     * @param  Request  $request  Аутентифицированный HTTP-запрос
     * @param  BookingService  $bookings  Сервис чтения бронирований
     * @return LengthAwarePaginator<int, Booking> Страница пользовательского списка броней
     */
    public function index(Request $request, BookingService $bookings): LengthAwarePaginator
    {
        return $bookings->paginateForUser($request->user()->id);
    }

    /**
     * Создаёт бронь по проверенным данным от имени текущего пользователя.
     *
     * @param  StoreBookingRequest  $request  Валидированный запрос создания
     * @param  CreateBookingAction  $create  Операция создания брони
     * @return JsonResponse Созданная бронь с HTTP-статусом 201
     */
    public function store(StoreBookingRequest $request, CreateBookingAction $create): JsonResponse
    {
        $booking = $create->execute($request->toData(), $request->user()->id);

        return response()->json($booking, 201);
    }

    /**
     * Отменяет найденную бронь с проверкой прав внутри операции отмены.
     *
     * @param  Request  $request  Аутентифицированный HTTP-запрос
     * @param  Booking  $booking  Бронь, найденная по параметру маршрута
     * @param  CancelBookingAction  $cancel  Операция отмены брони
     * @return Response Пустой ответ с HTTP-статусом 204
     */
    public function destroy(Request $request, Booking $booking, CancelBookingAction $cancel): Response
    {
        $cancel->execute($booking, $request->user()->id);

        return response()->noContent();
    }

    /**
     * Возвращает расписание выбранной комнаты на сегодня.
     *
     * @param  Room  $room  Комната, найденная по параметру маршрута
     * @param  BookingService  $bookings  Сервис чтения бронирований
     * @return Collection<int, array{time: string, author: string}> Интервалы встреч и имена авторов
     */
    public function today(Room $room, BookingService $bookings): Collection
    {
        return $bookings->todayForRoom($room);
    }
}
