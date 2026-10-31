<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Reset Password</title>
    <link rel="shortcut icon" href="{{ asset('assets/images/logo/icon-logo.png') }}" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="{{ asset('assets/css/vendor/font-awesome.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/css/vendor/bootstrap.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/css/style.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/css/custom.css') }}" rel="stylesheet">
    <style>
        .fp-btn  { display: block; width: 100%; }
        .fp-back { font-size: 13.5px; color: #3b6fc9; }
        .fp-back:hover { color: #1a3a5c; }
        .pw-match-error { font-size: 12px; color: #dc2626; margin: 4px 0 0; display: none; }
        /* ── Password strength bar ── */
        .pw-strength-wrap { margin: 6px 0 0; }
        .pw-strength-bar  { height: 4px; border-radius: 2px; background: #e5e7eb; overflow: hidden; }
        .pw-strength-fill { height: 100%; width: 0; border-radius: 2px; transition: width .2s, background .2s; }
        .pw-strength-text { font-size: 11px; margin-top: 3px; color: #6b7280; }
        .pw-rules         { font-size: 11px; color: #6b7280; margin: 6px 0 0; line-height: 1.7; }
        .pw-rules li      { list-style: none; padding: 0; }
        .pw-rules .ok     { color: #16a34a; }
        .pw-rules .ok::before   { content: '✓ '; }
        .pw-rules .fail::before { content: '✗ '; color: #dc2626; }
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
                <h6>Set New Password</h6>
            </div>

            @if($errors->any())
                <div class="alert alert-danger py-2 px-3 mb-3" style="font-size:13px;border-radius:8px;">
                    <i class="fa fa-exclamation-circle me-1"></i> {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('password.update') }}" id="reset-form">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">

                <div class="form-group local-forms">
                    <label>Email Address <span class="req-star">*</span></label>
                    <input class="form-control" type="email" name="email"
                           value="{{ old('email') }}"
                           placeholder="Your registered email"
                           autofocus required>
                </div>

                <div class="form-group local-forms">
                    <label>New Password <span class="req-star">*</span></label>
                    <div class="input-group group-input">
                        <input class="form-control showhide-password" type="password"
                               name="password" id="rp_password"
                               placeholder="Min 8 chars, upper, lower, digit, symbol"
                               minlength="8" required autocomplete="new-password">
                        <span class="input-group-text toggle-show fa fa-eye"></span>
                    </div>
                    <div class="pw-strength-wrap">
                        <div class="pw-strength-bar"><div class="pw-strength-fill" id="pw-fill"></div></div>
                        <div class="pw-strength-text" id="pw-strength-text"></div>
                    </div>
                    <ul class="pw-rules" id="pw-rules">
                        <li id="pr-len"   class="fail">At least 8 characters</li>
                        <li id="pr-upper" class="fail">At least one uppercase letter (A–Z)</li>
                        <li id="pr-lower" class="fail">At least one lowercase letter (a–z)</li>
                        <li id="pr-digit" class="fail">At least one digit (0–9)</li>
                        <li id="pr-sym"   class="fail">At least one symbol (!@#$ …)</li>
                    </ul>
                </div>

                <div class="form-group local-forms">
                    <label>Confirm Password <span class="req-star">*</span></label>
                    <div class="input-group group-input">
                        <input class="form-control showhide-password" type="password"
                               name="password_confirmation" id="rp_confirm"
                               placeholder="Re-enter new password" required>
                        <span class="input-group-text toggle-show fa fa-eye"></span>
                    </div>
                    <div class="pw-match-error" id="pw-match-msg">
                        <i class="fa fa-exclamation-circle me-1"></i> Passwords do not match.
                    </div>
                </div>

                <div class="form-group">
                    <button type="submit" class="btn btn-primary fp-btn" id="btn-reset">
                        <i class="fa fa-lock me-1"></i> Reset Password
                    </button>
                </div>
            </form>

            <div class="text-center mt-1">
                <a href="{{ route('login') }}" class="fp-back"><i class="fa fa-arrow-left me-1"></i> Back to Login</a>
            </div>
        </div>
    </div>
<script src="{{ asset('assets/js/jquery-3.7.0.min.js') }}"></script>
<script src="{{ asset('assets/js/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('assets/js/custom-script.js') }}"></script>
<script>
var colors  = ['#ef4444','#f97316','#eab308','#22c55e','#16a34a'];
var labels  = ['Very weak','Weak','Fair','Strong','Very strong'];

function checkPw() {
    var pw  = $('#rp_password').val();
    var ok  = {
        len   : pw.length >= 8,
        upper : /[A-Z]/.test(pw),
        lower : /[a-z]/.test(pw),
        digit : /[0-9]/.test(pw),
        sym   : /[^A-Za-z0-9]/.test(pw)
    };
    $('#pr-len').attr('class',   ok.len   ? 'ok' : 'fail');
    $('#pr-upper').attr('class', ok.upper ? 'ok' : 'fail');
    $('#pr-lower').attr('class', ok.lower ? 'ok' : 'fail');
    $('#pr-digit').attr('class', ok.digit ? 'ok' : 'fail');
    $('#pr-sym').attr('class',   ok.sym   ? 'ok' : 'fail');

    var score = Object.values(ok).filter(Boolean).length; // 0–5
    var pct   = score * 20;
    $('#pw-fill').css({ width: pct + '%', background: colors[Math.max(0, score - 1)] });
    $('#pw-strength-text').text(pw.length ? labels[Math.max(0, score - 1)] : '');
    return Object.values(ok).every(Boolean);
}

$('#rp_password').on('input', function () {
    checkPw();
    var cf = $('#rp_confirm').val();
    var mismatch = cf.length > 0 && $(this).val() !== cf;
    $('#pw-match-msg').toggle(mismatch);
    $('#btn-reset').prop('disabled', mismatch);
});

$('#rp_confirm').on('input', function () {
    var mismatch = $(this).val().length > 0 && $('#rp_password').val() !== $(this).val();
    $('#pw-match-msg').toggle(mismatch);
    $('#btn-reset').prop('disabled', mismatch);
});

$('#reset-form').on('submit', function (e) {
    if (!checkPw()) {
        e.preventDefault();
        return;
    }
    if ($('#rp_password').val() !== $('#rp_confirm').val()) {
        e.preventDefault();
        $('#pw-match-msg').show();
    }
});
</script>
</body>
</html>
