<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $user = User::where('username', strtolower($credentials['username']))->first();
        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return response()->json(['message' => 'Username atau kata sandi salah.'], 422);
        }

        $token = $user->createToken($credentials['device_name'] ?? 'flutter-app')->plainTextToken;
        $payload = $user->apiData();

        return response()->json(['token' => $token, 'user' => $payload]);
    }

    public function me(Request $request): JsonResponse
    {
        $payload = $request->user()->apiData();

        return response()->json(['user' => $payload]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Berhasil keluar.']);
    }
}
