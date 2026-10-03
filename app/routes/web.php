<?php

use Illuminate\Support\Facades\Route;

// 所有非 API 页面返回 React 单页应用
Route::view('/{any?}', 'app')->where('any', '^(?!api|up).*$');
