<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Reset Your Password</title>
<style>
body { margin:0; padding:0; background:#f4f7fb; font-family:'Segoe UI',system-ui,sans-serif; color:#1f2937; }
.wrap { max-width:520px; margin:40px auto; background:#fff; border-radius:12px; box-shadow:0 2px 16px rgba(26,58,92,.10); overflow:hidden; }
.header { background:linear-gradient(135deg,#1a3a5c 55%,#3b6fc9 100%); padding:32px 40px 28px; text-align:center; }
.header h1 { margin:0; font-size:22px; font-weight:700; color:#fff; letter-spacing:.02em; }
.header p  { margin:6px 0 0; font-size:13.5px; color:rgba(255,255,255,.80); }
.body { padding:32px 40px 28px; }
.body p { font-size:14.5px; line-height:1.7; margin:0 0 16px; }
.btn-wrap { text-align:center; margin:28px 0 24px; }
.btn { display:inline-block; padding:13px 36px; background:#1a3a5c; color:#fff !important; font-size:15px; font-weight:600; border-radius:8px; text-decoration:none; letter-spacing:.02em; }
.link-fallback { font-size:12px; color:#6b7280; word-break:break-all; margin:0 0 16px; }
.link-fallback a { color:#3b6fc9; }
.expiry { font-size:13px; color:#9ca3af; background:#f9fafb; border:1px solid #e5e7eb; border-radius:6px; padding:10px 14px; margin:0 0 16px; }
.footer { border-top:1px solid #e5e7eb; padding:18px 40px; text-align:center; font-size:12px; color:#9ca3af; }
</style>
</head>
<body>
<div class="wrap">
    <div class="header">
        <h1>🏥 Password Reset Request</h1>
        <p>{{ config('app.name') }}</p>
    </div>
    <div class="body">
        <p>Hi <strong>{{ $staffName }}</strong>,</p>
        <p>We received a request to reset the password for your staff account. Click the button below to set a new password:</p>

        <div class="btn-wrap">
            <a href="{{ route('password.reset', $token) }}" class="btn">Reset My Password</a>
        </div>

        <p class="expiry">⏱ This link will expire in <strong>60 minutes</strong>.</p>

        <p class="link-fallback">
            If the button doesn't work, copy and paste this link into your browser:<br>
            <a href="{{ route('password.reset', $token) }}">{{ route('password.reset', $token) }}</a>
        </p>

        <p>If you did not request a password reset, you can safely ignore this email — your password will not change.</p>
    </div>
    <div class="footer">
        This email was sent by {{ config('app.name') }}. Do not reply to this email.
    </div>
</div>
</body>
</html>
