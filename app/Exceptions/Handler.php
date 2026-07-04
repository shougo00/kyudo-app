<?php

namespace App\Exceptions;

use Throwable;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;

class Handler extends ExceptionHandler
{
    public function render($request, Throwable $exception)
    {
        if ($exception instanceof TokenMismatchException) {
            return redirect()->route('login')
                ->with('status', 'セッションが切れました。再ログインしてください');
        }

        return parent::render($request, $exception);
    }
}