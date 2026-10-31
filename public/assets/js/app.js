$(window).on("load", function() {
	
});

var dtsalebillsungrouped;
var dtcollections;

$(document).ready(function() {
	$(".inner-menu li a").each(function() {  
			/*console.log("PAGE:"+this.href.split('?')[0]);
			console.log("HREF:"+window.location.href.split('?')[0]);*/
		if (this.href.split('?')[0] == window.location.href.split('?')[0]) {
			$(this).addClass("active");
		}
	});

	//Select2 Drop down when focus
	// on first focus (bubbles up to document), open the menu
	$(document).on('focus', '.select2-selection.select2-selection--single', function (e) {
		$(this).closest(".select2-container").siblings('select:enabled').select2('open');
	});
	$('select.select2').on('select2:closing', function (e) {
		$(e.target).data("select2").$selection.one('focus focusin', function (e) {
			e.stopPropagation();
		});
	});

	jQuery.extend(jQuery.expr[':'], {
		focusable: function (el, index, selector) {
			return $(el).is('a, button, :input, [tabindex]');
		}
	});

	$(document).on('keydown ,select2:close', 'input, select, button', function (e) {
		if (e.which == 13) {
			e.preventDefault();
			// Get all focusable elements on the page
			var $canfocus = $(':focusable');
			var index = $canfocus.index(document.activeElement) + 1;
			if (index >= $canfocus.length) index = 0;
			//console.log($canfocus.prop('nodeName')); //GET Current Tag Name like INPUT (Note .eq(index) means next control)
			//console.log($canfocus.eq(index).attr('onclick')); //GET onclick attribute like function
			//var nexe_class = $canfocus.eq(index).attr('class'); 
			//console.log($canfocus.eq(index).attr('type')); //GET type like submit, number, text, email etc
			//console.log($canfocus.eq(index).prop('nodeName')); //GET Next Tag Name like INPUT
			var etype = $canfocus.eq(index).attr('type');
			
			if(etype == 'submit')
			{
				$canfocus.eq(index).click(); //Perform click
			}
			var func = $canfocus.eq(index).attr('onclick');
			if(func != undefined)
			{
				eval(func); // Call function
			}
			else
			{
				$canfocus.eq(index).focus();
			}
		}
	});

	/*$(".ta-field").keydown(function(e){
		
		if (e.which == 13) {

		}
	});*/

	

	//Show focus for first input field when modal shown
	$('body').on('shown.bs.modal', '.modal', function () {
		$('input:visible:enabled:first', this).focus();
	});

	$(".sortable-tbl tbody").sortable({		
		update: function( event, ui ) {
			updateOrder();
		}
	}); 

	// Initialize Summernote only if the library is loaded
	if (typeof $.fn.summernote !== 'undefined') {
		$('.summernote, .summernote-lite, #systemic_examination').each(function() {
			var $this = $(this);
			if (!$this.data('summernote')) {
				var h = $this.attr('id') === 'systemic_examination' ? 186 : ($this.data('height') || 250);
				$this.summernote({
					height: h,
					callbacks: {
						onChange: function(contents) {
							$this.val($this.summernote('isEmpty') ? '' : contents);
						},
						onPaste: function(e) {
							var bufferText = ((e.originalEvent || e).clipboardData || window.clipboardData).getData('Text');
							e.preventDefault();
							document.execCommand('insertText', false, bufferText);
						}
					}
				});
			}
		});
	}

	// Global form submit hook to ensure any summernote content is synced to underlying textarea
	$(document).on('submit', 'form', function() {
		$(this).find('.summernote, .summernote-lite, #systemic_examination').each(function() {
			if ($(this).data('summernote')) {
				$(this).val($(this).summernote('isEmpty') ? '' : $(this).summernote('code'));
			}
		});
	});

	// Initialize timepicker only if the library is loaded
	if (typeof $.fn.timepicker !== 'undefined') {
		$('.tmpicker').timepicker({
			timeFormat: 'h:mm p',
			interval: 1,
			minTime: '12:00am',
			maxTime: '11:59pm',
			//defaultTime: '09:00am',
			startTime: '09:00am',
			dynamic: false,
			dropdown: false,
			scrollbar: true
		});

		$('.tmpicker').on('input', function () {
			let value = $(this).val();
			// Allow only digits, colon, a, p, m (case insensitive)
			value = value.replace(/[^0-9:apm]/gi, '');
			$(this).val(value);
		});
	}

	// Initialize flatpickr timepicker only if the library is loaded
	if (typeof flatpickr !== 'undefined') {
		$(".timepicker").flatpickr({
		enableTime: true,
		noCalendar: true,
		dateFormat: "h:i K",
	});

	$(".datepicker").flatpickr({
		enableTime: false,
		dateFormat: "d-m-Y"
	});

	$(".datetimepicker").flatpickr({
		enableTime: true,
		dateFormat: "d-m-Y h:i K"
	});

	$(".daterangepicker").flatpickr({
		enableTime: false,
		dateFormat: "d-m-Y",
		mode: "range"
	});

	$(".adatepicker").flatpickr({
		enableTime: false,
		dateFormat: "d-m-Y",
		minDate: new Date().fp_incr(-1), 
		maxDate: new Date()
	});
	}
	
	// Initialize select2 only if the library is loaded
	if (typeof $.fn.select2 !== 'undefined') {
		// select.select2: Select2's own wrapper <span> carries the class "select2" as well.
		$('select.select2').select2({
			placeholder: "Select",
		});
	}

	$("#checkAll").click(function () {
		$('input:checkbox').not(this).prop('checked', this.checked);
	});

	// Initialize DataTables only if the library is loaded
	if (typeof $.fn.DataTable !== 'undefined') {
		$('.data-tbl').DataTable({
			"pageLength": 100,
			dom: 'Blfrtip',
			buttons: [
				{
					extend: 'excel',
					text: 'Excel',
					className: 'buttons-html5',
					exportOptions: {
						modifer: {
							page: 'all',
							search: 'none'
						},
						columns: 'th:not(:first-child)'
					}
				},
				{
					extend: 'pdf',
					text: 'PDF',
					className: 'buttons-html5',
					exportOptions: {
						columns: 'th:not(:first-child)'
					}
				},
				{
					extend: 'print',
					text: 'Print',
					className: 'buttons-html5',
					exportOptions: {
						columns: 'th:not(:first-child)'
					}
				}
			],
			columnDefs: [
				{ targets: 'no-sort', orderable: false }
			]
		});
	}





	// Initialize server-side DataTables only if library is loaded and table exists
	if (typeof $.fn.DataTable !== 'undefined') {

	if ($('#patients_medicine_issue_tbl').length > 0) {
	var dtpatientmedicineissue = $('#patients_medicine_issue_tbl').DataTable({
		dom: 'Blfrtip',
		"pageLength": 100,
		'processing': true,
		'serverSide': true,
		'autoWidth': false,
		'serverMethod': 'post',
		'ajax': {
			'url': window.location.origin + '/periodic-medicine/issue-list/data',
			'data': function(data){
				var search_patient_medicine_issue = 'Yes';
				var report_period = $('#s_report_period').val();
				var search_date = $('#s_date').val();
				var search_month = $('#s_month').val();
				var from_date = $('#s_from_date').val();
				var to_date = $('#s_to_date').val();
				data.search_patient_medicine_issue = search_patient_medicine_issue;
				data.report_period = report_period;
				data.search_date = search_date;
				data.search_month = search_month;
				data.from_date = from_date;
				data.to_date = to_date;
			}
		},
		'columns': [
			{ data: 'action' }, 
			{ data: 'medicine_date_time' }, 
			{ data: 'next_medicine_date' }, 
			{ data: 'patient_name' }, 
			{ data: 'mobile_number' },
			{ data: 'address' }, 
			{ data: 'diseases' },
			{ data: 'delivery' },
			{ data: 'remarks' },
		],
		columnDefs: [{className: 'tr-sm-action-buttons center', targets: [0]}],
		buttons: [
			{
				extend: 'excel',
				text: 'Excel',
				className: 'buttons-html5',
				exportOptions: {
					modifer: {
						page: 'all',
						search: 'none'
					},
					columns: 'th:not(:first-child)'
				}
			},
			{
				extend: 'pdf',
				text: 'PDF',
				className: 'buttons-html5',
				exportOptions: {
					columns: 'th:not(:first-child)'
				},
			},
			{
				extend: 'print',
				text: 'Print',
				className: 'buttons-html5',
				exportOptions: {
					columns: 'th:not(:first-child)'
				}
			}
		],
		initComplete: function (settings, json) {
			$('[data-bs-toggle="tooltip"]').tooltip();
		},
		drawCallback: function (settings) {
			$('[data-bs-toggle="tooltip"]').tooltip();
		}
	});

	$('#search-patient-medicine-issue-btn').click(function(){
		dtpatientmedicineissue.draw();
	});
	} // End patients_medicine_issue_tbl check

	if ($('#patients_medicine_due_days_tbl').length > 0) {
		var dtpatientmedicineduedays = $('#patients_medicine_due_days_tbl').DataTable({
		dom: 'Blfrtip',
		"pageLength": 100,
		'processing': true,
		'serverSide': true,
		'autoWidth': false,
		'serverMethod': 'post',
		'ajax': {
			'url': window.location.origin + '/periodic-medicine/due-days/data',
			'data': function(data){
				var search_patient_medicine_due_days = 'Yes';
				var due_days = $('#s_due_days').val();
				data.search_patient_medicine_due_days = search_patient_medicine_due_days;
				data.due_days = due_days;
			}
		},
		'columns': [
			{ data: 'action' },
			{ data: 'id' }, 
			{ data: 'due_days' },  
			{ data: 'patient_name' }, 
			{ data: 'mobile_number' },
			{ data: 'address' }, 
			{ data: 'age' }, 
			{ data: 'diseases' },
			{ data: 'medicine_date_time' }, 
			{ data: 'next_medicine_date' }, 
			{ data: 'delivery' },
		],
		columnDefs: [{className: 'tr-sm-action-buttons center', targets: [0]}],
		buttons: [
			{
				extend: 'excel',
				text: 'Excel',
				className: 'buttons-html5',
				exportOptions: {
					modifer: {
						page: 'all',
						search: 'none'
					},
					columns: 'th:not(:first-child)'
				}
			},
			{
				extend: 'pdf',
				text: 'PDF',
				className: 'buttons-html5',
				exportOptions: {
					columns: 'th:not(:first-child)'
				},
			},
			{
				extend: 'print',
				text: 'Print',
				className: 'buttons-html5',
				exportOptions: {
					columns: 'th:not(:first-child)'
				}
			}
		],
		initComplete: function (settings, json) {
			$('[data-bs-toggle="tooltip"]').tooltip();
		},
		drawCallback: function (settings) {
			$('[data-bs-toggle="tooltip"]').tooltip();
		}
	});

	$('#search-pmdd-btn').click(function(){
		dtpatientmedicineduedays.draw();
	});
	} // End patients_medicine_due_days_tbl check

	if ($('#billings_tbl').length > 0) {
		var dtbillings = $('#billings_tbl').DataTable({
		dom: 'Blfrtip',
		"pageLength": 100,
		'processing': true,
		'serverSide': true,
		'autoWidth': false,
		'serverMethod': 'post',
		'ajax': {
			'url': $('#billings_tbl').data('url'),
			'data': function(data){
				var report_period = $('#s_report_period').val();
				var search_date = $('#s_date').val();
				var search_month = $('#s_month').val();
				var from_date = $('#s_from_date').val();
				var to_date = $('#s_to_date').val();
				var charge_department_id = $('#s_charge_department_id').val();
				data.report_period = report_period;
				data.search_date = search_date;
				data.search_month = search_month;
				data.from_date = from_date;
				data.to_date = to_date;
				data.charge_department_id = charge_department_id;
			}
		},
		'columns': [
			{ data: 'action' },
			{ data: 'id' },
			{ data: 'billing_date_time' },
			{ data: 'patient_name' },
			{ data: 'bill_title' },
			{ data: 'department_name' },
			{ data: 'amount' },
			{ data: 'discount' },
			{ data: 'addon_discount_amount' },
			{ data: 'total' },
			{ data: 'paid_amount' },
			{ data: 'payment_mode' },
			{ data: 'invoice_status', render: function(data) {
				return data === 'cancelled'
					? '<span class="badge bg-danger">Cancelled</span>'
					: '<span class="badge bg-success">Issued</span>';
			}}
		],
		order: [
			[0, 'asc'],
			[1, 'asc']
		],
		columnDefs: [{
			targets: [0, 1],
			visible: true
		},
		{className: 'td-number', targets: [7, 8, 9, 10, 11]}],
		buttons: [
			{
				extend: 'excel',
				text: 'Excel',
				className: 'buttons-html5',
				exportOptions: {
					modifer: {
						page: 'all',
						search: 'none'
					},
					columns: 'th:not(:first-child)'
				}
			},
			{
				extend: 'pdf',
				text: 'PDF',
				className: 'buttons-html5',
				exportOptions: {
					columns: 'th:not(:first-child)'
				},
			},
			{
				extend: 'print',
				text: 'Print',
				className: 'buttons-html5',
				exportOptions: {
					columns: 'th:not(:first-child)'
				}
			}
		],
		initComplete: function (settings, json) {
			$('[data-bs-toggle="tooltip"]').tooltip();
		},
		drawCallback: function (settings) {
			$('[data-bs-toggle="tooltip"]').tooltip();
		}
	});

	$('#search-billing-btn').click(function(){
		dtbillings.draw();
	});
	} // End billings_tbl check

 // End sale_bills_tbl check

	if ($('#sale_bills_ungrouped_tbl').length > 0) {
		dtsalebillsungrouped = $('#sale_bills_ungrouped_tbl').DataTable({
		dom: 'Blfrtip',
		"pageLength": 1000,
		'processing': true,
		'serverSide': true,
		'autoWidth': false,
		'serverMethod': 'post',
		'ajax': {
			'url': $('#sale_bills_ungrouped_tbl').data('url'),
			'data': function(data){
				var report_period = $('#s_report_period').val();
				var search_date = $('#s_date').val();
				var search_month = $('#s_month').val();
				var from_date = $('#s_from_date').val();
				var to_date = $('#s_to_date').val();
				var charge_department_id = $('#s_charge_department_ivy_id').val();
				data.report_period = report_period;
				data.search_date = search_date;
				data.search_month = search_month;
				data.from_date = from_date;
				data.to_date = to_date;
				data.charge_department_id = charge_department_id;
			}
		},
		'columns': [
			{ data: 'id' },
			{ data: 'billing_date_time' }, 
			{ data: 'department_name' },
			{ data: 'bill_title' }, 
			{ data: 'total' },
			{ data: 'payment_mode' }
		],
		order: [
			[0, 'asc']
		],
		buttons: [
			{
				extend: 'excel',
				text: 'Excel',
				className: 'buttons-html5',
				exportOptions: {
					modifer: {
						page: 'all',
						search: 'none'
					}
				}
			},
			{
				extend: 'pdf',
				text: 'PDF',
				className: 'buttons-html5',
				exportOptions: {
					columns: 'th:not(:first-child)'
				},
			},
			{
				extend: 'print',
				text: 'Print',
				className: 'buttons-html5',
				exportOptions: {
					columns: 'th:not(:first-child)'
				}
			}
		],
		"footerCallback": function ( row, data, start, end, display ) {
			var api = this.api(), data;

            // converting to interger to find total
			var intVal = function ( i ) {
				return typeof i === 'string' ?
				i.replace(/[\$,]/g, '')*1 :
				typeof i === 'number' ?
				i : 0;
			};

			var allbillingtotal = api
			.column( 4 )
			.data()
			.reduce( function (a, b) {
				return intVal(a) + intVal(b);
			}, 0 );


			$( api.column( 3 ).footer() ).html('All Total');
			$( api.column( 4 ).footer() ).html(allbillingtotal);

		},
		initComplete: function (settings, json) {
			$('[data-bs-toggle="tooltip"]').tooltip();
		},
		drawCallback: function (settings) {
			$('[data-bs-toggle="tooltip"]').tooltip();
		}
	});

	$('#search-sale-bill-ungrouped-btn').click(function(){
		dtsalebillsungrouped.draw();
	});
	} // End sale_bills_ungrouped_tbl check

 // End sale_search_tbl check

 // End staff_attendance_report_tbl check

	} // End main DataTable library check

	
	$('#s_report_period').on('change', function (e) {
		var x = this.value;
		if(x == 'Daily')
		{
			$("#report-period-daily").show();
			$("#report-period-monthly").hide();
			$("#report-period-periodic").hide();
		}
		if(x == 'Monthly')
		{
			$("#report-period-daily").hide();
			$("#report-period-monthly").show();
			$("#report-period-periodic").hide();
		}
		if(x == 'Periodic')
		{
			$("#report-period-daily").hide();
			$("#report-period-monthly").hide();
			$("#report-period-periodic").css('display', 'flex');
		}
	});



	// Initialize AJAX-based select2 dropdowns only if library is loaded
	if (typeof $.fn.select2 !== 'undefined') {

	
	
	// Store the search term for later use
	var lastSearchTerm = '';
	$('#add_opd_patients_dropdown').on('select2:open', function (e) {
		$(this).data('select2').$dropdown.find('.select2-search__field').on('input', function() {
			lastSearchTerm = $(this).val();
		});
		
		// Add click handler for "no results" message
		setTimeout(function() {
			$('.select2-results').off('click', '.add-patient-link').on('click', '.add-patient-link', function(event) {
				event.stopPropagation();
				$('#add_opd_patients_dropdown').select2('close');
				if (lastSearchTerm && /^\d{10}$/.test(lastSearchTerm)) {
					addOPDPatientModal(lastSearchTerm);
				} else {
					addOPDPatientModal();
				}
			});
		}, 100);
	});
	
	// Handle when user presses Enter on "No results found" - open Add Patient modal with mobile prefilled
	$('#add_opd_patients_dropdown').on('select2:close', function (e) {
		var $dropdown = $(this).data('select2').$dropdown;
		var searchTerm = lastSearchTerm;
		
		// Check if search term looks like a mobile number (10 digits)
		if (searchTerm && /^\d{10}$/.test(searchTerm)) {
			// Check if no results were found (dropdown is empty and no value selected)
			if (!$(this).val()) {
				// Check if no results message is showing
				var hasResults = $dropdown.find('.select2-results__option').not('.select2-results__message').length > 0;
				if (!hasResults) {
					// Small delay to ensure modal transition works smoothly
					setTimeout(function() {
						addOPDPatientModal(searchTerm);
					}, 200);
				}
			}
		}
	});

	$('#a_patient_id').select2({
		placeholder: "Patient",
		minimumlnputLength: 3,
		ajax: {
			url: window.location.origin + '/periodic-medicine/issue-list/dropdown-patients',
			dataType: "json",
			type: "POST",
			delay: 250,
			data: function(params){
				return {
					q: params.term,
				};
			},
			processResults: function(data){
				return {
					results: data
				};
			},
			cache: true
		}
	});

	$('#e_patient_id').select2({
		placeholder: "Patient",
		minimumlnputLength: 3,
		ajax: {
			url: window.location.origin + '/periodic-medicine/issue-list/dropdown-patients',
			dataType: "json",
			type: "POST",
			delay: 250,
			data: function(params){
				return {
					q: params.term,
				};
			},
			processResults: function(data){
				return {
					results: data
				};
			},
			cache: true
		}
	});













	




	
	





















	$('#a_dose_interval').select2({
		placeholder: "Interval",
		minimumlnputLength: 3,
		ajax: {
			url: window.location.origin + '/periodic-medicine/issue-list/dropdown-dose-interval',
			dataType: "json",
			type: "POST",
			delay: 250,
			data: function(params){
				return {
					q: params.term,
				};
			},
			processResults: function(data){
				return {
					results: data
				};
			},
			cache: true
		}
	});

	$('#e_dose_interval').select2({
		placeholder: "Interval",
		minimumlnputLength: 3,
		ajax: {
			url: window.location.origin + '/periodic-medicine/issue-list/dropdown-dose-interval',
			dataType: "json",
			type: "POST",
			delay: 250,
			data: function(params){
				return {
					q: params.term,
				};
			},
			processResults: function(data){
				return {
					results: data
				};
			},
			cache: true
		}
	});



	$('#a_medicine_name').select2({
		placeholder: "Type here...",
		minimumlnputLength: 3,
		ajax: {
			url: window.location.origin + '/periodic-medicine/issue-list/dropdown-medicines',
			dataType: "json",
			type: "POST",
			delay: 250,
			data: function(params){
				return {
					q: params.term,
				};
			},
			processResults: function(data){
				return {
					results: data
				};
			},
			cache: true
		}
	});

	$('#e_medicine_name').select2({
		placeholder: "Type here...",
		minimumlnputLength: 3,
		ajax: {
			url: window.location.origin + '/periodic-medicine/issue-list/dropdown-medicines',
			dataType: "json",
			type: "POST",
			delay: 250,
			data: function(params){
				return {
					q: params.term,
				};
			},
			processResults: function(data){
				return {
					results: data
				};
			},
			cache: true
		}
	});



	} // End select2 library check
	



	$("#add-pmi-patient-form").on('submit',(function(e) {
		e.preventDefault();
		$.ajax({
			url: window.location.origin + '/periodic-medicine/issue-list/save-patient',
			type: "POST",
			data:  new FormData(this),
			contentType: false,
			cache: false,
			processData:false,
			dataType:'json',
			success: function(data)
			{
				
				$.ajax({ 
					type: 'POST',
					url: window.location.origin + '/periodic-medicine/issue-list/dropdown-patients',                  
					data: {},                       
					dataType: 'json',                   
					success: function(a)         
					{
						if (typeof $.fn.select2 !== 'undefined') {
							$(".pmi-patient-list").select2({
								data:a
							});	
						}
						$('.pmi-patient-list').val(data.patient_id);
						$('.pmi-patient-list').trigger('change');
					} 
				});
				$("#add-pmi-patient-form")[0].reset();
				$("#addPMIPatientModal").modal('hide');
				Swal.fire({
					icon: "success",
					title: "Added!",
					text: "Patient Added Successfully.",
					showConfirmButton: false,
					timer: 1500
				});
			}	        
		});
	}));











































	$("#add_patient_medicine_form").on('submit',(function(e) {
		e.preventDefault();
		var medicines_list = $('#patient_medicine_tbl >tbody >tr').length;
		$("#add-patient-medicine-btn").html("Please wait...");
		if(medicines_list == 0)
		{
			Swal.fire({
				icon: "error",
				title: "Required!",
				text: "Add some medicines in list",
				showConfirmButton: false,
				timer: 1500
			});
			return;
		}
		
		$.ajax({
			url: window.location.origin + '/periodic-medicine/issue-list/save',
			type: "POST",
			data:  new FormData(this),
			contentType: false,
			cache: false,
			processData:false,
			dataType:'json',
			success: function(data)
			{
				Swal.fire({
					icon: "success",
					title: "Saved!",
					text: "Saved Successfully.",
					showConfirmButton: false,
					timer: 1500
				});
				$("#add-patient-medicine-btn").html("Save");
				dtpatientmedicineissue.draw();
			}	        
		});
	}));

	$("#edit_patient_medicine_form").on('submit',(function(e) {
		e.preventDefault();
		var medicines_list = $('#e_patient_medicine_tbl >tbody >tr').length;
		$("#edit-patient-medicine-btn").html("Please wait...");
		if(medicines_list == 0)
		{
			Swal.fire({
				icon: "error",
				title: "Required!",
				text: "Add some medicines in list",
				showConfirmButton: false,
				timer: 1500
			});
			return;
		}
		
		$.ajax({
			url: window.location.origin + '/periodic-medicine/issue-list/update',
			type: "POST",
			data:  new FormData(this),
			contentType: false,
			cache: false,
			processData:false,
			dataType:'json',
			success: function(data)
			{
				Swal.fire({
					icon: "success",
					title: "Saved!",
					text: "Saved Successfully.",
					showConfirmButton: false,
					timer: 1500
				});
				$("#edit-patient-medicine-btn").html("Save");
				dtpatientmedicineissue.draw();
			}	        
		});
	}));






















	
















}); //Document Ready End


function decode(str) {
	if (!str) return '';
	let txt = document.createElement("textarea");
	txt.innerHTML = str;
	return txt.value;
}

function showPrescriptionDetails(prescription_id, department)
{
	$.ajax({
		type: 'GET',
		url: window.appRoutes.chargeLookupPrescriptionDetails,
		data: {id: prescription_id, department: department},
		dataType: 'json',
		success: function(data)         
		{
			$("#prescription_charge_types").html(data.html);
		}
	});
}

// Lists the selected prescription's items that are NOT in billing yet. Billing is what is saved on this visit's bills
// (the server finds the visit through the prescription) plus what is queued in the add-charges table of this page
// (sent as exclude[]). Pages call this again whenever a queued row is added or removed or a bill is deleted.
function showPrescriptionTD()
{
	var prescription_id = $("#charge_prescriptions").val();
	var type = $("#prescription_charge_types option:selected").text();
	var values = $("#prescription_charge_types option:selected").val();
	var tables = {DCD: '#dcd_charge_detail_tbl', IPD: '#ipd_charge_detail_tbl', OPD: '#opd_charge_detail_tbl'};
	var department = '';
	$.each(tables, function(dept, selector) { if (!department && $(selector).length) { department = dept; } });
	var queued = department ? $(tables[department] + ' tbody input[name="charge_id_value[]"]').map(function() { return $(this).val(); }).get() : [];
	if(type != '' && values)
	{
		$.ajax({
		type: 'GET',
		url: window.appRoutes.chargeLookupPrescriptionCharges,
		data: {type: type, values: values, department: department, prescription_id: prescription_id, exclude: queued},
		dataType: 'json',
		success: function(data)         
			{
				$("#prescription_td").html(data.html);
			}
	});
	}
	else
	{
		$("#prescription_td").html('');
	}
}

// Add prescription item to charges automatically
function addPrescriptionItemToCharges(charge_id, charge_type)
{
	// Get patient category for pricing
	var patient_category_id = $("#patient_category_id").val();
	
	// Get the charge details and auto-fill the form
	$.ajax({
		type: 'GET',
		url: window.appRoutes.chargeLookupChargeForPrescription,
		data: {charge_id: charge_id, charge_type: charge_type, patient_category_id: patient_category_id},
		dataType: 'json',
		success: function(data)         
		{
			if(data.success) {
				// Get department name for Select2
				$.ajax({
		type: 'GET',
		url: window.appRoutes.chargeLookupDepartmentName,
		data: {department_id: data.charge_department_id},
		dataType: 'json',
		success: function(deptData) {
						// Create option and append to Select2 for department
						var deptName = (deptData && deptData.department_name) ? deptData.department_name : ('Department #' + data.charge_department_id);
						var deptOption = new Option(deptName, data.charge_department_id, true, true);
						$("#chargedepartmentid").val(data.charge_department_id);
						
						// Wait and then set category
						setTimeout(function() {
							// Get category name for Select2
							$.ajax({
		type: 'GET',
		url: window.appRoutes.chargeLookupCategoryName,
		data: {category_id: data.charge_category_id},
		dataType: 'json',
		success: function(catData) {
									// Create option and append to Select2 for category
									var catName = (catData && catData.category_name) ? catData.category_name : ('Category #' + data.charge_category_id);
									var catOption = new Option(catName, data.charge_category_id, true, true);
									$('#a_charge_category_id').append(catOption).trigger('change');
									
									// Wait and then set charge
									setTimeout(function() {
										// Get charge name for Select2
										$.ajax({
		type: 'GET',
		url: window.appRoutes.chargeLookupChargeName,
		data: {charge_id: charge_id},
		dataType: 'json',
		success: function(chargeData) {
												// Create option and append to Select2 for charge
												var chargeName = (chargeData && chargeData.charge_name) ? chargeData.charge_name : ('Charge #' + charge_id);
												var chargeOption = new Option(chargeName, charge_id, true, true);
												$('#a_ad_charge_id').append(chargeOption).trigger('change');
												
												// Set amount, discount, and quantity
												$("#charge_amount").val(data.amount);
												$("#charge_discount_percentage").val(0);
												$("#charge_quantity").val(1);
												
												// Calculate total
												calcOPDChargeTotal();
											}
	});
									}, 300);
								}
	});
						}, 300);
					}
	});
			} else {
				alert(data.message || 'Error loading charge details');
			}
		},
		error: function() {
			alert('Error adding charge. Please try again.');
		}
	});
}

function updateOrder()
{
	var db_table = $(".sortable-tbl").attr("db_table");
	var i = 0;
	$(".sortable-tbl tbody tr").each(function() { 
		i = i + 1;
		var tr_id = $(this).attr("tr_id"); 
		$.ajax({
		type: 'PATCH',
		url: appRoute('sortOrder', db_table),
		data: {tr_id: tr_id, i: i},
		dataType: 'json',
		success: function(data)         
			{
				
			}
	});
	});
}


/* -- Required For Multi Select2--*/

function createDCTTOIPDModal(id)
{
	$("#createDCTTOIPDModal").modal('show');
	$.ajax({
		type: 'GET',
		url: appRoute('dcdToIpd', id),
		dataType: 'json',
		success: function(data)         
		{
			$("#patient_id").val(data.patient_id);
			$("#opd_id").val(data.opd_id);
			$("#selected_bed_id").val(data.bed_id);
			$("#selected_bed_name").html("Bed: "+data.bed_name);
		}
	});
}





function createIPDModal()
{
	$("#createIPDModal").modal('show');
}

function createDCDModal()
{
	$("#createDCDModal").modal('show');
}

function createOPDModal()
{
	$("#createOPDModal").modal('show');
	// Token is now generated server-side during save to prevent race condition
	$("#token_number").html("Token: Will be assigned on save");
}

function showBedsUIModal()
{
	$("#showBedsUIModal").modal('show');
	$.ajax({
		type: 'GET',
		url: window.appRoutes.ipdBedPicker,
		dataType: 'json',
		success: function(data)         
		{
			$("#beds-ui-box").html(data.html);
		}
	});
}


function chooseBed(id, bed_name, id_field, name_field, ba)
{
	if(ba == 'Available')
	{
		$("#"+id_field).val(id);
		$("#"+name_field).html("Selected: "+bed_name);
		$("#showBedsUIModal").modal('hide');
	}
}





function addPrescriptionMedicine()
{
	var pmnor = parseInt($("#pmnor").val());
	
	// Get new field values
	var pharma_product_id = $("#a_pharma_product_id").val();
	var medicine_name = $("#a_pharma_product_id option:selected").text();
	var dose_quantity = $("#a_dose_quantity").val();
	var frequency_type_id = $("#a_frequency_type_id").val();
	var frequency_text = $("#a_frequency_type_id option:selected").text();
	var timing_ids = $("#a_timing_ids").val(); // Array of selected timing IDs
	var timing_text = $("#a_timing_ids option:selected").map(function() { return $(this).text(); }).get().join(" + ");
	var meal_relation_id = $("#a_meal_relation_id").val();
	var meal_text = $("#a_meal_relation_id option:selected").text();
	var duration_days = $("#a_duration_days").val();
	var medical_condition_id = $("#a_medical_condition_id").val();
	var condition_text = medical_condition_id ? $("#a_medical_condition_id option:selected").text() : 'Not Specified';
	
	// Validation
	if(pharma_product_id == '' || dose_quantity == '' || frequency_type_id == '' || timing_ids.length == 0 || duration_days == '')
	{
		Swal.fire({
			icon: "error",
			title: "Required!",
			text: "Please fill all required fields (Medicine, Quantity, Frequency, Timing, Days)",
			showConfirmButton: false,
			timer: 2000
		});
		return false;
	}
	
	pmnor = pmnor + 1;
	$("#pmnor").val(pmnor);
	
	// Build timing hidden inputs
	var timing_hidden_inputs = '';
	for(var i = 0; i < timing_ids.length; i++) {
		timing_hidden_inputs += '<input type="hidden" name="timing_ids_' + pmnor + '[]" value="' + timing_ids[i] + '">';
	}
	
	// Append to table
	$("#prescription_medicine_tbl tbody").append(
		'<tr id="pmr_'+pmnor+'">' +
			'<td><a href="javascript:void(0)" onclick="removePrescriptionMedicine('+pmnor+')" data-bs-toggle="tooltip" data-bs-placement="top" title="Remove"><i class="fas fa-times"></i></a></td>' +
			'<td>'+medicine_name+'<input type="hidden" name="pharma_product_id_value[]" value="'+pharma_product_id+'"></td>' +
			'<td>'+dose_quantity+'<input type="hidden" name="dose_quantity_value[]" value="'+dose_quantity+'"></td>' +
			'<td>'+frequency_text+'<input type="hidden" name="frequency_type_id_value[]" value="'+frequency_type_id+'"></td>' +
			'<td>'+timing_text+timing_hidden_inputs+'</td>' +
			'<td>'+meal_text+'<input type="hidden" name="meal_relation_id_value[]" value="'+meal_relation_id+'"></td>' +
			'<td>'+duration_days+' days<input type="hidden" name="duration_days_value[]" value="'+duration_days+'"></td>' +
			'<td>'+condition_text+'<input type="hidden" name="medical_condition_id_value[]" value="'+(medical_condition_id || '')+'"></td>' +
		'</tr>'
	);
	
	// Reset fields
	$("#a_pharma_product_id").val('').trigger('change');
	$("#a_dose_quantity").val('1');
	$("#a_timing_ids").val(null).trigger('change');
	$("#a_duration_days").val('5');
	$("#a_medical_condition_id").val('').trigger('change');
	$("#a_pharma_product_id").focus();
}

function addIPDPrescriptionMedicine()
{
	var pmnor = parseInt($("#ipd_pmnor").val());
	
	// Get new field values
	var pharma_product_id = $("#a_ipd_medicine_name").val();
	var medicine_name = $("#a_ipd_medicine_name option:selected").text();
	var dose_quantity = $("#a_ipd_dose_quantity").val();
	var frequency_type_id = $("#a_ipd_frequency").val();
	var frequency_text = $("#a_ipd_frequency option:selected").text();
	var timing_ids = $("#a_ipd_timing").val(); // Array of selected timing IDs
	var timing_text = $("#a_ipd_timing option:selected").map(function() { return $(this).text(); }).get().join(" + ");
	var meal_relation_id = $("#a_ipd_meal").val();
	var meal_text = $("#a_ipd_meal option:selected").text();
	var duration_days = $("#a_ipd_duration_days").val();
	var medical_condition_id = $("#a_ipd_medical_condition_id").val();
	var condition_text = medical_condition_id ? $("#a_ipd_medical_condition_id option:selected").text() : 'Not Specified';
	
	// Validation
	if(pharma_product_id == '' || dose_quantity == '' || frequency_type_id == '' || timing_ids.length == 0 || duration_days == '')
	{
		Swal.fire({
			icon: "error",
			title: "Required!",
			text: "Please fill all required fields (Medicine, Quantity, Frequency, Timing, Days)",
			showConfirmButton: false,
			timer: 2000
		});
		return false;
	}
	
	pmnor = pmnor + 1;
	$("#ipd_pmnor").val(pmnor);
	
	// Build timing hidden inputs
	var timing_hidden_inputs = '';
	for(var i = 0; i < timing_ids.length; i++) {
		timing_hidden_inputs += '<input type="hidden" name="timing_ids_' + pmnor + '[]" value="' + timing_ids[i] + '">';
	}
	
	// Append to table
	$("#ipd_prescription_medicine_tbl tbody").append(
		'<tr id="ipd_pmr_'+pmnor+'">' +
			'<td><a href="javascript:void(0)" onclick="removeIPDPrescriptionMedicine('+pmnor+')" data-bs-toggle="tooltip" data-bs-placement="top" title="Remove"><i class="fas fa-times"></i></a></td>' +
			'<td>'+medicine_name+'<input type="hidden" name="pharma_product_id_value[]" value="'+pharma_product_id+'"></td>' +
			'<td>'+dose_quantity+'<input type="hidden" name="dose_quantity_value[]" value="'+dose_quantity+'"></td>' +
			'<td>'+frequency_text+'<input type="hidden" name="frequency_type_id_value[]" value="'+frequency_type_id+'"></td>' +
			'<td>'+timing_text+timing_hidden_inputs+'</td>' +
			'<td>'+meal_text+'<input type="hidden" name="meal_relation_id_value[]" value="'+meal_relation_id+'"></td>' +
			'<td>'+duration_days+' days<input type="hidden" name="duration_days_value[]" value="'+duration_days+'"></td>' +
			'<td>'+condition_text+'<input type="hidden" name="medical_condition_id_value[]" value="'+(medical_condition_id || '')+'"></td>' +
		'</tr>'
	);
	
	// Reset fields
	$("#a_ipd_medicine_name").val('').trigger('change');
	$("#a_ipd_dose_quantity").val('1');
	$("#a_ipd_timing").val(null).trigger('change');
	$("#a_ipd_duration_days").val('1');
	$("#a_ipd_medical_condition_id").val('').trigger('change');
	$("#a_ipd_medicine_name").focus();
}

function removeIPDPrescriptionMedicine(nor)
{
	Swal.fire({
		title: 'Are you sure?',
		text: "You want to remove this medicine!",
		icon: 'warning',
		showCancelButton: true,
		confirmButtonColor: '#3085d6',
		cancelButtonColor: '#d33',
		confirmButtonText: 'Yes'
	}).then((result) => {
		if (result.isConfirmed) {
			$("#ipd_pmr_"+nor).remove();
		}
	})
}

function addEIPDPrescriptionMedicine()
{
	var pmnor = parseInt($("#e_ipd_pmnor").val());
	
	// Get new field values
	var pharma_product_id = $("#e_ipd_medicine_name").val();
	var medicine_name = $("#e_ipd_medicine_name option:selected").text();
	var dose_quantity = $("#e_ipd_dose_quantity").val();
	var frequency_type_id = $("#e_ipd_frequency").val();
	var frequency_text = $("#e_ipd_frequency option:selected").text();
	var timing_ids = $("#e_ipd_timing").val(); // Array of selected timing IDs
	var timing_text = $("#e_ipd_timing option:selected").map(function() { return $(this).text(); }).get().join(" + ");
	var meal_relation_id = $("#e_ipd_meal").val();
	var meal_text = $("#e_ipd_meal option:selected").text();
	var duration_days = $("#e_ipd_duration_days").val();
	var medical_condition_id = $("#e_ipd_medical_condition_id").val();
	var condition_text = medical_condition_id ? $("#e_ipd_medical_condition_id option:selected").text() : 'Not Specified';
	
	// Validation
	if(pharma_product_id == '' || dose_quantity == '' || frequency_type_id == '' || timing_ids.length == 0 || duration_days == '')
	{
		Swal.fire({
			icon: "error",
			title: "Required!",
			text: "Please fill all required fields (Medicine, Quantity, Frequency, Timing, Days)",
			showConfirmButton: false,
			timer: 2000
		});
		return false;
	}
	
	pmnor = pmnor + 1;
	$("#e_ipd_pmnor").val(pmnor);
	
	// Build timing hidden inputs
	var timing_hidden_inputs = '';
	for(var i = 0; i < timing_ids.length; i++) {
		timing_hidden_inputs += '<input type="hidden" name="timing_ids_' + pmnor + '[]" value="' + timing_ids[i] + '">';
	}
	
	// Append to table
	$("#e_ipd_prescription_medicine_tbl tbody").append(
		'<tr id="e_ipd_pmr_'+pmnor+'">' +
			'<td><a href="javascript:void(0)" onclick="removeEIPDPrescriptionMedicine('+pmnor+')" data-bs-toggle="tooltip" data-bs-placement="top" title="Remove"><i class="fas fa-times"></i></a></td>' +
			'<td>'+medicine_name+'<input type="hidden" name="pharma_product_id_value[]" value="'+pharma_product_id+'"></td>' +
			'<td>'+dose_quantity+'<input type="hidden" name="dose_quantity_value[]" value="'+dose_quantity+'"></td>' +
			'<td>'+frequency_text+'<input type="hidden" name="frequency_type_id_value[]" value="'+frequency_type_id+'"></td>' +
			'<td>'+timing_text+timing_hidden_inputs+'</td>' +
			'<td>'+meal_text+'<input type="hidden" name="meal_relation_id_value[]" value="'+meal_relation_id+'"></td>' +
			'<td>'+duration_days+' days<input type="hidden" name="duration_days_value[]" value="'+duration_days+'"></td>' +
			'<td>'+condition_text+'<input type="hidden" name="medical_condition_id_value[]" value="'+(medical_condition_id || '')+'"></td>' +
		'</tr>'
	);
	
	// Reset fields
	$("#e_ipd_medicine_name").val('').trigger('change');
	$("#e_ipd_dose_quantity").val('1');
	$("#e_ipd_timing").val(null).trigger('change');
	$("#e_ipd_duration_days").val('1');
	$("#e_ipd_medical_condition_id").val('').trigger('change');
	$("#e_ipd_medicine_name").focus();
}

function removeEIPDPrescriptionMedicine(nor)
{
	Swal.fire({
		title: 'Are you sure?',
		text: "You want to remove this medicine!",
		icon: 'warning',
		showCancelButton: true,
		confirmButtonColor: '#3085d6',
		cancelButtonColor: '#d33',
		confirmButtonText: 'Yes'
	}).then((result) => {
		if (result.isConfirmed) {
			$("#e_ipd_pmr_"+nor).remove();
			$("#epmr_"+nor).remove();
		}
	})
}

function addDCDPrescriptionMedicine()
{
	var pmnor = parseInt($("#dcd_pmnor").val());
	
	// Get new field values
	var pharma_product_id = $("#a_dcd_medicine_name").val();
	var medicine_name = $("#a_dcd_medicine_name option:selected").text();
	var dose_quantity = $("#a_dcd_dose_quantity").val();
	var frequency_type_id = $("#a_dcd_frequency").val();
	var frequency_text = $("#a_dcd_frequency option:selected").text();
	var timing_ids = $("#a_dcd_timing").val(); // Array of selected timing IDs
	var timing_text = $("#a_dcd_timing option:selected").map(function() { return $(this).text(); }).get().join(" + ");
	var meal_relation_id = $("#a_dcd_meal").val();
	var meal_text = $("#a_dcd_meal option:selected").text();
	var duration_days = $("#a_dcd_duration_days").val();
	var medical_condition_id = $("#a_dcd_medical_condition").val();
	var medical_condition_text = $("#a_dcd_medical_condition option:selected").text();
	
	// Validation
	if(pharma_product_id == '' || dose_quantity == '' || frequency_type_id == '' || timing_ids.length == 0 || duration_days == '')
	{
		Swal.fire({
			icon: "error",
			title: "Required!",
			text: "Please fill all required fields (Medicine, Quantity, Frequency, Timing, Days)",
			showConfirmButton: false,
			timer: 2000
		});
		return false;
	}
	
	pmnor = pmnor + 1;
	$("#dcd_pmnor").val(pmnor);
	
	// Build timing hidden inputs
	var timing_hidden_inputs = '';
	for(var i = 0; i < timing_ids.length; i++) {
		timing_hidden_inputs += '<input type="hidden" name="timing_ids_' + pmnor + '[]" value="' + timing_ids[i] + '">';
	}
	
	// Append to table
	$("#dcd_prescription_medicine_tbl tbody").append(
		'<tr id="dcd_pmr_'+pmnor+'">' +
			'<td><a href="javascript:void(0)" onclick="removeDCDPrescriptionMedicine('+pmnor+')" data-bs-toggle="tooltip" data-bs-placement="top" title="Remove"><i class="fas fa-times"></i></a></td>' +
			'<td>'+medicine_name+'<input type="hidden" name="pharma_product_id_value[]" value="'+pharma_product_id+'"></td>' +
			'<td>'+dose_quantity+'<input type="hidden" name="dose_quantity_value[]" value="'+dose_quantity+'"></td>' +
			'<td>'+frequency_text+'<input type="hidden" name="frequency_type_id_value[]" value="'+frequency_type_id+'"></td>' +
			'<td>'+timing_text+timing_hidden_inputs+'</td>' +
			'<td>'+meal_text+'<input type="hidden" name="meal_relation_id_value[]" value="'+meal_relation_id+'"></td>' +
			'<td>'+duration_days+' days<input type="hidden" name="duration_days_value[]" value="'+duration_days+'"></td>' +
			'<td>'+medical_condition_text+'<input type="hidden" name="medical_condition_id_value[]" value="'+medical_condition_id+'"></td>' +
		'</tr>'
	);
	
	// Reset fields
	$("#a_dcd_medicine_name").val('').trigger('change');
	$("#a_dcd_dose_quantity").val('1');
	$("#a_dcd_timing").val(null).trigger('change');
	$("#a_dcd_duration_days").val('5');
	$("#a_dcd_medical_condition").val('').trigger('change');
	$("#a_dcd_medicine_name").focus();
}

function removeDCDPrescriptionMedicine(nor)
{
	Swal.fire({
		title: 'Are you sure?',
		text: "You want to remove this medicine!",
		icon: 'warning',
		showCancelButton: true,
		confirmButtonColor: '#3085d6',
		cancelButtonColor: '#d33',
		confirmButtonText: 'Yes'
	}).then((result) => {
		if (result.isConfirmed) {
			$("#dcd_pmr_"+nor).remove();
		}
	})
}

function addEDCDPrescriptionMedicine()
{
	var pmnor = parseInt($("#e_dcd_pmnor").val());
	
	// Get new field values
	var pharma_product_id = $("#e_dcd_medicine_name").val();
	var medicine_name = $("#e_dcd_medicine_name option:selected").text();
	var dose_quantity = $("#e_dcd_dose_quantity").val();
	var frequency_type_id = $("#e_dcd_frequency").val();
	var frequency_text = $("#e_dcd_frequency option:selected").text();
	var timing_ids = $("#e_dcd_timing").val(); // Array of selected timing IDs
	var timing_text = $("#e_dcd_timing option:selected").map(function() { return $(this).text(); }).get().join(" + ");
	var meal_relation_id = $("#e_dcd_meal").val();
	var meal_text = $("#e_dcd_meal option:selected").text();
	var duration_days = $("#e_dcd_duration_days").val();
	var medical_condition_id = $("#e_dcd_medical_condition").val();
	var medical_condition_text = $("#e_dcd_medical_condition option:selected").text();
	
	// Validation
	if(pharma_product_id == '' || dose_quantity == '' || frequency_type_id == '' || timing_ids.length == 0 || duration_days == '')
	{
		Swal.fire({
			icon: "error",
			title: "Required!",
			text: "Please fill all required fields (Medicine, Quantity, Frequency, Timing, Days)",
			showConfirmButton: false,
			timer: 2000
		});
		return false;
	}
	
	pmnor = pmnor + 1;
	$("#e_dcd_pmnor").val(pmnor);
	
	// Build timing hidden inputs
	var timing_hidden_inputs = '';
	for(var i = 0; i < timing_ids.length; i++) {
		timing_hidden_inputs += '<input type="hidden" name="timing_ids_' + pmnor + '[]" value="' + timing_ids[i] + '">';
	}
	
	// Append to table
	$("#e_dcd_prescription_medicine_tbl tbody").append(
		'<tr id="e_dcd_pmr_'+pmnor+'">' +
			'<td><a href="javascript:void(0)" onclick="removeEDCDPrescriptionMedicine('+pmnor+')" data-bs-toggle="tooltip" data-bs-placement="top" title="Remove"><i class="fas fa-times"></i></a></td>' +
			'<td>'+medicine_name+'<input type="hidden" name="pharma_product_id_value[]" value="'+pharma_product_id+'"></td>' +
			'<td>'+dose_quantity+'<input type="hidden" name="dose_quantity_value[]" value="'+dose_quantity+'"></td>' +
			'<td>'+frequency_text+'<input type="hidden" name="frequency_type_id_value[]" value="'+frequency_type_id+'"></td>' +
			'<td>'+timing_text+timing_hidden_inputs+'</td>' +
			'<td>'+meal_text+'<input type="hidden" name="meal_relation_id_value[]" value="'+meal_relation_id+'"></td>' +
			'<td>'+duration_days+' days<input type="hidden" name="duration_days_value[]" value="'+duration_days+'"></td>' +
			'<td>'+medical_condition_text+'<input type="hidden" name="medical_condition_id_value[]" value="'+medical_condition_id+'"></td>' +
		'</tr>'
	);
	
	// Reset fields
	$("#e_dcd_medicine_name").val('').trigger('change');
	$("#e_dcd_dose_quantity").val('1');
	$("#e_dcd_timing").val(null).trigger('change');
	$("#e_dcd_duration_days").val('5');
	$("#e_dcd_medical_condition").val('').trigger('change');
	$("#e_dcd_medicine_name").focus();
}

function removeEDCDPrescriptionMedicine(nor)
{
	Swal.fire({
		title: 'Are you sure?',
		text: "You want to remove this medicine!",
		icon: 'warning',
		showCancelButton: true,
		confirmButtonColor: '#3085d6',
		cancelButtonColor: '#d33',
		confirmButtonText: 'Yes'
	}).then((result) => {
		if (result.isConfirmed) {
			$("#e_dcd_pmr_"+nor).remove();
		}
	})
}

function addEPrescriptionMedicine()
{
	var pmnor = parseInt($("#epmnor").val()) || 0;
	
	// Get new field values (supports both e_* and e_ipd_* element IDs)
	var $medSelect = ($("#e_pharma_product_id").length && $("#e_pharma_product_id").val()) ? $("#e_pharma_product_id") : $("#e_ipd_medicine_name");
	var pharma_product_id = $medSelect.val();
	var medicine_name = $medSelect.find("option:selected").text();
	var dose_quantity = ($("#e_dose_quantity").length && $("#e_dose_quantity").val() ? $("#e_dose_quantity") : $("#e_ipd_dose_quantity")).val();
	var $freqSelect = ($("#e_frequency_type_id").length && $("#e_frequency_type_id").val()) ? $("#e_frequency_type_id") : $("#e_ipd_frequency");
	var frequency_type_id = $freqSelect.val();
	var frequency_text = $freqSelect.find("option:selected").text();
	var $timingSelect = ($("#e_timing_ids").length && $("#e_timing_ids").val() && $("#e_timing_ids").val().length) ? $("#e_timing_ids") : $("#e_ipd_timing");
	var timing_ids = $timingSelect.val() || [];
	var timing_text = $timingSelect.find("option:selected").map(function() { return $(this).text(); }).get().join(" + ");
	var $mealSelect = ($("#e_meal_relation_id").length && $("#e_meal_relation_id").val()) ? $("#e_meal_relation_id") : $("#e_ipd_meal");
	var meal_relation_id = $mealSelect.val();
	var meal_text = $mealSelect.find("option:selected").text();
	var duration_days = ($("#e_duration_days").length && $("#e_duration_days").val() ? $("#e_duration_days") : $("#e_ipd_duration_days")).val();
	
	// Validation
	if(!pharma_product_id || !dose_quantity || !frequency_type_id || timing_ids.length === 0 || !duration_days)
	{
		Swal.fire({
			icon: "error",
			title: "Required!",
			text: "Please fill all required fields (Medicine, Quantity, Frequency, Timing, Days)",
			showConfirmButton: false,
			timer: 2000
		});
		return false;
	}
	
	pmnor = pmnor + 1;
	$("#epmnor").val(pmnor);
	
	// Build timing hidden inputs
	var timing_hidden_inputs = '';
	for(var i = 0; i < timing_ids.length; i++) {
		timing_hidden_inputs += '<input type="hidden" name="timing_ids_' + pmnor + '[]" value="' + timing_ids[i] + '">';
	}
	
	// Append to table
	$("#e_prescription_medicine_tbl tbody").append(
		'<tr id="epmr_'+pmnor+'" class="e_ipd_pmr_'+pmnor+'">' +
			'<td><a href="javascript:void(0)" onclick="removeEPrescriptionMedicine('+pmnor+')" data-bs-toggle="tooltip" data-bs-placement="top" title="Remove"><i class="fas fa-times"></i></a></td>' +
			'<td>'+medicine_name+'<input type="hidden" name="pharma_product_id_value[]" value="'+pharma_product_id+'"></td>' +
			'<td>'+dose_quantity+'<input type="hidden" name="dose_quantity_value[]" value="'+dose_quantity+'"></td>' +
			'<td>'+frequency_text+'<input type="hidden" name="frequency_type_id_value[]" value="'+frequency_type_id+'"></td>' +
			'<td>'+timing_text+timing_hidden_inputs+'</td>' +
			'<td>'+meal_text+'<input type="hidden" name="meal_relation_id_value[]" value="'+meal_relation_id+'"></td>' +
			'<td>'+duration_days+' days<input type="hidden" name="duration_days_value[]" value="'+duration_days+'"></td>' +
		'</tr>'
	);
	
	// Reset fields
	$("#e_pharma_product_id, #e_ipd_medicine_name").val('').trigger('change');
	$("#e_dose_quantity, #e_ipd_dose_quantity").val('1');
	$("#e_timing_ids, #e_ipd_timing").val(null).trigger('change');
	$("#e_duration_days, #e_ipd_duration_days").val('5');
	$("#e_pharma_product_id, #e_ipd_medicine_name").first().focus();
}

function removePrescriptionMedicine(id)
{
	$("#pmr_"+id).remove();
}

function removeEPrescriptionMedicine(id)
{
	$("#epmr_"+id).remove();
	$("#e_ipd_pmr_"+id).remove();
}


function addOPDPatientModal(mobileNumber)
{
	// Clear form first
	$("#add-opd-patient-form")[0].reset();
	
	// ESM fields visibility is controlled by server-side PHP based on logged-in category
	// No need to hide it here - it's already set correctly
	
	// Prefill mobile number if provided
	if (mobileNumber) {
		$("#addOPDPatientModal input[name='mobile_number']").val(mobileNumber);
	}
	
	$("#addOPDPatientModal").modal('show');
}

function addIPDPrescriptionModal()
{
	$("#addIPDPrescriptionModal").modal('show');
}

function addDCDPrescriptionModal()
{
	$("#addDCDPrescriptionModal").modal('show');
}

function addIPDTreatmentModal()
{
	$("#addIPDTreatmentModal").modal('show');
}

function addIPDVitalMonitoringModal()
{
	$("#addIPDVitalMonitoringModal").modal('show');
}

function addIPDIntakeOutputModal()
{
	$("#addIPDIntakeOutputModal").modal('show');
}

function addSubTestModal()
{
	$("#addSubTestModal").modal('show');
}

function addOPDPrescriptionModal()
{
	$("#addOPDPrescriptionModal").modal('show');
}

function addStaffMemberModal()
{
	$("#addStaffMemberModal").modal('show');
}

function addPatientModal()
{
	$("#addPatientModal").modal('show');
}

function addIPDDoctorModal()
{
	$("#addIPDDoctorModal").modal('show');
}

function addOPDDoctorModal()
{
	$("#addOPDDoctorModal").modal('show');
}

// ---------------------------------------------------------------
// ABDM M3/M4 (HIU side) - "Request Records From Abha" button on
// opd-visits.php, wired to abdm/hiu_ajax.php. The mirror direction of
// everything above: this app PULLING a patient's records from wherever
// else they've been treated, rather than pushing this facility's own
// records out. Keyed by patient_id (not opd_id) since the pull covers the
// patient's whole history, not just this one visit.
// ---------------------------------------------------------------
var ABDM_HIU_POLL_INTERVAL_SECONDS = 15;
var abdmHiuPollTimer = null;

function abdmHiuClearPoll()
{
	if (abdmHiuPollTimer) {
		clearInterval(abdmHiuPollTimer);
		abdmHiuPollTimer = null;
	}
}

// Called once on page load (see opd-visits.php) and again after every
// action below - purely a status read, safe to call as often as needed.
function abdmHiuRefreshButton(btnEl, patientId)
{
	if (!btnEl) { return; }
	$.ajax({
		url: appRoute('abdmHiuPatientStatus', patientId),
		type: 'GET',
		dataType: 'json',
		success: function(resp) {
			abdmHiuRenderButton(btnEl, patientId, resp);
		},
		error: function() {
			$(btnEl).text('ABDM Records').data('hiuState', 'error');
		}
	});
}

function abdmHiuRenderButton(btnEl, patientId, resp)
{
	var $btn = $(btnEl);
	abdmHiuClearPoll();

	if (!resp || !resp.success) {
		$btn.text('ABDM Records').data('hiuState', 'error');
		return;
	}

	if (!resp.has_abha) {
		// Per spec: no ABHA on file means there's nothing to request yet - shown
		// as a plain warning label (not the actionable button text) so staff
		// immediately understand why, rather than the button silently not
		// being there at all.
		$btn.text('Abha Details Not Provided').data('hiuState', 'no-abha');
		return;
	}

	var req = resp.request;
	if (!req || req.retryable) {
		$btn.text('Request Records From Abha').data('hiuState', 'requestable');
		return;
	}

	if (req.latest_status === 'REQUESTED') {
		$btn.text('Patient Approval Pending').data('hiuState', 'pending');
		// Local DB read, updated in the background by the notify webhook -
		// but also actively nudge ABDM for a fresh status on every tick (the
		// server throttles how often this actually hits ABDM's API - see
		// ABDM_HIU_STATUS_CHECK_THROTTLE_SECONDS), so a consent that was
		// quietly denied or expired without ABDM ever calling our notify
		// webhook still gets caught instead of staying stuck as "pending"
		// forever.
		$.ajax({
			url: appRoute('abdmHiuPatientConsentStatus', patientId),
			type: 'POST',
			dataType: 'json'
		});
		abdmHiuPollTimer = setInterval(function() {
			abdmHiuRefreshButton(btnEl, patientId);
		}, ABDM_HIU_POLL_INTERVAL_SECONDS * 1000);
		return;
	}

	if (req.latest_status === 'GRANTED') {
		// Full request detail (purpose/HI types/date range/expiry) is cached here
		// too, not just the consent id - abdmHiuButtonClick()'s 'granted' state
		// below shows it in a SweetAlert rather than an extra round-trip.
		$btn.text('Fetch Records').data('hiuState', 'granted').data('hiuConsentId', req.consent_id).data('hiuRequest', req);
		return;
	}

	// Any other/unknown in-between status - show it plainly rather than
	// guessing at a friendlier label for something unexpected.
	$btn.text('ABDM: ' + req.latest_status).data('hiuState', 'other');
}

function abdmHiuButtonClick(btnEl, patientId)
{
	var $btn = $(btnEl);
	// Bootstrap tooltips use a "hover focus" trigger by default, and clicking
	// this link keeps it focused (it never navigates), so the tooltip would
	// otherwise stay visible - floating on top of whatever this click opens
	// (SweetAlert/offcanvas/modal) - until the mouse/focus moves elsewhere.
	// Hide it immediately, before anything else below runs.
	$btn.tooltip('hide');
	var state = $btn.data('hiuState');

	if (state === 'no-abha') {
		Swal.fire({
			icon: 'warning',
			title: 'Abha Details Not Provided',
			text: "This patient has no ABHA Number/Address on file - unable to fetch records until their ABHA identity is linked."
		});
		return;
	}

	if (state === 'pending' || state === 'error' || state === 'other' || !state) {
		// Nothing actionable yet - just re-check now instead of waiting for
		// the next silent poll tick (also covers the case where this is
		// clicked before the very first status check has come back).
		abdmHiuRefreshButton(btnEl, patientId);
		return;
	}

	if (state === 'granted') {
		// A GRANTED consent already exists - rather than silently reusing it (or
		// silently starting over), show what it actually covers and let staff
		// choose: view/fetch with the existing grant, or request a fresh consent
		// (opens #hiuRequestConsentModal) with different Purpose/HI Types/duration.
		var req = $btn.data('hiuRequest') || {};
		var hiTypesText = (req.hi_types && req.hi_types.length) ? req.hi_types.join(', ') : 'N/A';
		var escape = function(text) { return $('<div>').text(text == null ? 'N/A' : text).html(); };
		var detailHtml = '<div style="text-align:left;font-size:13px;">' +
			'<div><b>Purpose:</b> ' + escape(req.purpose_text) + '</div>' +
			'<div><b>HI Types:</b> ' + escape(hiTypesText) + '</div>' +
			'<div><b>Health Info Range:</b> ' + escape(req.date_range_from) + ' - ' + escape(req.date_range_to) + '</div>' +
			'<div><b>Granted On:</b> ' + escape(req.granted_on) + '</div>' +
			'<div><b>Expires On:</b> ' + escape(req.expiry_on) + '</div>' +
			'</div>';

		Swal.fire({
			icon: 'info',
			title: 'Consent Already Granted',
			html: detailHtml,
			showDenyButton: true,
			showCancelButton: true,
			confirmButtonText: 'View / Fetch Records',
			denyButtonText: 'Request New Consent',
			cancelButtonText: 'Cancel',
			reverseButtons: true
		}).then(function(result) {
			if (result.isConfirmed) {
				// The view page looks up every granted consent artefact for this
				// patient itself (a grant can cover more than one facility) -
				// patient_id alone is enough. Shown in the shared full-width
				// offcanvas (see abdm-hiu-view-offcanvas.php, included on this
				// page) rather than a new tab.
				abdmHiuOpenViewOffcanvas(patientId);
			} else if (result.isDenied) {
				openHiuRequestConsentModal(btnEl, patientId);
			}
		});
		return;
	}

	// state === 'requestable' - let staff pick the Purpose/HI Types/duration/
	// expiry first (see #hiuRequestConsentModal on opd-visits.php) instead of
	// firing the request immediately with hardcoded defaults. The patient's
	// ABHA Address itself is already known server-side (resolved from
	// patient_id) so, unlike consent-requests.php's modal, there is no
	// Existing Patient/ABHA Address field here at all.
	openHiuRequestConsentModal(btnEl, patientId);
}

// ---------------------------------------------------------------
// ABDM M3/M4 (HIU side) - the shared "ABDM Health Records" offcanvas
// (abdm-hiu-view-offcanvas.php, opened by abdmHiuOpenViewOffcanvas() further
// below) shown from the "Fetch Records" state of abdmHiuButtonClick() above
// and from the Consent List's "VIEW" button. Drives the fetch-artefact ->
// request-health-data -> wait-for-push chain entirely client-side against
// abdm/hiu_ajax.php, then renders the decrypted records once they arrive.
// ---------------------------------------------------------------
var ABDM_HIU_VIEW_POLL_MS = 5000;
var ABDM_HIU_VIEW_MAX_SECONDS = 900; // 15 minutes - the HIP's push can genuinely take a while
var abdmHiuViewPollTimer = null;
var abdmHiuViewElapsedSeconds = 0;
// A single consent REQUEST can be granted as several separate consent
// ARTEFACTS - one per HIP that actually holds records for this patient (the
// whole point of "pull records from other facilities" - there can genuinely
// be more than one). These two are keyed by consent_id so every facility's
// fetch/request-health-data call fires exactly once, independently.
var abdmHiuViewArtefactRequested = {};
var abdmHiuViewHealthDataRequested = {};

function abdmHiuViewRecordsInit(patientId)
{
	// Guard against a leftover polling loop from a previously-viewed patient
	// still ticking (this can now be opened repeatedly, for different
	// patients, within the same page load - see abdmHiuOpenViewOffcanvas()).
	clearTimeout(abdmHiuViewPollTimer);
	abdmHiuViewPollTimer = null;
	abdmHiuViewElapsedSeconds = 0;
	abdmHiuViewArtefactRequested = {};
	abdmHiuViewHealthDataRequested = {};
	abdmHiuViewCheck(patientId);
}

function abdmHiuViewCheck(patientId)
{
	$.ajax({
		url: appRoute('abdmHiuPatientStatus', patientId),
		type: 'GET',
		dataType: 'json',
		success: function(resp) {
			abdmHiuViewHandleStatus(patientId, resp);
		},
		error: function() {
			$('#abdm_hiu_progress').html('<div class="alert alert-danger">Could not reach the server. Please reload the page.</div>');
		}
	});
}

function abdmHiuViewPollAgain(patientId)
{
	clearTimeout(abdmHiuViewPollTimer);
	abdmHiuViewElapsedSeconds += ABDM_HIU_VIEW_POLL_MS / 1000;
	if (abdmHiuViewElapsedSeconds >= ABDM_HIU_VIEW_MAX_SECONDS) {
		$('#abdm_hiu_progress').append('<div class="alert alert-warning mt-10">This is taking longer than expected - reload this page later to check again.</div>');
		return;
	}
	abdmHiuViewPollTimer = setTimeout(function() {
		abdmHiuViewCheck(patientId);
	}, ABDM_HIU_VIEW_POLL_MS);
}

function abdmHiuViewHandleStatus(patientId, resp)
{
	var $progress = $('#abdm_hiu_progress');

	if (!resp || !resp.success) {
		$progress.html('<div class="alert alert-danger">' + ((resp && resp.error) || 'Something went wrong') + '</div>');
		return;
	}

	// get_status always returns these on success - fills in the offcanvas
	// header (#abdm_hiu_view_title/#abdm_hiu_view_abha_line).
	if (resp.patient_name) {
		$('#abdm_hiu_view_title').text('ABDM Health Records - ' + resp.patient_name);
	}
	if (resp.abha_address) {
		$('#abdm_hiu_view_abha_line').html('ABHA Address: <b>' + $('<div>').text(resp.abha_address).html() + '</b>');
	}

	var req = resp.request;
	if (!req || req.latest_status !== 'GRANTED') {
		$progress.html('<div class="alert alert-warning">This patient\'s consent is not currently granted (status: ' + (req ? req.latest_status : 'none') + ') - records cannot be fetched right now.</div>');
		return;
	}

	// One consent REQUEST can be granted as several consent ARTEFACTS - one per
	// facility that actually holds records for this patient. Drive every one of
	// them through fetch-artefact -> request-health-data independently, and show
	// records from whichever facilities have already responded while still
	// polling in the background for the rest.
	var consents = req.consents || [];
	if (!consents.length) {
		$progress.html('<div class="alert alert-info"><i class="fa fa-spinner fa-spin"></i> Waiting for ABDM to confirm the consent details...</div>');
		abdmHiuViewPollAgain(patientId);
		return;
	}

	var doneCount = 0;
	var totalRecords = 0;
	var waitingCount = 0;
	var gracePeriodMinutes = 3; // Consider a facility done if no records after 3 minutes
	
	$.each(consents, function(i, c) {
		totalRecords += c.records_count || 0;

		if (!c.artefact_fetched) {
			if (!abdmHiuViewArtefactRequested[c.consent_id]) {
				abdmHiuViewArtefactRequested[c.consent_id] = true;
				$.ajax({
					url: window.appRoutes.abdmHiuFetchArtefact,
					type: 'POST',
					data: { consent_id: c.consent_id },
					dataType: 'json',
					success: function(fetchResp) {
						if (!fetchResp || !fetchResp.success) {
							// The initial synchronous call itself failed (not just "still
							// waiting for ABDM's callback") - allow a retry on the next
							// poll tick instead of getting stuck forever.
							abdmHiuViewArtefactRequested[c.consent_id] = false;
						}
					},
					error: function() { abdmHiuViewArtefactRequested[c.consent_id] = false; }
				});
			}
		} else if (!c.health_info_requested) {
			if (!abdmHiuViewHealthDataRequested[c.consent_id]) {
				abdmHiuViewHealthDataRequested[c.consent_id] = true;
				$.ajax({
					url: window.appRoutes.abdmHiuRequestHealthData,
					type: 'POST',
					data: { consent_id: c.consent_id },
					dataType: 'json',
					success: function(hiResp) {
						if (!hiResp || !hiResp.success) {
							abdmHiuViewHealthDataRequested[c.consent_id] = false;
						}
					},
					error: function() { abdmHiuViewHealthDataRequested[c.consent_id] = false; }
				});
			}
		} else {
			// Health info has been requested - check if we should consider it done
			if (c.records_count > 0) {
				// Has records - definitely done
				doneCount++;
			} else if (c.health_info_requested_at) {
				// No records yet - check how long we've been waiting
				var requestedAt = new Date(c.health_info_requested_at + ' UTC');
				var now = new Date();
				var minutesWaiting = (now - requestedAt) / 1000 / 60;
				
				if (minutesWaiting >= gracePeriodMinutes) {
					// Waited long enough - consider it done (facility has no records)
					doneCount++;
				} else {
					// Still within grace period - keep waiting
					waitingCount++;
				}
			} else {
				// No timestamp available - keep waiting
				waitingCount++;
			}
		}
	});

	if (totalRecords > 0) {
		$('#abdm_hiu_records_layout').show();
		abdmHiuViewLoadRecordsList(consents);
	}

	if (doneCount >= consents.length) {
		var facilitiesWithRecords = 0;
		$.each(consents, function(i, c) {
			if (c.records_count > 0) facilitiesWithRecords++;
		});
		
		var msg = totalRecords + ' record(s) received from ';
		if (facilitiesWithRecords === consents.length) {
			msg += consents.length + ' facilit' + (consents.length === 1 ? 'y' : 'ies') + '.';
		} else {
			msg += facilitiesWithRecords + ' of ' + consents.length + ' facilit' + (consents.length === 1 ? 'y' : 'ies') + '.';
			if (facilitiesWithRecords < consents.length) {
				var noRecordCount = consents.length - facilitiesWithRecords;
				msg += ' (' + noRecordCount + ' facilit' + (noRecordCount === 1 ? 'y has' : 'ies have') + ' no matching records)';
			}
		}
		$progress.html('<div class="alert alert-success">' + msg + '</div>');
		return;
	}

	var waitingMsg = consents.length > 1
		? (doneCount + ' of ' + consents.length + ' facilities have responded so far - still waiting on the rest (this can take a few minutes)...')
		: 'Waiting for the facility to push the actual encrypted records - this can take a few minutes.';
	$progress.html('<div class="alert alert-info"><i class="fa fa-spinner fa-spin"></i> ' + waitingMsg + '</div>' +
		(totalRecords > 0 ? '<div class="alert alert-success mt-10">' + totalRecords + ' record(s) received so far.</div>' : ''));
	abdmHiuViewPollAgain(patientId);
}

function abdmHiuViewLoadRecordsList(consents)
{
	var consentIds = $.map(consents, function(c) { return c.consent_id; });
	var allRecords = [];
	var remaining = consentIds.length;

	if (!remaining) {
		$('#abdm_hiu_records_list').html('<div class="text-muted">No records received yet.</div>');
		return;
	}

	$.each(consentIds, function(i, consentId) {
		$.ajax({
			url: appRoute('abdmHiuConsentRecords', consentId),
			type: 'GET',
			dataType: 'json',
			success: function(resp) {
				if (resp && resp.success && resp.records) {
					allRecords = allRecords.concat(resp.records);
				}
			},
			complete: function() {
				remaining--;
				if (remaining === 0) {
					abdmHiuViewRenderRecordsList(allRecords);
				}
			}
		});
	});
}

// dd-Mon-yyyy (e.g. "07-Sep-2026") - accepts either a MySQL "YYYY-MM-DD
// HH:MM:SS" datetime or a FHIR ISO datetime ("YYYY-MM-DDTHH:MM:SS+00:00").
// Deliberately reformats the value's own digits rather than round-tripping
// through Date() (which would silently reinterpret an offset-less MySQL
// datetime as the browser's local time) - staff just need it readable, not
// converted.
function abdmHiuFormatDate(raw)
{
	if (!raw) { return null; }
	var m = String(raw).match(/(\d{4})-(\d{2})-(\d{2})/);
	if (!m) { return raw; }
	var months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
	return m[3] + '-' + months[parseInt(m[2], 10) - 1] + '-' + m[1];
}

// dd-Mon-yyyy hh:mm AM/PM (e.g. "07-Sep-2026 04:32 PM")
function abdmHiuFormatDateTime(raw)
{
	if (!raw) { return null; }
	var m = String(raw).match(/(\d{4})-(\d{2})-(\d{2})[T ](\d{2}):(\d{2})/);
	if (!m) { return raw; }
	var months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
	var hour = parseInt(m[4], 10);
	var ampm = hour >= 12 ? 'PM' : 'AM';
	var hour12 = hour % 12; if (hour12 === 0) { hour12 = 12; }
	return m[3] + '-' + months[parseInt(m[2], 10) - 1] + '-' + m[1] + ' ' +
		(hour12 < 10 ? '0' + hour12 : hour12) + ':' + m[5] + ' ' + ampm;
}

function abdmHiuViewRenderRecordsList(records)
{
	if (!records.length) {
		$('#abdm_hiu_records_list').html('<div class="text-muted">No records received yet.</div>');
		return;
	}
	var html = '';
	$.each(records, function(i, rec) {
		var badgeHtml = '';
		if (rec.checksum_ok !== null && typeof rec.checksum_ok !== 'undefined') {
			badgeHtml = parseInt(rec.checksum_ok, 10) === 1
				? '<span class="badge bg-success" style="width:fit-content;">Verified</span>'
				: '<span class="badge bg-danger" style="width:fit-content;">Data Integrity Issue</span>';
		}
		var titleLine = rec.title || 'Health Record';
		var whenText = abdmHiuFormatDate(rec.visit_date) || abdmHiuFormatDate(rec.received_at);

		html += '<div class="card mb-10" style="cursor:pointer;padding:10px;" onclick="abdmHiuViewShowRecord(' + rec.id + ', this)">' +
			badgeHtml +
			'<div style="font-size:13px;font-weight:600;margin-top:5px;">' + $('<div>').text(titleLine).html() + '</div>' +
			(rec.facility ? '<div style="font-size:12px;color:#666;">' + $('<div>').text(rec.facility).html() + '</div>' : '') +
			(whenText ? '<div style="font-size:12px;color:#666;">' + whenText + '</div>' : '') +
			'</div>';
	});
	$('#abdm_hiu_records_list').html(html);
}

function abdmHiuViewShowRecord(recordId, triggerEl)
{
	$('#abdm_hiu_records_list .card').css('background', '');
	if (triggerEl) { $(triggerEl).css('background', '#e0f7f4'); }
	$('#abdm_hiu_record_detail').html('<div class="text-muted"><i class="fa fa-spinner fa-spin"></i> Loading...</div>');
	$.ajax({
		url: appRoute('abdmHiuRecordShow', recordId),
		type: 'GET',
		dataType: 'json',
		success: function(resp) {
			if (!resp.success) {
				$('#abdm_hiu_record_detail').html('<div class="alert alert-danger">' + (resp.error || 'Something went wrong') + '</div>');
				return;
			}
			abdmHiuViewRenderRecordDetail(resp.record);
		},
		error: function() {
			$('#abdm_hiu_record_detail').html('<div class="alert alert-danger">Could not reach the server. Please try again.</div>');
		}
	});
}

function abdmHiuViewRenderRecordDetail(rec)
{
	if (rec.decryption_error) {
		$('#abdm_hiu_record_detail').html('<div class="alert alert-danger">This record could not be decrypted: ' +
			$('<div>').text(rec.decryption_error).html() + '</div>');
		return;
	}

	var bundle = null;
	try { bundle = JSON.parse(rec.decrypted_bundle); } catch (e) { bundle = null; }

	if (!bundle) {
		$('#abdm_hiu_record_detail').html('<div class="alert alert-warning">No readable data is available for this record.</div>');
		return;
	}

	var summary = abdmHiuBuildClinicalSummary(bundle);
	var html = '';

	html += '<div style="font-size:15px;font-weight:600;">' + $('<div>').text(summary.title || 'Health Record').html() + '</div>';
	if (summary.facility) { html += '<div style="font-size:13px;color:#666;">' + $('<div>').text(summary.facility).html() + '</div>'; }
	var subLine = [];
	if (summary.visitDate) { subLine.push('Visit Date: ' + abdmHiuFormatDate(summary.visitDate)); }
	if (summary.doctorName) { subLine.push('Doctor: ' + $('<div>').text(summary.doctorName).html()); }
	if (subLine.length) { html += '<div style="font-size:12px;color:#888;margin-top:3px;">' + subLine.join(' &nbsp;|&nbsp; ') + '</div>'; }

	$.each(summary.sections, function(i, sec) {
		if (!sec.lines.length) { return; }
		html += '<hr><h6>' + $('<div>').text(sec.heading).html() + '</h6>';
		$.each(sec.lines, function(j, line) {
			html += '<div style="font-size:13px;margin-bottom:3px;">' + line + '</div>';
		});
	});

	if (summary.attachments.length) {
		html += '<hr><h6>Attachments</h6>';
		$.each(summary.attachments, function(i, att) {
			var filename = abdmHiuAttachmentFilename(summary, att);
			html += '<div class="mb-10" style="font-size:13px;"><b>' + $('<div>').text(att.title).html() + '</b> - ' +
				'<a download="' + $('<div>').text(filename).html() + '" href="data:' + att.contentType + ';base64,' + att.data + '">Download / View</a></div>';
		});
	}

	html += '<hr><div style="font-size:11px;color:#aaa;">Added to this patient\'s records on ' + abdmHiuFormatDateTime(rec.received_at) + '</div>';

	$('#abdm_hiu_record_detail').html(html);
}

// Human-friendly label for the handful of common LOINC/SNOMED display names
// that show up verbatim in vitals - kept short since staff scan these
// quickly, rather than showing the full clinical coding terms.
var ABDM_HIU_OBSERVATION_LABELS = {
	'Heart rate': 'Heart Rate',
	'Body temperature': 'Temperature',
	'Respiratory rate': 'Respiratory Rate',
	'Oxygen saturation': 'Oxygen Saturation (SpO2)',
	'Body weight': 'Weight',
	'Body height': 'Height'
};

function abdmHiuCodeText(codeable)
{
	if (!codeable) { return null; }
	if (codeable.text) { return codeable.text; }
	if (codeable.coding && codeable.coding[0]) { return codeable.coding[0].display || null; }
	return null;
}

function abdmHiuFormatObservation(res)
{
	var label = abdmHiuCodeText(res.code) || 'Observation';
	label = ABDM_HIU_OBSERVATION_LABELS[label] || label;

	// Blood pressure (and similar multi-component panels) carry their
	// readings under "component" rather than a direct value - combine
	// systolic/diastolic into one familiar "120/80 mmHg" line when present,
	// otherwise list each component on its own.
	if (res.component && res.component.length) {
		var systolic = null, diastolic = null, otherLines = [];
		$.each(res.component, function(i, comp) {
			var compLabel = abdmHiuCodeText(comp.code) || '';
			var compVal = comp.valueQuantity ? (comp.valueQuantity.value + ' ' + (comp.valueQuantity.unit || '')) : null;
			if (/systolic/i.test(compLabel)) { systolic = comp.valueQuantity ? comp.valueQuantity.value : null; }
			else if (/diastolic/i.test(compLabel)) { diastolic = comp.valueQuantity ? comp.valueQuantity.value : null; }
			else if (compVal) { otherLines.push($('<div>').text(compLabel).html() + ': ' + compVal); }
		});
		if (systolic !== null && diastolic !== null) {
			return '<b>Blood Pressure:</b> ' + systolic + '/' + diastolic + ' mmHg';
		}
		return otherLines.length ? ('<b>' + $('<div>').text(label).html() + ':</b> ' + otherLines.join(', ')) : null;
	}

	var value = null;
	if (res.valueQuantity) { value = res.valueQuantity.value + ' ' + (res.valueQuantity.unit || ''); }
	else if (res.valueString) { value = res.valueString; }
	else if (res.valueCodeableConcept) { value = abdmHiuCodeText(res.valueCodeableConcept); }
	if (value === null) { return null; }

	return '<b>' + $('<div>').text(label).html() + ':</b> ' + $('<div>').text(String(value)).html();
}

// Strips a FHIR narrative's XHTML div down to plain text - these come from
// external systems, so this is treated as untrusted markup rather than
// injected directly (jQuery's .text() extraction never executes it).
function abdmHiuStripHtml(html)
{
	if (!html) { return ''; }
	return $('<div>').html(html).text().replace(/\s+/g, ' ').trim();
}

// Common content-type -> file extension mapping for downloaded attachments
// (FHIR attachments are rarely anything beyond these in ABDM bundles).
var ABDM_HIU_EXT_BY_CONTENT_TYPE = {
	'application/pdf': 'pdf',
	'image/jpeg': 'jpg',
	'image/jpg': 'jpg',
	'image/png': 'png',
	'text/plain': 'txt',
	'text/html': 'html',
	'application/fhir+json': 'json',
	'application/json': 'json',
	'application/msword': 'doc',
	'application/vnd.openxmlformats-officedocument.wordprocessingml.document': 'docx'
};

function abdmHiuExtFromContentType(contentType)
{
	if (ABDM_HIU_EXT_BY_CONTENT_TYPE[contentType]) { return ABDM_HIU_EXT_BY_CONTENT_TYPE[contentType]; }
	var m = /\/([a-z0-9.+-]+)$/i.exec(contentType || '');
	return m ? m[1].split('+')[0] : 'bin';
}

// Turns free text into a safe, readable filename fragment - Windows/macOS
// both reject \/:*?"<>| in filenames, so those (plus any other non-typical
// character) become spaces, which then collapse to single underscores.
function abdmHiuFilenamePart(text)
{
	if (!text) { return ''; }
	return String(text).replace(/[\\/:*?"<>|]+/g, ' ').replace(/[^a-zA-Z0-9 _-]+/g, ' ').trim().replace(/\s+/g, '_');
}

// Builds a meaningful download filename for one attachment, e.g.
// "Simple_Kumari_OPD_Consultation_06-Sep-2026_OPD_Visit_Summary.pdf"
// instead of the browser's generic "attachment_0.pdf".
function abdmHiuAttachmentFilename(summary, att)
{
	var parts = [];
	if (summary.patientName) { parts.push(abdmHiuFilenamePart(summary.patientName)); }
	if (summary.title) { parts.push(abdmHiuFilenamePart(summary.title)); }
	if (summary.visitDate) { parts.push(abdmHiuFilenamePart(abdmHiuFormatDate(summary.visitDate))); }
	if (att.title) { parts.push(abdmHiuFilenamePart(att.title)); }
	if (!parts.length) { parts.push('ABDM_Health_Record'); }
	return parts.join('_') + '.' + abdmHiuExtFromContentType(att.contentType);
}

// Builds a readable clinical summary from a decrypted FHIR document Bundle
// by walking the Composition's own "section" list (the FHIR-correct way to
// read a document) and resolving each section's resource references,
// rather than just dumping every resource type found anywhere in the
// bundle - this keeps the same section headings/order the originating
// facility actually authored ("Chief Complaint", "Vital Signs",
// "Prescription", etc.) instead of a technical resource-type listing.
function abdmHiuBuildClinicalSummary(bundle)
{
	var byRef = {};
	var composition = null;
	$.each(bundle.entry || [], function(i, entry) {
		var res = entry.resource;
		if (!res || !res.resourceType) { return; }
		if (res.id) { byRef[res.resourceType + '/' + res.id] = res; }
		if (res.resourceType === 'Composition' && !composition) { composition = res; }
	});

	function resolve(ref) {
		return (ref && ref.reference) ? byRef[ref.reference] : null;
	}

	var summary = { title: null, facility: null, visitDate: null, doctorName: null, patientName: null, sections: [], attachments: [] };

	if (composition) {
		summary.title = composition.title || abdmHiuCodeText(composition.type) || null;
		summary.visitDate = composition.date || null;
		var org = resolve(composition.custodian);
		if (org) { summary.facility = org.name || null; }
		var author = composition.author && composition.author[0] ? resolve(composition.author[0]) : null;
		if (author) { summary.doctorName = (author.name && author.name[0] && author.name[0].text) || null; }
		var patient = resolve(composition.subject);
		if (patient) { summary.patientName = (patient.name && patient.name[0] && patient.name[0].text) || null; }

		$.each(composition.section || [], function(i, sec) {
			// The document's own attachment (e.g. a PDF visit summary) is
			// surfaced separately under "Attachments" below, not as a text
			// section.
			if (/clinical document/i.test(sec.title || '')) { return; }

			var lines = [];
			if (sec.text && sec.text.div) {
				var text = abdmHiuStripHtml(sec.text.div);
				if (text) { lines.push($('<div>').text(text).html()); }
			}
			$.each(sec.entry || [], function(j, entryRef) {
				var res = resolve(entryRef);
				if (!res) { return; }
				var line = abdmHiuFormatSectionResource(res);
				if (line) { lines.push(line); }
			});
			if (lines.length) { summary.sections.push({ heading: sec.title || 'Details', lines: lines }); }
		});
	}

	// Attachments can appear as a DocumentReference (this app's own OPD
	// visit summary PDF), inside a DiagnosticReport's presentedForm, or as a
	// bare Binary resource - collected regardless of whether the Composition
	// referenced them via a section, so nothing is silently dropped.
	$.each(bundle.entry || [], function(i, entry) {
		var res = entry.resource || {};
		if (res.resourceType === 'DocumentReference') {
			$.each(res.content || [], function(j, c) {
				if (c.attachment && c.attachment.data) {
					summary.attachments.push({ title: c.attachment.title || 'Document', contentType: c.attachment.contentType || 'application/octet-stream', data: c.attachment.data });
				}
			});
		} else if (res.resourceType === 'DiagnosticReport') {
			$.each(res.presentedForm || [], function(j, pf) {
				if (pf.data) {
					summary.attachments.push({ title: abdmHiuCodeText(res.code) || 'Diagnostic Report', contentType: pf.contentType || 'application/octet-stream', data: pf.data });
				}
			});
		} else if (res.resourceType === 'Binary' && res.data) {
			summary.attachments.push({ title: 'Attachment', contentType: res.contentType || 'application/octet-stream', data: res.data });
		}
	});

	return summary;
}

// Renders one resource referenced from a Composition section, based on its
// FHIR resource type - covers every type this app's own bundle generator
// (abdm/build_opd_fhir_bundle.php) produces, which is the same shape any
// standards-compliant ABDM HIP's OPConsultation/DischargeSummary bundle
// should follow.
function abdmHiuFormatSectionResource(res)
{
	switch (res.resourceType) {
		case 'Observation':
			return abdmHiuFormatObservation(res);
		case 'Condition':
			return abdmHiuCodeText(res.code) ? ('<b>Diagnosis:</b> ' + $('<div>').text(abdmHiuCodeText(res.code)).html()) : null;
		case 'MedicationRequest':
		case 'MedicationStatement': {
			var med = abdmHiuCodeText(res.medicationCodeableConcept);
			if (!med) { return null; }
			var dosage = res.dosageInstruction && res.dosageInstruction[0] && res.dosageInstruction[0].text;
			return '<b>' + $('<div>').text(med).html() + '</b>' + (dosage ? ' - ' + $('<div>').text(dosage).html() : '');
		}
		case 'Procedure':
			return abdmHiuCodeText(res.code) ? $('<div>').text(abdmHiuCodeText(res.code)).html() : null;
		case 'DiagnosticReport': {
			var name = abdmHiuCodeText(res.code) || 'Diagnostic Report';
			return '<b>' + $('<div>').text(name).html() + '</b>' + (res.conclusion ? ' - ' + $('<div>').text(res.conclusion).html() : '');
		}
		case 'AllergyIntolerance':
			return abdmHiuCodeText(res.code) ? ('<b>Allergy:</b> ' + $('<div>').text(abdmHiuCodeText(res.code)).html()) : null;
		case 'Immunization':
			return abdmHiuCodeText(res.vaccineCode) ? $('<div>').text(abdmHiuCodeText(res.vaccineCode)).html() : null;
		default:
			return null;
	}
}

function addChargeCategoryModal()
{
	$("#addChargeCategoryModal").modal('show');
}

function addChargeModal() {
	$("#addChargeModal").modal('show');
}

function addTPACompanyModal() {
	$("#addTPACompanyModal").modal('show');
}

function addUserRoleModal() {
	$("#addUserRoleModal").modal('show');
}

function addCategoryChargeModal() {
	$("#addCategoryChargeModal").modal('show');
}

function addIPDChargeModal()
{
	$("#addIPDChargeModal").modal('show');
	$("#add_ipd_charges_form")[0].reset();
	$('#a_ad_charge_id').val('').trigger('change');
	$("#ipd_charge_detail_tbl >tbody").empty();
}

function addDCDChargeModal()
{
	$("#addDCDChargeModal").modal('show');
	$("#add_dcd_charges_form")[0].reset();
	$('#a_ad_charge_id').val('').trigger('change');
	$("#dcd_charge_detail_tbl >tbody").empty();
	// The prescription panel is rebuilt on demand: a list left from an earlier opening could be out of date.
	$("#prescription_charge_types").empty();
	$("#prescription_td").empty();
}

function addBillModal()
{
	$("#addBillModal").modal('show');
	$("#add_billing_form")[0].reset();
	$('#a_ad_charge_id').val('').trigger('change');
	$("#bill_charge_detail_tbl >tbody").empty();
}

function addOPDChargeModal()
{
	$("#addOPDChargeModal").modal('show');
	$("#add_opd_charges_form")[0].reset();
	$('#a_ad_charge_id').val('').trigger('change');
	$("#opd_charge_detail_tbl >tbody").empty();
}

function addFloorModal()
{
	$("#addFloorModal").modal('show');
}

function addWardCategoryModal()
{
	$("#addWardCategoryModal").modal('show');
}

function addFloorAreaModal()
{
	$("#addFloorAreaModal").modal('show');
}

function addBedModal()
{
	$("#addBedModal").modal('show');
}

function showSlimCropper()
{
	$("#edit_image_box").show();
	$("#existing_image_box").hide();
}

function addPharmaProductCategoryModal()
{
	$("#addPharmaProductCategoryModal").modal('show');
}

function addPPCProductModal()
{
	$("#addPPCProductModal").modal('show');
}

function addTreatmentMedicine()
{
	var pmnor = parseInt($("#treatment_pmnor").val());
	
	// Get new field values
	var pharma_product_id = $("#a_treatment_medicine_name").val();
	var medicine_name = $("#a_treatment_medicine_name option:selected").text();
	var dose_quantity = $("#a_treatment_dose_quantity").val();
	var frequency_type_id = $("#a_treatment_frequency").val();
	var frequency_text = $("#a_treatment_frequency option:selected").text();
	var timing_ids = $("#a_treatment_timing").val() || []; // Array of selected timing IDs
	var timing_text = $("#a_treatment_timing option:selected").map(function() { return $(this).text(); }).get().join(" + ");
	var meal_relation_id = $("#a_treatment_meal").val();
	var meal_text = $("#a_treatment_meal option:selected").text();
	var duration_days = $("#a_treatment_duration_days").val();
	var medical_condition_id = $("#a_treatment_medical_condition_id").val();
	var condition_text = medical_condition_id ? $("#a_treatment_medical_condition_id option:selected").text() : 'Not Specified';
	
	// Validation
	if(pharma_product_id == '' || dose_quantity == '' || frequency_type_id == '' || !timing_ids || timing_ids.length == 0 || duration_days == '')
	{
		Swal.fire({
			icon: "error",
			title: "Required!",
			text: "Please fill all required fields (Medicine, Quantity, Frequency, Timing, Days)",
			showConfirmButton: false,
			timer: 2000
		});
		return false;
	}
	
	pmnor = pmnor + 1;
	$("#treatment_pmnor").val(pmnor);
	
	// Build timing hidden inputs
	var timing_hidden_inputs = '';
	for(var i = 0; i < timing_ids.length; i++) {
		timing_hidden_inputs += '<input type="hidden" name="timing_ids_' + pmnor + '[]" value="' + timing_ids[i] + '">';
	}
	
	// Append to table
	$("#treatment_medicine_tbl tbody").append(
		'<tr id="pmr_'+pmnor+'">' +
			'<td><a href="javascript:void(0)" onclick="removeTreatmentMedicine('+pmnor+')" data-bs-toggle="tooltip" data-bs-placement="top" title="Remove"><i class="fas fa-times"></i></a></td>' +
			'<td>'+medicine_name+'<input type="hidden" name="pharma_product_id_value[]" value="'+pharma_product_id+'"></td>' +
			'<td>'+dose_quantity+'<input type="hidden" name="dose_quantity_value[]" value="'+dose_quantity+'"></td>' +
			'<td>'+frequency_text+'<input type="hidden" name="frequency_type_id_value[]" value="'+frequency_type_id+'"></td>' +
			'<td>'+timing_text+timing_hidden_inputs+'</td>' +
			'<td>'+meal_text+'<input type="hidden" name="meal_relation_id_value[]" value="'+meal_relation_id+'"></td>' +
			'<td>'+duration_days+' days<input type="hidden" name="duration_days_value[]" value="'+duration_days+'"></td>' +
			'<td>'+condition_text+'<input type="hidden" name="medical_condition_id_value[]" value="'+(medical_condition_id || '')+'"></td>' +
		'</tr>'
	);
	
	// Reset fields
	$("#a_treatment_medicine_name").val('').trigger('change');
	$("#a_treatment_dose_quantity").val('1');
	$("#a_treatment_timing").val(null).trigger('change');
	$("#a_treatment_duration_days").val('5');
	$("#a_treatment_medical_condition_id").val('').trigger('change');
	$("#a_treatment_medicine_name").focus();
}

function addETreatmentMedicine()
{
	var pmnor = parseInt($("#e_treatment_pmnor").val());
	
	// Get new field values
	var pharma_product_id = $("#e_treatment_medicine_name").val();
	var medicine_name = $("#e_treatment_medicine_name option:selected").text();
	var dose_quantity = $("#e_treatment_dose_quantity").val();
	var frequency_type_id = $("#e_treatment_frequency").val();
	var frequency_text = $("#e_treatment_frequency option:selected").text();
	var timing_ids = $("#e_treatment_timing").val() || []; // Array of selected timing IDs
	var timing_text = $("#e_treatment_timing option:selected").map(function() { return $(this).text(); }).get().join(" + ");
	var meal_relation_id = $("#e_treatment_meal").val();
	var meal_text = $("#e_treatment_meal option:selected").text();
	var duration_days = $("#e_treatment_duration_days").val();
	var medical_condition_id = $("#e_treatment_medical_condition_id").val();
	var condition_text = medical_condition_id ? $("#e_treatment_medical_condition_id option:selected").text() : 'Not Specified';
	
	// Validation
	if(pharma_product_id == '' || dose_quantity == '' || frequency_type_id == '' || !timing_ids || timing_ids.length == 0 || duration_days == '')
	{
		Swal.fire({
			icon: "error",
			title: "Required!",
			text: "Please fill all required fields (Medicine, Quantity, Frequency, Timing, Days)",
			showConfirmButton: false,
			timer: 2000
		});
		return false;
	}
	
	pmnor = pmnor + 1;
	$("#e_treatment_pmnor").val(pmnor);
	
	// Build timing hidden inputs
	var timing_hidden_inputs = '';
	for(var i = 0; i < timing_ids.length; i++) {
		timing_hidden_inputs += '<input type="hidden" name="timing_ids_' + pmnor + '[]" value="' + timing_ids[i] + '">';
	}
	
	// Append to table
	$("#e_treatment_medicine_tbl tbody").append(
		'<tr id="epmr_'+pmnor+'">' +
			'<td><a href="javascript:void(0)" onclick="removeETreatmentMedicine('+pmnor+')" data-bs-toggle="tooltip" data-bs-placement="top" title="Remove"><i class="fas fa-times"></i></a></td>' +
			'<td>'+medicine_name+'<input type="hidden" name="pharma_product_id_value[]" value="'+pharma_product_id+'"></td>' +
			'<td>'+dose_quantity+'<input type="hidden" name="dose_quantity_value[]" value="'+dose_quantity+'"></td>' +
			'<td>'+frequency_text+'<input type="hidden" name="frequency_type_id_value[]" value="'+frequency_type_id+'"></td>' +
			'<td>'+timing_text+timing_hidden_inputs+'</td>' +
			'<td>'+meal_text+'<input type="hidden" name="meal_relation_id_value[]" value="'+meal_relation_id+'"></td>' +
			'<td>'+duration_days+' days<input type="hidden" name="duration_days_value[]" value="'+duration_days+'"></td>' +
			'<td>'+condition_text+'<input type="hidden" name="medical_condition_id_value[]" value="'+(medical_condition_id || '')+'"></td>' +
		'</tr>'
	);
	
	// Reset fields
	$("#e_treatment_medicine_name").val('').trigger('change');
	$("#e_treatment_dose_quantity").val('1');
	$("#e_treatment_timing").val(null).trigger('change');
	$("#e_treatment_duration_days").val('5');
	$("#e_treatment_medical_condition_id").val('').trigger('change');
	$("#e_treatment_medicine_name").focus();
}

function removeTreatmentMedicine(id)
{
	$("#pmr_"+id).remove();
}

function removeETreatmentMedicine(id)
{
	$("#epmr_"+id).remove();
}

function addVMD()
{
	var pmnor = parseInt($("#pmnor").val());
	var vital_monitoring_time = $("#a_vital_monitoring_time").val();
	var bp = $("#a_bp").val();
	var pulse = $("#a_pulse").val();
	var temperature = $("#a_temperature").val();
	var respiration = $("#a_respiration").val();
	var spo2 = $("#a_spo2").val();
	var fbs = $("#a_fbs").val();
	var rbs = $("#a_rbs").val();
	var intake = $("#a_intake").val();
	var output = $("#a_output").val();
	if(vital_monitoring_time == '')
	{
		Swal.fire({
			icon: "error",
			title: "Required!",
			text: "Please select time",
			showConfirmButton: false,
			timer: 1500
		});
		return false;
	}
	if(bp == '' && pulse == '' && temperature == '' && respiration == '' && spo2 == '' && fbs == '' && rbs == '' && intake == '' && output == '')
	{
		Swal.fire({
			icon: "error",
			title: "Required!",
			text: "Please enter some data",
			showConfirmButton: false,
			timer: 1500
		});
		return false;
	}
	pmnor = pmnor + 1;
	$("#pmnor").val(pmnor);
	$("#vital_monitoring_detail_tbl tbody").append('<tr id="pmr_'+pmnor+'"><td><a href="javascript:void(0)" onclick="removeVMD('+pmnor+')" data-bs-toggle="tooltip" data-bs-placement="top" title="Remove"><i class="fas fa-times"></i></a></td><td>'+vital_monitoring_time+'<input type="hidden" name="vital_monitoring_time_value[]" value="'+vital_monitoring_time+'"></td><td>'+bp+'<input type="hidden" name="bp_value[]" value="'+bp+'"></td><td>'+pulse+'<input type="hidden" name="pulse_value[]" value="'+pulse+'"></td><td>'+temperature+'<input type="hidden" name="temperature_value[]" value="'+temperature+'"></td><td>'+respiration+'<input type="hidden" name="respiration_value[]" value="'+respiration+'"></td><td>'+spo2+'<input type="hidden" name="spo2_value[]" value="'+spo2+'"></td><td>'+fbs+'<input type="hidden" name="fbs_value[]" value="'+fbs+'"></td><td>'+rbs+'<input type="hidden" name="rbs_value[]" value="'+rbs+'"></td><td>'+intake+'<input type="hidden" name="intake_value[]" value="'+intake+'"></td><td>'+output+'<input type="hidden" name="output_value[]" value="'+output+'"></td></tr>');
	$("#a_vital_monitoring_time").val('');
	$("#a_bp").val('');
	$("#a_pulse").val('');
	$("#a_temperature").val('');
	$("#a_respiration").val('');
	$("#a_spo2").val('');
	$("#a_fbs").val('');
	$("#a_rbs").val('');
	$("#a_intake").val('');
	$("#a_output").val('');
	$("#a_vital_monitoring_time").focus();
}

function addEVMD()
{
	var pmnor = parseInt($("#epmnor").val());
	var vital_monitoring_time = $("#e_vital_monitoring_time").val();
	var bp = $("#e_bp").val();
	var pulse = $("#e_pulse").val();
	var temperature = $("#e_temperature").val();
	var respiration = $("#e_respiration").val();
	var spo2 = $("#e_spo2").val();
	var fbs = $("#e_fbs").val();
	var rbs = $("#e_rbs").val();
	var intake = $("#e_intake").val();
	var output = $("#e_output").val();
	if(vital_monitoring_time == '')
	{
		Swal.fire({
			icon: "error",
			title: "Required!",
			text: "Please select time",
			showConfirmButton: false,
			timer: 1500
		});
		return false;
	}
	if(bp == '' && pulse == '' && temperature == '' && respiration == '' && spo2 == '' && fbs == '' && rbs == '' && intake == '' && output == '')
	{
		Swal.fire({
			icon: "error",
			title: "Required!",
			text: "Please enter some data",
			showConfirmButton: false,
			timer: 1500
		});
		return false;
	}
	pmnor = pmnor + 1;
	$("#epmnor").val(pmnor);
	$("#e_vital_monitoring_detail_tbl tbody").append('<tr id="epmr_'+pmnor+'"><td><a href="javascript:void(0)" onclick="removeEVMD('+pmnor+')" data-bs-toggle="tooltip" data-bs-placement="top" title="Remove"><i class="fas fa-times"></i></a></td><td>'+vital_monitoring_time+'<input type="hidden" name="vital_monitoring_time_value[]" value="'+vital_monitoring_time+'"></td><td>'+bp+'<input type="hidden" name="bp_value[]" value="'+bp+'"></td><td>'+pulse+'<input type="hidden" name="pulse_value[]" value="'+pulse+'"></td><td>'+temperature+'<input type="hidden" name="temperature_value[]" value="'+temperature+'"></td><td>'+respiration+'<input type="hidden" name="respiration_value[]" value="'+respiration+'"></td><td>'+spo2+'<input type="hidden" name="spo2_value[]" value="'+spo2+'"></td><td>'+fbs+'<input type="hidden" name="fbs_value[]" value="'+fbs+'"></td><td>'+rbs+'<input type="hidden" name="rbs_value[]" value="'+rbs+'"></td><td>'+intake+'<input type="hidden" name="intake_value[]" value="'+intake+'"></td><td>'+output+'<input type="hidden" name="output_value[]" value="'+output+'"></td></tr>');
	$("#e_vital_monitoring_time").val('');
	$("#e_bp").val('');
	$("#e_pulse").val('');
	$("#e_temperature").val('');
	$("#e_respiration").val('');
	$("#e_spo2").val('');
	$("#e_fbs").val('');
	$("#e_rbs").val('');
	$("#e_intake").val('');
	$("#e_output").val('');
	$("#e_vital_monitoring_time").focus();
}

function removeVMD(id)
{
	$("#pmr_"+id).remove();
}

function removeEVMD(id)
{
	$("#epmr_"+id).remove();
}

function addIntakeOutputDetail()
{
	var pmnor = parseInt($("#pmnor").val());
	var intake_output_time = $("#a_intake_output_time").val();
	var iv_fluids = $("#a_iv_fluids").val();
	var oral = $("#a_oral").val();
	var urine = $("#a_urine").val();
	var stool = $("#a_stool").val();
	var vomiting = $("#a_vomiting").val();
	var rt_asp = $("#a_rt_asp").val();
	var drain = $("#a_drain").val();
	if(intake_output_time == '')
	{
		Swal.fire({
			icon: "error",
			title: "Required!",
			text: "Please select time",
			showConfirmButton: false,
			timer: 1500
		});
		return false;
	}
	if(iv_fluids == '' && oral == '' && urine == '' && stool == '' && vomiting == '' && rt_asp == '' && drain == '')
	{
		Swal.fire({
			icon: "error",
			title: "Required!",
			text: "Please enter some data",
			showConfirmButton: false,
			timer: 1500
		});
		return false;
	}
	pmnor = pmnor + 1;
	$("#pmnor").val(pmnor);
	$("#intake_output_detail_tbl tbody").append('<tr id="pmr_'+pmnor+'"><td><a href="javascript:void(0)" onclick="removeIntakeOutputDetail('+pmnor+')" data-bs-toggle="tooltip" data-bs-placement="top" title="Remove"><i class="fas fa-times"></i></a></td><td>'+intake_output_time+'<input type="hidden" name="intake_output_time_value[]" value="'+intake_output_time+'"></td><td>'+iv_fluids+'<input type="hidden" name="iv_fluids_value[]" value="'+iv_fluids+'"></td><td>'+oral+'<input type="hidden" name="oral_value[]" value="'+oral+'"></td><td>'+urine+'<input type="hidden" name="urine_value[]" value="'+urine+'"></td><td>'+stool+'<input type="hidden" name="stool_value[]" value="'+stool+'"></td><td>'+vomiting+'<input type="hidden" name="vomiting_value[]" value="'+vomiting+'"></td><td>'+rt_asp+'<input type="hidden" name="rt_asp_value[]" value="'+rt_asp+'"></td><td>'+drain+'<input type="hidden" name="drain_value[]" value="'+drain+'"></td></tr>');
	$("#a_intake_output_time").val('');
	$("#a_iv_fluids").val('');
	$("#a_oral").val('');
	$("#a_urine").val('');
	$("#a_stool").val('');
	$("#a_vomiting").val('');
	$("#a_rt_asp").val('');
	$("#a_drain").val('');
	$("#a_intake_output_time").focus();
}

function addEIntakeOutputDetail()
{
	var pmnor = parseInt($("#epmnor").val());
	var intake_output_time = $("#e_intake_output_time").val();
	var iv_fluids = $("#e_iv_fluids").val();
	var oral = $("#e_oral").val();
	var urine = $("#e_urine").val();
	var stool = $("#e_stool").val();
	var vomiting = $("#e_vomiting").val();
	var rt_asp = $("#e_rt_asp").val();
	var drain = $("#e_drain").val();
	if(intake_output_time == '')
	{
		Swal.fire({
			icon: "error",
			title: "Required!",
			text: "Please select time",
			showConfirmButton: false,
			timer: 1500
		});
		return false;
	}
	if(iv_fluids == '' && oral == '' && urine == '' && stool == '' && vomiting == '' && rt_asp == '' && drain == '')
	{
		Swal.fire({
			icon: "error",
			title: "Required!",
			text: "Please enter some data",
			showConfirmButton: false,
			timer: 1500
		});
		return false;
	}
	pmnor = pmnor + 1;
	$("#epmnor").val(pmnor);
	$("#e_intake_output_detail_tbl tbody").append('<tr id="epmr_'+pmnor+'"><td><a href="javascript:void(0)" onclick="removeEIntakeOutputDetail('+pmnor+')" data-bs-toggle="tooltip" data-bs-placement="top" title="Remove"><i class="fas fa-times"></i></a></td><td>'+intake_output_time+'<input type="hidden" name="intake_output_time_value[]" value="'+intake_output_time+'"></td><td>'+iv_fluids+'<input type="hidden" name="iv_fluids_value[]" value="'+iv_fluids+'"></td><td>'+oral+'<input type="hidden" name="oral_value[]" value="'+oral+'"></td><td>'+urine+'<input type="hidden" name="urine_value[]" value="'+urine+'"></td><td>'+stool+'<input type="hidden" name="stool_value[]" value="'+stool+'"></td><td>'+vomiting+'<input type="hidden" name="vomiting_value[]" value="'+vomiting+'"></td><td>'+rt_asp+'<input type="hidden" name="rt_asp_value[]" value="'+rt_asp+'"></td><td>'+drain+'<input type="hidden" name="drain_value[]" value="'+drain+'"></td></tr>');
	$("#e_intake_output_time").val('');
	$("#e_iv_fluids").val('');
	$("#e_oral").val('');
	$("#e_urine").val('');
	$("#e_stool").val('');
	$("#e_vomiting").val('');
	$("#e_rt_asp").val('');
	$("#e_drain").val('');
	$("#e_intake_output_time").focus();
}

function removeIntakeOutputDetail(id)
{
	$("#pmr_"+id).remove();
}

function removeEIntakeOutputDetail(id)
{
	$("#epmr_"+id).remove();
}









function editOPDPrescriptionModal(id)
{
	$("#editOPDPrescriptionModal").modal('show');
	$.ajax({
		type: 'GET',
		url: appRoute('opdPrescriptionGet', id),
		dataType: 'json',
		success: function(data)         
		{

			$("#edit_opd_prescription_form #opd_prescription_id").val(data.row.id);
			$("#edit_opd_prescription_form #prescription_date_time").val(data.row.prescription_date_time);
			$("#edit_opd_prescription_form #e_doctor_id").html('<option value="'+data.row.doctor_id+'" selected>'+data.row.staff_name+'</option>');
			var pathology_tests = data.row.pathology_tests;
			if(pathology_tests != '')
			{
				var pathology_tests_array = pathology_tests.split(',');
				$("#edit_opd_prescription_form #e_pathology_tests").val(pathology_tests_array);
				$("#edit_opd_prescription_form #e_pathology_tests").trigger('change');
			}
			var radiology_tests = data.row.radiology_tests;
			if(radiology_tests != '')
			{
				var radiology_tests_array = radiology_tests.split(',');
				$("#edit_opd_prescription_form #e_radiology_tests").val(radiology_tests_array);
				$("#edit_opd_prescription_form #e_radiology_tests").trigger('change');
			}
			var procedures = data.row.procedures;
			if(procedures != '')
			{
				var procedures_array = procedures.split(',');
				$("#edit_opd_prescription_form #e_procedures").val(procedures_array);
				$("#edit_opd_prescription_form #e_procedures").trigger('change');
			}
			$("#edit_opd_prescription_form #findings").val(data.row.findings);
			$("#edit_opd_prescription_form #epmnor").val(data.nor);
			$("#e_prescription_medicine_tbl tbody").html(data.table_html);
			$("#edit_opd_prescription_form #e_follow_up_detail").val(data.row.follow_up_detail);
		}
	});	
}

function editDCDPrescriptionModal(id)
{
	$("#editDCDPrescriptionModal").modal('show');
	$.ajax({
		type: 'GET',
		url: appRoute('dcdPrescriptionGet', id),
		dataType: 'json',
		success: function(data)         
		{

			$("#edit_dcd_prescription_form #dcd_prescription_id").val(data.row.id);
			$("#edit_dcd_prescription_form #prescription_date_time").val(data.row.prescription_date_time);
			// Keep the full doctor list (so the doctor can still be changed); only add the saved doctor if the list
			// does not have them (e.g. deactivated since).
			var $eDoctor = $("#edit_dcd_prescription_form #e_doctor_id");
			if(!$eDoctor.find('option').filter(function(){ return this.value == data.row.doctor_id; }).length)
			{
				$eDoctor.append($('<option></option>').val(data.row.doctor_id).text(data.row.staff_name));
			}
			$eDoctor.val(String(data.row.doctor_id)).trigger('change');
			var pathology_tests = data.row.pathology_tests;
			if(pathology_tests != '')
			{
				var pathology_tests_array = pathology_tests.split(',');
				$("#edit_dcd_prescription_form #e_pathology_tests").val(pathology_tests_array);
				$("#edit_dcd_prescription_form #e_pathology_tests").trigger('change');
			}
			var radiology_tests = data.row.radiology_tests;
			if(radiology_tests != '')
			{
				var radiology_tests_array = radiology_tests.split(',');
				$("#edit_dcd_prescription_form #e_radiology_tests").val(radiology_tests_array);
				$("#edit_dcd_prescription_form #e_radiology_tests").trigger('change');
			}
			var procedures = data.row.procedures;
			if(procedures != '')
			{
				var procedures_array = procedures.split(',');
				$("#edit_dcd_prescription_form #e_procedures").val(procedures_array);
				$("#edit_dcd_prescription_form #e_procedures").trigger('change');
			}
			$("#edit_dcd_prescription_form #findings").val(data.row.findings);
			$("#edit_dcd_prescription_form #e_dcd_pmnor").val(data.nor);
			$("#e_dcd_prescription_medicine_tbl tbody").html(data.table_html);
		}
	});	
}






function copyIPDPrescriptionModal(id)
{
	$("#copyIPDPrescriptionModal").modal('show');
	$.ajax({
		type: 'GET',
		url: appRoute('ipdPrescriptionGet', id),
		dataType: 'json',
		success: function(data)         
		{

			$("#edit_ipd_prescription_form #e_doctor_id").html('<option value="'+data.row.doctor_id+'" selected>'+data.row.staff_name+'</option>');
			var pathology_tests = data.row.pathology_tests;
			if(pathology_tests != '')
			{
				var pathology_tests_array = pathology_tests.split(',');
				$("#edit_ipd_prescription_form #e_pathology_tests").val(pathology_tests_array);
				$("#edit_ipd_prescription_form #e_pathology_tests").trigger('change');
			}
			var radiology_tests = data.row.radiology_tests;
			if(radiology_tests != '')
			{
				var radiology_tests_array = radiology_tests.split(',');
				$("#edit_ipd_prescription_form #e_radiology_tests").val(radiology_tests_array);
				$("#edit_ipd_prescription_form #e_radiology_tests").trigger('change');
			}
			$("#edit_ipd_prescription_form #findings").val(data.row.findings);
			$("#edit_ipd_prescription_form #epmnor").val(data.nor);
			$("#e_prescription_medicine_tbl tbody").html(data.table_html);
		}
	});	
}









function editStaffMemberModal(id)
{
	$("#editStaffMemberModal").modal('show');
	
	$.ajax({
		type: 'GET',
		url: appRoute('staffShow', id),
		dataType: 'json',
		success: function(data)         
		{
			$("#edit_staff_member_form #staff_member_id").val(id);
			$("#edit_staff_member_form #joining_date").val(data.joining_date);
			$("#edit_staff_member_form #staff_name").val(data.staff_name);
			$("#edit_staff_member_form #mobile_number").val(data.mobile_number);

			$("#edit_staff_member_form #e_department_id").html('<option value="'+data.department_id+'" selected>'+data.department+'</option>');
			$("#edit_staff_member_form #e_designation_id").html('<option value="'+data.designation_id+'" selected>'+data.designation+'</option>');
			
			var specialities = data.specialities;
			var specialities_array = specialities.split(',');
			$("#edit_staff_member_form #e_specialities").val(specialities_array);
			$("#edit_staff_member_form #e_specialities").trigger('change');

			$("#edit_staff_member_form #gender").val(data.gender);
			$("#edit_staff_member_form #dob").val(data.dob);
			$("#edit_staff_member_form #email").val(data.email);
			$("#edit_staff_member_form #residential_address").val(data.residential_address);
			$("#edit_staff_member_form #permanent_address").val(data.permanent_address);
			$("#edit_staff_member_form #qualification").val(data.qualification);
			$("#edit_staff_member_form #experience_years").val(data.experience_years);
			$("#edit_staff_member_form #aadhar_number, #edit_staff_member_form #aadhaar_number").val(data.aadhar_number || data.aadhaar_number);
			$("#edit_staff_member_form #password").val(data.password);
			$("#edit_staff_member_form #e_user_role_id").html('<option value="'+data.user_role_id+'" selected>'+data.role+'</option>');

			if(data.photo != '')
			{
				$("#edit_staff_member_form #edit_photo").val(data.photo);
				$("#staff_image").attr('src', appUploadUrl('staffmembers', data.photo));
			}
		}
	});	
}


function editPatientModal(id, onLoaded)
{
	$("#editPatientModal").modal('show');
	
	$.ajax({
		type: 'GET',
		url: appRoute('patientShow', id),
		dataType: 'json',
		success: function(data)         
		{
			data = data.patient || data;
			$("#update-patient-form #patient_id").val(id);
			$("#update-patient-form #patient_category_id").val(data.patient_category_id);
			$("#update-patient-form #patient_name").val(data.patient_name);
			$("#update-patient-form #guardian_name").val(data.guardian_name);
			$("#update-patient-form #gender").val(data.gender);
			$("#update-patient-form #age").val(data.age);
			$("#update-patient-form #blood_group").val(data.blood_group);
			$("#update-patient-form #mobile_number").val(data.mobile_number);
			$("#update-patient-form #alternate_mobile_number").val(data.alternate_mobile_number);
			$("#update-patient-form #esm_name").val(data.esm_name);
			$("#update-patient-form #card_number").val(data.card_number);
			$("#update-patient-form #service_number").val(data.service_number);
			$("#update-patient-form #claim_id").val(data.claim_id);
			$("#update-patient-form #address").val(data.address);

			// Show/hide ESM fields based on patient category
			var categoryId = parseInt(data.patient_category_id);
			if (categoryId && categoryId != 1) {
				$('#edit_esm_fields_container').show();
			} else {
				$('#edit_esm_fields_container').hide();
			}

			if(data.photo != '')
			{
				$("#update-patient-form #edit_photo").val(data.photo);
				$("#patient_image").attr('src', appUploadUrl('patients', data.photo));
			}

			// Patient Identity (ABDM) - prefill hidden fields from the patient's
			// current DB values, and show them read-only if already linked so
			// editing a patient never forces re-verification.
			var abhaNumber = data.abha_number || '';
			var abhaAddress = data.abha_address || '';
			var aadhaarNumber = data.aadhaar_number || '';
			$('#edit_new_patient_abha_number').val(abhaNumber);
			$('#edit_new_patient_abha_address').val(abhaAddress);
			$('#edit_new_patient_aadhaar_number').val(aadhaarNumber);
			// Date of birth is only ever populated from ABDM (never manually
			// entered) - prefill the hidden field from the patient's current DB
			// value so re-saving the form without re-verifying identity doesn't
			// wipe it out.
			$('#edit_new_patient_date_of_birth').val(data.date_of_birth || '');
			if (abhaNumber || abhaAddress) {
				$('#edit_identity_linked_abha_number').text(abhaNumber || '-');
				$('#edit_identity_linked_abha_address').text(abhaAddress || '-');
				$('#edit_identity_linked_summary').show();
				$('#edit_identity_form').hide();
			} else {
				$('#edit_identity_linked_summary').hide();
				$('#edit_identity_form').show();
			}

			// Optional hook used by patients.php's "Use This Existing Patient
			// Instead" flow (ABDM M1 returning-patient match), to apply a
			// freshly-verified identity onto this patient once the form is loaded.
			if (typeof onLoaded === 'function') {
				onLoaded(data);
			}
		}
	});	
}

function showOPDIDPatient()
{
	var opd_id = $("#opd_id").val();
	if(opd_id == '')
	{
		Swal.fire({
			icon: "error",
			title: "Required!",
			text: "Please Enter OPD ID",
			showConfirmButton: false,
			timer: 1500
		});
		return;
	}
	$.ajax({
		type: 'GET',
		url: appRoute('dcdOpdPatient', opd_id),
		dataType: 'json',
		success: function(data)         
		{
			if(data.opd_exist == 'Yes')
			{
				Swal.fire({
					icon: "error",
					title: "Exist!",
					text: "IPD Already Exist Please Check OPD ID",
					showConfirmButton: false,
					timer: 1500
				});
				return;
			}

			if(data.nor > 0)
			{
				$("#ipd_patient_info_box").css('display', 'flex');
				$("#ipd_patient_info").html(data.html);
				$("#patient_id").val(data.patient_id);
				$("#mobile_number").val(data.mobile_number);
				$("#address").val(data.address);
			}
			else
			{
				Swal.fire({
					icon: "error",
					title: "No Record!",
					text: "Please Check Entered OPD ID",
					showConfirmButton: false,
					timer: 1500
				});
			}
			
		}
	});	
}

// Currently selected existing patient in the Create OPD modal - used by
// opd-list.php's "Patient Identity (Optional)" section to know which patient
// any verify/save actions apply to.
var opdSelectedPatient = null;




function editChargeModal(id)
{
	$("#editChargeModal").modal('show');

	$.ajax({
		type: 'GET',
		url: appRoute('masterChargeShow', id),
		dataType: 'json',
		success: function(data)         
		{
			$("#edit_charge_form #charge_id").val(id);
			$("#edit_charge_form #charge_code").val(data.charge_code);
			$("#edit_charge_form #charge_name").val(data.charge_name);
			$("#edit_charge_form #e_charge_department_id").html('<option value="'+data.charge_department_id+'" selected>'+data.department_name+'</option>');
			$("#edit_charge_form #e_charge_category_id").html('<option value="'+data.charge_category_id+'" selected>'+data.charge_category+'</option>');
			
		}
	});	
}


function calcOPDChargeTotal()
{
	var charge_amount = 0;
	var charge_quantity = 0;
	var discount_percentage = 0;
	if($("#charge_amount").val() != '')
	{
		charge_amount = parseFloat($("#charge_amount").val());
	}
	if($("#charge_quantity").val() != '')
	{
		charge_quantity = parseInt($("#charge_quantity").val());
	}
	if($("#charge_discount_percentage").val() != '')
	{
		discount_percentage = parseFloat($("#charge_discount_percentage").val());
	}
	var charge_total = charge_amount * charge_quantity;
	var discount_amount = (charge_total * discount_percentage) / 100;
	var total_amount = charge_total - discount_amount;
	$("#charge_discount_amount").val(discount_amount);
	$("#charge_total").val(total_amount);
}

function calcEOPDChargeTotal()
{
	var charge_amount = 0;
	var charge_quantity = 0;
	var discount_percentage = 0;
	if($("#e_charge_amount").val() != '')
	{
		charge_amount = parseFloat($("#e_charge_amount").val());
	}
	if($("#e_charge_quantity").val() != '')
	{
		charge_quantity = parseInt($("#e_charge_quantity").val());
	}
	if($("#e_charge_discount_percentage").val() != '')
	{
		discount_percentage = parseFloat($("#e_charge_discount_percentage").val());
	}
	var charge_total = charge_amount * charge_quantity;
	var discount_amount = (charge_total * discount_percentage) / 100;
	var total_amount = charge_total - discount_amount;
	$("#e_charge_discount_amount").val(discount_amount);
	$("#e_charge_total").val(total_amount);
}


function addEOPDChargeDetail()
{
	var chnor = parseInt($("#echnor").val());
	var charge_id = $("#e_ad_charge_id").val();
	var charge_data = $('#e_ad_charge_id').select2('data')
	var charge_name = charge_data[0].text;
	var charge_amount = parseFloat($("#e_charge_amount").val());
	var charge_discount_percentage = $("#e_charge_discount_percentage").val();
	var charge_discount_amount = parseFloat($("#e_charge_discount_amount").val());
	var charge_quantity = $("#e_charge_quantity").val();
	var charge_total = parseFloat($("#e_charge_total").val());
	
	if(charge_id === '' || charge_amount === '' || charge_discount_percentage === '' || charge_quantity === '')
	{
		Swal.fire({
			icon: "error",
			title: "Required!",
			text: "Please fill all required fields",
			showConfirmButton: false,
			timer: 1500
		});
		return false;
	}
	chnor = chnor + 1;
	$("#echnor").val(chnor);
	$("#e_opd_charge_detail_tbl tbody").append('<tr id="chr_'+chnor+'"><td><a href="javascript:void(0)" onclick="removeEOPDChargeDetail('+chnor+')" data-bs-toggle="tooltip" data-bs-placement="top" title="Remove"><i class="fas fa-times"></i></a></td><td>'+charge_name+'<input type="hidden" name="charge_id_value[]" value="'+charge_id+'"></td><td>'+charge_amount+'<input type="hidden" name="amount_value[]" value="'+charge_amount+'"></td><td>'+charge_discount_percentage+'<input type="hidden" name="discount_percentage_value[]" value="'+charge_discount_percentage+'"></td><td>'+charge_discount_amount+'<input type="hidden" name="discount_amount_value[]" value="'+charge_discount_amount+'"></td><td>'+charge_quantity+'<input type="hidden" name="quantity_value[]" value="'+charge_quantity+'"></td><td>'+charge_total+'<input type="hidden" name="total_value[]" value="'+charge_total+'"></td></tr>');
	
	calcEOPDChargeDetail();

	$("#e_charge_amount").val('');
	$("#e_charge_discount_percentage").val('');
	$("#e_charge_discount_amount").val('');
	$("#e_charge_quantity").val('');
	$("#e_charge_total").val('');
	$("#e_ad_charge_id").select2('open');
	$("#e_ad_charge_id").focus();
}

function calcEOPDChargeDetail()
{
	var total_amount = 0;
	var total_discount = 0;
	var grand_total = 0;
	$i = 0;
	$("#e_opd_charge_detail_tbl tbody tr").each(function() { 
		var quantity = $(this).find("input[name='quantity_value[]']").val(); 
		var amount = $(this).find("input[name='amount_value[]']").val(); 
		total_amount = total_amount + (amount * quantity);
		var discount = $(this).find("input[name='discount_amount_value[]']").val(); 
		total_discount = total_discount + (discount * quantity);
		var total = parseFloat($(this).find("input[name='total_value[]']").val()); 
		grand_total = grand_total + total;
		$i = $i + 1;
	});
	$("#e_opd_charges_amount").val(total_amount);
	$("#e_opd_charges_discount").val(total_discount);
	$("#e_opd_charges_total").val(grand_total);
	$("#e_total_amount").html(total_amount);
	$("#e_total_discount").html(total_discount);
	$("#e_grand_total").html(grand_total);
	
	if($i > 0)
	{
	}
	else
	{
	}
	calcEOPDAddonDiscount();
}

function calcEOPDAddonDiscount()
{

	var opd_charges_amount = $("#e_opd_charges_amount").val();
	var opd_charges_discount = $("#e_opd_charges_discount").val();
	var after_charges_discount_amount = opd_charges_amount - opd_charges_discount;
	var addon_discount_mode = $("#e_addon_discount_mode").val();
	var addon_discount_value = $("#e_addon_discount_value").val();
	if(addon_discount_mode == 'Percentage')
	{
		addon_discount_value = (opd_charges_amount * addon_discount_value) / 100;
		$("#e_opd_charges_addon_discount").val(addon_discount_value);
		$("#e_addon_discount").html(addon_discount_value.toFixed(2));
		var grand_total = after_charges_discount_amount - addon_discount_value;
		$("#e_opd_charges_total").val(grand_total);
		$("#e_grand_total").html(grand_total.toFixed(2));
		
	}
	else
	{
		$("#e_opd_charges_addon_discount").val(addon_discount_value);
		$("#e_addon_discount").html(parseFloat(addon_discount_value).toFixed(2));
		var grand_total = after_charges_discount_amount - addon_discount_value;
		$("#e_opd_charges_total").val(grand_total);
		$("#e_grand_total").html(grand_total.toFixed(2));
		
	}
}



function addOPDChargeDetail()
{
	var chnor = parseInt($("#chnor").val());
	var charge_department_id = $("#a_charge_department_id").val();
	var department_data = $('#a_charge_department_id').select2('data');
	var department_name = (department_data && department_data[0]) ? department_data[0].text : '';
	var charge_id = $("#a_ad_charge_id").val();
	var charge_data = $('#a_ad_charge_id').select2('data')
	var charge_name = charge_data[0].text;
	var charge_amount = parseFloat($("#charge_amount").val());
	var charge_discount_percentage = $("#charge_discount_percentage").val();
	var charge_discount_amount = parseFloat($("#charge_discount_amount").val());
	var charge_quantity = $("#charge_quantity").val();
	var charge_total = parseFloat($("#charge_total").val());
	if(!charge_department_id || charge_id === '' || charge_amount === '' || charge_discount_percentage === '' || charge_quantity === '')
	{
		Swal.fire({
			icon: "error",
			title: "Required!",
			text: "Please fill all required fields",
			showConfirmButton: false,
			timer: 1500
		});
		return false;
	}
	chnor = chnor + 1;
	$("#chnor").val(chnor);
	$("#opd_charge_detail_tbl tbody").append('<tr id="chr_'+chnor+'"><td><a href="javascript:void(0)" onclick="removeOPDChargeDetail('+chnor+')" data-bs-toggle="tooltip" data-bs-placement="top" title="Remove"><i class="fas fa-times"></i></a></td><td>'+department_name+'<input type="hidden" name="charge_department_id_value[]" value="'+charge_department_id+'"></td><td>'+charge_name+'<input type="hidden" name="charge_id_value[]" value="'+charge_id+'"></td><td>'+charge_amount+'<input type="hidden" name="amount_value[]" value="'+charge_amount+'"></td><td>'+charge_discount_percentage+'<input type="hidden" name="discount_percentage_value[]" value="'+charge_discount_percentage+'"></td><td>'+charge_discount_amount+'<input type="hidden" name="discount_amount_value[]" value="'+charge_discount_amount+'"></td><td>'+charge_quantity+'<input type="hidden" name="quantity_value[]" value="'+charge_quantity+'"></td><td>'+charge_total+'<input type="hidden" name="total_value[]" value="'+charge_total+'"></td></tr>');
	
	calcOPDChargeDetail();

	$("#charge_amount").val('');
	$("#charge_discount_percentage").val('');
	$("#charge_discount_amount").val('');
	$("#charge_quantity").val('');
	$("#charge_total").val('');
	$("#a_ad_charge_id").select2('open');
	$("#a_ad_charge_id").focus();
}

function removeOPDChargeDetail(id)
{
	$("#chr_"+id).remove();
	calcOPDChargeDetail();
}

function calcOPDChargeDetail()
{
	var total_amount = 0;
	var total_discount = 0;
	var grand_total = 0;
	$i = 0;
	$("#opd_charge_detail_tbl tbody tr").each(function() { 
		var quantity = $(this).find("input[name='quantity_value[]']").val(); 
		var amount = $(this).find("input[name='amount_value[]']").val(); 
		total_amount = total_amount + (amount * quantity);
		var discount = $(this).find("input[name='discount_amount_value[]']").val(); 
		total_discount = total_discount + (discount * quantity);
		var total = parseFloat($(this).find("input[name='total_value[]']").val()); 
		grand_total = grand_total + total;
		$i = $i + 1;
	});
	$("#opd_charges_amount").val(total_amount);
	$("#opd_charges_discount").val(total_discount);
	$("#opd_charges_total").val(grand_total);
	$("#total_amount").html(total_amount);
	$("#total_discount").html(total_discount);
	$("#grand_total").html(grand_total);
	$("#paid_amount").val('0');
	calcOPDAddonDiscount();
}

function calcOPDAddonDiscount()
{

	var opd_charges_amount = $("#opd_charges_amount").val();
	var opd_charges_discount = $("#opd_charges_discount").val();
	var after_charges_discount_amount = opd_charges_amount - opd_charges_discount;
	var addon_discount_mode = $("#addon_discount_mode").val();
	var addon_discount_value = $("#addon_discount_value").val();
	if(addon_discount_mode == 'Percentage')
	{
		addon_discount_value = (opd_charges_amount * addon_discount_value) / 100;
		$("#opd_charges_addon_discount").val(addon_discount_value);
		$("#addon_discount").html(addon_discount_value.toFixed(2));
		var grand_total = after_charges_discount_amount - addon_discount_value;
		$("#opd_charges_total").val(grand_total);
		$("#grand_total").html(grand_total.toFixed(2));
		$("#paid_amount").val('0');
	}
	else
	{
		$("#opd_charges_addon_discount").val(addon_discount_value);
		$("#addon_discount").html(parseFloat(addon_discount_value).toFixed(2));
		var grand_total = after_charges_discount_amount - addon_discount_value;
		$("#opd_charges_total").val(grand_total);
		$("#grand_total").html(grand_total.toFixed(2));
		$("#paid_amount").val('0');
	}
}

function removeEOPDChargeDetail(id)
{
	$("#chr_"+id).remove();
	calcEOPDChargeDetail();
}

/* ============================= */

function showBillChargeAmountDisc(id)
{
	var patient_category_id = $("#patient_category_id").val();
	if(id > 0)
	{
		$.ajax({
		type: 'GET',
		url: window.appRoutes.chargeLookupAmount,
		data: {id: id, patient_category_id: patient_category_id},
		dataType: 'json',
		success: function(data)         
			{
				$("#add_billing_form #charge_amount").val(data.amount);
				$("#add_billing_form #charge_discount_percentage").val(0);
				$("#add_billing_form #charge_quantity").val(1);
				calcBillChargeTotal();
			}
	});
	}
}

function calcBillChargeTotal()
{
	var charge_amount = 0;
	var charge_quantity = 0;
	var discount_percentage = 0;
	if($("#charge_amount").val() != '')
	{
		charge_amount = parseFloat($("#charge_amount").val());
	}
	if($("#charge_quantity").val() != '')
	{
		charge_quantity = parseInt($("#charge_quantity").val());
	}
	if($("#charge_discount_percentage").val() != '')
	{
		discount_percentage = parseFloat($("#charge_discount_percentage").val());
	}
	var charge_total = charge_amount * charge_quantity;
	var discount_amount = (charge_total * discount_percentage) / 100;
	var total_amount = charge_total - discount_amount;
	$("#charge_discount_amount").val(discount_amount);
	$("#charge_total").val(total_amount);
}

function addBillChargeDetail()
{
	var chnor = parseInt($("#chnor").val());
	var charge_id = $("#a_ad_charge_id").val();
	var charge_data = $('#a_ad_charge_id').select2('data')
	var charge_name = charge_data[0].text;
	var charge_amount = parseFloat($("#charge_amount").val());
	var charge_discount_percentage = $("#charge_discount_percentage").val();
	var charge_discount_amount = parseFloat($("#charge_discount_amount").val());
	var charge_quantity = $("#charge_quantity").val();
	var charge_total = parseFloat($("#charge_total").val());
	
	if(charge_id === '' || charge_amount === '' || charge_discount_percentage === '' || charge_quantity === '' || charge_total === 0)
	{
		Swal.fire({
			icon: "error",
			title: "Required!",
			text: "Please fill all required fields",
			showConfirmButton: false,
			timer: 1500
		});
		return false;
	}
	chnor = chnor + 1;
	$("#chnor").val(chnor);
	$("#bill_charge_detail_tbl tbody").append('<tr id="chr_'+chnor+'"><td><a href="javascript:void(0)" onclick="removeBillChargeDetail('+chnor+')" data-bs-toggle="tooltip" data-bs-placement="top" title="Remove"><i class="fas fa-times"></i></a></td><td>'+charge_name+'<input type="hidden" name="charge_id_value[]" value="'+charge_id+'"></td><td>'+charge_amount+'<input type="hidden" name="amount_value[]" value="'+charge_amount+'"></td><td>'+charge_discount_percentage+'<input type="hidden" name="discount_percentage_value[]" value="'+charge_discount_percentage+'"></td><td>'+charge_discount_amount+'<input type="hidden" name="discount_amount_value[]" value="'+charge_discount_amount+'"></td><td>'+charge_quantity+'<input type="hidden" name="quantity_value[]" value="'+charge_quantity+'"></td><td>'+charge_total+'<input type="hidden" name="total_value[]" value="'+charge_total+'"></td></tr>');
	
	calcBillChargeDetail();

	$("#charge_amount").val('');
	$("#charge_discount_percentage").val('');
	$("#charge_discount_amount").val('');
	$("#charge_quantity").val('');
	$("#charge_total").val('');
	$("#a_ad_charge_id").select2('open');
	$("#a_ad_charge_id").focus();
}

function calcBillChargeDetail()
{
	var total_amount = 0;
	var total_discount = 0;
	var grand_total = 0;
	$i = 0;
	$("#bill_charge_detail_tbl tbody tr").each(function() { 
		var quantity = $(this).find("input[name='quantity_value[]']").val(); 
		var amount = $(this).find("input[name='amount_value[]']").val(); 
		total_amount = total_amount + (amount * quantity);
		var discount = $(this).find("input[name='discount_amount_value[]']").val(); 
		total_discount = total_discount + (discount * quantity);
		var total = parseFloat($(this).find("input[name='total_value[]']").val()); 
		grand_total = grand_total + total;
		$i = $i + 1;
	});
	$("#bill_charges_amount").val(total_amount);
	$("#bill_charges_discount").val(total_discount);
	$("#bill_charges_total").val(grand_total);
	$("#total_amount").html(total_amount.toFixed(2));
	$("#total_discount").html(total_discount.toFixed(2));
	$("#grand_total").html(grand_total.toFixed(2));
	// Keep paid_amount at 0 until user adds payments via Add Payment modal
	if($i > 0)
	{
		$("#charge_department_id").prop("disabled", true);
	}
	else
	{
		$("#charge_department_id").prop("disabled", false);
	}
	calcBillAddonDiscount();
}

function calcBillAddonDiscount()
{
	var bill_charges_amount = $("#bill_charges_amount").val();
	var bill_charges_discount = $("#bill_charges_discount").val();
	var after_charges_discount_amount = bill_charges_amount - bill_charges_discount;
	var addon_discount_mode = $("#addon_discount_mode").val();
	var addon_discount_value = $("#addon_discount_value").val();
	if(addon_discount_mode == 'Percentage')
	{
		addon_discount_value = (bill_charges_amount * addon_discount_value) / 100;
		$("#bill_charges_addon_discount").val(addon_discount_value);
		$("#addon_discount").html(addon_discount_value.toFixed(2));
		var grand_total = after_charges_discount_amount - addon_discount_value;
		$("#bill_charges_total").val(grand_total);
		$("#grand_total").html(grand_total.toFixed(2));
		// Keep paid_amount at 0 until user adds payments via Add Payment modal
	}
	else
	{
		$("#bill_charges_addon_discount").val(addon_discount_value);
		$("#addon_discount").html(parseFloat(addon_discount_value).toFixed(2));
		var grand_total = after_charges_discount_amount - addon_discount_value;
		$("#bill_charges_total").val(grand_total);
		$("#grand_total").html(grand_total.toFixed(2));
		// Keep paid_amount at 0 until user adds payments via Add Payment modal
	}
}

function removeBillChargeDetail(id)
{
	$("#chr_"+id).remove();
	calcBillChargeDetail();
}

function showEBillChargeAmountDisc(id)
{
	$.ajax({
		type: 'GET',
		url: window.appRoutes.chargeLookupAmount,
		data: {id: id},
		dataType: 'json',
		success: function(data)         
		{
			$("#edit_billing_form #e_charge_amount").val(data.amount);
			$("#edit_billing_form #e_charge_discount_percentage").val(0);
			$("#edit_billing_form #e_charge_quantity").val(1);
			calcEBillChargeTotal();
		}
	});
}

function calcEBillChargeTotal()
{
	var charge_amount = 0;
	var charge_quantity = 0;
	var discount_percentage = 0;
	if($("#e_charge_amount").val() != '')
	{
		charge_amount = parseFloat($("#e_charge_amount").val());
	}
	if($("#e_charge_quantity").val() != '')
	{
		charge_quantity = parseInt($("#e_charge_quantity").val());
	}
	if($("#e_charge_discount_percentage").val() != '')
	{
		discount_percentage = parseFloat($("#e_charge_discount_percentage").val());
	}
	var charge_total = charge_amount * charge_quantity;
	var discount_amount = (charge_total * discount_percentage) / 100;
	var total_amount = charge_total - discount_amount;
	$("#e_charge_discount_amount").val(discount_amount);
	$("#e_charge_total").val(total_amount);
}


function addEBillChargeDetail()
{
	var chnor = parseInt($("#echnor").val());
	var charge_id = $("#e_ad_charge_id").val();
	var charge_data = $('#e_ad_charge_id').select2('data')
	var charge_name = charge_data[0].text;
	var charge_amount = parseFloat($("#e_charge_amount").val());
	var charge_discount_percentage = $("#e_charge_discount_percentage").val();
	var charge_discount_amount = parseFloat($("#e_charge_discount_amount").val());
	var charge_quantity = $("#e_charge_quantity").val();
	var charge_total = parseFloat($("#e_charge_total").val());
	if(charge_id === '' || charge_amount === '' || charge_discount_percentage === '' || charge_quantity === '' || charge_total === 0)
	{
		Swal.fire({
			icon: "error",
			title: "Required!",
			text: "Please fill all required fields",
			showConfirmButton: false,
			timer: 1500
		});
		return false;
	}
	chnor = chnor + 1;
	$("#echnor").val(chnor);
	$("#e_bill_charge_detail_tbl tbody").append('<tr id="chr_'+chnor+'"><td><a href="javascript:void(0)" onclick="removeEBillChargeDetail('+chnor+')" data-bs-toggle="tooltip" data-bs-placement="top" title="Remove"><i class="fas fa-times"></i></a></td><td>'+charge_name+'<input type="hidden" name="charge_id_value[]" value="'+charge_id+'"></td><td>'+charge_amount+'<input type="hidden" name="amount_value[]" value="'+charge_amount+'"></td><td>'+charge_discount_percentage+'<input type="hidden" name="discount_percentage_value[]" value="'+charge_discount_percentage+'"></td><td>'+charge_discount_amount+'<input type="hidden" name="discount_amount_value[]" value="'+charge_discount_amount+'"></td><td>'+charge_quantity+'<input type="hidden" name="quantity_value[]" value="'+charge_quantity+'"></td><td>'+charge_total+'<input type="hidden" name="total_value[]" value="'+charge_total+'"></td></tr>');
	
	calcEBillChargeDetail();

	$("#e_charge_amount").val('');
	$("#e_charge_discount_percentage").val('');
	$("#e_charge_discount_amount").val('');
	$("#e_charge_quantity").val('');
	$("#e_charge_total").val('');
	$("#e_ad_charge_id").select2('open');
	$("#e_ad_charge_id").focus();
}


function calcEBillChargeDetail()
{
	var total_amount = 0;
	var total_discount = 0;
	var grand_total = 0;
	$i = 0;
	$("#e_bill_charge_detail_tbl tbody tr").each(function() { 
		var quantity = $(this).find("input[name='quantity_value[]']").val(); 
		var amount = $(this).find("input[name='amount_value[]']").val(); 
		total_amount = total_amount + (amount * quantity);
		var discount = $(this).find("input[name='discount_amount_value[]']").val(); 
		total_discount = total_discount + (discount * quantity);
		var total = parseFloat($(this).find("input[name='total_value[]']").val()); 
		grand_total = grand_total + total;
		$i = $i + 1;
	});
	$("#e_bill_charges_amount").val(total_amount);
	$("#e_bill_charges_discount").val(total_discount);
	$("#e_bill_charges_total").val(grand_total);
	$("#e_total_amount").html(total_amount.toFixed(2));
	$("#e_total_discount").html(total_discount.toFixed(2));
	$("#e_grand_total").html(grand_total.toFixed(2));
	
	if($i > 0)
	{
	}
	else
	{
	}
	calcEBillAddonDiscount();
}


function calcEBillAddonDiscount()
{

	var bill_charges_amount = $("#e_bill_charges_amount").val();
	var bill_charges_discount = $("#e_bill_charges_discount").val();
	var after_charges_discount_amount = bill_charges_amount - bill_charges_discount;
	var addon_discount_mode = $("#e_addon_discount_mode").val();
	var addon_discount_value = $("#e_addon_discount_value").val();
	if(addon_discount_mode == 'Percentage')
	{
		addon_discount_value = (bill_charges_amount * addon_discount_value) / 100;
		$("#e_bill_charges_addon_discount").val(addon_discount_value);
		$("#e_addon_discount").html(addon_discount_value.toFixed(2));
		var grand_total = after_charges_discount_amount - addon_discount_value;
		$("#e_bill_charges_total").val(grand_total);
		$("#e_grand_total").html(grand_total.toFixed(2));
		
	}
	else
	{
		$("#e_bill_charges_addon_discount").val(addon_discount_value);
		$("#e_addon_discount").html(parseFloat(addon_discount_value).toFixed(2));
		var grand_total = after_charges_discount_amount - addon_discount_value;
		$("#e_bill_charges_total").val(grand_total);
		$("#e_grand_total").html(grand_total.toFixed(2));
		
	}
}

function removeEBillChargeDetail(id)
{
	$("#chr_"+id).remove();
	calcEBillChargeDetail();
}

function editBillModal(id)
{
	$.ajax({
		type: 'GET',
		url: appRoute('billingShow', id),
		dataType: 'json',
		success: function(data)         
		{
			$("#editBillModal").modal('show');
			$("#edit_billing_form #billing_id").val(id);
			$("#edit_billing_form #echnor").val(data.nor);
			$("#edit_billing_form #billing_date_time").val(data.row.charge_date_time);
			$("#edit_billing_form #bill_title").val(data.row.charge_title);
			$("#edit_billing_form #patient_name").val(data.row.module);
			$("#edit_billing_form #echargedepartmentid").val(data.row.charge_department_id);
			$("#edit_billing_form #e_charge_department_id").html('<option value="'+data.row.charge_department_id+'" selected>'+data.row.department_name+'</option>');
			$("#edit_billing_form #e_paid_amount").val(data.row.paid_amount);
			$("#edit_billing_form #e_payment_mode").val(data.row.payment_mode);
			$("#edit_billing_form #e_addon_discount_mode").val(data.row.addon_discount_mode);
			$("#edit_billing_form #e_addon_discount_value").val(data.row.addon_discount_value);
			$("#e_bill_charge_detail_tbl tbody").html(data.table_html);
			calcEBillChargeDetail();
			$.ajax({
		type: 'GET',
		url: appRoute('billingSplitPayments', id),
		dataType: 'json',
		success: function(data)         
				{
					$("#e_split_payments_tbl tbody").html(data.html);
					$("#e_spnor").val(data.nor);
					$("#e_split_payment_total_amount").val(data.total);
					$("#e_split_payment_total").html(data.total);
				}
	});
		}
	});	
}

/* ============================= */

function showDCDChargeAmount(id, patient_category_id)
{
	if(id > 0)
	{
		$.ajax({
		type: 'GET',
		url: window.appRoutes.chargeLookupAmount,
		data: {id: id, patient_category_id: patient_category_id},
		dataType: 'json',
		success: function(data)         
			{
				$("#add_dcd_charges_form #charge_amount").val(data.amount);
				$("#add_dcd_charges_form #charge_discount_percentage").val(0);
				$("#add_dcd_charges_form #charge_quantity").val(1);
				calcDCDChargeTotal();
			}
	});
	}
}

function calcDCDChargeTotal()
{
	var charge_amount = 0;
	var charge_quantity = 0;
	var discount_percentage = 0;
	if($("#charge_amount").val() != '')
	{
		charge_amount = parseFloat($("#charge_amount").val());
	}
	if($("#charge_quantity").val() != '')
	{
		charge_quantity = parseInt($("#charge_quantity").val());
	}
	if($("#charge_discount_percentage").val() != '')
	{
		discount_percentage = parseFloat($("#charge_discount_percentage").val());
	}
	var charge_total = charge_amount * charge_quantity;
	var discount_amount = (charge_total * discount_percentage) / 100;
	var total_amount = charge_total - discount_amount;
	$("#charge_discount_amount").val(discount_amount);
	$("#charge_total").val(total_amount);
}

function addDCDChargeDetail()
{
	var chnor = parseInt($("#chnor").val());
	var charge_department_id = $("#a_charge_department_id").val();
	var department_data = $('#a_charge_department_id').select2('data');
	var department_name = (department_data && department_data[0]) ? department_data[0].text : '';
	var charge_id = $("#a_ad_charge_id").val();
	var charge_data = $('#a_ad_charge_id').select2('data')
	var charge_name = charge_data[0].text;
	var charge_amount = parseFloat($("#charge_amount").val());
	var charge_discount_percentage = $("#charge_discount_percentage").val();
	var charge_discount_amount = parseFloat($("#charge_discount_amount").val());
	var charge_quantity = $("#charge_quantity").val();
	var charge_total = parseFloat($("#charge_total").val());
	
	if(!charge_department_id || charge_id === '' || charge_amount === '' || charge_discount_percentage === '' || charge_quantity === '')
	{
		Swal.fire({
			icon: "error",
			title: "Required!",
			text: "Please fill all required fields",
			showConfirmButton: false,
			timer: 1500
		});
		return false;
	}
	chnor = chnor + 1;
	$("#chnor").val(chnor);
	$("#dcd_charge_detail_tbl tbody").append('<tr id="chr_'+chnor+'"><td><a href="javascript:void(0)" onclick="removeDCDChargeDetail('+chnor+')" data-bs-toggle="tooltip" data-bs-placement="top" title="Remove"><i class="fas fa-times"></i></a></td><td>'+department_name+'<input type="hidden" name="charge_department_id_value[]" value="'+charge_department_id+'"></td><td>'+charge_name+'<input type="hidden" name="charge_id_value[]" value="'+charge_id+'"></td><td>'+charge_amount+'<input type="hidden" name="amount_value[]" value="'+charge_amount+'"></td><td>'+charge_discount_percentage+'<input type="hidden" name="discount_percentage_value[]" value="'+charge_discount_percentage+'"></td><td>'+charge_discount_amount+'<input type="hidden" name="discount_amount_value[]" value="'+charge_discount_amount+'"></td><td>'+charge_quantity+'<input type="hidden" name="quantity_value[]" value="'+charge_quantity+'"></td><td>'+charge_total+'<input type="hidden" name="total_value[]" value="'+charge_total+'"></td></tr>');
	
	calcDCDChargeDetail();
	showPrescriptionTD(); // the item is in billing now: drop it from the prescription list

	$("#charge_amount").val('');
	$("#charge_discount_percentage").val('');
	$("#charge_discount_amount").val('');
	$("#charge_quantity").val('');
	$("#charge_total").val('');
	$("#a_ad_charge_id").select2('open');
	$("#a_ad_charge_id").focus();
}

function calcDCDChargeDetail()
{
	var total_amount = 0;
	var total_discount = 0;
	var grand_total = 0;
	$i = 0;
	$("#dcd_charge_detail_tbl tbody tr").each(function() { 
		var quantity = $(this).find("input[name='quantity_value[]']").val(); 
		var amount = $(this).find("input[name='amount_value[]']").val(); 
		total_amount = total_amount + (amount * quantity);
		var discount = $(this).find("input[name='discount_amount_value[]']").val(); 
		total_discount = total_discount + (discount * quantity);
		var total = parseFloat($(this).find("input[name='total_value[]']").val()); 
		grand_total = grand_total + total;
		$i = $i + 1;
	});
	$("#dcd_charges_amount").val(total_amount);
	$("#dcd_charges_discount").val(total_discount);
	$("#dcd_charges_total").val(grand_total);
	$("#total_amount").html(total_amount.toFixed(2));
	$("#total_discount").html(total_discount.toFixed(2));
	$("#grand_total").html(grand_total.toFixed(2));
	$("#paid_amount").val('0');
	calcDCDAddonDiscount();
}

function calcDCDAddonDiscount()
{
	var dcd_charges_amount = $("#dcd_charges_amount").val();
	var dcd_charges_discount = $("#dcd_charges_discount").val();
	var after_charges_discount_amount = dcd_charges_amount - dcd_charges_discount;
	var addon_discount_mode = $("#addon_discount_mode").val();
	var addon_discount_value = $("#addon_discount_value").val();
	if(addon_discount_mode == 'Percentage')
	{
		addon_discount_value = (dcd_charges_amount * addon_discount_value) / 100;
		$("#dcd_charges_addon_discount").val(addon_discount_value);
		$("#addon_discount").html(addon_discount_value.toFixed(2));
		var grand_total = after_charges_discount_amount - addon_discount_value;
		$("#dcd_charges_total").val(grand_total);
		$("#grand_total").html(grand_total.toFixed(2));
		$("#paid_amount").val('0');
	}
	else
	{
		$("#dcd_charges_addon_discount").val(addon_discount_value);
		$("#addon_discount").html(parseFloat(addon_discount_value).toFixed(2));
		var grand_total = after_charges_discount_amount - addon_discount_value;
		$("#dcd_charges_total").val(grand_total);
		$("#grand_total").html(grand_total.toFixed(2));
		$("#paid_amount").val('0');
	}
}

function removeDCDChargeDetail(id)
{
	$("#chr_"+id).remove();
	calcDCDChargeDetail();
	showPrescriptionTD(); // removed from billing: offer the item again
}

function showEDCDChargeAmount(id, patient_category_id)
{
	$.ajax({
		type: 'GET',
		url: window.appRoutes.chargeLookupAmount,
		data: {id: id, patient_category_id: patient_category_id},
		dataType: 'json',
		success: function(data)         
		{
			$("#edit_dcd_charges_form #e_charge_amount").val(data.amount);
			$("#edit_dcd_charges_form #e_charge_discount_percentage").val(0);
			$("#edit_dcd_charges_form #e_charge_quantity").val(1);
			calcEDCDChargeTotal();
		}
	});
}

function calcEDCDChargeTotal()
{
	var charge_amount = 0;
	var charge_quantity = 0;
	var discount_percentage = 0;
	if($("#e_charge_amount").val() != '')
	{
		charge_amount = parseFloat($("#e_charge_amount").val());
	}
	if($("#e_charge_quantity").val() != '')
	{
		charge_quantity = parseInt($("#e_charge_quantity").val());
	}
	if($("#e_charge_discount_percentage").val() != '')
	{
		discount_percentage = parseFloat($("#e_charge_discount_percentage").val());
	}
	var charge_total = charge_amount * charge_quantity;
	var discount_amount = (charge_total * discount_percentage) / 100;
	var total_amount = charge_total - discount_amount;
	$("#e_charge_discount_amount").val(discount_amount);
	$("#e_charge_total").val(total_amount);
}


function addEDCDChargeDetail()
{
	var chnor = parseInt($("#echnor").val());
	var charge_id = $("#e_ad_charge_id").val();
	var charge_data = $('#e_ad_charge_id').select2('data')
	var charge_name = charge_data[0].text;
	var charge_amount = parseFloat($("#e_charge_amount").val());
	var charge_discount_percentage = $("#e_charge_discount_percentage").val();
	var charge_discount_amount = parseFloat($("#e_charge_discount_amount").val());
	var charge_quantity = $("#e_charge_quantity").val();
	var charge_total = parseFloat($("#e_charge_total").val());
	if(charge_id === '' || charge_amount === '' || charge_discount_percentage === '' || charge_quantity === '')
	{
		Swal.fire({
			icon: "error",
			title: "Required!",
			text: "Please fill all required fields",
			showConfirmButton: false,
			timer: 1500
		});
		return false;
	}
	chnor = chnor + 1;
	$("#echnor").val(chnor);
	$("#e_dcd_charge_detail_tbl tbody").append('<tr id="chr_'+chnor+'"><td><a href="javascript:void(0)" onclick="removeEDCDChargeDetail('+chnor+')" data-bs-toggle="tooltip" data-bs-placement="top" title="Remove"><i class="fas fa-times"></i></a></td><td>'+charge_name+'<input type="hidden" name="charge_id_value[]" value="'+charge_id+'"></td><td>'+charge_amount+'<input type="hidden" name="amount_value[]" value="'+charge_amount+'"></td><td>'+charge_discount_percentage+'<input type="hidden" name="discount_percentage_value[]" value="'+charge_discount_percentage+'"></td><td>'+charge_discount_amount+'<input type="hidden" name="discount_amount_value[]" value="'+charge_discount_amount+'"></td><td>'+charge_quantity+'<input type="hidden" name="quantity_value[]" value="'+charge_quantity+'"></td><td>'+charge_total+'<input type="hidden" name="total_value[]" value="'+charge_total+'"></td></tr>');
	
	calcEDCDChargeDetail();

	$("#e_charge_amount").val('');
	$("#e_charge_discount_percentage").val('');
	$("#e_charge_discount_amount").val('');
	$("#e_charge_quantity").val('');
	$("#e_charge_total").val('');
	$("#e_ad_charge_id").select2('open');
	$("#e_ad_charge_id").focus();
}


function calcEDCDChargeDetail()
{
	var total_amount = 0;
	var total_discount = 0;
	var grand_total = 0;
	$i = 0;
	$("#e_dcd_charge_detail_tbl tbody tr").each(function() { 
		var quantity = $(this).find("input[name='quantity_value[]']").val(); 
		var amount = $(this).find("input[name='amount_value[]']").val(); 
		total_amount = total_amount + (amount * quantity);
		var discount = $(this).find("input[name='discount_amount_value[]']").val(); 
		total_discount = total_discount + (discount * quantity);
		var total = parseFloat($(this).find("input[name='total_value[]']").val()); 
		grand_total = grand_total + total;
		$i = $i + 1;
	});
	$("#e_dcd_charges_amount").val(total_amount);
	$("#e_dcd_charges_discount").val(total_discount);
	$("#e_dcd_charges_total").val(grand_total);
	$("#e_total_amount").html(total_amount.toFixed(2));
	$("#e_total_discount").html(total_discount.toFixed(2));
	$("#e_grand_total").html(grand_total.toFixed(2));
	
	if($i > 0)
	{
	}
	else
	{
	}
	calcEDCDAddonDiscount();
}


function calcEDCDAddonDiscount()
{

	var dcd_charges_amount = $("#e_dcd_charges_amount").val();
	var dcd_charges_discount = $("#e_dcd_charges_discount").val();
	var after_charges_discount_amount = dcd_charges_amount - dcd_charges_discount;
	var addon_discount_mode = $("#e_addon_discount_mode").val();
	var addon_discount_value = $("#e_addon_discount_value").val();
	if(addon_discount_mode == 'Percentage')
	{
		addon_discount_value = (dcd_charges_amount * addon_discount_value) / 100;
		$("#e_dcd_charges_addon_discount").val(addon_discount_value);
		$("#e_addon_discount").html(addon_discount_value.toFixed(2));
		var grand_total = after_charges_discount_amount - addon_discount_value;
		$("#e_dcd_charges_total").val(grand_total);
		$("#e_grand_total").html(grand_total.toFixed(2));
		
	}
	else
	{
		$("#e_dcd_charges_addon_discount").val(addon_discount_value);
		$("#e_addon_discount").html(parseFloat(addon_discount_value).toFixed(2));
		var grand_total = after_charges_discount_amount - addon_discount_value;
		$("#e_dcd_charges_total").val(grand_total);
		$("#e_grand_total").html(grand_total.toFixed(2));
		
	}
}

function removeEDCDChargeDetail(id)
{
	$("#chr_"+id).remove();
	calcEDCDChargeDetail();
}

function editDCDChargeModal(id)
{
	$.ajax({
		type: 'GET',
		url: appRoute('dcdChargeGet', id),
		dataType: 'json',
		success: function(data)         
		{
			$("#editDCDChargeModal").modal('show');
			$("#edit_dcd_charges_form #dcd_charge_id").val(id);
			$("#edit_dcd_charges_form #echnor").val(data.nor);
			$("#edit_dcd_charges_form #charge_date_time").val(data.row.charge_date_time);
			$("#edit_dcd_charges_form #charge_title").val(data.row.charge_title);
			$("#edit_dcd_charges_form #echargedepartmentid").val(data.row.charge_department_id);
			$("#edit_dcd_charges_form #e_charge_department_id").html('<option value="'+data.row.charge_department_id+'" selected>'+data.row.department_name+'</option>');
			
			$("#edit_dcd_charges_form #e_addon_discount_mode").val(data.row.addon_discount_mode);
			$("#edit_dcd_charges_form #e_addon_discount_value").val(data.row.addon_discount_value);
			$("#edit_dcd_charges_form #e_paid_amount").val(data.row.paid_amount);
			
			$("#e_dcd_charge_detail_tbl tbody").html(data.table_html);
			calcEDCDChargeDetail();
			$.ajax({
		type: 'GET',
		url: appRoute('dcdChargeSplitPayments', id),
		dataType: 'json',
		success: function(data)         
				{
					$("#e_split_payments_tbl tbody").html(data.html);
					$("#e_spnor").val(data.nor);
					$("#e_split_payment_total_amount").val(data.total);
					$("#e_split_payment_total").html(data.total);
				}
	});
		}
	});	
}

/* ============================= */

function calcIPDChargeTotal()
{
	var charge_amount = 0;
	var charge_quantity = 0;
	var discount_percentage = 0;
	if($("#charge_amount").val() != '')
	{
		charge_amount = parseFloat($("#charge_amount").val());
	}
	if($("#charge_quantity").val() != '')
	{
		charge_quantity = parseInt($("#charge_quantity").val());
	}
	if($("#charge_discount_percentage").val() != '')
	{
		discount_percentage = parseFloat($("#charge_discount_percentage").val());
	}
	var charge_total = charge_amount * charge_quantity;
	var discount_amount = (charge_total * discount_percentage) / 100;
	var total_amount = charge_total - discount_amount;
	$("#charge_discount_amount").val(discount_amount);
	$("#charge_total").val(total_amount);
}

function calcEIPDChargeTotal()
{
	var charge_amount = 0;
	var charge_quantity = 0;
	var discount_percentage = 0;
	if($("#e_charge_amount").val() != '')
	{
		charge_amount = parseFloat($("#e_charge_amount").val());
	}
	if($("#e_charge_quantity").val() != '')
	{
		charge_quantity = parseInt($("#e_charge_quantity").val());
	}
	if($("#e_charge_discount_percentage").val() != '')
	{
		discount_percentage = parseFloat($("#e_charge_discount_percentage").val());
	}
	var charge_total = charge_amount * charge_quantity;
	var discount_amount = (charge_total * discount_percentage) / 100;
	var total_amount = charge_total - discount_amount;
	$("#e_charge_discount_amount").val(discount_amount);
	$("#e_charge_total").val(total_amount);
}






function updateCDIField(v, field_id)
{
	$("#"+field_id).val(v);
}

function addIPDChargeDetail()
{
	var chnor = parseInt($("#chnor").val());
	var charge_department_id = $("#a_charge_department_id").val();
	var department_data = $('#a_charge_department_id').select2('data');
	var department_name = (department_data && department_data[0]) ? department_data[0].text : '';
	var charge_id = $("#a_ad_charge_id").val();
	var charge_data = $('#a_ad_charge_id').select2('data')
	var charge_name = charge_data[0].text;
	var charge_amount = parseFloat($("#charge_amount").val());
	var charge_discount_percentage = $("#charge_discount_percentage").val();
	var charge_discount_amount = parseFloat($("#charge_discount_amount").val());
	var charge_quantity = $("#charge_quantity").val();
	var charge_total = parseFloat($("#charge_total").val());
	
	if(!charge_department_id || charge_id === '' || charge_amount === '' || charge_discount_percentage === '' || charge_discount_amount === '' || charge_quantity === '')
	{
		Swal.fire({
			icon: "error",
			title: "Required!",
			text: "Please fill all required fields",
			showConfirmButton: false,
			timer: 1500
		});
		return false;
	}
	chnor = chnor + 1;
	$("#chnor").val(chnor);
	$("#ipd_charge_detail_tbl tbody").append('<tr id="chr_'+chnor+'"><td><a href="javascript:void(0)" onclick="removeIPDChargeDetail('+chnor+')" data-bs-toggle="tooltip" data-bs-placement="top" title="Remove"><i class="fas fa-times"></i></a></td><td>'+department_name+'<input type="hidden" name="charge_department_id_value[]" value="'+charge_department_id+'"></td><td>'+charge_name+'<input type="hidden" name="charge_id_value[]" value="'+charge_id+'"></td><td>'+charge_amount+'<input type="hidden" name="amount_value[]" value="'+charge_amount+'"></td><td>'+charge_discount_percentage+'<input type="hidden" name="discount_percentage_value[]" value="'+charge_discount_percentage+'"></td><td>'+charge_discount_amount+'<input type="hidden" name="discount_amount_value[]" value="'+charge_discount_amount+'"></td><td>'+charge_quantity+'<input type="hidden" name="quantity_value[]" value="'+charge_quantity+'"></td><td>'+charge_total+'<input type="hidden" name="total_value[]" value="'+charge_total+'"></td></tr>');
	
	calcIPDChargeDetail();

	$("#charge_amount").val('');
	$("#charge_discount_percentage").val('');
	$("#charge_discount_amount").val('');
	$("#charge_quantity").val('');
	$("#charge_total").val('');
	$("#a_ad_charge_id").select2('open');
	$("#a_ad_charge_id").focus();
}

function removeIPDChargeDetail(id)
{
	$("#chr_"+id).remove();
	calcIPDChargeDetail();
}

function calcIPDChargeDetail()
{
	var total_amount = 0;
	var total_discount = 0;
	var grand_total = 0;
	$i = 0;
	$("#ipd_charge_detail_tbl tbody tr").each(function() { 
		var quantity = $(this).find("input[name='quantity_value[]']").val(); 
		var amount = $(this).find("input[name='amount_value[]']").val(); 
		total_amount = total_amount + (amount * quantity);
		var discount = $(this).find("input[name='discount_amount_value[]']").val(); 
		total_discount = total_discount + (discount * quantity);
		var total = parseFloat($(this).find("input[name='total_value[]']").val()); 
		grand_total = grand_total + total;
		$i = $i + 1;
	});
	$("#ipd_charges_amount").val(total_amount);
	$("#ipd_charges_discount").val(total_discount);
	$("#ipd_charges_total").val(grand_total);
	$("#total_amount").html(total_amount);
	$("#total_discount").html(total_discount);
	$("#grand_total").html(grand_total);
	$("#paid_amount").val('0');
	calcIPDAddonDiscount();
}

function calcIPDAddonDiscount()
{

	var ipd_charges_amount = $("#ipd_charges_amount").val();
	var ipd_charges_discount = $("#ipd_charges_discount").val();
	var after_charges_discount_amount = ipd_charges_amount - ipd_charges_discount;
	var addon_discount_mode = $("#addon_discount_mode").val();
	var addon_discount_value = $("#addon_discount_value").val();
	if(addon_discount_mode == 'Percentage')
	{
		addon_discount_value = (ipd_charges_amount * addon_discount_value) / 100;
		$("#ipd_charges_addon_discount").val(addon_discount_value);
		$("#addon_discount").html(addon_discount_value.toFixed(2));
		var grand_total = after_charges_discount_amount - addon_discount_value;
		$("#ipd_charges_total").val(grand_total);
		$("#grand_total").html(grand_total.toFixed(2));
		$("#paid_amount").val('0');
	}
	else
	{
		$("#ipd_charges_addon_discount").val(addon_discount_value);
		$("#addon_discount").html(parseFloat(addon_discount_value).toFixed(2));
		var grand_total = after_charges_discount_amount - addon_discount_value;
		$("#ipd_charges_total").val(grand_total);
		$("#grand_total").html(grand_total.toFixed(2));
		$("#paid_amount").val('0');
	}
}


function addEIPDChargeDetail()
{
	var chnor = parseInt($("#echnor").val());
	var charge_id = $("#e_ad_charge_id").val();
	var charge_data = $('#e_ad_charge_id').select2('data')
	var charge_name = charge_data[0].text;
	var charge_amount = parseFloat($("#e_charge_amount").val());
	var charge_discount_percentage = $("#e_charge_discount_percentage").val();
	var charge_discount_amount = parseFloat($("#e_charge_discount_amount").val());
	var charge_quantity = $("#e_charge_quantity").val();
	var charge_total = parseFloat($("#e_charge_total").val());
	
	if(charge_id === '' || charge_amount === '' || charge_discount_percentage === '' || charge_quantity === '')
	{
		Swal.fire({
			icon: "error",
			title: "Required!",
			text: "Please fill all required fields",
			showConfirmButton: false,
			timer: 1500
		});
		return false;
	}
	chnor = chnor + 1;
	$("#echnor").val(chnor);
	$("#e_ipd_charge_detail_tbl tbody").append('<tr id="chr_'+chnor+'"><td><a href="javascript:void(0)" onclick="removeEIPDChargeDetail('+chnor+')" data-bs-toggle="tooltip" data-bs-placement="top" title="Remove"><i class="fas fa-times"></i></a></td><td>'+charge_name+'<input type="hidden" name="charge_id_value[]" value="'+charge_id+'"></td><td>'+charge_amount+'<input type="hidden" name="amount_value[]" value="'+charge_amount+'"></td><td>'+charge_discount_percentage+'<input type="hidden" name="discount_percentage_value[]" value="'+charge_discount_percentage+'"></td><td>'+charge_discount_amount+'<input type="hidden" name="discount_amount_value[]" value="'+charge_discount_amount+'"></td><td>'+charge_quantity+'<input type="hidden" name="quantity_value[]" value="'+charge_quantity+'"></td><td>'+charge_total+'<input type="hidden" name="total_value[]" value="'+charge_total+'"></td></tr>');
	
	calcEIPDChargeDetail();

	$("#e_charge_amount").val('');
	$("#e_charge_discount_percentage").val('');
	$("#e_charge_discount_amount").val('');
	$("#e_charge_quantity").val('');
	$("#e_charge_total").val('');
	$("#e_ad_charge_id").select2('open');
	$("#e_ad_charge_id").focus();
}

function removeEIPDChargeDetail(id)
{
	$("#chr_"+id).remove();
	calcEIPDChargeDetail();
}

function calcEIPDChargeDetail()
{
	var total_amount = 0;
	var total_discount = 0;
	var grand_total = 0;
	$i = 0;
	$("#e_ipd_charge_detail_tbl tbody tr").each(function() { 
		var quantity = $(this).find("input[name='quantity_value[]']").val(); 
		var amount = $(this).find("input[name='amount_value[]']").val(); 
		total_amount = total_amount + (amount * quantity);
		var discount = $(this).find("input[name='discount_amount_value[]']").val(); 
		total_discount = total_discount + (discount * quantity);
		var total = parseFloat($(this).find("input[name='total_value[]']").val()); 
		grand_total = grand_total + total;
		$i = $i + 1;
	});
	$("#e_ipd_charges_amount").val(total_amount);
	$("#e_ipd_charges_discount").val(total_discount);
	$("#e_ipd_charges_total").val(grand_total);
	$("#e_total_amount").html(total_amount);
	$("#e_total_discount").html(total_discount);
	$("#e_grand_total").html(grand_total);
	
	if($i > 0)
	{
	}
	else
	{
	}
	calcEIPDAddonDiscount();
}

function calcEIPDAddonDiscount()
{

	var ipd_charges_amount = $("#e_ipd_charges_amount").val();
	var ipd_charges_discount = $("#e_ipd_charges_discount").val();
	var after_charges_discount_amount = ipd_charges_amount - ipd_charges_discount;
	var addon_discount_mode = $("#e_addon_discount_mode").val();
	var addon_discount_value = $("#e_addon_discount_value").val();
	if(addon_discount_mode == 'Percentage')
	{
		addon_discount_value = (ipd_charges_amount * addon_discount_value) / 100;
		$("#e_ipd_charges_addon_discount").val(addon_discount_value);
		$("#e_addon_discount").html(addon_discount_value.toFixed(2));
		var grand_total = after_charges_discount_amount - addon_discount_value;
		$("#e_ipd_charges_total").val(grand_total);
		$("#e_grand_total").html(grand_total.toFixed(2));
		
	}
	else
	{
		$("#e_ipd_charges_addon_discount").val(addon_discount_value);
		$("#e_addon_discount").html(parseFloat(addon_discount_value).toFixed(2));
		var grand_total = after_charges_discount_amount - addon_discount_value;
		$("#e_ipd_charges_total").val(grand_total);
		$("#e_grand_total").html(grand_total.toFixed(2));
		
	}
}

function addIPDConsultationModal()
{
	$("#addIPDConsultationModal").modal('show');
}




function addDischargeInvestigationBox()
{
	let rand = parseInt(Math.floor((Math.random() * 9999) + 1000));
	var box = '<div id="ibox_'+rand+'" class="col-sm-12 ibox">';
	box = box +'<a class="rdi-tiny-btn" href="javascript:void(0)" onclick="removeIBox('+rand+');"><i class="fas fa-times-circle"></i></a>';
	box = box +'<input type="text" name="ipd_discharge_investigation_name[]" class="form-control fminwidth" placeholder="Investigation" required>';
	box = box +'<input type="text" name="ipd_discharge_investigation_value[]" class="form-control fmaxwidth" placeholder="Result" required>';
	box = box +'</div>';
	$("#ipd_discharge_investigations").append(box);
}

function addDischargeProcedureBox()
{
	let rand = parseInt(Math.floor((Math.random() * 9999) + 1000));
	var box = '<div id="pcbox_'+rand+'" class="col-sm-6 pcbox">';
	box = box +'<a class="rdi-tiny-btn" href="javascript:void(0)" onclick="removePCBox('+rand+');"><i class="fas fa-times-circle"></i></a>';
	box = box +'<input type="text" name="ipd_discharge_procedure[]" class="form-control" placeholder="Procedure" required>';
	box = box +'</div>';
	$("#ipd_discharge_procedures").append(box);
}

function addDDischargeInvestigationBox()
{
	let rand = parseInt(Math.floor((Math.random() * 9999) + 1000));
	var box = '<div id="ibox_'+rand+'" class="col-sm-12 ibox">';
	box = box +'<a class="rdi-tiny-btn" href="javascript:void(0)" onclick="removeIBox('+rand+');"><i class="fas fa-times-circle"></i></a>';
	box = box +'<input type="text" name="dcd_discharge_investigation_name[]" class="form-control fminwidth" placeholder="Investigation" required>';
	box = box +'<input type="text" name="dcd_discharge_investigation_value[]" class="form-control fmaxwidth" placeholder="Result" required>';
	box = box +'</div>';
	$("#dcd_discharge_investigations").append(box);
}

function addDDischargeProcedureBox()
{
	let rand = parseInt(Math.floor((Math.random() * 9999) + 1000));
	var box = '<div id="pcbox_'+rand+'" class="col-sm-6 pcbox">';
	box = box +'<a class="rdi-tiny-btn" href="javascript:void(0)" onclick="removePCBox('+rand+');"><i class="fas fa-times-circle"></i></a>';
	box = box +'<input type="text" name="dcd_discharge_procedure[]" class="form-control" placeholder="Procedure" required>';
	box = box +'</div>';
	$("#dcd_discharge_procedures").append(box);
}

function removeIBox(id)
{
	$("#ibox_"+id).remove();
}

function removePCBox(id)
{
	$("#pcbox_"+id).remove();
}

/*function addDischargeTABox()
{
	let rand = parseInt(Math.floor((Math.random() * 9999) + 1000));
	var box = '<div id="tabox_'+rand+'" class="col-sm-6 tabox">';
	box = box +'<a class="rdi-tiny-btn" href="javascript:void(0)" onclick="removeTABox('+rand+');"><i class="fas fa-times-circle"></i></a>';
	box = box +'<input type="text" name="ipd_discharge_treatment_advised[]" class="form-control" placeholder="Write here" required>';
	box = box +'</div>';
	$("#ipd_discharge_treatment_advised").append(box);
}

function removeTABox(id)
{
	$("#tabox_"+id).remove();
}*/

function removePDMedicine(id)
{
	$("#pdmedicine_"+id).remove();
}





function getDCDPatientFile(dcd_id)
{
	
	$.ajax({
		type: 'GET',
		url: appRoute('dcdPatientFileShow', dcd_id),
		dataType: 'json',
		success: function(data)         
		{
			
			if(data != null)
			{
				
				$("#chief_complaints").summernote('code', decode(data.chief_complaints));
				$("#past_medical_history").summernote('code', decode(data.past_medical_history));
				$('#mlc').val(data.mlc);
				$('#mlc').trigger('change');
				$("#mlc_number").val(data.mlc_number);	
				$("#referred_from_other_hospital").val(data.referred_from_other_hospital);	
				$('#referred_from_other_hospital').trigger('change');
				$("#referred_from_hospital_name").val(data.referred_from_hospital_name);	
				$("#mode").val(data.mode);	
				$('#mode').trigger('change');
				$("#history_given_by").val(data.history_given_by);	
				$("#identification_marks").val(data.identification_marks);	
				$("#emergency_arrival_time").val(data.emergency_arrival_time);	
				$("#allergies").val(data.allergies);	
				$("#gcs").val(data.gcs);	
				$("#pallor").val(data.pallor);	
				$("#pain_score").val(data.pain_score);	
				$("#pain_score").trigger('change');	
				$("#cns").val(data.cns);	
				$("#rs").val(data.rs);	
				$("#cvs").val(data.cvs);	
				$("#per_abdomen").val(data.per_abdomen);	
				$("#female_menstural_history").val(data.female_menstural_history);	
				$("#provisional_diagnosis").val(data.provisional_diagnosis);	
				
				
			}
			
		}
	});
}





function deleteDCDCharge(id)
{
	Swal.fire({
		title: "Delete?",
		text: "Are you sure you want to delete!",
		icon: "warning",
		showCancelButton: true,
		confirmButtonColor: "#3085d6",
		cancelButtonColor: "#d33",
		confirmButtonText: "Yes, delete it!"
	}).then((result) => {
		if (result.isConfirmed) {
			$.ajax({
		type: 'DELETE',
		url: appRoute('dcdChargeDestroy', id),
		dataType: 'json',
		success: function(data)         
				{
					$("#row_"+id).remove();
					showPrescriptionTD(); // a deleted bill puts its items back on the prescription list
					Swal.fire({
						icon: "success",
						title: "Deleted!",
						text: "Deleted Successfully.",
						showConfirmButton: false,
						timer: 1500
					});
				}
	});	
		}
	});
}


function deleteOPDPrescription(id)
{
	Swal.fire({
		title: "Delete?",
		text: "Are you sure you want to delete!",
		icon: "warning",
		showCancelButton: true,
		confirmButtonColor: "#3085d6",
		cancelButtonColor: "#d33",
		confirmButtonText: "Yes, delete it!"
	}).then((result) => {
		if (result.isConfirmed) {
			$.ajax({
		type: 'DELETE',
		url: appRoute('opdPrescriptionDestroy', id),
		dataType: 'json',
		success: function(data)         
				{
					$("#row_"+id).remove();
				}
	});	
		}
	});
}

function deleteDCDPrescription(id)
{
	Swal.fire({
		title: "Delete?",
		text: "Are you sure you want to delete!",
		icon: "warning",
		showCancelButton: true,
		confirmButtonColor: "#3085d6",
		cancelButtonColor: "#d33",
		confirmButtonText: "Yes, delete it!"
	}).then((result) => {
		if (result.isConfirmed) {
			$.ajax({
		type: 'DELETE',
		url: appRoute('dcdPrescriptionDestroy', id),
		dataType: 'json',
		success: function(data)         
				{
					$("#row_"+id).remove();
				}
	});	
		}
	});
}



function deleteStaffMember(id)
{
	Swal.fire({
		title: "Delete?",
		text: "Are you sure you want to delete!",
		icon: "warning",
		showCancelButton: true,
		confirmButtonColor: "#3085d6",
		cancelButtonColor: "#d33",
		confirmButtonText: "Yes, delete it!"
	}).then((result) => {
		if (result.isConfirmed) {
			$.ajax({
		type: 'DELETE',
		url: appRoute('staffDestroy', id),
		dataType: 'json',
		success: function(data)         
				{
					$("#row_"+id).remove();
				}
	});	
		}
	});
}





function deleteCharge(id)
{
	Swal.fire({
		title: "Delete?",
		text: "Are you sure you want to delete!",
		icon: "warning",
		showCancelButton: true,
		confirmButtonColor: "#3085d6",
		cancelButtonColor: "#d33",
		confirmButtonText: "Yes, delete it!"
	}).then((result) => {
		if (result.isConfirmed) {
			$.ajax({
		type: 'DELETE',
		url: appRoute('masterChargeDestroy', id),
		dataType: 'json',
		success: function(data)         
				{
					var r = data.result.trim();
					if(r == 'OK')
					{
						$("#row_"+id).remove();
						Swal.fire({
							icon: "success",
							title: "Success!",
							text: "Deleted Successfully",
							showConfirmButton: false,
							timer: 1500
						});
					}
					else
					{
						Swal.fire({
							icon: "error",
							title: "Error!",
							text: "In use unable to delete",
							showConfirmButton: false,
							timer: 1500
						});
					}
				}
	});	
		}
	});
}

function deleteBill(id)
{
	Swal.fire({
		title: "Delete?",
		text: "Are you sure you want to delete!",
		icon: "warning",
		showCancelButton: true,
		confirmButtonColor: "#3085d6",
		cancelButtonColor: "#d33",
		confirmButtonText: "Yes, delete it!"
	}).then((result) => {
		if (result.isConfirmed) {
			$.ajax({
		type: 'DELETE',
		url: appRoute('billingDestroy', id),
		dataType: 'json',
		success: function(data)         
				{
					location.reload();
				}
	});	
		}
	});
}




// OPD equivalents of saveTestDetailedResults()/viewTestDetail() above - same
// per-cell save loop and modal pattern, just posting to the OPD-specific
// ajax.php actions (savetestdetailedresultsopd/viewtestdetailopd) backed by
// opd_lab_test_results instead of biochemistry_tests_result (IPD-only table).










// Procedure Notes ("Detail" button on opd-procedures.php / ipd-procedures.php)
// - loads any existing note (Summernote HTML) for this procedure service row
// into the modal's editor before showing it, so re-opening the same row's
// note shows what was saved last time instead of a blank editor.






function viewSaleBills()
{
	var from_date = $("#s_from_date").val();
	var to_date = $("#s_to_date").val();
	window.open('rpt-sale-bills.php?from_date='+from_date+'&to_date='+to_date, '_blank');
}

function viewSaleSearch()
{
	var from_date = $("#ss_from_date").val();
	var to_date = $("#ss_to_date").val();
	var module_filter = $("#ss_module_filter").val();
	var payment_status_filter = $("#ss_payment_status_filter").val() || '';
	
	// Validate that both dates are in the same month and year
	if(from_date && to_date) {
		// Parse dates (format: dd-mm-yyyy)
		var from_parts = from_date.split('-');
		var to_parts = to_date.split('-');
		
		var from_month = from_parts[1];
		var from_year = from_parts[2];
		var to_month = to_parts[1];
		var to_year = to_parts[2];
		
		// Check if month and year are the same
		if(from_month !== to_month || from_year !== to_year) {
			Swal.fire({
				icon: 'warning',
				title: 'Invalid Date Range',
				text: 'You can print 1 month report only. Please select dates within the same month.',
				confirmButtonColor: '#3085d6'
			});
			return;
		}
	}
	
	window.open('rpt-sale-search.php?from_date='+from_date+'&to_date='+to_date+'&module_filter='+module_filter+'&payment_status_filter='+payment_status_filter, '_blank');
}

function showHideCollectionCB()
{
	var v = parseInt($("#show_hide_collection_cb").val());
	if(v == 0)
	{
		$(".inline-checkbox").css('display', 'inline-block');
		$("#action-td").removeClass('tr3btns');
		$("#action-td").addClass('tr4btns');
		$("#show_hide_collection_cb").val(1);
		$("#collect_ipd_payments_btn").show();
	}
	else
	{
		$(".inline-checkbox").css('display', 'none');
		$("#action-td").addClass('tr3btns');
		$("#action-td").removeClass('tr4btns');
		$("#show_hide_collection_cb").val(0);
		$("#collect_ipd_payments_btn").hide();
	}
}

function addToCollection(id)
{
	var total = parseFloat($("#collection_amount").val());
	var amount = parseFloat($("#balance_amount_"+id).val());
	if ($('#cb_'+id).is(":checked"))
	{
		total = total + amount;
		$("#collection_amount").val(total);
		$("#collection_amount_text").html(total);
	}
	else
	{
		total = total - amount;
		$("#collection_amount").val(total);
		$("#collection_amount_text").html(total);
	}

}

function collectIPDPaymentsModal()
{
	var amount = parseFloat($("#collection_amount").val());
	if(amount > 0)
	{
		// Update modal remaining display
		$("#split_collection_remaining").html(amount.toFixed(2));
		
		$("#collectPaymentsModal").modal('show');	
	}
	else
	{
		Swal.fire({
			icon: "error",
			title: "Required!",
			text: "Please select invoices before collection",
			showConfirmButton: false,
			timer: 1500
		});
	}
	
}


// OPD

function showHideOPDCollectionCB()
{
	var v = parseInt($("#show_hide_collection_cb").val());
	if(v == 0)
	{
		$(".inline-checkbox").css('display', 'inline-block');
		$("#action-td").removeClass('tr3btns');
		$("#action-td").addClass('tr4btns');
		$("#show_hide_collection_cb").val(1);
		$("#collect_opd_payments_btn").show();
	}
	else
	{
		$(".inline-checkbox").css('display', 'none');
		$("#action-td").addClass('tr3btns');
		$("#action-td").removeClass('tr4btns');
		$("#show_hide_collection_cb").val(0);
		$("#collect_opd_payments_btn").hide();
	}
}

function addToOPDCollection(id)
{
	var total = parseFloat($("#collection_amount").val());
	var amount = parseFloat($("#balance_amount_"+id).val());
	if ($('#cb_'+id).is(":checked"))
	{
		total = total + amount;
		$("#collection_amount").val(total);
		$("#collection_amount_text").html(total);
	}
	else
	{
		total = total - amount;
		$("#collection_amount").val(total);
		$("#collection_amount_text").html(total);
	}

}

function collectOPDPaymentsModal()
{
	var amount = parseFloat($("#collection_amount").val());
	if(amount > 0)
	{
		// Update modal remaining display
		$("#split_collection_remaining").html(amount.toFixed(2));
		
		$("#collectPaymentsModal").modal('show');	
	}
	else
	{
		Swal.fire({
			icon: "error",
			title: "Required!",
			text: "Please select invoices before collection",
			showConfirmButton: false,
			timer: 1500
		});
	}
	
}


// DCD

function showHideDCDCollectionCB()
{
	var v = parseInt($("#show_hide_collection_cb").val());
	if(v == 0)
	{
		$(".inline-checkbox").css('display', 'inline-block');
		$("#action-td").removeClass('tr3btns');
		$("#action-td").addClass('tr4btns');
		$("#show_hide_collection_cb").val(1);
		$("#collect_dcd_payments_btn").show();
	}
	else
	{
		$(".inline-checkbox").css('display', 'none');
		$("#action-td").addClass('tr3btns');
		$("#action-td").removeClass('tr4btns');
		$("#show_hide_collection_cb").val(0);
		$("#collect_dcd_payments_btn").hide();
	}
}

function addToDCDCollection(id)
{
	var total = parseFloat($("#collection_amount").val());
	var amount = parseFloat($("#balance_amount_"+id).val());
	if ($('#cb_'+id).is(":checked"))
	{
		total = total + amount;
		$("#collection_amount").val(total);
		$("#collection_amount_text").html(total);
	}
	else
	{
		total = total - amount;
		$("#collection_amount").val(total);
		$("#collection_amount_text").html(total);
	}

}

function collectDCDPaymentsModal()
{
	var amount = parseFloat($("#collection_amount").val());
	if(amount > 0)
	{
		// Update modal remaining display
		$("#split_collection_remaining").html(amount.toFixed(2));
		
		$("#collectPaymentsModal").modal('show');	
	}
	else
	{
		Swal.fire({
			icon: "error",
			title: "Required!",
			text: "Please select invoices before collection",
			showConfirmButton: false,
			timer: 1500
		});
	}
	
}

function collectDCDSelectedAmount()
{
	var collection_amount =$("#collection_amount").val();
	var collecting_amount = $("#split_collection_total_amount").val();
	if(collection_amount != collecting_amount)
	{
		Swal.fire({
			icon: "error",
			title: "Amount Mismatch!",
			text: "You are collecting "+collecting_amount+". Amount should be "+collection_amount,
			showConfirmButton: false,
			timer: 3000
		});
		return false;
	}

	let payment_mode = $('input[name="sc_mode[]"]').map(function() {
		return $(this).val();
	}).get().join(',');

	var sp_mode = [];
	$('input[name="sc_mode[]"]').each(function() {
		sp_mode.push($(this).val());
	});

	var sp_amount = [];
	$('input[name="sc_amount[]"]').each(function() {
		sp_amount.push($(this).val());
	});

	var cb_values = [];
	$("input[name='cb']:checked").each(function(){
		cb_values.push(this.value);
	});

	var dcd_id = $("#dcd_id").val();
	var payment_date_time = $("#payment_date_time").val();
	
	if(payment_mode == '')
	{
		Swal.fire({
			icon: "error",
			title: "Required!",
			text: "Please select payment mode",
			showConfirmButton: false,
			timer: 1500
		});
		return;
	}
	
	$.ajax({
		type: 'POST',
		url: appRoute('dcdCollectPayments', dcd_id),
		data: {payment_date_time: payment_date_time, payment_mode: payment_mode, cb: cb_values, sp_mode: sp_mode, sp_amount: sp_amount},
		dataType: 'json',
		success: function(data)         
		{
			$("#collectDCDPaymentsModal").modal('hide');
			Swal.fire({
				icon: "success",
				title: "Success!",
				text: "Saved Successfully",
				showConfirmButton: false,
				timer: 1500
			});
			location.reload();
		}
	});

}

//===


function addBUnitModal()
{
	$("#addBUnitModal").modal('show');
}






function addSplitPayment()
{
	var nor = $("#spnor").val();
	var total_amount = $("#split_payment_total_amount").val();
	var payment_mode = $("#split_payment_mode").val();
	if(payment_mode == '')
	{
		Swal.fire({
			icon: "error",
			title: "Required",
			text: "Please select payment mode",
			showConfirmButton: false,
			timer: 1500
		});
		$("#split_payment_mode").focus();
		return false;

	}
	var amount = $("#split_payment_amount").val();
	if(amount == '')
	{
		Swal.fire({
			icon: "error",
			title: "Required",
			text: "Please enter amount",
			showConfirmButton: false,
			timer: 1500
		});
		$("#split_payment_amount").focus();
		return false;
	}
	var spid = parseInt(nor) + 1;
	var total = parseFloat(total_amount) + parseFloat(amount);
	$("#split_payments_tbl tbody").append("<tr id='sp_"+spid+"'><td><a href='javascript:void(0)' onclick='delSplitPayment("+spid+");'>x</a></td><td>"+payment_mode+"<input name='sp_mode[]' type='hidden' value='"+payment_mode+"'></td><td>"+amount+"<input id='sp_amount_"+spid+"' name='sp_amount[]' type='hidden' value='"+amount+"'></td></tr>");
	$("#split_payment_total_amount").val(total);
	$("#split_payment_total").html(total);
	$("#spnor").val(spid);
	$("#split_payment_mode").val('');
	$("#split_payment_amount").val('');
	
	// Update remaining balance display
	updateChargePaymentRemaining();
}

function delSplitPayment(id)
{
	var nor = $("#spnor").val();
	var new_nor = parseInt(nor - 1);
	$("#spnor").val(new_nor);
	var total_amount = $("#split_payment_total_amount").val();
	var amount = $("#sp_amount_"+id).val();
	var total = parseFloat(total_amount) - parseFloat(amount);
	$("#split_payment_total_amount").val(total);
	$("#split_payment_total").html(total);
	$("#sp_"+id).remove();
	
	// Update remaining balance display
	updateChargePaymentRemaining();
}

function applySplitPayments()
{
	var nor = $("#spnor").val();
	var paid_amount = $("#split_payment_total_amount").val();
	
	// Allow closing modal even if no payments added (for cash-only payments)
	if(parseFloat(paid_amount) == 0) {
		// If no split payments, set payment mode to empty and paid amount to charge total
		var charge_total = parseFloat($("#opd_charges_total").val()) || parseFloat($("#ipd_charges_total").val()) || parseFloat($("#dcd_charges_total").val()) || 0;
		$("#payment_mode").val('');
		$("#paid_amount").val(charge_total);
	} else {
		let payment_modes = $('input[name="sp_mode[]"]').map(function() {
			return $(this).val();
		}).get().join(',');
		$("#payment_mode").val(payment_modes);
		$("#paid_amount").val(paid_amount);
	}
	
	$("#addSplitPaymentModal").modal('hide');
}

function addESplitPayment()
{
	var nor = $("#e_spnor").val();
	var total_amount = $("#e_split_payment_total_amount").val();
	var payment_mode = $("#e_split_payment_mode").val();
	if(payment_mode == '')
	{
		Swal.fire({
			icon: "error",
			title: "Required",
			text: "Please select payment mode",
			showConfirmButton: false,
			timer: 1500
		});
		$("#e_split_payment_mode").focus();
		return false;

	}
	var amount = $("#e_split_payment_amount").val();
	if(amount == '')
	{
		Swal.fire({
			icon: "error",
			title: "Required",
			text: "Please enter amount",
			showConfirmButton: false,
			timer: 1500
		});
		$("#e_split_payment_amount").focus();
		return false;
	}
	var spid = parseInt(nor) + 1;
	var total = parseFloat(total_amount) + parseFloat(amount);
	$("#e_split_payments_tbl tbody").append("<tr id='e_sp_"+spid+"'><td><a href='javascript:void(0)' onclick='delESplitPayment("+spid+");'>x</a></td><td>"+payment_mode+"<input name='sp_mode[]' type='hidden' value='"+payment_mode+"'></td><td>"+amount+"<input id='e_sp_amount_"+spid+"' name='sp_amount[]' type='hidden' value='"+amount+"'></td></tr>");
	$("#e_split_payment_total_amount").val(total);
	$("#e_split_payment_total").html(total);
	$("#e_spnor").val(spid);
	$("#e_split_payment_mode").val('');
	$("#e_split_payment_amount").val('');
	
	// Update remaining balance display
	updateEditChargePaymentRemaining();
}

function delESplitPayment(id)
{
	var nor = $("#e_spnor").val();
	var new_nor = parseInt(nor - 1);
	$("#e_spnor").val(new_nor);
	var total_amount = $("#e_split_payment_total_amount").val();
	var amount = $("#e_sp_amount_"+id).val();
	var total = parseFloat(total_amount) - parseFloat(amount);
	$("#e_split_payment_total_amount").val(total);
	$("#e_split_payment_total").html(total.toFixed(2));
	$("#e_sp_"+id).remove();
	
	// Update the paid amount input field
	$("#e_paid_amount").val(total.toFixed(2));
	
	// Update remaining balance display
	if (typeof updateEditChargePaymentRemaining === 'function') {
		updateEditChargePaymentRemaining();
	} else {
		console.error('updateEditChargePaymentRemaining function not found');
	}
}

function applyESplitPayments()
{
	var nor = $("#e_spnor").val();
	var paid_amount = $("#e_split_payment_total_amount").val();
	
	console.log('applyESplitPayments called:', {
		nor: nor,
		paid_amount: paid_amount
	});
	
	// If no split payments exist (all removed), set paid_amount to 0
	if(parseFloat(paid_amount) == 0 || paid_amount == '' || paid_amount == null) {
		$("#e_payment_mode").val('');
		$("#e_paid_amount").val('0.00');
		console.log('No payments - setting paid_amount to 0');
	} else {
		// Get all payment modes from remaining split payments
		let payment_modes = $('#e_split_payments_tbl tbody input[name="sp_mode[]"]').map(function() {
			return $(this).val();
		}).get().join(',');
		
		$("#e_payment_mode").val(payment_modes);
		// Set paid_amount to the total of split payments
		$("#e_paid_amount").val(parseFloat(paid_amount).toFixed(2));
		
		console.log('Payments exist - setting paid_amount to split payment total:', paid_amount, 'payment_modes:', payment_modes);
	}
	
	$("#editSplitPaymentModal").modal('hide');
}


function addSplitCollection()
{
	var nor = $("#scnor").val();
	var total_amount = $("#split_collection_total_amount").val();
	var payment_mode = $("#split_collection_mode").val();
	if(payment_mode == '')
	{
		Swal.fire({
			icon: "error",
			title: "Required",
			text: "Please select payment mode",
			showConfirmButton: false,
			timer: 1500
		});
		$("#split_collection_mode").focus();
		return false;

	}
	var amount = $("#split_collection_amount").val();
	if(amount == '')
	{
		Swal.fire({
			icon: "error",
			title: "Required",
			text: "Please enter amount",
			showConfirmButton: false,
			timer: 1500
		});
		$("#split_collection_amount").focus();
		return false;
	}
	var spid = parseInt(nor) + 1;
	var total = parseFloat(total_amount) + parseFloat(amount);
	$("#split_collection_tbl tbody").append("<tr id='sc_"+spid+"'><td><a href='javascript:void(0)' onclick='delSplitCollection("+spid+");'>x</a></td><td>"+payment_mode+"<input name='sc_mode[]' type='hidden' value='"+payment_mode+"'></td><td>"+amount+"<input id='sc_amount_"+spid+"' name='sc_amount[]' type='hidden' value='"+amount+"'></td></tr>");
	$("#split_collection_total_amount").val(total);
	$("#split_collection_total").html(total);
	$("#scnor").val(spid);
	$("#split_collection_mode").val('');
	$("#split_collection_amount").val('');
	
	// Update remaining balance display
	updatePaymentRemainingBalance();
}

function delSplitCollection(id)
{
	var nor = $("#scnor").val();
	var new_nor = parseInt(nor - 1);
	$("#scnor").val(new_nor);
	var total_amount = $("#split_collection_total_amount").val();
	var amount = $("#sc_amount_"+id).val();
	var total = parseFloat(total_amount) - parseFloat(amount);
	$("#split_collection_total_amount").val(total);
	$("#split_collection_total").html(total);
	$("#sc_"+id).remove();
	
	// Update remaining balance display
	updatePaymentRemainingBalance();
}

function applySplitCollection()
{
	var nor = $("#scnor").val();
	var paid_amount = $("#split_collection_total_amount").val();
	let payment_modes = $('input[name="sc_mode[]"]').map(function() {
		return $(this).val();
	}).get().join(',');
	$("#payment_mode").val(payment_modes);
	$("#paid_amount").val(paid_amount);
	$("#collectPaymentsModal").modal('hide');
}

// Update remaining balance display in payment modal
function updatePaymentRemainingBalance()
{
	var total_amount = parseFloat($("#collection_amount").val()) || 0;
	var collecting_amount = parseFloat($("#split_collection_total_amount").val()) || 0;
	var remaining = total_amount - collecting_amount;
	
	$("#split_collection_remaining").html(remaining.toFixed(2));
	
	// Change color based on remaining balance
	if(remaining < 0) {
		$("#split_collection_remaining").css('color', '#dc3545'); // Red for overpayment
	} else if(remaining === 0) {
		$("#split_collection_remaining").css('color', '#28a745'); // Green for exact match
	} else {
		$("#split_collection_remaining").css('color', '#ffc107'); // Yellow for underpayment
	}
}

// Update remaining balance display in charge payment modal (Add Charges form)
function updateChargePaymentRemaining()
{
	var charge_total = parseFloat($("#opd_charges_total").val()) || parseFloat($("#ipd_charges_total").val()) || parseFloat($("#dcd_charges_total").val()) || parseFloat($("#bill_charges_total").val()) || 0;
	var payment_total = parseFloat($("#split_payment_total_amount").val()) || 0;
	var remaining = charge_total - payment_total;
	
	$("#split_payment_remaining").html(remaining.toFixed(2));
	
	// Change color based on remaining balance
	if(remaining < 0) {
		$("#split_payment_remaining").css('color', '#dc3545'); // Red for overpayment
	} else if(remaining === 0) {
		$("#split_payment_remaining").css('color', '#28a745'); // Green for exact match
	} else {
		$("#split_payment_remaining").css('color', '#ffc107'); // Yellow for underpayment
	}
}

// Update remaining balance display in edit charge payment modal
function updateEditChargePaymentRemaining()
{
	var charge_total = parseFloat($("#e_opd_charges_total").val()) || parseFloat($("#e_ipd_charges_total").val()) || parseFloat($("#e_dcd_charges_total").val()) || parseFloat($("#bill_charges_total").val()) || 0;
	var payment_total = parseFloat($("#e_split_payment_total_amount").val()) || 0;
	var remaining = charge_total - payment_total;
	
	console.log('Edit Payment Remaining Update:', {
		charge_total: charge_total,
		payment_total: payment_total,
		remaining: remaining
	});
	
	$("#e_split_payment_remaining").html(remaining.toFixed(2));
	
	// Change color based on remaining balance
	if(remaining < 0) {
		$("#e_split_payment_remaining").css('color', '#dc3545'); // Red for overpayment
	} else if(remaining === 0) {
		$("#e_split_payment_remaining").css('color', '#28a745'); // Green for exact match
	} else {
		$("#e_split_payment_remaining").css('color', '#ffc107'); // Yellow for underpayment
	}
}

// Bootstrap modal event - initialize displays when modal opens
$(document).ready(function() {
	// When addSplitPaymentModal opens, initialize the balance display
	$('#addSplitPaymentModal').on('shown.bs.modal', function () {
		updateChargePaymentRemaining();
	});
	
	// When editSplitPaymentModal opens, initialize the balance display
	$('#editSplitPaymentModal').on('shown.bs.modal', function () {
		updateEditChargePaymentRemaining();
	});
	
	// When collectPaymentsModal opens, initialize the remaining display
	$('#collectPaymentsModal').on('shown.bs.modal', function () {
		updatePaymentRemainingBalance();
	});
});

function addPMIPatientModal()
{
	$("#addPMIPatientModal").modal('show');
}

function addDiseaseModal()
{
	$("#addDiseaseModal").modal('show');
}


function addPatientMedicineModal()
{
	$("#addPatientMedicineModal").modal('show');
}

function editPatientMedicineModal(id)
{
	$("#editPatientMedicineModal").modal('show');
	$.ajax({ 
		type: 'POST',
		url: window.location.origin + '/periodic-medicine/issue-list/issue-medicines',                  
		data: {id: id},                       
		dataType: 'json',                   
		success: function(data)         
		{
			if(data.previous_date != "" && data.next_date != "") {
				$("#e_previous_medicine_dates").html("Previous Date: "+data.previous_date+" Next Date: "+data.next_date);
			}
			$("#edit_patient_medicine_form #patient_periodic_medicine_id").val(data.row.id);
			$("#edit_patient_medicine_form #e_patient_id").html('<option value="'+data.row.patient_id+'" selected>'+data.row.patient_name+'</option>');
			$("#edit_patient_medicine_form #medicine_date_time").val(data.row.medicine_date_time);
			$("#edit_patient_medicine_form #days").val(data.row.days);
			$("#edit_patient_medicine_form #delivery").val(data.row.delivery);
			$("#edit_patient_medicine_form #remarks").val(data.row.remarks);
			var disease_ids = data.row.disease_ids;
			if(disease_ids != '')
			{
				var disease_ids_array = disease_ids.split(',');
				$("#edit_patient_medicine_form #disease_ids").val(disease_ids_array);
				$("#edit_patient_medicine_form #disease_ids").trigger('change');
			}
			$("#e_patient_previous_medicines_list").html(data.previous_medicine_list);
			$("#e_patient_medicine_tbl tbody").html(data.table_html);
		} 
	});	
}

function addPrevMedicine(id) {
	var pmnor = parseInt($("#pmnor").val());

    // Get the <tr> by ID
	var row = $("#patient_previous_medicines_list tr#" + id);

    // Get values from the respective <td>s
	var medicine_name = row.find("td:eq(1)").text().trim();
	var dose = row.find("td:eq(2)").text().trim();
	var dose_interval = row.find("td:eq(3)").text().trim();
    var remarks = ''; // You can modify this to include any remarks if needed

    if (medicine_name === '' || dose === '' || dose_interval === '') {
    	Swal.fire({
    		icon: "error",
    		title: "Required!",
    		text: "Invalid medicine entry",
    		showConfirmButton: false,
    		timer: 1500
    	});
    	return false;
    }

    pmnor = pmnor + 1;
    $("#pmnor").val(pmnor);

    $("#patient_medicine_tbl tbody").append(
    	'<tr id="o_' + pmnor + '">' +
    	'<td><a href="javascript:void(0)" onclick="removePatientMedicine(\'o_' + pmnor + '\')" data-bs-toggle="tooltip" title="Remove">' +
    	'<i class="fas fa-times"></i></a></td>' +
    	'<td>' + medicine_name + '<input type="hidden" name="medicine_name_value[]" value="' + medicine_name + '"></td>' +
    	'<td>' + dose + '<input type="hidden" name="dose_value[]" value="' + dose + '"></td>' +
    	'<td>' + dose_interval + '<input type="hidden" name="dose_interval_value[]" value="' + dose_interval + '"></td>' +
    	'<td>' + remarks + '<input type="hidden" name="remarks_value[]" value="' + remarks + '"></td>' +
    	'</tr>'
    	);

    row.remove();
}


function addPatientMedicine()
{
	var pmnor = parseInt($("#pmnor").val());
	var medicine_name = $("#a_medicine_name").val();
	var dose = $("#a_dose").val();
	var dose_interval = $("#a_dose_interval").val();
	var remarks = $("#a_remarks").val();
	if(medicine_name == '' || dose == '' || dose_interval == '')
	{
		Swal.fire({
			icon: "error",
			title: "Required!",
			text: "Please fill all required fields",
			showConfirmButton: false,
			timer: 1500
		});
		return false;
	}
	pmnor = pmnor + 1;
	$("#pmnor").val(pmnor);
	$("#patient_medicine_tbl tbody").append('<tr id="n_'+pmnor+'"><td><a href="javascript:void(0)" onclick="removePatientMedicine(\'n_' + pmnor + '\')" data-bs-toggle="tooltip" data-bs-placement="top" title="Remove"><i class="fas fa-times"></i></a></td><td>'+medicine_name+'<input type="hidden" name="medicine_name_value[]" value="'+medicine_name+'"></td><td>'+dose+'<input type="hidden" name="dose_value[]" value="'+dose+'"></td><td>'+dose_interval+'<input type="hidden" name="dose_interval_value[]" value="'+dose_interval+'"></td><td>'+remarks+'<input type="hidden" name="remarks_value[]" value="'+remarks+'"></td></tr>');
	$("#a_dose").val('');
	$("#a_remarks").val('');
	$("#a_medicine_name").focus();
}




function removePatientMedicine(id)
{
	if (id.charAt(0) === 'o') {
		var oid = id.replace("o_", "");
		var row = $("#patient_medicine_tbl tr#" + id);
		var medicine_name = row.find("td:eq(1)").text().trim();
		var dose = row.find("td:eq(2)").text().trim();
		var dose_interval = row.find("td:eq(3)").text().trim();

		$("#patient_previous_medicines_tbl tbody").append(
			'<tr id="' + oid + '">' +
			'<td class="tr-sm-action-buttons"><a href="javascript:void(0)" onclick="addPrevMedicine(\'' + oid + '\')" data-bs-toggle="tooltip" title="Add">' +
			'+</a></td>' +
			'<td>' + medicine_name + '</td>' +
			'<td>' + dose + '</td>' +
			'<td>' + dose_interval + '</td>' +
			'</tr>'
			);
		row.remove();

	} else {
		$("#"+id).remove();
	}
	
}


function addEPrevMedicine(id) {
	var pmnor = parseInt($("#epmnor").val());

    // Get the <tr> by ID
	var row = $("#e_patient_previous_medicines_list tr#" + id);

    // Get values from the respective <td>s
	var medicine_name = row.find("td:eq(1)").text().trim();
	var dose = row.find("td:eq(2)").text().trim();
	var dose_interval = row.find("td:eq(3)").text().trim();
    var remarks = ''; // You can modify this to include any remarks if needed

    if (medicine_name === '' || dose === '' || dose_interval === '') {
    	Swal.fire({
    		icon: "error",
    		title: "Required!",
    		text: "Invalid medicine entry",
    		showConfirmButton: false,
    		timer: 1500
    	});
    	return false;
    }

    pmnor = pmnor + 1;
    $("#epmnor").val(pmnor);

    $("#e_patient_medicine_tbl tbody").append(
    	'<tr id="o_' + pmnor + '">' +
    	'<td><a href="javascript:void(0)" onclick="removeEPatientMedicine(\'o_' + pmnor + '\')" data-bs-toggle="tooltip" title="Remove">' +
    	'<i class="fas fa-times"></i></a></td>' +
    	'<td>' + medicine_name + '<input type="hidden" name="medicine_name_value[]" value="' + medicine_name + '"></td>' +
    	'<td>' + dose + '<input type="hidden" name="dose_value[]" value="' + dose + '"></td>' +
    	'<td>' + dose_interval + '<input type="hidden" name="dose_interval_value[]" value="' + dose_interval + '"></td>' +
    	'<td>' + remarks + '<input type="hidden" name="remarks_value[]" value="' + remarks + '"></td>' +
    	'</tr>'
    	);

    row.remove();
}


function addEPatientMedicine()
{
	var pmnor = parseInt($("#epmnor").val());
	var medicine_name = $("#e_medicine_name").val();
	var dose = $("#e_dose").val();
	var dose_interval = $("#e_dose_interval").val();
	var remarks = $("#e_remarks").val();
	if(medicine_name == '' || dose == '' || dose_interval == '')
	{
		Swal.fire({
			icon: "error",
			title: "Required!",
			text: "Please fill all required fields",
			showConfirmButton: false,
			timer: 1500
		});
		return false;
	}
	pmnor = pmnor + 1;
	$("#epmnor").val(pmnor);
	$("#e_patient_medicine_tbl tbody").append('<tr id="n_'+pmnor+'"><td><a href="javascript:void(0)" onclick="removeEPatientMedicine(\'n_'+pmnor+'\')" data-bs-toggle="tooltip" data-bs-placement="top" title="Remove"><i class="fas fa-times"></i></a></td><td>'+medicine_name+'<input type="hidden" name="medicine_name_value[]" value="'+medicine_name+'"></td><td>'+dose+'<input type="hidden" name="dose_value[]" value="'+dose+'"></td><td>'+dose_interval+'<input type="hidden" name="dose_interval_value[]" value="'+dose_interval+'"></td><td>'+remarks+'<input type="hidden" name="remarks_value[]" value="'+remarks+'"></td></tr>');
	$("#e_dose").val('');
	$("#e_remarks").val('');
	$("#e_medicine_name").focus();
}


function removeEPatientMedicine(id)
{
	if (id.charAt(0) === 'o') {
		var oid = id.replace("o_", "");
		var row = $("#e_patient_medicine_tbl tr#" + id);
		var medicine_name = row.find("td:eq(1)").text().trim();
		var dose = row.find("td:eq(2)").text().trim();
		var dose_interval = row.find("td:eq(3)").text().trim();

		$("#e_patient_previous_medicines_tbl tbody").append(
			'<tr id="' + oid + '">' +
			'<td class="tr-sm-action-buttons"><a href="javascript:void(0)" onclick="addEPrevMedicine(\'' + oid + '\')" data-bs-toggle="tooltip" title="Add">' +
			'+</a></td>' +
			'<td>' + medicine_name + '</td>' +
			'<td>' + dose + '</td>' +
			'<td>' + dose_interval + '</td>' +
			'</tr>'
			);
		row.remove();

	} else {
		$("#"+id).remove();
	}
}

function issueMedicineDetailModal(id)
{
	$("#issueMedicineDetailModal").modal('show');
	$.ajax({ 
		type: 'POST',
		url: window.location.origin + '/periodic-medicine/issue-detail',                  
		data: {id: id},                       
		dataType: 'json',                   
		success: function(data)         
		{
			$("#issue-medicine-detail-tbl tbody").html(data.medicine_detail);
		} 
	});
}

function showPatientMedicineRecord(patient_id)
{
	$.ajax({ 
		type: 'POST',
		url: window.location.origin + '/periodic-medicine/issue-list/patient-record',                  
		data: {patient_id: patient_id},                       
		dataType: 'json',                   
		success: function(data)         
		{
			if(data.previous_date != "" && data.next_date != "")
			{
				$("#previous_medicine_dates").html("Previous Date: "+data.previous_date+" Next Date: "+data.next_date);
			}
			else
			{
				$("#previous_medicine_dates").html("Previous medicine record not found");
			}

			$("#patient_previous_medicines_list").html(data.previous_medicine_list);

		} 
	});
}

function deletePatientIssuedMedicine(id)
{
	Swal.fire({
		title: "Delete?",
		text: "Are you sure you want to delete!",
		icon: "warning",
		showCancelButton: true,
		confirmButtonColor: "#3085d6",
		cancelButtonColor: "#d33",
		confirmButtonText: "Yes, delete it!"
	}).then((result) => {
		if (result.isConfirmed) {
			$.ajax({ 
				type: 'POST',
				url: window.location.origin + '/periodic-medicine/issue-list/delete',                  
				data: {id: id},                       
				dataType: 'json',                   
				success: function(data)         
				{
					Swal.fire({
						icon: "success",
						title: "Deleted!",
						text: "Deleted Successfully",
						showConfirmButton: false,
						timer: 1500
					});
					$("#row_"+id).remove();
				} 
			});
		}
	});
}




function staffSalaryPaymentModal(id, amount)
{
	$("#staffSalaryPaymentModal").modal('show');
	$("#staff_salary_payment_form #staff_id").val(id);
	$("#staff_salary_payment_form #amount").val(amount);
	$('select[name="payment_mode"]').focus();
}

function viewSalaryPaymentDetail(id)
{
	$("#salaryPaymentDetailModal").modal('show');
	$.ajax({
		type: 'GET',
		url: appRoute('staffSalaryPaymentDetail', id),
		dataType: 'json',
		success: function(data)         
		{
			$("#salary_payment_detail").html(data.html);
		}
	});
}

function printSelectedInvoices() {
	console.log("Printing selected");
	var ids = [];
	$('.picb:checked').each(function () {
		ids.push($(this).val());
	});

	if (ids.length === 0) {
		Swal.fire({
			icon: "error",
			title: "Required!",
			text: "Please select at least one invoice",
			showConfirmButton: false,
			timer: 1500
		});
		return false;
	}

	$('#invoice_ids').val(ids.join(','));
	$('#printSelectedInvoicesForm').submit();
}

function showHidePICB() {
	var v = parseInt($("#show_hide_pi_cb").val());
	if (v == 0) {
		$(".picb-checkbox").css('display', 'inline-block');
		$("#action-td").removeClass('tr3btns');
		$("#action-td").addClass('tr4btns');
		$("#show_hide_pi_cb").val(1);
		$("#print_selected_invoices_btn").show();
	}
	else {
		$(".picb-checkbox").css('display', 'none');
		$("#action-td").addClass('tr3btns');
		$("#action-td").removeClass('tr4btns');
		$("#show_hide_pi_cb").val(0);
		$("#print_selected_invoices_btn").hide();
	}
}


// Dashboard Module Charges Modal

// ---------------------------------------------------------------
// ABDM M3 (HIU side) - consent-requests.php, the standalone "Consent List"
// page. Wired to the list_consent_requests / search_patient_by_abha /
// create_consent_request actions in abdm/hiu_ajax.php (see the
// "consent-requests.php actions" section there) - the same file already
// backing the per-patient "Request Records From ABHA" button
// (abdmHiuButtonClick() etc above). A GRANTED row's "VIEW" button reuses
// abdmHiuOpenViewOffcanvas() further below.
// ---------------------------------------------------------------

function initConsentRequestsTable()
{
	if (typeof $.fn.DataTable === 'undefined' || $('#consent_requests_tbl').length === 0) {
		return;
	}
	if ($.fn.DataTable.isDataTable('#consent_requests_tbl')) {
		$('#consent_requests_tbl').DataTable().destroy();
	}
	$('#consent_requests_tbl').DataTable({
		dom: 'Blfrtip',
		'pageLength': 5,
		'processing': true,
		'serverSide': true,
		'autoWidth': false,
		'serverMethod': 'get',
		'order': [],
		'ajax': {
			'url': window.appRoutes.abdmHiuConsentsIndex,
			'data': function(data) {
				// Top filter bar (see initConsentRequestsFilters()) - all optional,
				// so an empty value here just means "don't filter on this field"
				data.patient_id = $('#cr_f_patient_id').val() || '';
				data.status = $('#cr_f_status').val() || '';
				data.purpose_code = $('#cr_f_purpose_code').val() || '';
				data.created_on_range = $('#cr_f_created_on').val() || '';
			}
		},
		'columns': [
			{ data: 'name' },
			{ data: 'patient_identifier' },
			{ data: 'status' },
			{ data: 'purpose' },
			{ data: 'hi_type' },
			{ data: 'created_on' },
			{ data: 'granted_on' },
			{ data: 'expiry_on' },
			{ data: 'consent_range' },
			{ data: 'view' }
		],
		buttons: [
			{
				extend: 'excel',
				text: 'Excel',
				className: 'buttons-html5',
				exportOptions: { columns: ':not(:last-child)' }
			},
			{
				extend: 'print',
				text: 'Print',
				className: 'buttons-html5',
				exportOptions: { columns: ':not(:last-child)' }
			}
		],
		initComplete: function (settings, json) {
			$('[data-bs-toggle="tooltip"]').tooltip();
		},
		drawCallback: function (settings) {
			$('[data-bs-toggle="tooltip"]').tooltip();
		}
	});
}

function reloadConsentRequestsTable()
{
	if ($.fn.DataTable.isDataTable('#consent_requests_tbl')) {
		$('#consent_requests_tbl').DataTable().ajax.reload(null, false);
	}
}

// Top filter bar on consent-requests.php (Patient/Status/Purpose/Created On)
// - same "pick filters, then click the search icon" UX as #search-opds-btn on
// opd-list.php, rather than reloading on every keystroke/change. The actual
// filter values are read straight off these controls inside
// initConsentRequestsTable()'s ajax.data callback above, so this function
// only needs to wire up the Patient select2 and the search button click.
function initConsentRequestsFilters()
{
	if (typeof $.fn.select2 !== 'undefined' && $('#cr_f_patient_id').length > 0 && !$('#cr_f_patient_id').hasClass('select2-hidden-accessible')) {
		$('#cr_f_patient_id').select2({
			placeholder: 'All Patients',
			allowClear: true,
			minimumInputLength: 3,
			width: '100%',
			ajax: {
				url: window.appRoutes.abdmHiuPatientsDropdown,
				dataType: 'json',
				type: 'GET',
				delay: 250,
				data: function(params) {
					return { q: params.term };
				},
				processResults: function(data) {
					return { results: data };
				},
				cache: true
			}
		});
	}

	$('#search-consent-requests-btn').off('click').on('click', function() {
		reloadConsentRequestsTable();
	});

	$('#cr_f_created_on_reset').off('click').on('click', function() {
		var input = document.getElementById('cr_f_created_on');
		if (input && input._flatpickr) {
			input._flatpickr.clear();
		} else {
			$('#cr_f_created_on').val('');
		}
		reloadConsentRequestsTable();
	});
}

function openAddConsentRequestModal()
{
	$('#create_consent_request_form')[0].reset();
	initConsentExistingPatientDropdown();
	$('#consent_existing_patient_dropdown').val(null).trigger('change.select2');
	$('#consent_abha_search_result').hide().empty();
	$('#consent_request_form_error').hide().empty();
	$('.consent-hi-type').prop('checked', false);
	$('#hi_OPConsultation, #hi_ImmunizationRecord, #hi_Prescription').prop('checked', true);
	$('#consent_duration_preset').val('180');
	applyConsentDurationPreset();

	// Consent Expiry must be in the future - constrained here (scoped to just
	// this one field) rather than in the shared ".datetimepicker" flatpickr
	// config in this file's main $(document).ready(), which plenty of other
	// pages reuse for genuinely past date/times (admission, discharge,
	// billing, ...).
	var expiryEl = $('#consent_expiry')[0];
	if (expiryEl && expiryEl._flatpickr) {
		expiryEl._flatpickr.set('minDate', 'today');
	}

	// Default expiry - 30 days from now, matching the "Consent Expiry"
	// field in the reference Consent Request Form modal. Uses flatpickr's own
	// API (see #consent_expiry's "datetimepicker" class, bound in this file's
	// main $(document).ready()) rather than .val(), so the picker's displayed
	// text and internal state stay in sync.
	consentSetFlatpickrDate('#consent_expiry', new Date(Date.now() + 30 * 24 * 60 * 60 * 1000));

	$('#addConsentRequestModal').modal('show');
}

// Sets a flatpickr-bound input's value via the picker's own API when one is
// attached (falls back to a plain .val() if flatpickr failed to load), so
// programmatic changes stay in sync with what the calendar UI shows/submits.
function consentSetFlatpickrDate(selector, date)
{
	var el = $(selector)[0];
	if (el && el._flatpickr) {
		el._flatpickr.setDate(date, true);
	} else {
		$(selector).val(date ? date.toISOString().slice(0, 10) : '');
	}
}

function consentClearFlatpickrDate(selector)
{
	var el = $(selector)[0];
	if (el && el._flatpickr) {
		el._flatpickr.clear();
	} else {
		$(selector).val('');
	}
}

// Presets fill From/To with a sensible date range; "All"/"Custom Range"
// leave them editable ("All" clears them - hi_ajax.php's create_consent_request/
// request_consent then omit the date range entirely, matching ABDM's "no range
// = full history" semantics; "Custom Range" simply unlocks the same two fields
// for manual entry). Shared (via explicit selectors, rather than hardcoded ids)
// between consent-requests.php's "New Consent Request" modal and
// opd-visits.php's "Request Records From Abha" modal - see
// applyConsentDurationPreset()/applyHiuConsentDurationPreset() below.
function applyDurationPreset(presetSelector, fromSelector, toSelector)
{
	var preset = $(presetSelector).val();
	var $from = $(fromSelector);
	var $to = $(toSelector);

	if (preset === 'all') {
		consentClearFlatpickrDate(fromSelector);
		consentClearFlatpickrDate(toSelector);
		$from.prop('disabled', true);
		$to.prop('disabled', true);
		return;
	}
	if (preset === 'custom') {
		$from.prop('disabled', false);
		$to.prop('disabled', false);
		return;
	}

	var days = parseInt(preset, 10) || 0;
	var now = new Date();
	var from = new Date(now.getTime() - days * 24 * 60 * 60 * 1000);
	consentSetFlatpickrDate(fromSelector, from);
	consentSetFlatpickrDate(toSelector, now);
	$from.prop('disabled', true);
	$to.prop('disabled', true);
}

function applyConsentDurationPreset()
{
	applyDurationPreset('#consent_duration_preset', '#consent_from_date', '#consent_to_date');
}

function consentFullAbhaAddress()
{
	var local = $.trim($('#consent_abha_local').val());
	if (local === '') { return ''; }
	// Allow staff to type the full address (with @domain already included)
	// too, without ending up with it duplicated.
	if (local.indexOf('@') !== -1) { return local; }
	return local + '@' + $('#consent_abha_domain').val();
}

// "Existing Patient" dropdown on the New Consent Request modal - same
// select2-against-ajax.php pattern as #add_opd_patients_dropdown on
// opd-list.php's Create OPD modal, but purely a convenience to auto-fill the
// ABHA Address fields below: unlike that dropdown, a consent request can
// always still be sent for a raw ABHA Address with no local patient at all,
// so there is no "no results, add new patient" flow here.
var consentExistingPatientDropdownInitialized = false;
function initConsentExistingPatientDropdown()
{
	if (consentExistingPatientDropdownInitialized || typeof $.fn.select2 === 'undefined') {
		return;
	}
	consentExistingPatientDropdownInitialized = true;

	$('#consent_existing_patient_dropdown').select2({
		placeholder: 'Search existing patient by name/mobile (optional)',
		minimumInputLength: 0,
		allowClear: true,
		width: '100%',
		dropdownParent: $('#addConsentRequestModal'),
		ajax: {
			url: window.appRoutes.abdmHiuPatientsDropdown,
			dataType: 'json',
			type: 'GET',
			delay: 250,
			data: function(params) {
				return { q: params.term };
			},
			processResults: function(data) {
				return { results: data };
			},
			cache: true
		}
	});

	$('#consent_existing_patient_dropdown').on('change', function() {
		var patientId = $(this).val();
		var $result = $('#consent_abha_search_result');
		if (!patientId) { return; }
		$.ajax({
		url: appRoute('abdmHiuPatientShow', patientId),
		type: 'GET',
		dataType: 'json',
		success: function(resp) {
				if (resp && resp.abha_address) {
					var parts = String(resp.abha_address).split('@');
					$('#consent_abha_local').val(parts[0]);
					if (parts[1]) {
						if ($('#consent_abha_domain option[value="' + parts[1] + '"]').length === 0) {
							$('#consent_abha_domain').append('<option value="' + parts[1] + '">@' + parts[1] + '</option>');
						}
						$('#consent_abha_domain').val(parts[1]);
					}
					$result.hide().empty();
				} else {
					$('#consent_abha_local').val('');
					$result.html('<div class="text-warning"><i class="fa fa-exclamation-triangle"></i> This patient has no ABHA Address on file yet - link one first (Patient Identity), or enter an ABHA Address manually below.</div>').show();
				}
			},
		error: function() {
				$result.html('<div class="text-danger">Could not look up this patient. Please try again.</div>').show();
			}
	});
	});
}

function searchConsentPatientByAbha()
{
	var abha = consentFullAbhaAddress();
	if (abha === '') {
		Swal.fire({ icon: 'warning', title: 'ABHA Address Required', text: "Please enter the patient's ABHA Address first." });
		return;
	}
	var $btn = $('#consent_search_patient_btn');
	var $result = $('#consent_abha_search_result');
	$btn.prop('disabled', true);
	$result.hide().empty();
	
	// Search in local database for a patient with this exact ABHA
	$.ajax({
		url: window.appRoutes.abdmHiuPatientsSearch,
		type: 'GET',
		data: { abha_address: abha },
		dataType: 'json',
		success: function(resp) {
			if (!resp || !resp.success) {
				$result.html('<div class="text-danger">' + ((resp && resp.error) || 'Something went wrong') + '</div>').show();
				return;
			}
			if (resp.found && resp.patient) {
				$result.html('<div class="text-success"><i class="fa fa-check-circle"></i> Found - ' +
					$('<div>').text(resp.patient.patient_name).html() + ' (ABHA Number: ' +
					$('<div>').text(resp.patient.abha_number || 'N/A').html() + ')</div>').show();
			} else {
				$result.html('<div class="text-muted"><i class="fa fa-info-circle"></i> No local patient record found for this ABHA Address - the consent request will still be sent to ABDM.</div>').show();
			}
		},
		error: function() {
			$result.html('<div class="text-danger">Could not reach the server. Please try again.</div>').show();
		},
		complete: function() {
			$btn.prop('disabled', false);
		}
	});
}

// Real Date object for a flatpickr-bound input's current selection (rather
// than re-parsing its displayed "d-m-Y"/"d-m-Y h:i K" text, which native
// Date parsing does not reliably support) - null if nothing is selected or
// flatpickr failed to load.
function consentFlatpickrDate(selector)
{
	var el = $(selector)[0];
	if (el && el._flatpickr && el._flatpickr.selectedDates.length) {
		return el._flatpickr.selectedDates[0];
	}
	return null;
}

function submitConsentRequest()
{
	console.log('[CONSENT] submitConsentRequest called - VERSION 2024-CHECK');
	var abha = consentFullAbhaAddress();
	var selectedPatientId = $('#consent_existing_patient_dropdown').val();
	var $error = $('#consent_request_form_error');
	$error.hide().empty();
	
	console.log('[CONSENT] selectedPatientId:', selectedPatientId, 'abha:', abha);

	if (abha === '') {
		$error.text("Patient's ABHA Address is required").show();
		return;
	}
	// Basic shape check only ("local@domain", no whitespace) - ABDM itself is
	// the real authority on whether the address actually exists; the Search
	// button already gives staff an earlier, friendlier way to catch a typo.
	if (!/^\S+@\S+$/.test(abha)) {
		$error.text('Enter a valid ABHA Address (e.g. name@sbx)').show();
		return;
	}

	var hiTypes = $('.consent-hi-type:checked').map(function() { return $(this).val(); }).get();
	if (!hiTypes.length) {
		$error.text('Select at least one Health Info type').show();
		return;
	}

	var fromDate = consentFlatpickrDate('#consent_from_date');
	var toDate = consentFlatpickrDate('#consent_to_date');
	if ($('#consent_duration_preset').val() === 'custom' && (!fromDate || !toDate)) {
		$error.text('Enter both a From and To date for a custom Health Info duration').show();
		return;
	}
	if (fromDate && toDate && fromDate > toDate) {
		$error.text('The Health Info "From" date must not be after the "To" date').show();
		return;
	}

	var expiryDate = consentFlatpickrDate('#consent_expiry');
	if (!expiryDate) {
		$error.text('Consent Expiry is required').show();
		return;
	}
	if (expiryDate.getTime() <= Date.now()) {
		$error.text('Consent Expiry must be a date/time in the future').show();
		return;
	}

	// Proceed directly to send consent request - no patient update
	console.log('[CONSENT] Proceeding to send consent request');
	proceedWithConsentRequest();
}

function proceedWithConsentRequest()
{
	console.log('[Consent Request] Sending consent request');
	var abha = consentFullAbhaAddress();
	var hiTypes = $('.consent-hi-type:checked').map(function() { return $(this).val(); }).get();
	var $error = $('#consent_request_form_error');

	var $btn = $('#submit_consent_request_btn');
	$btn.prop('disabled', true).text('Requesting...');
	
	var requestData = {
		abha_address: abha,
		purpose_code: $('#consent_purpose_code').val(),
		'hi_types[]': hiTypes,
		from_date: $('#consent_from_date').val(),
		to_date: $('#consent_to_date').val(),
		expiry: $('#consent_expiry').val()
	};
	console.log('[Consent Request] Request data:', requestData);

	$.ajax({
		url: window.appRoutes.abdmHiuConsentsStore,
		type: 'POST',
		data: requestData,
		traditional: true,
		dataType: 'json',
		success: function(resp) {
			if (!resp || !resp.success) {
				$error.text((resp && resp.error) || 'Something went wrong').show();
				return;
			}
			$('#addConsentRequestModal').modal('hide');
			reloadConsentRequestsTable();
			Swal.fire({ icon: 'success', title: 'Consent Request Sent', text: 'The patient will be asked to approve this in their ABHA app.' });
		},
		error: function() {
			$error.text('Could not reach the server. Please try again.').show();
		},
		complete: function() {
			$btn.prop('disabled', false).text('Request Consent');
		}
	});
}

// "Request Records From Abha" modal on opd-visits.php (#hiuRequestConsentModal)
// - opened from the 'requestable' state of abdmHiuButtonClick() above instead
// of firing abdm/hiu_ajax.php's request_consent action immediately with
// hardcoded defaults. Mirrors consent-requests.php's "New Consent Request"
// modal (same Purpose/duration-preset/HI Type/Expiry fields, same
// applyDurationPreset()/consentSetFlatpickrDate()/consentFlatpickrDate()
// helpers), just without the Existing Patient/ABHA Address fields - the ABHA
// Address is already known here (resolved server-side from patient_id).
function openHiuRequestConsentModal(btnEl, patientId)
{
	// Same rationale as abdmHiuOpenViewOffcanvas() above - dismiss any lingering
	// Bootstrap tooltip on the triggering button before showing this modal.
	$('[data-bs-toggle="tooltip"]').tooltip('hide');

	var $modal = $('#hiuRequestConsentModal');
	$modal.data('btnEl', btnEl).data('patientId', patientId);

	$('#hiu_consent_request_form')[0].reset();
	$('#hiu_consent_request_form_error').hide().empty();
	$('.hiu-consent-hi-type').prop('checked', false);
	$('#hiu_hi_OPConsultation, #hiu_hi_ImmunizationRecord, #hiu_hi_Prescription').prop('checked', true);
	$('#hiu_consent_duration_preset').val('180');
	applyHiuConsentDurationPreset();

	// Consent Expiry must be in the future - see the matching comment on
	// openAddConsentRequestModal() above.
	var expiryEl = $('#hiu_consent_expiry')[0];
	if (expiryEl && expiryEl._flatpickr) {
		expiryEl._flatpickr.set('minDate', 'today');
	}
	consentSetFlatpickrDate('#hiu_consent_expiry', new Date(Date.now() + 30 * 24 * 60 * 60 * 1000));

	$modal.modal('show');
}

function applyHiuConsentDurationPreset()
{
	applyDurationPreset('#hiu_consent_duration_preset', '#hiu_consent_from_date', '#hiu_consent_to_date');
}


// Shared full-width offcanvas (abdm-hiu-view-offcanvas.php, included on
// both opd-visits.php and consent-requests.php) showing a patient's fetched
// ABDM health records - opened from the "Fetch Records" state of
// abdmHiuButtonClick() above and from the "VIEW" button on the Consent
// List. No iframe/separate page load - abdmHiuViewRecordsInit() and the
// abdmHiuView*() family it drives (further above) run directly against
// this same page/session, targeting the offcanvas's own element IDs.
function abdmHiuOpenViewOffcanvas(patientId)
{
	// The triggering button (e.g. #abdm_hiu_pull_btn on opd-visits.php) can be
	// left with its Bootstrap tooltip still showing (hover/focus right before
	// this fires) - tooltips render in a body-level layer above the offcanvas,
	// so it would otherwise float on top of it. Hide any active tooltip first.
	$('[data-bs-toggle="tooltip"]').tooltip('hide');

	$('#abdm_hiu_view_title').text('ABDM Health Records');
	$('#abdm_hiu_view_abha_line').empty();
	$('#abdm_hiu_progress').empty();
	$('#abdm_hiu_records_layout').hide();
	$('#abdm_hiu_records_list').empty();
	$('#abdm_hiu_record_detail').html('<div class="text-muted">Select a record from the list to view its contents.</div>');

	var offcanvasEl = document.getElementById('abdmHiuViewOffcanvas');
	var offcanvas = bootstrap.Offcanvas.getOrCreateInstance(offcanvasEl);
	offcanvas.show();

	abdmHiuViewRecordsInit(patientId);
}

// Stop the fetch/health-data polling loop as soon as the offcanvas closes,
// rather than leaving it running in the background.
$(document).on('hidden.bs.offcanvas', '#abdmHiuViewOffcanvas', function() {
	clearTimeout(abdmHiuViewPollTimer);
	abdmHiuViewPollTimer = null;
});
