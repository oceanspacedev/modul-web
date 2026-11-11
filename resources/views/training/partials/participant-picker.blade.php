{{--
    Pemilih peserta di form pelatihan (buat & edit).

    Server hanya mengirim data ringkas semua user sebagai JSON; baris daftar dibuat di browser dan ditampilkan
    bertahap (PAGE_SIZE per langkah), supaya ukuran halaman tidak ikut membesar bersama jumlah karyawan. Pilihan
    disimpan di memori browser dan dikirim sebagai input tersembunyi participants[] saat form disimpan, jadi user yang
    barisnya tidak sedang tampil tetap ikut terkirim.

    Variabel: $users (baris ringkas: id, nama_lengkap, employee_id, username, divisi_id, no_hp), $divisis,
    $selected (id user terpilih), $saved (opsional, id peserta tersimpan: mengeluarkannya meminta konfirmasi).
--}}
<div class="form-row">
    <div class="form-group col-sm-6">
        <select id="filterDivisi" class="form-control form-control-sm select2" aria-label="Divisi" style="width: 100%;">
            <option value="">Semua Divisi</option>
            @foreach($divisis as $div)
                <option value="{{ $div->id }}">{{ $div->name }} ({{ $div->users_count }})</option>
            @endforeach
        </select>
    </div>
    <div class="form-group col-sm-6">
        <input type="text" id="searchParticipant" class="form-control form-control-sm" placeholder="Cari nama, ID karyawan, username...">
    </div>
</div>

@if($users->isEmpty())
    <div class="border rounded">
        <x-empty-state icon="fa-users" title="Belum ada data user" text="Peserta dipilih dari data user. Tambahkan user terlebih dahulu.">
            @can('create', \App\Models\User::class)
                <a href="{{ route('user.create') }}" class="btn btn-primary btn-sm">Tambah User</a>
            @endcan
        </x-empty-state>
    </div>
@else
    <div class="border rounded" id="participantList" style="max-height: 420px; overflow-y: auto;"></div>
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-2">
        <span class="text-muted small" id="participantListInfo"></span>
        <button type="button" class="btn btn-default btn-xs d-none" id="btnMoreParticipants"></button>
    </div>
@endif
<div id="participantInputs"></div>

@php
    $pickerRows = $users->map(fn ($user) => [$user->id, $user->nama_lengkap, $user->employee_id ?? '', $user->username ?? '', $user->divisi_id, filled($user->no_hp)])->values();
    $pickerDivisiNames = $divisis->pluck('name', 'id');
    $pickerSaved = array_values(array_map('intval', $saved ?? []));
    $pickerSelected = array_values(array_map('intval', $selected));
@endphp
<script>
document.addEventListener('DOMContentLoaded', function () {
    // [id, nama, id karyawan, username, id divisi, punya nomor WA]
    const USERS = @json($pickerRows);
    const DIVISI_NAMES = @json($pickerDivisiNames);
    const SAVED_IDS = @json($pickerSaved);
    const PAGE_SIZE = 100;

    const selected = new Set(@json($pickerSelected));
    const list = document.getElementById('participantList');
    const info = document.getElementById('participantListInfo');
    const btnMore = document.getElementById('btnMoreParticipants');
    const badge = document.getElementById('selectedCountBadge');
    const filterDivisi = document.getElementById('filterDivisi');
    const searchInput = document.getElementById('searchParticipant');
    const inputs = document.getElementById('participantInputs');
    const form = inputs.closest('form');
    let limit = PAGE_SIZE;

    // Teks pencarian per user (huruf kecil), dibuat sekali.
    const haystack = USERS.map(user => (user[1] + '\n' + user[2] + '\n' + user[3]).toLowerCase());

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text == null ? '' : String(text);
        return div.innerHTML;
    }

    // User yang cocok dengan pencarian dan filter divisi (semua, bukan hanya yang sedang tampil).
    function matches() {
        const search = searchInput.value.toLowerCase().trim();
        const divisiId = filterDivisi.value;

        return USERS.filter((user, index) => (!search || haystack[index].includes(search))
            && (!divisiId || String(user[4]) === divisiId));
    }

    function updateCount() {
        badge.textContent = selected.size;
    }

    function render() {
        updateCount();
        if (!list) return;

        const found = matches();
        const shown = found.slice(0, limit);

        list.innerHTML = shown.length ? shown.map(user => {
            const id = user[0];
            return '<div class="participant-item d-flex align-items-center justify-content-between px-3 py-2 border-bottom">' +
                '<div class="custom-control custom-checkbox min-w-0">' +
                    '<input type="checkbox" value="' + id + '" class="custom-control-input participant-checkbox" id="user_' + id + '"' + (selected.has(id) ? ' checked' : '') + '>' +
                    '<label class="custom-control-label" for="user_' + id + '">' +
                        '<strong class="d-block text-dark">' + escapeHtml(user[1]) + '</strong>' +
                        '<span class="text-muted small">' + escapeHtml(DIVISI_NAMES[user[4]] || 'Divisi Umum') + '</span>' +
                    '</label>' +
                '</div>' +
                (user[5] ? '' : '<span class="badge badge-secondary ml-2" title="Nomor WhatsApp belum diisi">Tanpa WA</span>') +
            '</div>';
        }).join('') : '<div class="text-center text-muted small py-4">Tidak ada user yang cocok.</div>';

        const rest = found.length - shown.length;
        info.textContent = 'Menampilkan ' + shown.length + ' dari ' + found.length + ' user';
        btnMore.classList.toggle('d-none', rest <= 0);
        btnMore.textContent = 'Tampilkan ' + Math.min(PAGE_SIZE, rest) + ' berikutnya';
    }

    function refilter() {
        limit = PAGE_SIZE;
        render();
        if (list) list.scrollTop = 0;
    }

    if (list) {
        list.addEventListener('change', function (e) {
            if (!e.target.classList.contains('participant-checkbox')) return;
            const id = parseInt(e.target.value, 10);
            e.target.checked ? selected.add(id) : selected.delete(id);
            updateCount();
        });

        btnMore.addEventListener('click', function () {
            limit += PAGE_SIZE;
            render();
        });
    }

    // "Pilih semua" / "Kosongkan" berlaku untuk semua user yang cocok dengan filter saat ini.
    document.getElementById('btnSelectAll').addEventListener('click', function () {
        matches().forEach(user => selected.add(user[0]));
        render();
    });
    document.getElementById('btnDeselectAll').addEventListener('click', function () {
        matches().forEach(user => selected.delete(user[0]));
        render();
    });

    searchInput.addEventListener('input', refilter);
    // Select2 mengirim event change lewat jQuery, jadi dengarkan lewat jQuery bila ada.
    if (typeof $ !== 'undefined') {
        $(filterDivisi).on('change', refilter);
    } else {
        filterDivisi.addEventListener('change', refilter);
    }

    form.addEventListener('submit', function (e) {
        // Peserta lama yang dikeluarkan ikut dihapus datanya, jadi minta konfirmasi sebelum menyimpan.
        const removed = SAVED_IDS.filter(id => !selected.has(id));
        if (removed.length) {
            const names = new Map(USERS.map(user => [user[0], user[1]]));
            const labels = removed.map(id => names.get(id) || ('User #' + id));
            if (!confirm(removed.length + ' peserta akan dikeluarkan beserta presensi & nilainya:\n- '
                + labels.slice(0, 10).join('\n- ') + (labels.length > 10 ? '\n- dan ' + (labels.length - 10) + ' lainnya' : '')
                + '\n\nLanjutkan simpan?')) {
                e.preventDefault();
                return;
            }
        }

        inputs.innerHTML = Array.from(selected).map(id => '<input type="hidden" name="participants[]" value="' + id + '">').join('');
    });

    render();
});
</script>
