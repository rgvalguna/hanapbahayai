<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'phone'    => 'nullable|string|max:20',
        ]);

        $user = User::create([
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'password' => $validated['password'],
            'phone'    => $validated['phone'] ?? null,
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return response()->json(['data' => $user], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        if (!Auth::attempt($validated, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $request->session()->regenerate();

        return response()->json(['data' => $request->user()]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Logged out.']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['data' => $request->user()->load('profile', 'currentFinances')]);
    }

    public function refresh(Request $request): JsonResponse
    {
        $request->session()->regenerateToken();
        return response()->json(['data' => $request->user()]);
    }

    public function requestOtp(Request $request): JsonResponse
    {
        $validated = $request->validate(['phone' => 'required|string|max:20']);

        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        Cache::put("otp:{$validated['phone']}", $otp, now()->addMinutes(5));

        // TODO: integrate with SMS provider (Semaphore or Vonage)
        $payload = app()->isLocal() ? ['otp' => $otp] : [];

        return response()->json(['message' => 'OTP sent.', ...$payload]);
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => 'required|string|max:20',
            'otp'   => 'required|string|size:6',
        ]);

        $stored = Cache::get("otp:{$validated['phone']}");

        if (!$stored || !hash_equals($stored, $validated['otp'])) {
            return response()->json(['message' => 'Invalid or expired OTP.'], 422);
        }

        Cache::forget("otp:{$validated['phone']}");

        $user = User::where('phone', $validated['phone'])->first();
        if ($user) {
            Auth::login($user);
            $request->session()->regenerate();
            return response()->json(['data' => $user]);
        }

        return response()->json(['verified' => true, 'phone' => $validated['phone']]);
    }

    public function redirectToGoogle(): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        return Socialite::driver('google')->stateless()->redirect();
    }

    public function handleGoogleCallback(Request $request): JsonResponse
    {
        $socialUser = Socialite::driver('google')->stateless()->user();

        $user = User::firstOrCreate(
            ['email' => $socialUser->getEmail()],
            [
                'name'              => $socialUser->getName(),
                'password'          => '',
                'email_verified_at' => now(),
            ],
        );

        Auth::login($user);
        $request->session()->regenerate();

        return response()->json(['data' => $user]);
    }

    public function redirectToApple(): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        return Socialite::driver('apple')->redirect();
    }

    public function handleAppleCallback(Request $request): JsonResponse
    {
        $socialUser = Socialite::driver('apple')->stateless()->user();

        $user = User::firstOrCreate(
            ['email' => $socialUser->getEmail()],
            [
                'name'              => $socialUser->getName() ?? 'Apple User',
                'password'          => '',
                'email_verified_at' => now(),
            ],
        );

        Auth::login($user);
        $request->session()->regenerate();

        return response()->json(['data' => $user]);
    }
}
