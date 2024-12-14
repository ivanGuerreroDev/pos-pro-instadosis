<?php

namespace App\Http\Controllers\Auth;

use Illuminate\View\View;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\RedirectResponse;
use App\Http\Requests\Auth\LoginRequest;


class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request)
    {
        $request->authenticate();

        $request->session()->regenerate();

        $remember = $request->filled('remember') ? 1 : 0;
        $redirect_url = url('/');
        $user = auth()->user();

        if ($user->role == 'shop-owner' || $user->role == 'staff') {
            Auth::logout();
            return response()->json([
                'redirect' => route('login'),
                'message' => __('You can not login as an business account right now.'),
            ]);
        } else {
            $role = Role::where('name', $user->role)->first();
            $first_role = $role->permissions->pluck('name')->all()[0];
            $page = explode('-', $first_role);
            $redirect_url = route('admin.' . $page[0] . '.index');
        }

        return response()->json([
            'message' => __('Logged In Successfully'),
            'remember' => $remember,
            'redirect' => $redirect_url,
        ]);
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
