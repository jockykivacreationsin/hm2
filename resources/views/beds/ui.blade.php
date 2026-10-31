@extends('layouts.app')
@section('title', 'Beds UI')
@section('content')
<div class="themebody-wrap">
    <div class="theme-body common-dash" data-simplebar="">
        <div class="container-fluid">
            @forelse($floors as $flrow)
            @php
                $floor_id = $flrow->id;
                $floor_name = $flrow->floor;
                $floor_bed_count = $flrow->beds_count;
            @endphp
            <div class="row section-box">
                <div class="col-12 col-lg-12">
                    <div class="card floor-card">
                        <div class="card-header">
                            <h4>{{ $floor_name }} ({{ $floor_bed_count }} Beds)</h4>
                        </div>
                        <div class="card-body">
                            @foreach($flrow->areas as $farow)
                            @php
                                $floor_area_id = $farow->id;
                                $floor_area = $farow->area;
                                $area_beds = $farow->beds;
                                $area_bed_count = $area_beds->count();
                            @endphp
                            <div class="floor-area-box"><!-- Floor Area -->
                                <div class="row">
                                    <div class="col-lg-12">
                                        <span class="floor-area-name">Area: {{ $floor_area }} ({{ $area_bed_count }} Bed{{ $area_bed_count == 1 ? '' : 's' }})</span>
                                    </div>
                                </div>
                                <div class="row">
                                    @foreach($area_beds as $bdrow)
                                    @php
                                        $ba = $bdrow->status;
                                        $bed_id = $bdrow->id;
                                        $bed_name = $bdrow->bed_name;
                                        $ward_category = $bdrow->wardCategory?->ward_category ?? '';
                                        $bed_image = $bdrow->bedType?->image ?? 'bed-1.png';
                                        
                                        if ($ba === 'Available') {
                                            $aclass = 'bed-green';
                                            $bdpd = 'Available';
                                            $href = 'javascript:void(0)';
                                        } else {
                                            $aclass = 'bed-red';
                                            $ipd = $activeIpds[$bed_id] ?? null;
                                            $dcd = $activeDcds[$bed_id] ?? null;
                                            if ($ipd && $ipd->patient) {
                                                $admTime = $ipd->admission_date_time ? \Carbon\Carbon::parse($ipd->admission_date_time)->format('d-m-Y h:i A') : '';
                                                $bdpd = $ipd->patient->patient_name . ' ' . $admTime;
                                                $href = url('ipd/' . $ipd->id);
                                            } elseif ($dcd && $dcd->patient) {
                                                $admTime = $dcd->admission_date_time ? \Carbon\Carbon::parse($dcd->admission_date_time)->format('d-m-Y h:i A') : '';
                                                $bdpd = $dcd->patient->patient_name . ' ' . $admTime;
                                                $href = url('dcd/' . $dcd->id);
                                            } else {
                                                $bdpd = 'Occupied';
                                                $href = 'javascript:void(0)';
                                            }
                                        }
                                    @endphp
                                    <div class="col-lg-1 bed-box"><!-- Bed -->
                                        <a href="javascript:void(0)" class="empty-bed-ico" onclick="emptyBed({{ $bed_id }})"><i class="far fa-empty-set"></i></a>
                                        <a href="{{ $href }}" data-bs-toggle="tooltip" data-bs-placement="top" title="{{ $bdpd }}">
                                            <div class="bed-card {{ $aclass }}">
                                                <span class="bed-icon"><img src="{{ asset('assets/images/beds/' . $bed_image) }}" alt="Bed"></span>
                                                <div class="bed-content">
                                                    <span class="bed-patient">{{ $ward_category }}</span>
                                                    <span class="bed-text">{{ $bed_name }} ({{ $bed_id }})</span>
                                                </div>
                                            </div>
                                        </a>
                                    </div><!-- Bed End -->
                                    @endforeach
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
            @empty
            <div class="row">
                <div class="col-12 text-center p-4">
                    <p class="text-muted">No beds found.</p>
                </div>
            </div>
            @endforelse
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function emptyBed(id) {
    Swal.fire({
        title: "Available?",
        text: "Are you sure you want to make it available!",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        confirmButtonText: "Yes, make it available!"
    }).then(function(result) {
        if (result.isConfirmed) {
            $.ajax({ 
                type: 'POST',
                url: '{{ route("beds.empty") }}',                  
                data: { _token: '{{ csrf_token() }}', id: id },                       
                dataType: 'json',                   
                success: function(data) {
                    Swal.fire({
                        icon: "success",
                        title: "Success!",
                        text: "Saved Successfully",
                        showConfirmButton: false,
                        timer: 1500
                    });
                    location.reload();
                },
                error: function(xhr) {
                    Swal.fire({
                        icon: "error",
                        title: "Error",
                        text: xhr.responseJSON ? xhr.responseJSON.message : "Failed to update bed status."
                    });
                }
            });
        }
    });
}
</script>
@endpush
