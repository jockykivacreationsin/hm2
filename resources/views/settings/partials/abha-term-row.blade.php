{{-- One numbered ABHA term (settings tab "ABDM Facility"). Var: $text. The number is filled in by the page script. --}}
<div class="abha-term">
	<span class="abha-term-no">1</span>
	<textarea class="form-control" name="abha_terms_items[]" rows="2" maxlength="2000" placeholder="Type the term here">{{ $text }}</textarea>
	<div class="abha-term-actions">
		<button type="button" class="btn btn-sm btn-light abha-term-up" title="Move up"><i class="fa fa-arrow-up"></i></button>
		<button type="button" class="btn btn-sm btn-light abha-term-down" title="Move down"><i class="fa fa-arrow-down"></i></button>
		<button type="button" class="btn btn-sm btn-light text-danger abha-term-delete" title="Delete this term"><i class="fa fa-trash"></i></button>
	</div>
</div>
