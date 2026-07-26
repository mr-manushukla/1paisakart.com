<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request): UserResource
    {
        // No address at sign-up — it's collected at checkout, where it's actually
        // needed. Phone must be unique because it doubles as a login handle.
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20', 'unique:users,phone'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        // Store digits only, so a number typed as "+91 98765 43210" still matches
        // at login however the customer types it next time.
        if (! empty($data['phone'])) {
            $data['phone'] = preg_replace('/\D/', '', $data['phone']);
        }

        // Public sign-up always creates a customer. Vendors are created by the admin.
        $user = new User($data);
        $user->role = 'customer';
        $user->save();

        Auth::login($user);
        $request->session()->regenerate();

        return new UserResource($user);
    }

    /**
     * Sign in with EITHER an email address or a phone number, plus the password.
     * `login` is the single field the form sends; `email` is still accepted so
     * older clients keep working.
     */
    public function login(Request $request): UserResource
    {
        $data = $request->validate([
            'login' => ['required_without:email', 'nullable', 'string', 'max:120'],
            'email' => ['required_without:login', 'nullable', 'string', 'max:120'],
            'password' => ['required', 'string'],
        ]);

        $handle = trim($data['login'] ?? $data['email'] ?? '');
        // Digits-only (ignoring +, spaces, dashes) means they typed a phone number.
        $field = preg_match('/^\+?[\d\s-]+$/', $handle) ? 'phone' : 'email';
        $value = $field === 'phone' ? preg_replace('/\D/', '', $handle) : $handle;

        $credentials = [$field => $value, 'password' => $data['password']];

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages(['login' => 'These credentials do not match our records.']);
        }

        $request->session()->regenerate();

        return new UserResource($request->user()->load('shop'));
    }

    public function logout(Request $request): array
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return ['message' => 'Logged out.'];
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user()->load('shop'));
    }
}
