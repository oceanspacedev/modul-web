{{--
    Pilihan "Dipakai di" sebuah soal pelatihan: pretest & posttest, pretest saja, atau posttest saja. Hanya tampil bila
    pelatihan punya pretest dan posttest; pelatihan dengan satu fase memakai semua soalnya.
    $current: nilai saat ini. $fieldId: id unik field.
    $followsType (opsional): id <select> tipe soal. Selama pilihan ini belum disentuh, memilih Essay menggesernya ke
        "Posttest saja" (essay harus dinilai pemateri) dan kembali ke Pilihan Ganda mengembalikannya.
--}}
@if($training->hasBothPhases())
    <div class="form-group">
        <label for="{{ $fieldId }}">Dipakai di</label>
        <select name="used_in" id="{{ $fieldId }}" class="form-control" @isset($followsType) data-follows-type="{{ $followsType }}" @endisset>
            @foreach(\App\Models\TrainingQuestion::USED_IN as $value => $label)
                <option value="{{ $value }}" @selected($current === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <small class="form-text text-muted">Essay yang dipakai di pretest juga dinilai manual oleh pemateri.</small>
    </div>
    @isset($followsType)
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            var usedIn = document.getElementById(@json($fieldId));
            var type = document.getElementById(@json($followsType));
            var touched = false;
            usedIn.addEventListener('change', function () { touched = true; });
            type.addEventListener('change', function () {
                if (!touched) usedIn.value = type.value === 'essay' ? 'posttest' : 'both';
            });
        });
        </script>
    @endisset
@endif
