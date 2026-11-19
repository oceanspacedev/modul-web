@extends('layout.main_template')

@php($showUser = auth()->user()->can('manage-od-report') && ! $memberId)

@section('content')
<section class="content-header">
    <div class="container-fluid">
        <x-page-header title="Weekly Report" :subtitle="App\Support\OdWeek::monthLabel($month).' · minggu Sabtu–Jumat'" eyebrow="Report">
            <x-slot:actions>
                @if (Route::has('od-report.export'))
                    <form action="{{ route('od-report.export') }}" method="POST" class="m-0">
                        @csrf
                        <input type="hidden" name="type" value="weekly">
                        <input type="hidden" name="bulan" value="{{ $month }}">
                        <input type="hidden" name="user" value="{{ $memberId }}">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-file-excel"></i> Ekspor .xlsx</button>
                    </form>
                @endif
                <button type="button" class="btn btn-default" onclick="window.print()"><i class="fas fa-print"></i> Cetak</button>
            </x-slot:actions>
        </x-page-header>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        <x-flash />

        <div class="card">
            <div class="card-header filter-toolbar">
                <form action="{{ route('od-report.weekly') }}" method="GET" class="d-flex flex-wrap align-items-center gap-2 w-100 m-0">
                    @include('od-report._period-filter')
                    <select name="minggu" class="custom-select custom-select-sm" style="width: 150px;" onchange="this.form.submit()" aria-label="Minggu">
                        <option value="">Semua minggu</option>
                        @foreach ($weeks as $week)
                            <option value="{{ $week->key() }}" @selected($weekKey === $week->key())>Week {{ $week->number() }}</option>
                        @endforeach
                    </select>
                    <select name="status" class="custom-select custom-select-sm" style="width: 150px;" onchange="this.form.submit()" aria-label="Status">
                        <option value="">Semua status</option>
                        @foreach (App\Models\OdActivity::RESULTS as $result)
                            <option value="{{ $result }}" @selected(request('status') === $result)>{{ $result }}</option>
                        @endforeach
                    </select>
                    <x-search-field placeholder="Cari pekerjaan..." class="ml-auto" style="width: 220px; max-width: 100%;"
                        :clear-url="route('od-report.weekly', request()->except('search'))" />
                </form>
            </div>
        </div>

        @forelse ($groups as $rows)
            @php($week = $rows->first()->week)
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Week {{ $week->number() }} <span class="text-muted font-weight-normal">({{ $week->range() }})</span></h3>
                    <span class="badge badge-secondary ml-2">{{ $rows->count() }}</span>
                </div>
                <div class="card-body table-responsive p-0">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                @if ($showUser)<th>Nama</th>@endif
                                <th>Detail pekerjaan</th>
                                <th>Status</th>
                                <th>Kendala</th>
                                <th>Solusi</th>
                                <th>Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                                <tr>
                                    @if ($showUser)<td class="text-nowrap">{{ $row->user->nama_lengkap }}</td>@endif
                                    <td style="white-space: normal; min-width: 220px;">
                                        {{ $row->name }}
                                        @if ($row->count > 1)<span class="badge badge-light">{{ $row->count }}x</span>@endif
                                        @if ($row->document)
                                            <div class="small text-muted"><i class="far fa-file-alt"></i> {{ $row->document->title }}</div>
                                        @endif
                                    </td>
                                    <td><span class="badge {{ $row->result === 'Done' ? 'badge-success' : ($row->result === 'Hold' ? 'badge-secondary' : 'badge-warning') }}">{{ $row->result }}</span></td>
                                    <td style="white-space: normal;">{{ $row->kendala ?: '-' }}</td>
                                    <td style="white-space: normal;">{{ $row->solusi ?: '-' }}</td>
                                    <td style="white-space: normal;">{{ $row->keterangan ?: '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @empty
            <div class="card">
                <x-empty-state icon="fa-calendar-week" title="Belum ada laporan" text="Aktivitas yang sudah disimpan di Daily Report muncul di sini." />
            </div>
        @endforelse
    </div>
</section>
@endsection
