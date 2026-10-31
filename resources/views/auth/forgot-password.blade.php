<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Forgot Password</title>
    <link rel="shortcut icon" href="{{ asset('assets/images/logo/icon-logo.png') }}" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="{{ asset('assets/css/vendor/font-awesome.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/css/vendor/bootstrap.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/css/style.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/css/custom.css') }}" rel="stylesheet">
    <style>
        .fp-btn  { display: block; width: 100%; padding: 11px; font-size: 15px; font-weight: 600; border-radius: 6px; background: #1a3a5c; border: none; color: #fff; margin-top: 4px; letter-spacing: .02em; transition: background .2s; }
        .fp-btn:hover  { background: #14304e; color: #fff; }
        .fp-back { font-size: 13.5px; color: #3b6fc9; }
        .fp-back:hover { color: #1a3a5c; }
        .fp-desc  { font-size: 13.5px; }
        .fp-error { font-size: 13px; border-radius: 12px; }
        .alert-success-box   { background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 8px; padding: 14px 18px; text-align: center; }
        .alert-success-box i { font-size: 26px; color: #059669; display: block; margin-bottom: 8px; }
        .alert-success-box p { margin: 0; font-size: 13.5px; color: #065f46; line-height: 1.6; }
    </style>
</head>
<body>
    <div class="auth-main">
        <div class="codex-authbox">
            <div class="auth-header">
                <div class="nl-sp-logo">
                    <img class="sp-logo" src="{{ asset('assets/images/hlogo.png') }}" alt="">
                    <h3>{{ $hospitalName ?? config('app.name') }}</h3>
                </div>
                <h6>Reset Your Password</h6>
            </div>

            @if(session('status'))
                <div class="alert-success-box mb-3">
                    <i class="fa fa-check-circle"></i>
                    <p>{{ session('status') }}</p>
                </div>
                <div class="text-center mt-2">
                    <a href="{{ route('login') }}" class="fp-back"><i class="fa fa-arrow-left me-1"></i> Back to Login</a>
                </div>
            @else
                <p class="text-muted mb-14 fp-desc">Enter your registered email address and we'll send you a link to reset your password.</p>

                @error('email')
                    <div class="alert alert-danger py-2 px-3 mb-14 fp-error">
                        <i class="fa fa-exclamation-circle me-1"></i> {{ $message }}
                    </div>
                @enderror

                <form method="POST" action="{{ route('password.email') }}">
                    @csrf
                    <div class="form-group local-forms">
                        <label>Email Address <span class="req-star">*</span></label>
                        <input class="form-control" type="email" name="email"
                               value="{{ old('email') }}"
                               placeholder="Enter your registered email"
                               autofocus required>
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary fp-btn">
                            <i class="fa fa-paper-plane me-1"></i> Send Reset Link
                        </button>
                    </div>
                </form>

                <div class="text-center mt-1">
                    <a href="{{ route('login') }}" class="fp-back"><i class="fa fa-arrow-left me-1"></i> Back to Login</a>
                </div>
            @endif
        </div>
    </div>
<script src="{{ asset('assets/js/jquery-3.7.0.min.js') }}"></script>
<script src="{{ asset('assets/js/bootstrap.bundle.min.js') }}"></script>
</body>
</html>
