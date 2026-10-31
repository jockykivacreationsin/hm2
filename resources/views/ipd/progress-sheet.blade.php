@extends('layouts.app')
@section('title', 'IPD Progress Sheet')
@section('content')
<div class="themebody-wrap ipd-wrap">
    <div class="card-ipd">
        <div class="card-menu">
            @include('ipd.partials.menu', ['menu' => 'pf'])
        </div>
        <form id="ipd-progress-sheet-form" data-action="{{ route('ipd.progress-sheet.save', $ipd->id) }}" data-method="POST">
            @csrf
            <input type="hidden" name="ipd_id" value="{{ $ipd->id }}">
            <div class="card-body">
                <div class="row ipd-patient-treatment-row">
                    <div class="ipd-title-with-buttons row-bottom-border">
                        <div class="ipd-title">Progress Sheet</div>
                        @if($save)
                            <div class="ipd-buttons">
                                <ul>
                                    <li><button type="submit"><i class="fa fa-save"></i> Save</button></li>
                                </ul>
                            </div>
                        @endif
                    </div>	
                    <div class="mt-20">
                        <div class="row">
                            <div class="col-12 col-md-12">
                                <div class="form-group local-forms">
                                    <textarea id="progress_sheet" name="progress_sheet" class="form-control summernote">{{ \App\Support\SafeHtml::clean($progress_sheet, true) }}</textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
/* appForm:wired */
appForm.bind('#ipd-progress-sheet-form', { syncEditors: true });
</script>
@endpush
