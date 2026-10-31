{{-- Shared jQuery helpers for the IPD pages: AJAX form submit, record lookup and confirmed delete.
     Include once inside the page's @push('scripts') block, before the page-specific script. --}}
<script>
(function (window, $) {
    function errorMessage(xhr, fallback) {
        var json = xhr.responseJSON || {};
        if (json.errors) {
            var first = Object.keys(json.errors)[0];
            if (first && json.errors[first].length) { return json.errors[first][0]; }
        }
        return json.message || fallback;
    }

    function requiredNotice() {
        Swal.fire({ icon: 'error', title: 'Required!', text: 'Please Enter Some Data', showConfirmButton: false, timer: 1500 });
    }

    // Binds an AJAX submit handler to a form. The global app.js listener for the same form id (bound earlier, in its
    // own document-ready) is removed first so the form is never submitted twice.
    //   opts.url           string | function(form) => string   target route
    //   opts.method        'PUT' | 'PATCH' | 'DELETE'          spoofed through _method (default plain POST)
    //   opts.requiredRows  jQuery selector that must match at least one row, else "Please Enter Some Data"
    //   opts.syncEditors   copy summernote content into the textareas before sending
    //   opts.confirm       { title, text, button }              ask before submitting
    //   opts.busyButton    jQuery selector of the submit button to disable while the request runs
    //   opts.onSuccess     function(data)                       default: success toast + page reload
    window.ipdBindForm = function (selector, opts) {
        opts = opts || {};
        $(selector).off('submit').on('submit', function (e) {
            e.preventDefault();
            var form = this;

            if (opts.requiredRows && $(opts.requiredRows).length === 0) {
                requiredNotice();
                return;
            }
            if (opts.syncEditors) {
                $(form).find('.summernote, .summernote-lite').each(function () {
                    if ($(this).data('summernote')) {
                        $(this).val($(this).summernote('isEmpty') ? '' : $(this).summernote('code'));
                    }
                });
            }

            var send = function () {
                var formData = new FormData(form);
                if (opts.method) { formData.append('_method', opts.method); }
                var $btn = $(opts.busyButton || $(form).find('button[type="submit"]'));
                var original = $btn.html();
                $btn.prop('disabled', true);

                $.ajax({
                    url: typeof opts.url === 'function' ? opts.url(form) : opts.url,
                    type: 'POST',
                    data: formData,
                    contentType: false,
                    cache: false,
                    processData: false,
                    dataType: 'json',
                    success: function (data) {
                        $btn.prop('disabled', false).html(original);
                        if (opts.onSuccess) { opts.onSuccess(data); return; }
                        Swal.fire({ icon: 'success', title: 'Saved!', text: 'Saved Successfully.', showConfirmButton: false, timer: 1500 });
                        location.reload();
                    },
                    error: function (xhr) {
                        $btn.prop('disabled', false).html(original);
                        Swal.fire({ icon: 'error', title: 'Error!', text: errorMessage(xhr, 'Failed to save. Please try again.'), showConfirmButton: false, timer: 2500 });
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
    };

    // Fills the __ID__ placeholder of a URL generated with route(..., {id: '__ID__'}).
    window.ipdUrl = function (template, id) {
        return template.replace('__ID__', encodeURIComponent(id));
    };

    // GET a JSON record (used to fill the edit modals).
    window.ipdFetch = function (url, onSuccess) {
        $.ajax({ type: 'GET', url: url, dataType: 'json', success: onSuccess });
    };

    // Asks for confirmation, sends DELETE and removes the table rows (#row_<id> / #drow_<id>) afterwards.
    window.ipdConfirmDelete = function (url, id) {
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
                success: function () {
                    $('#row_' + id).remove();
                    $('#drow_' + id).remove();
                },
                error: function (xhr) {
                    Swal.fire({ icon: 'error', title: 'Error!', text: errorMessage(xhr, 'Failed to delete.') });
                }
            });
        });
    };
})(window, jQuery);
</script>
