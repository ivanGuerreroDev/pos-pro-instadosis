<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use App\Mail\WelcomeMail;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class RegisteredUserController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:15'],
            'password' => ['required', 'min:4', 'max:15'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'client_secret' => $request->role == 'user' ? Str::uuid() : null,
        ]);

        $data = [
            'url' => route('login'),
            'name' => $request->name,
            'login_id' => $request->email,
            'password' => $request->password,
        ];

        if (env('MAIL_USERNAME')) {
            if (env('QUEUE_MAIL')) {
                Mail::to($request->email)->queue(new WelcomeMail($data));
            } else {
                Mail::to($request->email)->send(new WelcomeMail($data));
            }
        } else {
            return response()->json([
                'message' => __('Please setup you mail credentials. For sending mail to the users.'),
            ], 406);
        }

        Auth::login($user);

        sendNotification($user->id, route('admin.users.index', ['users' => 'user']), __('New user Registerd.'));

        return response()->json([
            'message' => __('Registerd Successfully'),
            'redirect' => url('/'),
        ]);
    }
}
