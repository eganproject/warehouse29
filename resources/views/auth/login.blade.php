<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Metronic') }} | {{ __('Sign In') }}</title>
    <link rel="shortcut icon" href="{{ asset('metronic/media/logos/logo-demo3.png') }}">
    <link href="{{ asset('metronic/plugins/global/plugins.bundle.css') }}" rel="stylesheet" type="text/css">
    <link href="{{ asset('metronic/css/style.bundle.css') }}" rel="stylesheet" type="text/css">
</head>
<body id="kt_body" class="bg-body">
<div class="d-flex flex-column flex-root">
    <div class="d-flex flex-column flex-lg-row flex-column-fluid">
        <!-- Aside -->
        <div class="d-flex flex-column flex-lg-row-auto w-xl-600px positon-xl-relative" style="background-color:#F2C98A;">
            <div class="d-flex flex-column position-xl-fixed top-0 bottom-0 w-xl-600px scroll-y">
                <div class="d-flex flex-row-fluid flex-column text-center p-10 pt-lg-20">
                    <a href="{{ url('/') }}" class="py-9 mb-5">
                        <img alt="Logo" src="{{ asset('metronic/media/logos/logo-demo3.png') }}" class="h-60px">
                    </a>
                    <h1 class="fw-bolder fs-2qx pb-5 pb-md-10" style="color:#986923;">
                        {{ __('Welcome to') }} {{ config('app.name', 'Metronic') }}
                    </h1>
                    <p class="fw-bold fs-2" style="color:#986923;">
                        {{ __('Discover reliable visibility & insights for your supply chain') }}
                    </p>
                </div>
                <div class="d-flex flex-row-auto bgi-no-repeat bgi-position-x-center bgi-size-contain bgi-position-y-bottom min-h-100px min-h-lg-350px"
                     style="background-image:url('{{ asset('metronic/media/illustrations/sketchy-1/13.png') }}');"></div>
            </div>
        </div>
        <!-- /Aside -->

        <!-- Body -->
        <div class="d-flex flex-column flex-lg-row-fluid py-10">
            <div class="d-flex flex-center flex-column flex-column-fluid px-5">
                <div class="w-lg-500px p-10 p-lg-15 mx-auto">
                    @if (session('status'))
                        <div class="alert alert-success mb-5">{{ session('status') }}</div>
                    @endif

                    <form class="form w-100" method="POST" action="{{ route('login') }}" id="kt_sign_in_form">
                        @csrf
                        <div class="text-center mb-10">
                            <h1 class="text-dark mb-3">{{ __('Sign In to') }} {{ config('app.name', 'Metronic') }}</h1>
                            <div class="text-gray-400 fw-bold fs-4">
                                {{ __('New here?') }}
                                @if (Route::has('register'))
                                    <a href="{{ route('register') }}" class="link-primary fw-bolder">{{ __('Create an account') }}</a>
                                @endif
                            </div>
                        </div>

                        <div class="fv-row mb-10">
                            <label class="form-label fs-6 fw-bolder text-dark">{{ __('Email') }}</label>
                            <input class="form-control form-control-lg form-control-solid @error('email') is-invalid @enderror"
                                   type="email"
                                   name="email"
                                   value="{{ old('email') }}"
                                   required autofocus autocomplete="username">
                            @error('email')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="fv-row mb-10">
                            <div class="d-flex flex-stack mb-2">
                                <label class="form-label fw-bolder text-dark fs-6 mb-0">{{ __('Password') }}</label>
                                @if (Route::has('password.request'))
                                    <a href="{{ route('password.request') }}" class="link-primary fs-6 fw-bolder">{{ __('Forgot Password?') }}</a>
                                @endif
                            </div>
                            <input class="form-control form-control-lg form-control-solid @error('password') is-invalid @enderror"
                                   type="password"
                                   name="password"
                                   required autocomplete="current-password">
                            @error('password')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="d-flex justify-content-between mb-7">
                            <label class="form-check form-check-custom form-check-solid">
                                <input class="form-check-input" type="checkbox" name="remember">
                                <span class="form-check-label text-gray-600">{{ __('Remember me') }}</span>
                            </label>
                        </div>

                        <div class="text-center">
                            <button type="submit" id="kt_sign_in_submit" class="btn btn-lg btn-primary w-100 mb-5">
                                <span class="indicator-label">{{ __('Continue') }}</span>
                                <span class="indicator-progress">
                                    {{ __('Please wait...') }}
                                    <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                                </span>
                            </button>

                        </div>
                    </form>
                </div>
            </div>

            <div class="d-flex flex-center flex-wrap fs-6 p-5 pb-0">
                <div class="d-flex flex-center fw-bold fs-6">
                    <a href="https://keenthemes.com" class="text-muted text-hover-primary px-2" target="_blank">About</a>
                    <a href="https://keenthemes.com/support" class="text-muted text-hover-primary px-2" target="_blank">Support</a>
                    <a href="https://1.envato.market/EA4JP" class="text-muted text-hover-primary px-2" target="_blank">Purchase</a>
                </div>
            </div>
        </div>
        <!-- /Body -->
    </div>
</div>

<script src="{{ asset('metronic/plugins/global/plugins.bundle.js') }}"></script>
<script src="{{ asset('metronic/js/scripts.bundle.js') }}"></script>
</body>
</html>
