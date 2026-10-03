<?php

use Illuminate\Support\Facades\Route;

// 前端 SPA（React）。API 见 routes/api.php，前缀 /api。
Route::get('/{any?}', function () {
    return view('app');
})->where('any', '^(?!api|up|storage).*$');
