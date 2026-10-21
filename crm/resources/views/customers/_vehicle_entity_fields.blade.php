{{-- the finance entity a requested vehicle was sent to, its branch and the stage there (filled by data-fill-* when editing) --}}
<div class="form-grid cols-2 mt">
    <div class="field"><label>جهة التقسيط</label><select class="input" name="finance_entity"><option value="">— بدون —</option>@foreach(\App\Models\Lookup::list('finance_entity') as $x)<option>{{ $x }}</option>@endforeach</select></div>
    <div class="field"><label>فرع الجهة</label><input class="input" name="entity_branch" maxlength="120" list="entity-branches" placeholder="مثلاً قفط"></div>
    <div class="field" style="grid-column:1/-1"><label>المرحلة</label><select class="input" name="stage"><option value="">— اختر —</option>@foreach(\App\Models\Lookup::list('installment_stage') as $x)<option>{{ $x }}</option>@endforeach</select></div>
</div>
