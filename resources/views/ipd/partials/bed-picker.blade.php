{{-- Legacy getbedsui: floors > areas > beds. Only Available beds can be chosen (see chooseIpdBed() in ipd/index). --}}
@forelse($floors as $floor)
    @php
        $floorAreas = $areas[$floor->id] ?? collect();
        $floorBedCount = $floorAreas->sum(fn ($a) => ($beds[$a->id] ?? collect())->count());
    @endphp
    @continue($floorBedCount === 0)
    <div class="row section-box">
        <div class="col-12 col-lg-12">
            <div class="card floor-card">
                <div class="card-header"><h4>{{ $floor->floor }} ({{ $floorBedCount }} Beds)</h4></div>
                <div class="card-body">
                    @foreach($floorAreas as $area)
                        @php $areaBeds = $beds[$area->id] ?? collect(); @endphp
                        @continue($areaBeds->isEmpty())
                        <div class="floor-area-box">
                            <div class="row"><div class="col-lg-12"><span class="floor-area-name">Area: {{ $area->area }} ({{ $areaBeds->count() }} Bed)</span></div></div>
                            <div class="row">
                                @foreach($areaBeds as $bed)
                                    @php $free = $bed->status === 'Available'; @endphp
                                    <div class="col-lg-1 bed-box">
                                        <a href="javascript:void(0)"
                                           @if($free) onclick="if(typeof chooseBed === 'function'){ chooseBed({{ $bed->id }}, @js($bed->bed_name), 'selected_bed_id', 'selected_bed_name', '{{ $bed->status }}'); } if(typeof chooseIpdBed === 'function'){ chooseIpdBed({{ $bed->id }}, @js($bed->bed_name)); }" @endif
                                           data-bs-toggle="tooltip" title="{{ $free ? 'Available' : 'Occupied' }}">
                                            <div class="bed-card {{ $free ? 'bed-green' : 'bed-red' }}">
                                                <span class="bed-icon"><img src="{{ asset('assets/images/beds/' . $bed->image) }}" alt=""></span>
                                                <div class="bed-content">
                                                    <span class="bed-patient">{{ $bed->ward_category }}</span>
                                                    <span class="bed-text">{{ $bed->bed_name }}</span>
                                                </div>
                                            </div>
                                        </a>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
@empty
    <p class="text-center text-muted">No beds configured.</p>
@endforelse
