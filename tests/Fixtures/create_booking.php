<?php

use Illuminate\Contracts\Http\Kernel;
use Tests\Support\BookingRequestRunner;

/**
 * Точка входа дочернего процесса теста конкурентности.
 * Параметры передаются через stdin, результат возвращается через stdout.
 */
require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$runner = new BookingRequestRunner($app->make(Kernel::class));

echo $runner->run(stream_get_contents(STDIN));
