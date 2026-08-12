<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Concurrency probe
|--------------------------------------------------------------------------
|
| Boots the framework and consumes exactly one document number, then prints it.
|
| tests/Unit/DocumentNumbererTest.php launches twenty of these at once. Running
| the numberer twenty times in a loop inside one process would prove nothing —
| the whole point is that two separate database connections must not be handed
| the same sequence value, and that only happens with real processes.
|
| Usage: php tests/Support/next-number.php quote
|
*/

use App\Enums\DocType;
use App\Services\DocumentNumberer;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;

require __DIR__.'/../../vendor/autoload.php';

/** @var Application $app */
$app = require __DIR__.'/../../bootstrap/app.php';

$app->make(Kernel::class)->bootstrap();

$type = DocType::from($argv[1] ?? 'quote');

echo $app->make(DocumentNumberer::class)->next($type)->number, PHP_EOL;
