@extends('layouts.app')
@section('title', 'ABDM Settings')

@push('styles')
<style>
/* ── Lock screen ── */
@if(! $is_authenticated)
.abdm-gate-wrap {
    min-height: calc(100vh - 208px);
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f4f7fb;
    padding: 40px 20px;
}
.abdm-gate-card {
    width: 100%;
    max-width: 440px;
    background: #fff;
    border: 1px solid #e3e8ef;
    border-radius: 16px;
    padding: 30px 36px 30px;
    box-shadow: 0 4px 40px rgba(26,58,92,.11);
}
.abdm-gate-icon {
    width: 80px; height: 80px; border-radius: 50%;
    background: linear-gradient(135deg,#1a3a5c 55%,#3b6fc9 100%);
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 22px;
}
.abdm-gate-icon i { font-size: 32px; color: #fff; }
.abdm-gate-title  { font-size: 22px; font-weight: 700; color: #1a3a5c; margin-bottom: 8px; }
.abdm-gate-sub    { color: #6b7280; font-size: 14px; line-height: 1.6; margin: 0 0 28px; }
.abdm-gate-label  { font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 7px; display: block; }
.abdm-gate-footer { font-size: 12px; color: #9ca3af; margin: 20px 0 0; }
@endif
/* ── Shared gate elements ── */
.gate-alert      { font-size: 13px; border-radius: 8px; }
.gate-pw-input   { border-radius: 8px 0 0 8px !important; font-size: 14.5px; padding: 8px 15px; height: auto; }
.gate-pw-toggle  { border-radius: 0 8px 8px 0 !important; border-color: #dee2e6; padding: 0 16px; }
.gate-btn-unlock { padding: 13px; font-size: 15px; font-weight: 600; border-radius: 8px;
                   background: #1a3a5c; border-color: #1a3a5c; letter-spacing: .03em; }
.gate-btn-unlock:hover,
.gate-btn-unlock:focus { background: #14304e; border-color: #14304e; }
/* ── ABDM settings content ── */
.abdm-content-wrap { padding: 20px; }
.abdm-link         { color: #2563eb; }
.abdm-link:hover   { color: #1d4ed8; }
.field-help-mt     { margin-top: 12px; }
.abdm-settings-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 24px; margin-bottom: 20px; }
.abdm-settings-card h3 { font-size: 18px; margin: 0 0 8px; color: #111827; }
.abdm-settings-card .help-text { font-size: 13px; color: #6b7280; margin-bottom: 20px; }
.abdm-form-group { margin-bottom: 18px; }
.abdm-form-group label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: #374151; }
.abdm-form-group input[type="text"],
.abdm-form-group input[type="password"] { width: 100%; max-width: 600px; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; }
.abdm-form-group .field-help { font-size: 12px; color: #6b7280; margin-top: 4px; }
.abdm-btn-group { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 20px; }
.abdm-btn { padding: 10px 18px; border: none; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer; }
.abdm-btn-primary { background: #2563eb; color: #fff; }
.abdm-btn-primary:hover { background: #1d4ed8; }
.abdm-btn-secondary { background: #111827; color: #fff; }
.abdm-btn-secondary:hover { background: #000; }
.abdm-alert { padding: 12px 16px; border-radius: 6px; font-size: 13px; margin-bottom: 16px; }
.abdm-alert-success { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
.abdm-alert-error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
.abdm-response-box { background: #0b1021; color: #d1fae5; padding: 14px; border-radius: 8px; font-size: 12px; overflow-x: auto; white-space: pre-wrap; word-break: break-word; max-height: 360px; font-family: 'Courier New', monospace; }
.field-with-toggle { position: relative; display: inline-block; width: 100%; max-width: 600px; }
.field-with-toggle input { padding-right: 70px; }
.toggle-btn { position: absolute; right: 6px; top: 6px; border: none; background: #f3f4f6; border-radius: 5px; padding: 6px 10px; font-size: 12px; cursor: pointer; color: #374151; }
.toggle-btn:hover { background: #e5e7eb; }
</style>
@endpush

@section('content')
@if(! $is_authenticated)
{{-- ══════════════════════════════════════════════════════
     LOCK SCREEN — fills content column, sidebar visible
     ══════════════════════════════════════════════════════ --}}
<div class="themebody-wrap abdm-gate-wrap">
    <div class="abdm-gate-card">

        <div class="text-center">
            <div class="abdm-gate-icon">
                <i class="fa fa-lock"></i>
            </div>
            <h4 class="abdm-gate-title">ABDM Settings Protected</h4>
            <p class="abdm-gate-sub">
                Enter the admin password to unlock<br>and modify ABDM settings.
            </p>
        </div>

        @if(session('abdm_settings_password_error'))
            <div class="alert alert-danger py-2 px-3 mb-4 gate-alert">
                <i class="fa fa-exclamation-circle me-1"></i> {{ session('abdm_settings_password_error') }}
            </div>
        @endif

        <form method="POST" action="{{ route('abdm.settings.unlock') }}">
            @csrf
            <div class="mb-4">
                <label class="abdm-gate-label">Admin Password</label>
                <div class="input-group">
                    <input type="password" class="form-control gate-pw-input" name="admin_password"
                           id="abdm_gate_password"
                           autocomplete="current-password"
                           placeholder="Enter admin password"
                           autofocus required>
                    <button class="btn btn-outline-secondary btn-pw-toggle gate-pw-toggle" type="button"
                            data-target="abdm_gate_password" tabindex="-1">
                        <i class="fa fa-eye"></i>
                    </button>
                </div>
            </div>
            <div class="d-grid">
                <button type="submit" name="admin_password_submit" value="1"
                        class="btn btn-primary gate-btn-unlock">
                    <i class="fa fa-unlock me-2"></i>Unlock Settings
                </button>
            </div>
        </form>

        <p class="text-center abdm-gate-footer">
            <i class="fa fa-shield-alt me-1"></i>Protected by admin password
        </p>
    </div>
</div>
@else
{{-- ══════════════════════════════════════════════════════
     ABDM SETTINGS (unlocked)
     ══════════════════════════════════════════════════════ --}}
<div class="themebody-wrap">
    <div class="card-ipd">
        <div class="card-body">
            <div class="ipd-title-with-buttons row-bottom-border">
                <div class="ipd-title"><i class="fa-solid fa-shield-halved"></i> ABDM Settings</div>
                <div>
                    {{-- POST + CSRF: a GET link could be triggered by any page (e.g. an img tag) to force-lock the settings. --}}
                    <form method="POST" action="{{ route('abdm.settings.logout') }}" style="display:inline">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-sign-out-alt"></i> Lock
                        </button>
                    </form>
                </div>
            </div>

            <div class="abdm-content-wrap">

                @if($saved)
                    <div class="abdm-alert abdm-alert-success">✓ Settings saved successfully.</div>
                @endif

                @if($errors->any())
                    <div class="abdm-alert abdm-alert-error">
                        @foreach($errors->all() as $message)<div>{{ $message }}</div>@endforeach
                    </div>
                @endif

                <div class="abdm-settings-card">
                    <h3>ABDM Credentials &amp; Configuration</h3>
                    <p class="help-text">Sandbox credentials and Bridge Callback URL for this facility.</p>

                    <form method="post" action="{{ route('abdm.settings.save') }}" id="abdm-settings-form">
                        @csrf
                        <div class="abdm-form-group">
                            <label for="abdm_client_id">ABDM Client ID</label>
                            <input type="text" id="abdm_client_id" name="abdm_client_id" maxlength="255"
                                value="{{ old('abdm_client_id', $settings_row['abdm_client_id'] ?? '') }}" autocomplete="off">
                        </div>

                        <div class="abdm-form-group">
                            <label for="abdm_client_secret">ABDM Client Secret</label>
                            <div class="field-with-toggle">
                                <input type="password" id="abdm_client_secret" name="abdm_client_secret" maxlength="255"
                                    value="{{ old('abdm_client_secret', $settings_row['abdm_client_secret'] ?? '') }}" autocomplete="off">
                                <button type="button" class="toggle-btn" onclick="abdmTogglePassword('abdm_client_secret', this)">Show</button>
                            </div>
                        </div>

                        <div class="abdm-form-group">
                            <label for="facility_hfr_id">Facility HFR ID (HIP ID)</label>
                            <input type="text" id="facility_hfr_id" name="facility_hfr_id" maxlength="100"
                                value="{{ old('facility_hfr_id', $settings_row['facility_hfr_id'] ?? '') }}" autocomplete="off">
                        </div>

                        <div class="abdm-form-group">
                            <label for="abdm_hiu_id">ABDM HIU ID</label>
                            <input type="text" id="abdm_hiu_id" name="abdm_hiu_id" maxlength="100"
                                value="{{ old('abdm_hiu_id', $settings_row['abdm_hiu_id'] ?? '') }}" autocomplete="off">
                        </div>

                        <div class="abdm-form-group">
                            <label for="abdm_bridge_callback_base_url">Bridge Callback Base URL</label>
                            <input type="text" id="abdm_bridge_callback_base_url" name="abdm_bridge_callback_base_url" maxlength="255"
                                value="{{ old('abdm_bridge_callback_base_url', $settings_row['abdm_bridge_callback_base_url'] ?? '') }}"
                                placeholder="https://your-tunnel-url.example.com"
                                autocomplete="off">
                            <div class="field-help">This app's base URL (no trailing slash). ABDM appends paths like <code>/api/v3/hip/...</code> to it (e.g. <code>{{ url('/api/v3/hip/token/on-generate-token') }}</code>; the legacy <code>/abdm/api/v3/...</code> paths are served too).</div>
                        </div>

                        <div class="abdm-btn-group">
                            <button type="submit" name="save_settings" value="1" class="abdm-btn abdm-btn-primary">
                                <i class="fas fa-save"></i> Save Settings
                            </button>
                            <button type="submit" formaction="{{ route('abdm.settings.bridge-url') }}" name="update_bridge_url" value="1" class="abdm-btn abdm-btn-secondary"
                                    onclick="return abdmSubmitSaved(this, 'Update the Bridge URL on ABDM with the SAVED Bridge Callback Base URL?');">
                                <i class="fas fa-cloud-upload-alt"></i> Update Bridge URL on ABDM
                            </button>
                            <button type="submit" formaction="{{ route('abdm.settings.bridge-check') }}" name="check_bridge_url" value="1" class="abdm-btn abdm-btn-secondary">
                                <i class="fas fa-search"></i> Check Current Bridge URL
                            </button>
                        </div>
                    </form>
                </div>

                @if($bridge_result !== null)
                    <div class="abdm-settings-card">
                        <h3>{{ $bridge_result['success'] ? '✓ Bridge URL Updated' : '✗ Bridge URL Update Failed' }}</h3>
                        @if($bridge_result['success'])
                            <div class="abdm-alert abdm-alert-success">
                                ABDM accepted the new Bridge URL: <strong>{{ $bridge_result['bridge_url'] }}</strong>
                                (HTTP {{ (int) $bridge_result['patch_http_code'] }}).<br>
                                ABDM can take a few minutes to fully propagate this change.
                            </div>
                            <p class="help-text">Verification (GET /bridge-services, HTTP {{ (int) ($bridge_result['verify_http_code'] ?? 0) }}):</p>
                            <div class="abdm-response-box">{{ (string) ($bridge_result['verify_response'] ?? '') }}</div>
                        @else
                            <div class="abdm-alert abdm-alert-error">
                                <strong>Failed at step "{{ $bridge_result['step'] ?? 'unknown' }}"</strong>
                                {{ isset($bridge_result['http_code']) ? ' (HTTP ' . (int) $bridge_result['http_code'] . ')' : '' }}:
                                {{ (string) ($bridge_result['error'] ?? 'Unknown error') }}
                            </div>
                            @if(!empty($bridge_result['response']))
                                <div class="abdm-response-box">{{ (string) $bridge_result['response'] }}</div>
                            @endif
                            <p class="field-help field-help-mt">
                                If this keeps failing, update the Bridge URL via the sandbox portal:
                                <a href="https://hspsbx.abdm.gov.in/home" target="_blank" rel="noopener" class="abdm-link">https://hspsbx.abdm.gov.in/home</a>
                                → Resource Center → Software Linkage.
                            </p>
                        @endif
                    </div>
                @endif

                @if($bridge_check_result !== null)
                    <div class="abdm-settings-card">
                        <h3>{{ $bridge_check_result['success'] ? 'Current Bridge URL on ABDM' : 'Could Not Fetch Bridge URL' }}</h3>
                        @if($bridge_check_result['success'])
                            @php
                                $bridge     = $bridge_check_result['bridge'];
                                $active_url = is_array($bridge) ? (string) ($bridge['url'] ?? '') : '';
                                $is_active  = is_array($bridge) && !empty($bridge['active']);
                                $saved_url  = rtrim((string) ($settings_row['abdm_bridge_callback_base_url'] ?? ''), '/');
                                $matches    = $active_url !== '' && rtrim($active_url, '/') === $saved_url;
                            @endphp
                            @if(!is_array($bridge))
                                <div class="abdm-alert abdm-alert-error">ABDM returned no "bridge" object - see the raw response below.</div>
                            @else
                                <div class="abdm-alert {{ $matches ? 'abdm-alert-success' : 'abdm-alert-error' }}">
                                    <strong>Active Bridge URL:</strong> {{ $active_url }}<br>
                                    <strong>Bridge ID:</strong> {{ (string) ($bridge['id'] ?? '') }}
                                    &nbsp;|&nbsp;
                                    <strong>Active:</strong> {{ $is_active ? 'Yes' : 'No' }}
                                    &nbsp;|&nbsp;
                                    <strong>Blocklisted:</strong> {{ !empty($bridge['blocklisted']) ? 'Yes' : 'No' }}
                                    @if(!$matches)
                                        <br><br>⚠ This does <strong>not</strong> match the Bridge Callback Base URL currently saved ({{ $saved_url }}).
                                        Callbacks from ABDM are going to the URL shown above. Use "Update Bridge URL on ABDM" to sync them.
                                    @else
                                        <br><br>✓ This matches the Bridge Callback Base URL currently saved.
                                    @endif
                                </div>
                            @endif
                            <p class="help-text">Raw response (GET /bridge-services, HTTP {{ (int) $bridge_check_result['http_code'] }}):</p>
                            <div class="abdm-response-box">{{ (string) $bridge_check_result['raw_response'] }}</div>
                        @else
                            <div class="abdm-alert abdm-alert-error">
                                <strong>Failed at step "{{ $bridge_check_result['step'] ?? 'unknown' }}"</strong>
                                {{ isset($bridge_check_result['http_code']) ? ' (HTTP ' . (int) $bridge_check_result['http_code'] . ')' : '' }}:
                                {{ (string) ($bridge_check_result['error'] ?? 'Unknown error') }}
                            </div>
                            @if(!empty($bridge_check_result['response']))
                                <div class="abdm-response-box">{{ (string) $bridge_check_result['response'] }}</div>
                            @endif
                        @endif
                    </div>
                @endif

            </div>
        </div>
    </div>
</div>
@endif {{-- end locked / unlocked --}}
@endsection

@push('scripts')
<script>
// Password show/hide toggle (lock-screen gate field)
$(document).on('click', '.btn-pw-toggle', function () {
    var $btn   = $(this);
    var $input = $('#' + $btn.data('target'));
    var isText = $input.attr('type') === 'text';
    $input.attr('type', isText ? 'password' : 'text');
    $btn.find('i').toggleClass('fa-eye', !isText).toggleClass('fa-eye-slash', isText);
});

function abdmTogglePassword(fieldId, btn) {
    var el = document.getElementById(fieldId);
    if (el.type === 'password') {
        el.type = 'text';
        btn.textContent = 'Hide';
    } else {
        el.type = 'password';
        btn.textContent = 'Show';
    }
}

/* "Update Bridge URL" works on the SAVED row (legacy behaviour) - warn when the form has unsaved edits. */
var abdmSettingsInitial = null;
$(function () {
    var f = document.getElementById('abdm-settings-form');
    if (f) { abdmSettingsInitial = $(f).serialize(); }
});
function abdmSubmitSaved(btn, msg) {
    var f = document.getElementById('abdm-settings-form');
    if (f && abdmSettingsInitial !== null && $(f).serialize() !== abdmSettingsInitial) {
        return confirm('You have unsaved changes - this action uses the last SAVED values. Continue anyway?');
    }
    return true;
}
</script>
@endpush
