@extends('layout.main_template')

@section('content')
<section class="content-header">
    <div class="container-fluid">
        <div class="mb-3 d-flex flex-wrap justify-content-between align-items-center" style="gap: 12px;">
            <div class="d-flex align-items-center">
                <a href="{{ route('learning-module.index') }}" class="btn btn-default btn-sm mr-3">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div>
                    <h1 class="m-0 text-dark font-weight-bold" style="font-size: 1.5rem;">{{ $module->title }}</h1>
                    <span class="text-muted small">{{ $module->targetLabel() }}</span>
                </div>
            </div>
            <div class="d-flex flex-wrap" style="gap: 8px;">
                <a href="{{ route('learning-module.questions.index', $module) }}" class="btn btn-default btn-sm">Kelola Soal ({{ $module->questions_count }})</a>
                <a href="{{ route('learning-module.edit', $module) }}" class="btn btn-default btn-sm">Edit Modul & Materi</a>
                <a href="{{ route('learning-module.export', $module) }}" class="btn btn-success btn-sm">
                    <i class="fas fa-file-excel mr-1"></i> Export Excel
                </a>
            </div>
        </div>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        @include('learning_module.partials.alerts')

        <div class="row">
            <div class="col-md-3 col-6 mb-3">
                <div class="card h-100 mb-0"><div class="card-body py-3">
                    <span class="text-muted small d-block">Peserta mulai</span>
                    <strong style="font-size: 1.4rem;">{{ $participants->count() }}</strong>
                </div></div>
            </div>
            <div class="col-md-3 col-6 mb-3">
                <div class="card h-100 mb-0"><div class="card-body py-3">
                    <span class="text-muted small d-block">Sudah lulus (KKM {{ $module->passing_score }})</span>
                    <strong style="font-size: 1.4rem;">{{ $participants->filter->hasPassed()->count() }}</strong>
                </div></div>
            </div>
            <div class="col-md-3 col-6 mb-3">
                <div class="card h-100 mb-0"><div class="card-body py-3">
                    <span class="text-muted small d-block">Rata-rata pretest</span>
                    <strong style="font-size: 1.4rem;">{{ round($participants->pluck('pretest')->filter()->avg('score') ?? 0, 1) }}</strong>
                </div></div>
            </div>
            <div class="col-md-3 col-6 mb-3">
                <div class="card h-100 mb-0"><div class="card-body py-3">
                    <span class="text-muted small d-block">Rata-rata posttest terbaik</span>
                    <strong style="font-size: 1.4rem;">{{ round($participants->map->bestPosttest()->filter()->avg('score') ?? 0, 1) }}</strong>
                </div></div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h3 class="card-title font-weight-bold m-0">Rekap Nilai Peserta</h3>
                @if($module->is_published)
                    <span class="badge badge-success px-2 py-1">Dipublikasikan</span>
                @else
                    <span class="badge badge-secondary px-2 py-1">Draft</span>
                @endif
            </div>
            <div class="card-body p-0 table-responsive">
                <table class="table table-hover mb-0" style="font-size: 0.9rem;">
                    <thead class="bg-light text-muted">
                        <tr>
                            <th class="text-center" style="width: 50px; padding: 12px;">No</th>
                            <th style="padding: 12px;">Peserta</th>
                            <th class="text-center" style="padding: 12px;">Materi</th>
                            <th class="text-center" style="padding: 12px;">Pretest</th>
                            <th class="text-center" style="padding: 12px;">Jumlah Posttest</th>
                            <th class="text-center" style="padding: 12px;">Posttest Terbaik</th>
                            <th class="text-center" style="padding: 12px;">Posttest Terakhir</th>
                            <th class="text-center" style="padding: 12px;">Kenaikan</th>
                            <th class="text-center" style="padding: 12px;">Status</th>
                            <th class="text-center" style="padding: 12px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($participants->values() as $index => $p)
                        @php
                            $improvement = $p->improvement();
                            $status = $p->statusLabel();
                        @endphp
                        <tr>
                            <td class="text-center align-middle text-muted">{{ $index + 1 }}</td>
                            <td class="align-middle">
                                <strong class="d-block text-dark">{{ $p->user->nama_lengkap }}</strong>
                                <span class="text-muted small">{{ $p->user->divisi->name ?? '-' }} &middot; {{ $p->user->jabatan->name ?? '-' }}</span>
                            </td>
                            <td class="text-center align-middle">{{ $p->completedItemCount() }}/{{ $p->totalItemCount() }}</td>
                            <td class="text-center align-middle">{{ $p->pretest?->score ?? '-' }}</td>
                            <td class="text-center align-middle">{{ $p->posttests->count() }}x</td>
                            <td class="text-center align-middle font-weight-bold">{{ $p->bestPosttest()?->score ?? '-' }}</td>
                            <td class="text-center align-middle">{{ $p->latestPosttest()?->score ?? '-' }}</td>
                            <td class="text-center align-middle">
                                @if($improvement === null)
                                    <span class="text-muted">-</span>
                                @else
                                    <span class="{{ $improvement > 0 ? 'text-success' : ($improvement < 0 ? 'text-danger' : 'text-muted') }} font-weight-bold">
                                        {{ $improvement > 0 ? '+' : '' }}{{ $improvement }}
                                    </span>
                                @endif
                            </td>
                            <td class="text-center align-middle">
                                <span class="badge px-2 py-1 {{ match ($status) {
                                    'Lulus' => 'badge-success',
                                    'Belum Lulus' => 'badge-danger',
                                    'Belum Posttest' => 'badge-info',
                                    default => 'badge-secondary',
                                } }}">{{ $status }}</span>
                            </td>
                            <td class="text-center align-middle">
                                <a href="{{ route('learning-module.participant', [$module, $p->user]) }}" class="btn btn-default btn-xs">Riwayat</a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="10" class="text-center py-5 text-muted">Belum ada peserta yang mengerjakan modul ini.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header py-3">
                <h3 class="card-title font-weight-bold m-0">Materi ({{ $module->availableItems()->count() }})</h3>
            </div>
            <ul class="list-group list-group-flush">
                @forelse($module->items as $index => $item)
                    <li class="list-group-item d-flex align-items-center">
                        <span class="text-muted mr-2">{{ $index + 1 }}.</span>
                        <i class="fas {{ $item->isVideo() ? 'fa-play-circle text-danger' : 'fa-file-alt text-warning' }} mr-2"></i>
                        @if($item->materialable)
                            {{ $item->title() }}
                        @else
                            <span class="text-muted font-italic">Materi sudah dihapus (tidak lagi diwajibkan)</span>
                        @endif
                    </li>
                @empty
                    <li class="list-group-item text-muted">Belum ada materi.</li>
                @endforelse
            </ul>
        </div>
    </div>
</section>
@endsection
