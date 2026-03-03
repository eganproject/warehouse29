<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Console\Commands\RecalculatePoLineFulfillment;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        RecalculatePoLineFulfillment::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'activity.log' => \App\Http\Middleware\LogUserActivity::class,
            'menu.permission' => \App\Http\Middleware\AuthorizeMenuPermission::class,
            'restrict.picker' => \App\Http\Middleware\RestrictPickerAccess::class,
        ]);

        $middleware->appendToGroup('web', 'restrict.picker');
        $middleware->appendToGroup('web', 'activity.log');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
