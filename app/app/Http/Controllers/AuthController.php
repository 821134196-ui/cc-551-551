<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => '邮箱或密码错误。'], 401);
        }

        if (! $user->api_token) {
            $user->forceFill(['api_token' => Str::random(48)])->save();
        }

        return response()->json([
            'token' => $user->api_token,
            'user' => $this->presentUser($user->load('advisor')),
        ]);
    }

    public function me(Request $request)
    {
        return response()->json([
            'user' => $this->presentUser($request->user()->load('advisor')),
        ]);
    }

    private function presentUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'advisor_id' => $user->advisor_id,
            'advisor' => $user->relationLoaded('advisor') && $user->advisor ? [
                'id' => $user->advisor->id,
                'name' => $user->advisor->name,
            ] : null,
        ];
    }
}
