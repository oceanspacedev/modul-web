@extends('layout.main_template')

@section('content')
<section class="content-header">
    <div class="container-fluid">
        <x-page-header title="Daily Report" :subtitle="$today->locale('id')->translatedFormat('l, d F Y')" eyebrow="Report" />
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        <x-flash />

        <div class="row">
            <div class="col-lg-8">
                <form action="{{ route('od-report.daily.store') }}" method="POST" class="card">
                    @csrf
                    <div class="card-header"><h3 class="card-title">Tambah aktivitas</h3></div>
                    <div class="card-body pb-0">
                        @include('od-report.daily._fields', ['activity' => null])
                    </div>
                    <div class="card-footer text-right">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Tambah</button>
                    </div>
                </form>

                <div class="card">
                    <div class="card-header d-flex flex-wrap align-items-center gap-2">
                        <h3 class="card-title">Draft hari ini ({{ $drafts->count() }})</h3>
                        <div class="ml-auto d-flex flex-wrap gap-2">
                            <a href="{{ route('od-report.daily.index', ['teks' => 1]) }}#teks" class="btn btn-default btn-sm"><i class="far fa-file-alt"></i> Buat Teks Laporan</a>
                            @if ($drafts->isNotEmpty())
                                <form action="{{ route('od-report.daily.save') }}" method="POST" class="m-0">
                                    @csrf
                                    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save"></i> Simpan Laporan</button>
                                </form>
                            @endif
                        </div>
                    </div>
                    <div class="card-body p-0">
                        @forelse ($drafts as $activity)
                            @include('od-report.daily._row', ['activity' => $activity, 'movable' => true, 'first' => $loop->first, 'last' => $loop->last])
                        @empty
                            <x-empty-state icon="fa-edit" title="Belum ada draft" text="Tambah aktivitas, lalu Simpan Laporan." />
                        @endforelse
                    </div>
                </div>

                @if ($saved->isNotEmpty())
                    <div class="card">
                        <div class="card-header d-flex flex-wrap align-items-center gap-2">
                            <h3 class="card-title">Tersimpan hari ini ({{ $saved->count() }})</h3>
                            <div class="ml-auto d-flex flex-wrap gap-2">
                                @if ($sheetUrl)
                                    <a href="{{ $sheetUrl }}" target="_blank" rel="noopener" class="btn btn-default btn-sm"><i class="fas fa-table"></i> Spreadsheet</a>
                                @endif
                                <form action="{{ route('od-report.daily.reopen') }}" method="POST" class="m-0">
                                    @csrf
                                    <button type="submit" class="btn btn-default btn-sm"><i class="fas fa-undo"></i> Buka Kembali</button>
                                </form>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            @foreach ($saved as $activity)
                                @include('od-report.daily._row', ['activity' => $activity, 'movable' => false])
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($text !== null)
                    <div class="card" id="teks">
                        <div class="card-header d-flex flex-wrap align-items-center gap-2">
                            <h3 class="card-title">Teks laporan</h3>
                            <div class="ml-auto d-flex flex-wrap gap-2">
                                <button type="button" class="btn btn-default btn-sm" data-copy-target="#dailyText"><i class="far fa-copy"></i> Salin</button>
                                <button type="button" class="btn btn-default btn-sm" onclick="window.print()"><i class="fas fa-print"></i> Cetak</button>
                            </div>
                        </div>
                        <div class="card-body">
                            <pre id="dailyText" class="mb-0 printable" style="white-space: pre-wrap; overflow-wrap: anywhere; font-family: inherit;">{{ $text }}</pre>
                        </div>
                    </div>
                @endif
            </div>

            <div class="col-lg-4">
                @if ($teamStatus->isNotEmpty())
                    <div class="card">
                        <div class="card-header"><h3 class="card-title">Status tim hari ini</h3></div>
                        <div class="card-body p-0">
                            @foreach ($teamStatus as $row)
                                <div class="d-flex justify-content-between align-items-center gap-2 px-3 py-2 border-bottom small">
                                    <span class="min-w-0 text-break">{{ $row['user']->nama_lengkap }}</span>
                                    @if ($row['saved'])
                                        <span class="badge badge-success flex-shrink-0">Tersimpan {{ $row['saved'] }}</span>
                                    @elseif ($row['drafts'])
                                        <span class="badge badge-warning flex-shrink-0">Draft {{ $row['drafts'] }}</span>
                                    @else
                                        <span class="badge badge-secondary flex-shrink-0">Belum input</span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="card">
                    <div class="card-header d-flex flex-wrap align-items-center gap-2">
                        <h3 class="card-title">Riwayat</h3>
                        <a href="{{ route('od-report.weekly') }}" class="ml-auto small">Weekly &rarr;</a>
                    </div>
                    <div class="card-body p-0">
                        @forelse ($history as $activity)
                            <div class="px-3 py-2 border-bottom small">
                                <div class="d-flex justify-content-between align-items-start gap-2">
                                    <span class="min-w-0 text-break">{{ $activity->name }}</span>
                                    <span class="badge {{ $activity->badgeClass() }} flex-shrink-0">{{ $activity->result }}</span>
                                </div>
                                <span class="text-muted">{{ $activity->date->format('d/m/Y') }}</span>
                            </div>
                        @empty
                            <x-empty-state icon="fa-history" title="Belum ada riwayat" />
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

@section('footer')
<script>
    document.querySelectorAll('[data-copy-target]').forEach(function (button) {
        button.addEventListener('click', function () {
            const text = document.querySelector(button.dataset.copyTarget).textContent;
            navigator.clipboard.writeText(text).then(function () {
                button.innerHTML = '<i class="fas fa-check"></i> Disalin';
            });
        });
    });
</script>
@endsection
