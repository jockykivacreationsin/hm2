{{-- Signature block of the IPD/DCD discharge bills: signature image (if present) + logged-in user. Vars: $sign_name, $sign_designation. --}}
@if(is_file(public_path('assets/images/sign.jpg')))
<img style="width:80px" src="{{ public_path('assets/images/sign.jpg') }}"><br>
@endif
Signature<br>
@if(!empty($sign_name))<strong>{{ $sign_name }}</strong>@endif
@if(!empty($sign_designation))<br>{{ $sign_designation }}@endif
