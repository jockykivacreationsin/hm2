<!DOCTYPE html>
<html lang="en">
<head>
	<!-- Required meta tags-->
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="csrf-token" content="{{ csrf_token() }}">
	<meta name="author" content="">
	<meta name="keywords" content="">
	<title>eZeeHospital Login.</title>
	<!-- shortcut icon-->
	<link rel="shortcut icon" href="{{ asset('assets/images/logo/icon-logo.png') }}" type="image/x-icon">
	<!-- Fonts css-->
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&amp;display=swap" rel="stylesheet">
	<!-- Font awesome -->
	<link href="{{ asset('assets/css/vendor/font-awesome.css') }}" rel="stylesheet">
	<link href="{{ asset('assets/css/vendor/themify-icons.css') }}" rel="stylesheet">
	<link href="{{ asset('assets/css/vendor/icoicon.css') }}" rel="stylesheet">
	<!-- Scrollbar-->
	<link href="{{ asset('assets/css/vendor/simplebar.css') }}" rel="stylesheet">
	<!-- Bootstrap css-->
	<link href="{{ asset('assets/css/vendor/bootstrap.css') }}" rel="stylesheet">
	<!-- Custom css-->
	<link href="{{ asset('assets/css/vendor/sweetalert/sweetalert2.min.css') }}" rel="stylesheet">
	<link href="{{ asset('assets/css/style.css') }}" rel="stylesheet">
	<link href="{{ asset('assets/css/custom.css') }}" rel="stylesheet">
	<style>
		/* ── OTP step ───────────────────────────────────── */
		#otp_step { display:none; }
		.otp-boxes { display:flex; gap:10px; justify-content:flex-start; }
		.otp-boxes input {
			width:46px; height:54px;
			text-align:center; font-size:22px; font-weight:700;
			border:2px solid #d1d5db; border-radius:8px;
			background:#f9fafb; color:#1a3a5c;
			outline:none; transition:border-color .15s, box-shadow .15s;
			-moz-appearance:textfield;
		}
		.otp-boxes input::-webkit-outer-spin-button,
		.otp-boxes input::-webkit-inner-spin-button { -webkit-appearance:none; margin:0; }
		.otp-boxes input:focus {
			border-color:#1a3a5c; background:#fff;
			box-shadow:0 0 0 3px rgba(26,58,92,.15);
		}
		.otp-boxes input.filled { border-color:#1a3a5c; background:#fff; }
		#otp_timer { font-size:12.5px; color:#6b7280; margin-top:8px; }
		#otp_timer.expiring { color:#dc2626; font-weight:600; }
		#btn_resend {
			background:none; border:none; padding:0;
			color:#3b6fc9; font-size:12.5px; cursor:pointer;
			text-decoration:underline;
		}
		#btn_resend:disabled { color:#9ca3af; text-decoration:none; cursor:default; }
		.otp-back { font-size:12px; color:#9ca3af; margin-top:8px; display:block; cursor:pointer; }
		.otp-back:hover { color:#3b6fc9; }
	</style>
	<!-- main jquery-->
	<script src="{{ asset('assets/js/jquery-3.7.0.min.js') }}"></script>
	<script>
	$(function () {

		// ── Shared ─────────────────────────────────────────────────────────────
		var VERIFY_URL  = "{{ route('login.verify-otp') }}";
		var RESEND_URL  = "{{ route('login.resend-otp') }}";
		var csrfToken   = $('meta[name="csrf-token"]').attr('content');
		var timerHandle = null;

		// ── Button busy/idle helpers ───────────────────────────────────────────
		function setBusy($btn, label) {
			$btn.prop('disabled', true)
				.data('original-html', $btn.html())
				.html('<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>' + label);
		}
		function setIdle($btn) {
			$btn.prop('disabled', false).html($btn.data('original-html'));
		}

		function post(url, data, done, fail) {
			$.ajax({
				url: url, type: 'POST',
				data: data,
				headers: { 'X-CSRF-TOKEN': csrfToken },
				dataType: 'json',
				success: done,
				error: fail || function (xhr) { handleAjaxError(xhr); }
			});
		}

		function handleAjaxError(xhr) {
			if (xhr.status === 419) { window.location.reload(); return; }
			var throttled = xhr.status === 429 && xhr.responseJSON && xhr.responseJSON.message;
			Swal.fire({
				icon: 'error',
				title: throttled ? 'Login locked' : 'Error',
				text:  throttled ? xhr.responseJSON.message : 'Please try again.',
				showConfirmButton: false,
				timer: throttled ? 4000 : 1500
			});
		}

		// ── Step 1: password form submit ───────────────────────────────────────
		$('#login_form').on('submit', function (e) {
			e.preventDefault();
			var $btn = $('#login_btn');
			setBusy($btn, 'Logging in…');
			post(
				"{{ route('login.post') }}",
				$(this).serialize(),
				function (data) {
					if (data.result === '1') {
						// Keep button busy — page is about to redirect.
						Swal.fire({ icon:'success', title:'Logged In', text:'Login Successfully.', showConfirmButton:false, timer:1500 });
						window.location = data.redirect || "{{ route('dashboard') }}";
					} else if (data.result === 'mfa_required') {
						setIdle($btn);
						showOtpStep(data.seconds_remaining || 300, data.resends_remaining || 3);
					} else {
						setIdle($btn);
						Swal.fire({ icon:'error', title:'Error!', text:'Please Enter Valid Username & Password', showConfirmButton:false, timer:1500 });
					}
				},
				function (xhr) {
					setIdle($btn);
					if (xhr.status === 419) { window.location.reload(); return; }
					var throttled = xhr.status === 429 && xhr.responseJSON && xhr.responseJSON.message;
					Swal.fire({
						icon:'error',
						title: throttled ? 'Login locked' : 'Error!',
						text:  throttled ? xhr.responseJSON.message : 'Please Enter Valid Username & Password',
						showConfirmButton:false, timer: throttled ? 4000 : 1500
					});
				}
			);
		});

		// ── Step 2: OTP form submit ────────────────────────────────────────────
		// ── OTP 6-box behaviour ────────────────────────────────────────────────
		var $boxes = $('.otp-digit');

		$boxes.on('input', function () {
			var val = $(this).val().replace(/\D/g, '');
			// Accept only one digit per box
			$(this).val(val ? val.slice(-1) : '');
			$(this).toggleClass('filled', $(this).val() !== '');
			// Auto-advance
			if ($(this).val() !== '') {
				var next = $boxes.index(this) + 1;
				if (next < $boxes.length) { $boxes.eq(next).focus(); }
			}
		});

		$boxes.on('keydown', function (e) {
			var idx = $boxes.index(this);
			if (e.key === 'Backspace') {
				if ($(this).val() === '' && idx > 0) {
					$boxes.eq(idx - 1).val('').removeClass('filled').focus();
				} else {
					$(this).val('').removeClass('filled');
				}
				e.preventDefault();
			} else if (e.key === 'ArrowLeft' && idx > 0) {
				$boxes.eq(idx - 1).focus();
			} else if (e.key === 'ArrowRight' && idx < $boxes.length - 1) {
				$boxes.eq(idx + 1).focus();
			}
		});

		// Paste handler — spread digits across boxes
		$boxes.on('paste', function (e) {
			var pasted = (e.originalEvent.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '');
			if (pasted.length === 0) return;
			e.preventDefault();
			$boxes.each(function (i) {
				var ch = pasted[i] || '';
				$(this).val(ch).toggleClass('filled', ch !== '');
			});
			// Focus first empty or last box
			var firstEmpty = $boxes.filter(function () { return $(this).val() === ''; }).first();
			(firstEmpty.length ? firstEmpty : $boxes.last()).focus();
		});

		function getOtpValue() {
			return $boxes.map(function () { return $(this).val(); }).get().join('');
		}

		function clearOtpBoxes() {
			$boxes.val('').removeClass('filled').first().focus();
		}

		$('#otp_form').on('submit', function (e) {
			e.preventDefault();
			var otp = getOtpValue();
			if (otp.length !== 6) {
				Swal.fire({ icon:'warning', title:'Enter all 6 digits', showConfirmButton:false, timer:1200 });
				return;
			}
			var $btn = $('#otp_submit_btn');
			setBusy($btn, 'Verifying…');
			post(
				VERIFY_URL,
				{ otp: otp },
				function (data) {
					if (data.result === '1') {
						clearTimer();
						// Keep button busy — page is about to redirect.
						Swal.fire({ icon:'success', title:'Logged In', text:'Login Successfully.', showConfirmButton:false, timer:1500 });
						window.location = data.redirect || "{{ route('dashboard') }}";
					} else if (data.result === 'reset') {
						setIdle($btn);
						clearTimer();
						Swal.fire({ icon:'error', title:'Verification failed', text: data.message || 'Please log in again.', confirmButtonText:'OK' })
							.then(function () { showPasswordStep(); });
					} else {
						setIdle($btn);
						clearOtpBoxes();
						Swal.fire({ icon:'error', title:'Wrong code', text: data.message || 'Incorrect code.', showConfirmButton:false, timer:1800 });
					}
				},
				function (xhr) {
					setIdle($btn);
					if (xhr.status === 419) { window.location.reload(); return; }
					if (xhr.status === 429 && xhr.responseJSON) {
						Swal.fire({ icon:'error', title:'Blocked', text: xhr.responseJSON.message, confirmButtonText:'OK' })
							.then(function () { showPasswordStep(); });
						return;
					}
					handleAjaxError(xhr);
				}
			);
		});

		// ── Resend OTP ─────────────────────────────────────────────────────────
		$('#btn_resend').on('click', function () {
			var $btn = $(this);
			$btn.prop('disabled', true);
			post(
				RESEND_URL,
				{},
				function (data) {
					if (data.result === '1') {
						resetTimer(data.seconds_remaining || 300);
						var left = data.resends_remaining || 0;
						if (left > 0) {
							$btn.prop('disabled', false);
						}
						$('#resend_count').text(left);
						Swal.fire({ icon:'info', title:'Code resent', text:'A new code has been sent to your email.', showConfirmButton:false, timer:2000 });
					} else {
						Swal.fire({ icon:'warning', title:'Cannot resend', text: data.message || 'Resend limit reached.', showConfirmButton:false, timer:2000 });
					}
				}
			);
		});

		// ── Back to password step ──────────────────────────────────────────────
		$('#otp_back').on('click', function () {
			clearTimer();
			showPasswordStep();
		});

		// ── UI helpers ─────────────────────────────────────────────────────────
		function showOtpStep(seconds, resendsLeft) {
			$('#password_step').hide();
			$('#otp_step').show();
			clearOtpBoxes();
			$('#resend_count').text(resendsLeft);
			$('#btn_resend').prop('disabled', resendsLeft <= 0);
			resetTimer(seconds);
		}

		function showPasswordStep() {
			$('#otp_step').hide();
			$('#password_step').show();
			clearTimer();
		}

		function resetTimer(seconds) {
			clearTimer();
			var remaining = seconds;
			updateTimerDisplay(remaining);
			timerHandle = setInterval(function () {
				remaining--;
				updateTimerDisplay(remaining);
				if (remaining <= 0) {
					clearTimer();
					$('#otp_timer').text('Code expired. Please request a new one or log in again.').addClass('expiring');
					$('#otp_submit_btn').prop('disabled', true);
				}
			}, 1000);
		}

		function clearTimer() {
			if (timerHandle) { clearInterval(timerHandle); timerHandle = null; }
		}

		function updateTimerDisplay(seconds) {
			var m = Math.floor(seconds / 60);
			var s = seconds % 60;
			var txt = 'Code expires in ' + m + ':' + (s < 10 ? '0' : '') + s;
			var $t = $('#otp_timer');
			$t.text(txt).removeClass('expiring');
			if (seconds <= 30) { $t.addClass('expiring'); }
			$('#otp_submit_btn').prop('disabled', seconds <= 0);
		}

	});
	</script>
</head>
<body>
	<!-- Login Start-->
	<div class="auth-main">
		<div class="codex-authbox">
			<div class="auth-header">
				<div class="nl-sp-logo"><img class="sp-logo" src="{{ asset('assets/images/hlogo.png') }}" alt=""><h3>{{ $hospitalName ?: config('app.name') }}</h3></div>
				<h6>Login to your account</h6>
			</div>

			{{-- Status flash (e.g. password reset success) --}}
			@if(session('status'))
				<div class="alert alert-success mb-3" style="font-size:13.5px;border-radius:8px;">
					<i class="fa fa-check-circle me-1"></i> {{ session('status') }}
				</div>
			@endif

			{{-- ── Step 1: Password form ─────────────────────────────────── --}}
			<div id="password_step">
				<form id="login_form">
					@csrf
					<div class="row">
						{{-- A single patient category is used silently; several categories show the "Department" picker --}}
						@if($categories->count() == 1)
							<input type="hidden" name="patient_category_id" value="{{ $categories->first()->id }}">
						@else
							<div class="col-12 col-sm-12">
								<div class="form-group local-forms">
									<label>Department <span class="req-star">*</span></label>
									<select class="form-select" name="patient_category_id" required>
										<option value="">Select Department</option>
										@foreach($categories as $cat)
											<option value="{{ $cat->id }}">{{ $cat->patient_category_name }}</option>
										@endforeach
									</select>
								</div>
							</div>
						@endif
						<div class="col-12 col-sm-12">
							<div class="form-group local-forms">
								<label>Email/Mobile Number <span class="req-star">*</span></label>
								<input class="form-control" type="text" name="user_name" required placeholder="Enter Your Email or Mobile Number" autocomplete="username">
							</div>
						</div>
						<div class="col-12 col-sm-12">
							<div class="form-group local-forms">
								<label>Password <span class="req-star">*</span></label>
								<div class="input-group group-input">
									<input class="form-control showhide-password" type="password" name="password" placeholder="Enter Your Password" required="" autocomplete="current-password"><span class="input-group-text toggle-show fa fa-eye"></span>
								</div>
							</div>
						</div>
						<div class="col-12 col-sm-12">
							<div class="form-group mb-0">
								<div class="auth-remember" style="justify-content: space-between; align-items: center;">
									{{-- "Remember Me": keeps this device signed in for the number of days set in config/auth.php; logging out ends it at once. --}}
									<div class="form-check custom-chek mb-0">
										<input class="form-check-input" type="checkbox" name="remember" id="remember" value="1">
										<label class="form-check-label" for="remember">Remember Me</label>
									</div>
									<a class="text-primary f-pwd" href="{{ route('password.request') }}">Forgot your password?</a>
								</div>
							</div>
							<div class="form-group">
								<button id="login_btn" class="btn btn-primary" type="submit"><i class="fa fa-sign-in"></i> Login</button>
							</div>
						</div>
					</div>
				</form>
			</div>

			{{-- ── Step 2: OTP verification form ────────────────────────── --}}
			<div id="otp_step">
				<p style="font-size:13.5px;color:#374151;margin-bottom:16px;">
					<i class="fa fa-envelope-o me-1"></i>
					A 6-digit verification code has been sent to your registered email address. Enter it below to complete login.
				</p>
				<form id="otp_form">
					@csrf
					<div class="row">
						<div class="col-12">
							<div class="form-group local-forms">
								<label>Verification Code <span class="req-star">*</span></label>
								<div class="otp-boxes">
									<input class="otp-digit" type="number" inputmode="numeric" maxlength="1" min="0" max="9" autocomplete="off">
									<input class="otp-digit" type="number" inputmode="numeric" maxlength="1" min="0" max="9" autocomplete="off">
									<input class="otp-digit" type="number" inputmode="numeric" maxlength="1" min="0" max="9" autocomplete="off">
									<input class="otp-digit" type="number" inputmode="numeric" maxlength="1" min="0" max="9" autocomplete="off">
									<input class="otp-digit" type="number" inputmode="numeric" maxlength="1" min="0" max="9" autocomplete="off">
									<input class="otp-digit" type="number" inputmode="numeric" maxlength="1" min="0" max="9" autocomplete="one-time-code">
								</div>
								<div id="otp_timer"></div>
							</div>
						</div>
						<div class="col-12">
							<div class="form-group">
								<button id="otp_submit_btn" class="btn btn-primary" type="submit">
									<i class="fa fa-check"></i> Verify Code
								</button>
							</div>
						</div>
						<div class="col-12">
							<div style="font-size:12.5px;color:#6b7280;">
								Didn't receive the code?
								<button type="button" id="btn_resend">Resend</button>
								(<span id="resend_count">3</span> remaining)
							</div>
							<a id="otp_back" class="otp-back">&#8592; Back to login</a>
						</div>
					</div>
				</form>
			</div>

		</div>
	</div>
	<!-- Login End-->

	<!-- Feather iocns js-->
	<script src="{{ asset('assets/js/icons/feather-icon/feather.js') }}"></script>
	<!-- Bootstrap js-->
	<script src="{{ asset('assets/js/bootstrap.bundle.min.js') }}"></script>
	<script src="{{ asset('assets/js/vendors/notify/bootstrap-notify.js') }}"></script>
	<script src="{{ asset('assets/js/vendors/notify/bootstrap-customnotify.js') }}"></script>
	<!-- customizer-->
	<script src="{{ asset('assets/js/customizer.js') }}"></script>
	<!-- Scrollbar-->
	<script src="{{ asset('assets/js/vendors/simplebar.js') }}"></script>
	<script src="{{ asset('assets/js/vendors/sweetalert/sweetalert2.js') }}"></script>
	<!-- Custom script-->
	<script src="{{ asset('assets/js/custom-script.js') }}"></script>
</body>
</html>
