<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! $token) {
            return response()->json(['message' => '未提供认证令牌。'], 401);
        }

        // 本地模拟认证：token 明文存库；生产环境应使用 Laravel Sanctum。
        // ?api_token= 用于浏览器 <a download> 无法设置 Authorization 头的场景。
        $secret = $token ?: $request->query('api_token');

        if (! $secret) {
            return response()->json(['message' => '未提供认证令牌。'], 401);
        }

        $user = User::where('api_token', $secret)->first();

        if (! $user) {
            return response()->json(['message' => '认证令牌无效。'], 401);
        }

        auth()->setUser($user);

        return $next($request);
    }
}
