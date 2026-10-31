@extends('layouts.app')
@section('title', 'DCD Medication')
@section('content')
<div class="themebody-wrap ipd-wrap">
    <div class="card-ipd">
        <div class="card-menu">
            @include('dcd.partials.menu')
        </div>
        <div class="card-body">
            <div class="row ipd-patient-medication-row">
                <div class="ipd-title-with-buttons row-bottom-border">
                    <div class="ipd-title">Medication</div>
                </div>
                @forelse($prescriptions as $rx)
                    @php
                        $dt = $rx->prescription_date_time ? \Carbon\Carbon::parse($rx->prescription_date_time)->format('d-m-Y h:i A') : '';
                        $staffName = $rx->doctor?->staff_name ?? '';
                    @endphp
                    <div class="sm-table mt-10">
                        <div class="ipd-title-with-buttons mb-10">
                            <div class="mp-tbl-title">{{ $dt }} - {{ $staffName }}</div>
                        </div>	
                        <div class="table-responsive">  
                            <table class="table table-bordered table-sm table-text-sm">
                                <thead>
                                    <tr>
                                        <th>Medicine</th>
                                        <th>Dose</th>
                                        <th>Frequency</th>
                                        <th>Timing</th>
                                        <th>Meal</th>
                                        <th>Duration</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($rx->medicines as $med)
                                        @php
                                            $prodName = $med->product?->product_name ?? '';
                                            $doseQty = $med->dose_quantity ?? '';
                                            $freqDisplay = $med->frequency?->display_text ?? '';
                                            $timingNames = $med->timings
                                                ->sortBy(fn($t) => $t->timing?->sort_order ?? 0)
                                                ->map(fn($t) => $t->timing?->timing_name)
                                                ->filter()
                                                ->implode(' + ');
                                            $mealName = $med->mealRelation?->relation_name ?? '';
                                            $durationDays = $med->duration_days ? $med->duration_days . ' days' : '';
                                        @endphp
                                        <tr>
                                            <td>{{ $prodName }}</td>
                                            <td>{{ $doseQty }}</td>
                                            <td>{{ $freqDisplay }}</td>
                                            <td>{{ $timingNames }}</td>
                                            <td>{{ $mealName }}</td>
                                            <td>{{ $durationDays }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @empty
                    <div class="col-12 mt-3">
                        <p class="text-muted text-center">No medications recorded yet.</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(window).on("load", function() {
    if (typeof showDropDownPathologyTests === 'function') {
        showDropDownPathologyTests();
    }
    if (typeof showDropDownRadiologyTests === 'function') {
        showDropDownRadiologyTests();
    }
});
</script>
@endpush
