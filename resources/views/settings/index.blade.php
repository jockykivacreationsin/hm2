@extends('layouts.app')
@section('title', 'Settings')
@push('styles')
<style>
/* ── Lock screen: fills the content column, sidebar + header stay visible ── */
@if(! $isUnlocked)
.settings-gate-wrap {
    min-height: calc(100vh - 208px);
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f4f7fb;
    padding: 40px 20px;
}
.settings-gate-card {
    width: 100%;
    max-width: 440px;
    background: #fff;
    border: 1px solid #e3e8ef;
    border-radius: 16px;
    padding: 30px 36px 30px;
    box-shadow: 0 4px 40px rgba(26,58,92,.11);
}
.settings-gate-icon {
    width: 80px; height: 80px; border-radius: 50%;
    background: linear-gradient(135deg,#1a3a5c 55%,#3b6fc9 100%);
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 22px;
}
.settings-gate-icon i { font-size: 32px; color: #fff; }
.settings-gate-title { font-size: 22px; font-weight: 700; color: #1a3a5c; margin-bottom: 8px; }
.settings-gate-sub   { color: #6b7280; font-size: 14px; line-height: 1.6; margin: 0 0 28px; }
.settings-gate-label { font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 7px; display: block; }
.settings-gate-footer { font-size: 12px; color: #9ca3af; margin: 20px 0 0; }
@endif
/* ── Shared gate elements (used on both lock screens) ── */
.gate-alert      { font-size: 13px; border-radius: 8px; }
.gate-pw-input   { border-radius: 8px 0 0 8px !important; font-size: 14.5px; padding: 8px 15px; height: auto; }
.gate-pw-toggle  { border-radius: 0 8px 8px 0 !important; border-color: #dee2e6; padding: 0 16px; }
.gate-btn-unlock { padding: 13px; font-size: 15px; font-weight: 600; border-radius: 8px;
                   background: #1a3a5c; border-color: #1a3a5c; letter-spacing: .03em; }
.gate-btn-unlock:hover,
.gate-btn-unlock:focus { background: #14304e; border-color: #14304e; }
/* ── Settings tabs ── */
.settings-section { border: 1px solid #e3e8ef; border-radius: 8px; padding: 18px 18px 8px; margin-bottom: 20px; background: #fff; }
.settings-section-head h6 { margin: 0 0 4px; font-weight: 600; color: #1a3a5c; }
.settings-section-head small { display: block; margin-bottom: 20px; color: #6b7280; }
.settings-link-card { display: flex; justify-content: space-between; align-items: center; gap: 16px; background: #f4f7fb; border: 1px solid #d9e2ef; border-radius: 8px; padding: 14px 18px; margin-bottom: 12px; }
.settings-link-title { font-weight: 600; color: #1a3a5c; }
.settings-link-card small { color: #5b6b7f; }
.settings-note { color: #5b6b7f; font-size: 13px; margin: 0; }
/* ── Settings page specific ── */
.settings-logo-thumb { max-height: 46px; max-width: 140px; }
.settings-hr        { border-color: #e3e8ef; margin: 4px 0 16px; }
.settings-pw-heading { color: #1a3a5c; }
.settings-label-hint { font-weight: 400; }
.settings-alert-sm  { font-size: 13px; }
.settings-backup-heading { color: #1a3a5c; }
.text-storage-ok    { color: #2e7d32; }
.backup-file-item   { flex: 1 1 260px; }
/* ABHA creation terms editor */
.abha-term { display: flex; align-items: flex-start; gap: 10px; margin-bottom: 10px; }
.abha-term-no { flex: 0 0 28px; height: 28px; margin-top: 6px; border-radius: 50%; background: #1a3a5c; color: #fff; font-size: 13px; font-weight: 600; display: flex; align-items: center; justify-content: center; }
.abha-term textarea { flex: 1; min-height: 58px; resize: vertical; }
.abha-term-actions { display: flex; gap: 4px; margin-top: 4px; }
.abha-terms-empty { color: #6b7280; font-style: italic; padding: 6px 0 2px; }
/* Backup tab stat cards */
.backup-stat-card { background: #fff; border: 1px solid #e3e8ef; border-radius: 8px; padding: 16px 20px; display: flex; align-items: center; gap: 14px; height: 100%; }
.backup-stat-icon { flex: 0 0 44px; height: 44px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 18px; }
.backup-stat-icon.icon-total   { background: #eaf0fb; color: #3b6fc9; }
.backup-stat-icon.icon-latest  { background: #e8f5e9; color: #2e7d32; }
.backup-stat-icon.icon-storage { background: #fff8e1; color: #f57f17; }
.backup-stat-label { font-size: 12px; color: #6b7280; font-weight: 500; margin-bottom: 2px; }
.backup-stat-value { font-size: 18px; font-weight: 700; color: #1a3a5c; line-height: 1.2; }
.backup-stat-value.small-val   { font-size: 13px; font-weight: 600; }
/* Recent backup detail card */
.backup-detail-card { background: #f4f7fb; border: 1px solid #d9e2ef; border-radius: 8px; padding: 16px 20px; }
.backup-detail-row { display: flex; flex-wrap: wrap; gap: 10px 28px; align-items: center; }
.backup-detail-item { display: flex; flex-direction: column; }
.backup-detail-item .lbl { font-size: 11px; color: #6b7280; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; margin-bottom: 2px; }
.backup-detail-item .val { font-size: 13px; color: #1a3a5c; font-weight: 600; }
.backup-detail-item code { font-size: 12px; color: #3b6fc9; background: #eaf0fb; padding: 1px 5px; border-radius: 4px; }
/* Empty-state panel */
.backup-empty { text-align: center; padding: 40px 20px; color: #6b7280; }
.backup-empty i { font-size: 38px; color: #c5d0de; margin-bottom: 12px; display: block; }
@media (max-width: 767px) {
    .settings-link-card { flex-direction: column; align-items: flex-start; }
    .abha-term { flex-wrap: wrap; }
    .backup-detail-row { flex-direction: column; gap: 10px; }
}
</style>
@endpush
@section('content')
@if(! $isUnlocked)
{{-- ══════════════════════════════════════════════════════
     LOCK SCREEN — fills content column, sidebar visible
     ══════════════════════════════════════════════════════ --}}
<div class="themebody-wrap settings-gate-wrap">
    <div class="settings-gate-card">

        {{-- Icon + heading --}}
        <div class="text-center">
            <div class="settings-gate-icon">
                <i class="fa fa-lock"></i>
            </div>
            <h4 class="settings-gate-title">Settings Protected</h4>
            <p class="settings-gate-sub">
                Enter the admin password to unlock<br>and modify hospital settings.
            </p>
        </div>

        {{-- Error --}}
        @if(session('settings_password_error'))
            <div class="alert alert-danger py-2 px-3 mb-4 gate-alert">
                <i class="fa fa-exclamation-circle me-1"></i> {{ session('settings_password_error') }}
            </div>
        @endif

        {{-- Password form --}}
        <form method="POST" action="{{ route('settings.unlock') }}">
            @csrf
            <div class="mb-4">
                <label class="settings-gate-label">Admin Password</label>
                <div class="input-group">
                    <input type="password" class="form-control gate-pw-input" name="admin_password"
                           id="gate_admin_password"
                           autocomplete="current-password"
                           placeholder="Enter admin password"
                           autofocus required>
                    <button class="btn btn-outline-secondary btn-pw-toggle gate-pw-toggle" type="button"
                            data-target="gate_admin_password" tabindex="-1">
                        <i class="fa fa-eye"></i>
                    </button>
                </div>
            </div>
            <div class="d-grid">
                <button type="submit" class="btn btn-primary gate-btn-unlock">
                    <i class="fa fa-unlock me-2"></i>Unlock Settings
                </button>
            </div>
        </form>

        <p class="text-center settings-gate-footer">
            <i class="fa fa-shield-alt me-1"></i>Protected by admin password
        </p>
    </div>
</div>
@else
{{-- ══════════════════════════════════════════════════════
     NORMAL SETTINGS PAGE (unlocked)
     ══════════════════════════════════════════════════════ --}}
<div class="themebody-wrap ipd-wrap">
    <div class="card-ipd">
        <div class="card-body">
            <div class="row ipd-patient-consultant-row">
                <div class="ipd-title-with-buttons row-bottom-border">
                    <div class="ipd-title"><i class="fa fa-cogs text-primary me-2"></i>Hospital Settings</div>
                    <div class="tbl-top-buttons">
                        <ul>
                            <li>
                                <form method="POST" action="{{ route('settings.lock') }}" class="d-inline m-0 p-0">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-secondary">
                                        <i class="fas fa-sign-out-alt"></i> Lock
                                    </button>
                                </form>
                            </li>
                            <li><a href="{{ route('db-backups.index') }}"><i class="fa fa-database"></i> Database Backups</a></li>
                            <li><a href="{{ route('abdm.settings') }}"><i class="fa-solid fa-shield-halved"></i> ABDM Settings</a></li>
                            <li><a href="{{ route('roles.index') }}"><i class="fa-solid fa-user-shield"></i> User Roles</a></li>
                        </ul>
                    </div>
                </div>

                @php $isUnlocked = true; @endphp {{-- already true, keeps @else block clean --}}
                {{-- ── Settings Tabs (unlocked) ────────────────────── --}}
                <div class="col-12 mt-3">
                    <ul class="nav nav-tabs" id="settingsTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-hospital" type="button" role="tab">
                                <i class="fa fa-hospital me-1"></i> Hospital Info
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-abdm" type="button" role="tab">
                                <i class="fa-solid fa-shield-halved me-1"></i> ABDM Facility
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-idfields" type="button" role="tab">
                                <i class="fa fa-tags me-1"></i> ID & Department Mappings
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-backups" type="button" role="tab">
                                <i class="fa fa-database me-1"></i> Database Backups
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content pt-4">

                        {{-- ── Hospital Settings ─────────────────────── --}}
                        <div class="tab-pane fade show active" id="tab-hospital" role="tabpanel">
                            <form id="settings-form" enctype="multipart/form-data">
                                @csrf
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <div class="form-group local-forms">
                                            <label>Hospital Name <span class="req-star">*</span></label>
                                            <input type="text" class="form-control" name="hospital_name" value="{{ e($settings->hospital_name ?? '') }}" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <div class="form-group local-forms">
                                            <label>Email</label>
                                            <input type="email" class="form-control" name="email" value="{{ e($settings->email ?? '') }}">
                                        </div>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <div class="form-group local-forms">
                                            <label>Phone Number</label>
                                            <input type="text" class="form-control" name="phone_number" value="{{ e($settings->phone_number ?? '') }}">
                                        </div>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <div class="form-group local-forms">
                                            <label>Mobile Number</label>
                                            <input type="text" class="form-control" name="mobile_number" value="{{ e($settings->mobile_number ?? '') }}">
                                        </div>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <div class="form-group local-forms">
                                            <label>Contact Number</label>
                                            <input type="text" class="form-control" name="contact_number" value="{{ e($settings->contact_number ?? '') }}">
                                        </div>
                                    </div>
                                    <div class="col-md-12 mb-3">
                                        <div class="form-group local-forms">
                                            <label>Address</label>
                                            <textarea class="form-control" name="address" rows="3">{{ e($settings->address ?? '') }}</textarea>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <div class="form-group local-forms">
                                            <label>Slogan (General)</label>
                                            <input type="text" class="form-control" name="slogan_general" value="{{ e($settings->slogan_general ?? '') }}">
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <div class="form-group local-forms">
                                            <label>Slogan (Hospital)</label>
                                            <input type="text" class="form-control" name="slogan_hospital" value="{{ e($settings->slogan_hospital ?? '') }}">
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <div class="form-group local-forms">
                                            <label>Signer Name (for PDFs)</label>
                                            <input type="text" class="form-control" name="signer" value="{{ e($settings->signer ?? '') }}">
                                        </div>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <div class="form-group local-forms">
                                            <label>Billing Start No. (BMH)</label>
                                            <input type="number" class="form-control" name="bmh_billing_start_number" value="{{ $settings->bmh_billing_start_number ?? 0 }}">
                                        </div>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <div class="form-group local-forms">
                                            <label>Billing Start No. (IVY)</label>
                                            <input type="number" class="form-control" name="ivy_billing_start_number" value="{{ $settings->ivy_billing_start_number ?? 0 }}">
                                        </div>
                                    </div>
                                    <div class="col-md-12 mb-10"><h6 class="mb-0">UHID format</h6>
                                        <small class="text-muted">UHID = prefix + patient number (padded) + suffix. Use <code>{YY}</code> (26) or <code>{YYYY}</code> (2026) in the prefix or suffix for the year the patient was registered, e.g. prefix <code>BMH/{YY}/</code> gives BMH/26/0000014. The UHID is built from the patient's id, so changing it changes how every patient's UHID is printed, not the id. ABDM patient references are not affected.</small>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <div class="form-group local-forms">
                                            <label>UHID Prefix</label>
                                            <input type="text" class="form-control uhid-part" id="uhid_prefix" name="uhid_prefix" maxlength="20" pattern="(?:[A-Za-z0-9_.\/\-]|\{YYYY\}|\{YY\})*" value="{{ e($settings->uhid_prefix ?? 'BMH') }}" placeholder="BMH">
                                        </div>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <div class="form-group local-forms">
                                            <label>UHID Suffix</label>
                                            <input type="text" class="form-control uhid-part" id="uhid_suffix" name="uhid_suffix" maxlength="20" pattern="(?:[A-Za-z0-9_.\/\-]|\{YYYY\}|\{YY\})*" value="{{ e($settings->uhid_suffix ?? '') }}" placeholder="(none)">
                                        </div>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <div class="form-group local-forms">
                                            <label>Number Digits (zero padded)</label>
                                            <input type="number" class="form-control uhid-part" id="uhid_padding" name="uhid_padding" min="0" max="12" value="{{ $settings->uhid_padding ?? 7 }}">
                                        </div>
                                    </div>
                                    <div class="col-md-3 mb-3 d-flex align-items-center">
                                        <span>Preview (patient 14, registered this year): <b id="uhid_preview">{{ \App\Support\Uhid::format(14, now()) }}</b></span>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <div class="form-group local-forms">
                                            <label>Hospital Logo</label>
                                            <input type="file" class="form-control" name="logo" accept="image/*">
                                            @if(!empty($settings->logo))
                                                <div class="mt-2 d-flex align-items-center gap-2">
                                                    <img src="{{ \App\Support\Uploads::url('settings', $settings->logo) }}"
                                                         alt="Hospital Logo"
                                                         class="img-thumbnail settings-logo-thumb"
                                                         onerror="this.style.display='none'">
                                                    <small class="text-success"><i class="fa fa-check"></i> Current: {{ $settings->logo }}</small>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <div class="form-group local-forms">
                                            <label>Square Logo (Icons & Prints)</label>
                                            <input type="file" class="form-control" name="square_logo" accept="image/*">
                                            @if(!empty($settings->square_logo))
                                                <div class="mt-2 d-flex align-items-center gap-2">
                                                    <img src="{{ \App\Support\Uploads::url('settings', $settings->square_logo) }}"
                                                         alt="Square Logo"
                                                         class="img-thumbnail settings-logo-thumb"
                                                         onerror="this.style.display='none'">
                                                    <small class="text-success"><i class="fa fa-check"></i> Current: {{ $settings->square_logo }}</small>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                {{-- Admin Password change --}}
                                <hr class="settings-hr">
                                <h6 class="mb-1 settings-pw-heading"><i class="fa fa-lock me-2"></i>Admin Password</h6>
                                <small class="text-muted d-block mb-10">Used to unlock the ABDM API credentials page. Leave all three fields empty to keep the current password.</small>
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <div class="form-group local-forms">
                                            <label>Current Password <span class="text-muted settings-label-hint">(required to change)</span></label>
                                            <div class="input-group">
                                                <input type="password" class="form-control" name="current_admin_password" id="current_admin_password" autocomplete="current-password" placeholder="Enter current password">
                                                <button class="btn btn-outline-secondary btn-pw-toggle" type="button" data-target="current_admin_password" tabindex="-1"><i class="fa fa-eye"></i></button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <div class="form-group local-forms">
                                            <label>New Password</label>
                                            <div class="input-group">
                                                <input type="password" class="form-control" name="new_admin_password" id="new_admin_password" autocomplete="new-password" minlength="6" placeholder="New password (min 6 chars)">
                                                <button class="btn btn-outline-secondary btn-pw-toggle" type="button" data-target="new_admin_password" tabindex="-1"><i class="fa fa-eye"></i></button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <div class="form-group local-forms">
                                            <label>Confirm New Password</label>
                                            <div class="input-group">
                                                <input type="password" class="form-control" name="new_admin_password_confirmation" id="new_admin_password_confirmation" autocomplete="new-password" placeholder="Re-enter new password">
                                                <button class="btn btn-outline-secondary btn-pw-toggle" type="button" data-target="new_admin_password_confirmation" tabindex="-1"><i class="fa fa-eye"></i></button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-12 mb-2 d-none" id="pw-mismatch-msg">
                                        <div class="alert alert-danger py-1 px-3 mb-0 settings-alert-sm"><i class="fa fa-exclamation-circle me-1"></i> New passwords do not match.</div>
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <button type="submit" class="btn btn-primary"><i class="fa fa-save me-1"></i> Save Hospital Settings</button>
                                </div>
                            </form>
                        </div>

                        {{-- ── ABDM Settings ─────────────────────────── --}}
                        <div class="tab-pane fade" id="tab-abdm" role="tabpanel">
                            @php $abhaTerms = \App\Support\AbhaTerms::parse($settings->abha_creation_terms ?? ''); @endphp
                            <form id="abdm-settings-form" enctype="multipart/form-data">
                                @csrf

                                {{-- Facility identity --}}
                                <div class="settings-section">
                                    <div class="settings-section-head">
                                        <h6><i class="fa-solid fa-hospital me-2"></i>Facility</h6>
                                        <small>How this hospital is registered with ABDM (Health Facility Registry).</small>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6 mb-4">
                                            <div class="form-group local-forms">
                                                <label>Facility Name (HFR)</label>
                                                <input type="text" class="form-control" name="facility_name" value="{{ e($settings->facility_name ?? '') }}">
                                            </div>
                                        </div>
                                        <div class="col-md-6 mb-4">
                                            <div class="form-group local-forms">
                                                <label>Facility HFR ID</label>
                                                <input type="text" class="form-control" name="facility_hfr_id" value="{{ e($settings->facility_hfr_id ?? '') }}" placeholder="e.g. IN0210000405">
                                            </div>
                                        </div>
                                        <div class="col-md-6 mb-4">
                                            <div class="form-group local-forms">
                                                <label>ABDM HIU ID</label>
                                                <input type="text" class="form-control" name="abdm_hiu_id" value="{{ e($settings->abdm_hiu_id ?? '') }}">
                                            </div>
                                        </div>
                                        <div class="col-md-6 mb-4">
                                            <div class="form-group local-forms">
                                                <label>ABDM Bridge Callback Base URL</label>
                                                <input type="url" class="form-control" name="abdm_bridge_callback_base_url" value="{{ e($settings->abdm_bridge_callback_base_url ?? '') }}" placeholder="https://your-domain.com">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- ABHA creation terms: a title and numbered terms; saved as the JSON the patient screens read --}}
                                <div class="settings-section">
                                    <div class="settings-section-head">
                                        <h6><i class="fa-solid fa-file-signature me-2"></i>ABHA Creation Terms</h6>
                                        <small>Staff must read and accept these terms before an ABHA is created through Aadhaar (Add / Edit Patient and Create OPD). Each term is shown as a numbered point.</small>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6 mb-4">
                                            <div class="form-group local-forms">
                                                <label>Terms Heading</label>
                                                <input type="text" class="form-control" name="abha_terms_title" maxlength="255" value="{{ e($abhaTerms['title']) }}" placeholder="Terms and Conditions">
                                            </div>
                                        </div>
                                    </div>

                                    <div id="abha-terms-list">
                                        @foreach($abhaTerms['items'] as $term)
                                            @include('settings.partials.abha-term-row', ['text' => $term['text']])
                                        @endforeach
                                    </div>
                                    <div id="abha-terms-empty" class="abha-terms-empty @if(count($abhaTerms['items'])) d-none @endif">
                                        No terms yet. The ABHA creation dialog will show only its heading.
                                    </div>

                                    <button type="button" class="btn btn-outline-primary btn-sm mt-2" id="abha-term-add">
                                        <i class="fa fa-plus me-1"></i> Add Term
                                    </button>
                                    <template id="abha-term-template">@include('settings.partials.abha-term-row', ['text' => ''])</template>
                                </div>

                                {{-- Link to the ABDM API credentials page --}}
                                <div class="settings-link-card">
                                    <div>
                                        <div class="settings-link-title"><i class="fa-solid fa-shield-halved me-2"></i>ABDM API Gateway &amp; Security Settings</div>
                                        <small>Client ID, Client Secret, gateway endpoints, webhook secrets and health verification are configured on their own page.</small>
                                    </div>
                                    <a href="{{ route('abdm.settings') }}" class="btn btn-sm btn-outline-primary">
                                        <i class="fa fa-external-link-alt me-1"></i> Open ABDM Settings
                                    </a>
                                </div>
                                <p class="settings-note">
                                    <i class="fa fa-info-circle me-1"></i> API credentials (<code>abdm_client_id</code>, <code>abdm_client_secret</code>) are read from the <code>settings</code> table; matching <code>ABDM_*</code> values in <code>.env</code> are used when those columns are empty.
                                </p>

                                <div class="mt-3">
                                    <button type="submit" class="btn btn-primary"><i class="fa fa-save me-1"></i> Save ABDM Settings</button>
                                </div>
                            </form>
                        </div>

                        {{-- ── ID Field Settings ─────────────────────── --}}
                        <div class="tab-pane fade" id="tab-idfields" role="tabpanel">
                            <form id="idfields-form">
                                @csrf
                                <p class="text-muted mb-10">Map internal hospital departments and designations to system roles (used across OPD, IPD, Staff, and clinical screens).</p>
                                <div class="row">
                                    @php
                                    $deptFields = [
                                        'doctor_department_id'    => 'Doctor Department',
                                        'nurse_department_id'     => 'Nurse Department',
                                        'pathology_department_id' => 'Pathology Department',
                                        'opd_department_id'       => 'OPD Department',
                                        'radiology_department_id' => 'Radiology Department',
                                        'endoscopy_department_id' => 'Endoscopy Department',
                                        'dialysis_department_id'  => 'Dialysis Department',
                                        'procedure_department_id' => 'Procedure Department',
                                        'pharmacy_department_id'  => 'Pharmacy Department',
                                    ];
                                    $desigFields = [
                                        'doctor_designation_id'   => 'Doctor Designation',
                                        'nurse_designation_id'    => 'Nurse Designation',
                                    ];
                                    @endphp

                                    {{-- Department Pickers --}}
                                    @foreach($deptFields as $field => $label)
                                    <div class="col-md-4 mb-3">
                                        <div class="form-group local-forms">
                                            <label>{{ $label }}</label>
                                            @if(isset($departments) && $departments->isNotEmpty())
                                                <select class="form-control" name="{{ $field }}">
                                                    <option value="0" {{ ($idSettings->$field ?? 0) == 0 ? 'selected' : '' }}>-- Not Set (0) --</option>
                                                    @foreach($departments as $dept)
                                                        <option value="{{ $dept->id }}" {{ ($idSettings->$field ?? 0) == $dept->id ? 'selected' : '' }}>
                                                            {{ $dept->department }} (ID: {{ $dept->id }})
                                                        </option>
                                                    @endforeach
                                                </select>
                                            @else
                                                <input type="number" class="form-control" name="{{ $field }}"
                                                    value="{{ $idSettings->$field ?? 0 }}" min="0">
                                            @endif
                                        </div>
                                    </div>
                                    @endforeach

                                    {{-- Designation Pickers --}}
                                    @foreach($desigFields as $field => $label)
                                    <div class="col-md-4 mb-3">
                                        <div class="form-group local-forms">
                                            <label>{{ $label }}</label>
                                            @if(isset($designations) && $designations->isNotEmpty())
                                                <select class="form-control" name="{{ $field }}">
                                                    <option value="0" {{ ($idSettings->$field ?? 0) == 0 ? 'selected' : '' }}>-- Not Set (0) --</option>
                                                    @foreach($designations as $desig)
                                                        <option value="{{ $desig->id }}" {{ ($idSettings->$field ?? 0) == $desig->id ? 'selected' : '' }}>
                                                            {{ $desig->designation }} (ID: {{ $desig->id }})
                                                        </option>
                                                    @endforeach
                                                </select>
                                            @else
                                                <input type="number" class="form-control" name="{{ $field }}"
                                                    value="{{ $idSettings->$field ?? 0 }}" min="0">
                                            @endif
                                        </div>
                                    </div>
                                    @endforeach
                                </div>
                                <div class="mt-2">
                                    <button type="submit" class="btn btn-primary"><i class="fa fa-save me-1"></i> Save ID Settings</button>
                                </div>
                            </form>
                        </div>

                        {{-- ── Backups Overview Tab ──────────────────── --}}
                        <div class="tab-pane fade" id="tab-backups" role="tabpanel">

                            {{-- Header row: title + action buttons --}}
                            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                                <div>
                                    <h6 class="mb-0 fw-bold settings-backup-heading"><i class="fa fa-database me-2 text-primary"></i>Database Backups &amp; Archives</h6>
                                    <small class="text-muted">Automated and manual snapshots stored in secure hospital storage.</small>
                                </div>
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-primary btn-sm" id="btn-quick-backup">
                                        <i class="fa fa-plus-circle me-1"></i> Backup Now
                                    </button>
                                    <a href="{{ route('db-backups.index') }}" class="btn btn-outline-primary btn-sm">
                                        <i class="fa fa-list me-1"></i> View All Backups
                                    </a>
                                </div>
                            </div>

                            {{-- Stat cards row --}}
                            <div class="row g-3 mb-4">
                                <div class="col-sm-4">
                                    <div class="backup-stat-card">
                                        <div class="backup-stat-icon icon-total"><i class="fa fa-copy"></i></div>
                                        <div>
                                            <div class="backup-stat-label">Total Backups</div>
                                            <div class="backup-stat-value">{{ $backupStats['total_backups'] ?? 0 }}</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-sm-4">
                                    <div class="backup-stat-card">
                                        <div class="backup-stat-icon icon-latest"><i class="fa fa-clock"></i></div>
                                        <div>
                                            <div class="backup-stat-label">Latest Backup</div>
                                            @if(!empty($backupStats['latest_backup']))
                                                <div class="backup-stat-value small-val">{{ $backupStats['latest_backup']['formatted_time'] }}</div>
                                            @else
                                                <div class="backup-stat-value small-val text-muted fw-medium">None yet</div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                <div class="col-sm-4">
                                    <div class="backup-stat-card">
                                        <div class="backup-stat-icon icon-storage"><i class="fa fa-shield-alt"></i></div>
                                        <div>
                                            <div class="backup-stat-label">Storage</div>
                                            <div class="backup-stat-value small-val text-storage-ok">
                                                <i class="fa fa-check-circle me-1"></i>Protected
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Most-recent backup detail --}}
                            @if(!empty($backupStats['latest_backup']))
                                @php $lb = $backupStats['latest_backup']; @endphp
                                <div class="settings-section mb-0">
                                    <div class="settings-section-head">
                                        <h6><i class="fa fa-history me-2"></i>Most Recent Backup</h6>
                                        <small>Details of the last successful database snapshot.</small>
                                    </div>
                                    <div class="backup-detail-card mb-3">
                                        <div class="backup-detail-row">
                                            <div class="backup-detail-item backup-file-item">
                                                <span class="lbl"><i class="fa fa-file-archive me-1"></i>File</span>
                                                <span class="val"><code>{{ $lb['filename'] }}</code></span>
                                            </div>
                                            <div class="backup-detail-item">
                                                <span class="lbl"><i class="fa fa-calendar me-1"></i>Date</span>
                                                <span class="val">{{ $lb['display_date'] }}</span>
                                            </div>
                                            <div class="backup-detail-item">
                                                <span class="lbl"><i class="fa fa-clock me-1"></i>Timestamp</span>
                                                <span class="val">{{ $lb['formatted_time'] }}</span>
                                            </div>
                                            <div class="backup-detail-item">
                                                <span class="lbl"><i class="fa fa-weight me-1"></i>Size</span>
                                                <span class="val">{{ $lb['file_size'] }}</span>
                                            </div>
                                            <div class="backup-detail-item ms-auto align-self-center">
                                                <a href="{{ route('db-backups.download', ['filename' => $lb['filename']]) }}"
                                                   class="btn btn-primary btn-sm"
                                                   download>
                                                    <i class="fa fa-download me-1"></i> Download
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @else
                                <div class="settings-section">
                                    <div class="backup-empty">
                                        <i class="fa fa-database"></i>
                                        <p class="mb-3">No backup files found.</p>
                                        <button type="button" class="btn btn-primary btn-sm" id="btn-quick-backup-empty">
                                            <i class="fa fa-plus-circle me-1"></i> Create First Backup
                                        </button>
                                    </div>
                                </div>
                            @endif

                        </div>

                    </div>
                </div>

            </div>
        </div>
    </div>
</div>
@endif {{-- end locked / unlocked --}}

@endsection

@push('scripts')
<script>
// UHID format preview: prefix + patient number 14 padded to the chosen digits + suffix
function updateUhidPreview() {
    var pad = parseInt($('#uhid_padding').val(), 10);
    pad = isNaN(pad) ? 7 : Math.max(0, Math.min(12, pad));
    var num = String(14);
    while (num.length < pad) { num = '0' + num; }
    var year = String(new Date().getFullYear());
    var withYear = function (part) { return part.replace(/\{YYYY\}/g, year).replace(/\{YY\}/g, year.slice(-2)); };
    $('#uhid_preview').text(withYear($('#uhid_prefix').val()) + num + withYear($('#uhid_suffix').val()));
}
$(document).on('input', '.uhid-part', updateUhidPreview);

// ABHA creation terms editor: add / delete / move numbered terms; the order on screen is the order saved
function renumberAbhaTerms() {
    var $rows = $('#abha-terms-list .abha-term');
    $rows.each(function (i) {
        $(this).find('.abha-term-no').text(i + 1);
        $(this).find('.abha-term-up').prop('disabled', i === 0);
        $(this).find('.abha-term-down').prop('disabled', i === $rows.length - 1);
    });
    $('#abha-terms-empty').toggleClass('d-none', $rows.length !== 0);
}
$('#abha-term-add').on('click', function () {
    var $row = $($('#abha-term-template').html());
    $('#abha-terms-list').append($row);
    renumberAbhaTerms();
    $row.find('textarea').trigger('focus');
});
$('#abha-terms-list').on('click', '.abha-term-delete', function () {
    $(this).closest('.abha-term').remove();
    renumberAbhaTerms();
}).on('click', '.abha-term-up', function () {
    var $row = $(this).closest('.abha-term');
    $row.prev('.abha-term').before($row);
    renumberAbhaTerms();
}).on('click', '.abha-term-down', function () {
    var $row = $(this).closest('.abha-term');
    $row.next('.abha-term').after($row);
    renumberAbhaTerms();
});
renumberAbhaTerms();

$(document).ready(function(){
    // Tab persistence via URL hash
    var hash = window.location.hash;
    if (hash) {
        var triggerEl = document.querySelector('#settingsTabs button[data-bs-target="' + hash + '"]');
        if (triggerEl) {
            var tab = new bootstrap.Tab(triggerEl);
            tab.show();
        }
    }
    $('#settingsTabs button').on('shown.bs.tab', function (e) {
        window.location.hash = e.target.getAttribute('data-bs-target');
    });
});

// Password show/hide toggle
$(document).on('click', '.btn-pw-toggle', function(){
    var $btn = $(this);
    var $input = $('#' + $btn.data('target'));
    var isText = $input.attr('type') === 'text';
    $input.attr('type', isText ? 'password' : 'text');
    $btn.find('i').toggleClass('fa-eye', !isText).toggleClass('fa-eye-slash', isText);
});

// New password match validation
$(document).on('input', '#new_admin_password, #new_admin_password_confirmation', function(){
    var np = $('#new_admin_password').val();
    var nc = $('#new_admin_password_confirmation').val();
    $('#pw-mismatch-msg').toggleClass('d-none', !(nc.length > 0 && np !== nc));
});

$('#settings-form').on('submit', function(e){
    e.preventDefault();
    var np = $('#new_admin_password').val();
    var nc = $('#new_admin_password_confirmation').val();
    if (np || nc) {
        if (np !== nc) {
            $('#pw-mismatch-msg').removeClass('d-none');
            $('#new_admin_password_confirmation').trigger('focus');
            return;
        }
        if (np.length < 6) {
            showError('New password must be at least 6 characters.');
            return;
        }
    }
    showLoading();
    $.ajax({
        url: '{{ route('settings.update') }}', type: 'POST',
        data: new FormData(this), contentType: false, processData: false,
        success: function(resp){ Swal.close(); if(resp.success) showSuccess(resp.message); else showError(resp.message); },
        error: function(xhr){ Swal.close(); showError(xhr.responseJSON?.message || 'Error saving settings.'); }
    });
});

$('#abdm-settings-form').on('submit', function(e){
    e.preventDefault();
    // a blank term is not saved (the server drops them too)
    $('#abha-terms-list .abha-term').filter(function () { return $.trim($(this).find('textarea').val()) === ''; }).remove();
    renumberAbhaTerms();
    showLoading();
    $.ajax({
        url: '{{ route('settings.update') }}', type: 'POST',
        data: new FormData(this), contentType: false, processData: false,
        success: function(resp){ Swal.close(); if(resp.success) showSuccess(resp.message); else showError(resp.message); },
        error: function(xhr){ Swal.close(); showError(xhr.responseJSON?.message || 'Error.'); }
    });
});

$('#idfields-form').on('submit', function(e){
    e.preventDefault(); showLoading();
    $.post('{{ route('settings.id-fields') }}', $(this).serialize(), function(resp){
        Swal.close();
        if(resp.success) showSuccess(resp.message); else showError(resp.message);
    });
});

// Quick backup — Swal password prompt
function doQuickBackup() {
    Swal.fire({
        title: 'Admin Password Required',
        input: 'password',
        inputPlaceholder: 'Enter admin password',
        inputAttributes: { autocomplete: 'current-password' },
        showCancelButton: true,
        confirmButtonText: '<i class="fa fa-key me-1"></i> Verify & Backup',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#1a3a5c',
        showLoaderOnConfirm: true,
        allowOutsideClick: () => !Swal.isLoading(),
        preConfirm: function (password) {
            if (!password) { Swal.showValidationMessage('Please enter the admin password.'); return false; }
            return $.ajax({
                url: '{{ route('db-backups.verify-password') }}',
                type: 'POST',
                data: { _token: '{{ csrf_token() }}', password: password },
                dataType: 'json'
            }).then(function (r) { return r; }, function (xhr) {
                Swal.showValidationMessage(xhr.responseJSON?.message || 'Incorrect password.');
            });
        }
    }).then(function (result) {
        if (!result.isConfirmed) return;
        showLoading();
        $.ajax({
            url: '{{ route('db-backups.store') }}',
            type: 'POST',
            data: { _token: '{{ csrf_token() }}' },
            success: function(resp) {
                Swal.close();
                if (resp.success) {
                    Swal.fire({ icon: 'success', title: 'Backup Completed', text: resp.message || 'Database backup created successfully.', confirmButtonText: 'OK' })
                        .then(function () { window.location.hash = '#tab-backups'; window.location.reload(); });
                } else {
                    Swal.fire('Error', resp.message || 'Failed to create backup.', 'error');
                }
            },
            error: function(xhr) {
                Swal.close();
                Swal.fire('Error', xhr.responseJSON?.message || 'Server error while generating backup.', 'error');
            }
        });
    });
}
$('#btn-quick-backup, #btn-quick-backup-empty').on('click', function(){ doQuickBackup(); });
</script>
@endpush
