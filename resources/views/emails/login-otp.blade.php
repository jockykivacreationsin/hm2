<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Staff Login Code</title>
<style>
body { margin:0; padding:0; background:#f4f7fb; font-family:'Segoe UI',system-ui,sans-serif; color:#1f2937; }
.wrap { max-width:520px; margin:40px auto; background:#fff; border-radius:12px; box-shadow:0 2px 16px rgba(26,58,92,.10); overflow:hidden; }
.header { background:#1a3a5c; padding:28px 40px; text-align:center; }
.header h1 { margin:0; font-size:20px; font-weight:700; color:#fff; }
.header p  { margin:6px 0 0; font-size:13px; color:rgba(255,255,255,.75); }
.body { padding:32px 40px 28px; }
.body p { font-size:14.5px; line-height:1.7; margin:0 0 16px; }
.code-row { margin:24px 0; padding:16px 24px; background:#f0f4ff; border-left:4px solid #1a3a5c; border-radius:4px; }
.code-row .label { font-size:12px; color:#6b7280; margin-bottom:4px; }
.code-row .code  { font-size:32px; font-weight:700; letter-spacing:8px; color:#1a3a5c; font-family:monospace; }
.note { font-size:13px; color:#6b7280; background:#f9fafb; border:1px solid #e5e7eb; border-radius:4px; padding:10px 14px; }
.footer { border-top:1px solid #e5e7eb; padding:16px 40px; text-align:center; font-size:12px; color:#9ca3af; }
</style>
</head>
<body>
<div class="wrap">
    <div class="header">
        <h1>{{ config('app.name') }}</h1>
        <p>Staff Portal — Login Code</p>
    </div>
    <div class="body">
        <p>Hi <strong>{{ $staffName }}</strong>,</p>
        <p>Use the code below to complete your login. It is valid for <strong>5 minutes</strong>.</p>

        <div class="code-row">
            <div class="label">One-time login code</div>
            <div class="code">{{ $otp }}</div>
        </div>

        <p class="note">Do not share this code with anyone. If you did not request this, please contact your administrator.</p>
    </div>
    <div class="footer">
        {{ config('app.name') }} &mdash; {{ config('app.url') }}<br>
        Do not reply to this email.
    </div>
</div>
</body>
</html>
