$(document).ready(function() {

  //Basic JS 
  $('.basic-tbl').DataTable({
	    lengthMenu: [
	        [ 15, 25, 50, -1 ],
	        [ '15', '25', '50', 'Show all' ]
	    ],   
});


//advance data table JS
  $('.advancedata-tbl').DataTable({    
    "scrollX": true,    
    stateSave: false,
    dom: 'Bfrtip',
    buttons: [
        {
            extend: 'print',
            className: 'btn btn-primary'
        },
        {
            extend: 'excel',
            className: 'btn btn-primary'
        },
        {
            extend: 'pdf',
            className: 'btn btn-primary'
        },
        {
            extend: 'csv',
            className: 'btn btn-primary'
        },
        {
            extend: 'copy',
            className: 'btn btn-primary'
        }
    ]
  });


 //Ajax Datatable JS
  $('#datatbl-ajax').DataTable( {
    "scrollX": true,  
    "ajax": '../assets/js/vendors/datatable/ajax-data/datatable-ajax.txt',
    scrollCollapse: true,
    lengthMenu: [
	        [ 15, 25, 50, -1 ],
	        [ '15', '25', '50', 'Show all' ]
	    ], 
  });



  //*** Doctor list ***//
  $('.doctorslist-tbl').DataTable({    
    "scrollX": true,    
    stateSave: false,
    dom: 'Bfrtip',   
    buttons: [
        {
            extend: 'print',
            className: 'btn btn-primary'
        },
        {
            extend: 'excel',
            className: 'btn btn-primary'
        },
        {
            extend: 'pdf',
            className: 'btn btn-primary'
        },
        {
            extend: 'csv',
            className: 'btn btn-primary'
        },
        {
            extend: 'copy',
            className: 'btn btn-primary'
        }
    ]
  });


  //*** Patient list ***//
  $('.patients-tbl').DataTable({    
    "scrollX": true,    
    stateSave: false,
    dom: 'Bfrtip',   
    buttons: [
        {
            extend: 'print',
            className: 'btn btn-primary'
        },
        {
            extend: 'excel',
            className: 'btn btn-primary'
        },
        {
            extend: 'pdf',
            className: 'btn btn-primary'
        },
        {
            extend: 'csv',
            className: 'btn btn-primary'
        },
        {
            extend: 'copy',
            className: 'btn btn-primary'
        }
    ]
  });


    //Payment table JS 
    $('.payment-tbl').DataTable({
            lengthMenu: [
                [ 15, 25, 50],
                [ '15', '25', '50']
            ],   
    });



  
});