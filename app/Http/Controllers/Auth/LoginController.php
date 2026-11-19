<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\HomeController;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\Settings\SettingsManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class LoginController
{
    const bool REMEMBER = true;

    public function loginForm(): Response
    {
        return Inertia::render('auth/login');
    }

    public function processLogin(LoginRequest $request, SettingsManager $settings): RedirectResponse
    {
        $data = $request->validated();

        if (Auth::attempt($data, self::REMEMBER)) {
            $request->session()->regenerate();

            // Changing it is suggested, never required: the page offers to do it now or later.
            if ($settings->usesDefaultPassword($request->user(), $request->session())) {
                Inertia::flash(['default_password' => true]);
            }

            return to_action(HomeController::class);
        }

        return back()->withErrors(['failed' => __('auth.failed')])->onlyInput('name');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return to_action([self::class, 'loginForm']);
    }
}
