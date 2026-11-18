{{-- Filter bulan + anggota (Leader) untuk Weekly dan Monthly. $route = nama route halaman. --}}
<select name="bulan" class="custom-select custom-select-sm" style="width: 170px;" onchange="this.form.submit()" aria-label="Bulan">
    @foreach ($months as $value)
        <option value="{{ $value }}" @selected($month === $value)>{{ App\Support\OdWeek::monthLabel($value) }}</option>
    @endforeach
</select>
@if ($members->count() > 1 || auth()->user()->can('manage-od-report'))
    <select name="user" class="custom-select custom-select-sm" style="width: 190px;" onchange="this.form.submit()" aria-label="Anggota">
        <option value="">Semua anggota</option>
        @foreach ($members as $member)
            <option value="{{ $member->id }}" @selected($memberId === $member->id)>{{ $member->nama_lengkap }}</option>
        @endforeach
    </select>
@endif
