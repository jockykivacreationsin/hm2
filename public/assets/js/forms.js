/*
 * Shared, route-driven AJAX helpers (replaces the old "post a flag to ajax.php" scripts in app.js).
 * Views put their URLs on the markup (generated with route()) and this file does the plumbing:
 *
 *   <form id="add_x_form" data-action="{{ route('x.store') }}" data-method="POST">
 *   <form id="edit_x_form" data-action-template="{{ route('x.update', ':id') }}" data-method="PUT">   (id set by appForm.edit)
 *   <select data-lookup-url="{{ route('y.options') }}" data-lookup-params="charge_department_id=#e_charge_department_id">
 *
 * Loaded after app.js in layouts/app.blade.php.
 */
(function (window, $) {
	'use strict';

	function errorMessage(xhr, fallback) {
		var json = (xhr && xhr.responseJSON) || {};
		if (json.errors) {
			var first = Object.keys(json.errors)[0];
			if (first && json.errors[first].length) { return json.errors[first][0]; }
		}
		return json.message || json.error || fallback;
	}

	function toast(icon, title, text, timer) {
		Swal.fire({ icon: icon, title: title, text: text, showConfirmButton: false, timer: timer || 1500 });
	}

	// Replaces the :id / __ID__ placeholder of a URL generated with route('name', ':id').
	function fill(template, id) {
		return String(template).replace(':id', encodeURIComponent(id)).replace('__ID__', encodeURIComponent(id));
	}

	// Copies summernote content into its textarea before the form is serialised.
	function syncEditors(form) {
		$(form).find('.summernote, .summernote-lite').each(function () {
			if ($(this).data('summernote')) {
				$(this).val($(this).summernote('isEmpty') ? '' : $(this).summernote('code'));
			}
		});
	}

	/*
	 * Binds an AJAX submit handler to a form (any earlier submit listener is removed so a form is never sent twice).
	 *   opts.url          string | function(form) -> string   (default: form data-action, or data-action-template + form data-id)
	 *   opts.method       'PUT' | 'PATCH' | 'DELETE'          spoofed through _method (default: form data-method; POST stays plain)
	 *   opts.validate     function(form) -> true | message    client-side check; a string is shown as "Required!"
	 *   opts.requiredRows jQuery selector that must match at least one row
	 *   opts.syncEditors  copy summernote content before sending
	 *   opts.confirm      { title, text, button }              ask first
	 *   opts.busyText     text shown on the submit button while the request runs (e.g. 'Wait...')
	 *   opts.onSuccess    function(data, form)                 default: "Saved!" toast + page reload
	 *   opts.onError      function(xhr, form)                  default: error toast
	 */
	function bind(selector, opts) {
		opts = opts || {};
		$(selector).off('submit').on('submit', function (e) {
			e.preventDefault();
			var form = this;
			var $form = $(form);

			if (opts.requiredRows && $(opts.requiredRows).length === 0) {
				toast('error', 'Required!', 'Please Enter Some Data');
				return;
			}
			if (opts.validate) {
				var ok = opts.validate(form);
				if (ok !== true && ok !== undefined) {
					toast('error', 'Required!', ok || 'Please check the form');
					return;
				}
			}
			if (opts.syncEditors) { syncEditors(form); }

			var url = typeof opts.url === 'function' ? opts.url(form) : opts.url;
			if (!url) {
				url = $form.attr('data-action') || fill($form.attr('data-action-template') || '', $form.data('id') || '') || $form.attr('action');
			}
			var method = (opts.method || $form.data('method') || 'POST').toUpperCase();

			var send = function () {
				var formData = new FormData(form);
				if (method !== 'POST') { formData.append('_method', method); }
				var $btn = $form.find('button[type="submit"]');
				var original = $btn.html();
				$btn.prop('disabled', true);
				if (opts.busyText) { $btn.html(opts.busyText); }

				$.ajax({
					url: url,
					type: 'POST',
					data: formData,
					contentType: false,
					cache: false,
					processData: false,
					dataType: 'json',
					success: function (data) {
						$btn.prop('disabled', false).html(original);
						if (opts.onSuccess) { opts.onSuccess(data, form); return; }
						toast('success', 'Saved!', 'Saved Successfully.');
						location.reload();
					},
					error: function (xhr) {
						$btn.prop('disabled', false).html(original);
						if (opts.onError) { opts.onError(xhr, form); return; }
						toast('error', 'Error!', errorMessage(xhr, 'Failed to save. Please try again.'), 2500);
					}
				});
			};

			if (opts.confirm) {
				Swal.fire({
					title: opts.confirm.title || 'Submit?',
					text: opts.confirm.text || 'Are you sure you want to submit!',
					icon: 'warning',
					showCancelButton: true,
					confirmButtonColor: '#3085d6',
					cancelButtonColor: '#d33',
					confirmButtonText: opts.confirm.button || 'Yes, submit it!'
				}).then(function (result) { if (result.isConfirmed) { send(); } });
			} else {
				send();
			}
		});
	}

	// GET a JSON record.
	function get(url, onSuccess, onError) {
		$.ajax({
			type: 'GET', url: url, dataType: 'json', success: onSuccess,
			error: onError || function (xhr) { toast('error', 'Error!', errorMessage(xhr, 'Could not load the record.'), 2500); }
		});
	}

	/*
	 * Opens an edit modal: remembers the record id on the form (used by data-action-template), shows the modal,
	 * GETs the record and hands it to fillFn(data, $form).
	 */
	function edit(formSelector, modalSelector, showUrl, id, fillFn) {
		var $form = $(formSelector);
		$form.data('id', id);
		$(modalSelector).modal('show');
		get(showUrl, function (data) { fillFn(data, $form); });
	}

	// Sets a select2 to a single preselected option.
	function setOption(selector, id, text) {
		var $el = $(selector);
		$el.html($('<option>').val(id).text(text == null ? '' : text).prop('selected', true)).trigger('change');
	}

	/*
	 * Confirmed delete (DELETE verb). Removes #row_<id> afterwards. A 200 answer with result !== 'OK' (e.g. "In Use") shows the message.
	 *   opts.row       selector of the row to remove (default '#row_' + id)
	 *   opts.onSuccess function(data)
	 *   opts.inUseText text for a refused delete
	 */
	function destroy(url, id, opts) {
		opts = opts || {};
		Swal.fire({
			title: 'Delete?',
			text: 'Are you sure you want to delete!',
			icon: 'warning',
			showCancelButton: true,
			confirmButtonColor: '#3085d6',
			cancelButtonColor: '#d33',
			confirmButtonText: 'Yes, delete it!'
		}).then(function (result) {
			if (!result.isConfirmed) { return; }
			$.ajax({
				type: 'POST',
				url: url,
				data: { _method: 'DELETE' },
				dataType: 'json',
				success: function (data) {
					if (data && data.result !== undefined && String(data.result).trim() !== 'OK') {
						toast('error', 'Error!', opts.inUseText || data.message || 'In use unable to delete');
						return;
					}
					$(opts.row || ('#row_' + id)).remove();
					if (opts.onSuccess) { opts.onSuccess(data); return; }
					if (data && data.result !== undefined) { toast('success', 'Success!', 'Deleted Successfully'); }
				},
				error: function (xhr) { toast('error', 'Error!', errorMessage(xhr, 'Failed to delete.'), 2500); }
			});
		});
	}

	/*
	 * select2 fed by a GET route returning [{id, text}].
	 *   <select data-lookup-url="..." data-lookup-params="charge_department_id=#a_charge_department_id,x=#other">
	 * `q` always carries the search term; each name=selector pair is read at request time.
	 */
	function lookup(el, opts) {
		var $el = $(el);
		if (typeof $.fn.select2 === 'undefined' || $el.hasClass('select2-hidden-accessible')) { return; }
		opts = opts || {};
		var url = opts.url || $el.data('lookupUrl');
		var params = opts.params || $el.data('lookupParams') || '';
		$el.select2({
			placeholder: opts.placeholder || $el.data('placeholder') || $el.attr('placeholder') || '',
			allowClear: !!opts.allowClear,
			minimumInputLength: opts.minimumInputLength || 0,
			width: opts.width || '100%',
			// Inside a modal the dropdown is mounted on .modal-content, NOT on the full-screen .modal element: Bootstrap 5
			// treats any click whose target is the .modal element itself as a backdrop click and closes the modal.
			dropdownParent: opts.dropdownParent || ($el.closest('.modal-content').length ? $el.closest('.modal-content') : undefined),
			ajax: {
				url: url,
				dataType: 'json',
				type: 'GET',
				delay: 250,
				data: function (p) {
					var out = { q: p.term || '' };
					String(params).split(',').forEach(function (pair) {
						var kv = pair.split('=');
						if (kv.length === 2 && kv[0].trim()) { out[kv[0].trim()] = $(kv[1].trim()).val() || ''; }
					});
					return out;
				},
				processResults: function (data) { return { results: data }; },
				cache: true
			}
		});
	}

	window.appForm = {
		bind: bind, get: get, edit: edit, destroy: destroy, lookup: lookup, setOption: setOption,
		fill: fill, toast: toast, errorMessage: errorMessage
	};

	// Auto-binding from markup attributes.
	$(function () {
		$('form[data-action], form[data-action-template]').each(function () {
			if (this.id && !$(this).data('appFormManual')) { bind('#' + this.id); }
		});
		$('select[data-lookup-url]').each(function () { lookup(this); });
	});
})(window, jQuery);
