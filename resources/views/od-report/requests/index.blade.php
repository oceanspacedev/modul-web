@extends('layout.main_template')

@section('content')
<section class="content-header">
    <div class="container-fluid">
        <x-page-header title="Permintaan Masuk" subtitle="Pengajuan IK dari karyawan" eyebrow="Dokumen">
            <x-slot:actions>
                @if (Route::has('od-report.export'))
                    <form action="{{ route('od-report.export') }}" method="POST" class="m-0">
                        @csrf
                        <input type="hidden" name="type" value="requests">
                        <button type="submit" class="btn btn-default"><i class="fas fa-file-excel"></i> Ekspor .xlsx</button>
                    </form>
                @endif
                <a href="{{ route('document-request.create') }}" class="btn btn-default" target="_blank"><i class="fas fa-external-link-alt"></i> Form publik</a>
            </x-slot:actions>
        </x-page-header>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        <x-flash />

        <div class="row">
            @foreach ([['Total', $stats['total']], ['Belum dimulai', $stats['new']], ['Sedang berjalan', $stats['running']], ['Selesai', $stats['finished']]] as [$label, $value])
                <div class="col-6 col-xl-3 mb-3">
                    <div class="stat-tile h-100">
                        <div class="min-w-0">
                            <p class="stat-tile-label">{{ $label }}</p>
                            <p class="stat-tile-value">{{ $value }}</p>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="card">
            <div class="card-header filter-toolbar">
                <form action="{{ route('od-report.requests.index') }}" method="GET" class="d-flex flex-wrap align-items-center gap-2 w-100 m-0">
                    <select name="kategori" class="custom-select custom-select-sm" style="width: 160px;" onchange="this.form.submit()" aria-label="Kategori">
                        <option value="">Semua kategori</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected((int) request('kategori') === $category->id)>{{ $category->code }}</option>
                        @endforeach
                    </select>
                    <select name="departemen" class="custom-select custom-select-sm" style="width: 200px;" onchange="this.form.submit()" aria-label="Departemen">
                        <option value="">Semua departemen</option>
                        @foreach (App\Models\DocumentRequest::DEPARTMENTS as $department)
                            <option value="{{ $department }}" @selected(request('departemen') === $department)>{{ $department }}</option>
                        @endforeach
                    </select>
                    <select name="prioritas" class="custom-select custom-select-sm" style="width: 150px;" onchange="this.form.submit()" aria-label="Prioritas">
                        <option value="">Semua prioritas</option>
                        @foreach (App\Models\OdDocument::PRIORITAS as $priority)
                            <option value="{{ $priority }}" @selected(request('prioritas') === $priority)>{{ $priority }}</option>
                        @endforeach
                    </select>
                    <select name="tahap" class="custom-select custom-select-sm" style="width: 200px;" onchange="this.form.submit()" aria-label="Tahap">
                        <option value="">Semua tahap</option>
                        @foreach ($stageOptions as $stage)
                            <option value="{{ $stage }}" @selected(request('tahap') === $stage)>{{ $stage }}</option>
                        @endforeach
                    </select>
                    <select name="sort" class="custom-select custom-select-sm" style="width: 170px;" onchange="this.form.submit()" aria-label="Urutkan">
                        @foreach (App\Http\Controllers\OdRequestController::SORTS as $value => $label)
                            <option value="{{ $value }}" @selected($sort === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <x-search-field placeholder="Cari kode, nama, proses..." class="ml-auto" style="width: 240px; max-width: 100%;"
                        :clear-url="route('od-report.requests.index', request()->except(['search', 'page']))" />
                </form>
            </div>

            <div class="card-body table-responsive p-0">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Kode</th>
                            <th>Pemohon</th>
                            <th>Proses</th>
                            <th>Prioritas</th>
                            <th>Status</th>
                            <th style="width: 140px;">Progres</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($requests as $request)
                            @php($priority = $request->topPriority())
                            <tr>
                                <td><a href="{{ route('od-report.requests.show', $request) }}"><strong>{{ $request->code }}</strong></a>
                                    <div class="small text-muted">{{ $request->category?->code }} · {{ $request->created_at->format('d/m/Y') }}</div></td>
                                <td>{{ $request->nama }}<div class="small text-muted">{{ $request->departemen }}</div></td>
                                <td style="white-space: normal; min-width: 200px;">
                                    {{ $request->processes->first()?->title }}
                                    @if ($request->processes->count() > 1)<span class="text-muted small">(+{{ $request->processes->count() - 1 }})</span>@endif
                                </td>
                                <td>@if ($priority)<span class="badge {{ App\Models\DocumentRequest::priorityBadgeClass($priority) }}">{{ $priority }}</span>@endif</td>
                                <td>{{ $request->stage() }}</td>
                                <td>
                                    <div class="progress" style="height: 6px;"><div class="progress-bar bg-success" style="width: {{ $request->progress() }}%"></div></div>
                                    <span class="small text-muted">{{ $request->progress() }}%</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">
                                    <x-empty-state icon="fa-inbox" title="Belum ada permintaan" text="Pengajuan dari form Permintaan Dokumen muncul di sini." />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($requests->hasPages())
                <div class="card-footer d-flex justify-content-end">{{ $requests->links('pagination::bootstrap-4') }}</div>
            @endif
        </div>
    </div>
</section>
@endsection
