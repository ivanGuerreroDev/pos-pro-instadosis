@extends('layouts.auth.app')

@section('title')
    {{ __('Login') }}
@endsection

@section('main_content')
<div class="footer">
    <div class="footer-logo w-100 mx-4">
        <img src="{{ asset('assets/images/login/loginlogo.png') }}" alt="">
    </div>
    <div class="mybazar-login-section">
        <div class="mybazar-login-avatar">
            <img src="{{ asset('assets/images/login/login.png') }}" alt="">
        </div>
        <div class="mybazar-login-wrapper">
            <div class="login-wrapper">
                <div class="login-body w-100">
                    <h2>{{ __('Welcome to') }}<span>{{ __(env('APP_NAME')) }}</span></h2>
                    <h6>{{ __('Welcome back, Please login in to your account') }}</h6>
                    <form method="POST" action="{{ route('login') }}" class="ajaxform_instant_reload">
                        @csrf
                        <div class="input-group">
                            <span><img src="{{ asset('assets/images/icons/user.png') }}" alt="img"></span>
                            <input type="email" name="email" class="form-control email" placeholder="{{ __('Enter your Email') }}">
                        </div>

                        <div class="input-group">
                            <span><img src="{{ asset('assets/images/icons/lock.png') }}" alt="img"></span>
                            <span class="hide-pass">
                                <img src="{{ asset('assets/images/icons/Hide.svg') }}" alt="img">
                                <img src="{{ asset('assets/images/icons/show.svg') }}" alt="img">
                            </span>
                            <input type="password" name="password" class="form-control password" placeholder="{{ __('Password') }}">
                        </div>

                        <div class="mt-lg-3 mb-0 forget-password">
                            <label class="custom-control-label">
                                <input type="checkbox" name="remember" class="custom-control-input">
                                <span>{{ __('Remember me') }}</span>
                            </label>
                            <a href="{{ route('password.request') }}">{{ ('Forgot Password?') }}</a>
                        </div>

                        <button type="submit" class="btn login-btn submit-btn">{{ __('Log In') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
    <input type="hidden" data-model="Login" id="auth">
@endsection

@push('js')
<script src="{{ asset('assets/js/auth.js') }}"></script>
@endpush



