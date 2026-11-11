@extends('layout.main_template')

@php
    $subtitle = $module->isOwned()
        ? 'Kuis '.($module->isOwnedByVideo() ? 'video' : 'dokumen').' · '.(collect([$module->has_pretest ? 'pretest' : null, $module->has_posttest ? 'posttest' : null])->filter()->join(' & ') ?: 'pretest & posttest dimatikan')
        : 'Sasaran: '.$module->targetLabel().' · ±'.$targetCount.' user';
@endphp

@section('content')
<section class="content-header">
    <div class="container-fluid">
        <x-page-header :title="$module->title" :subtitle="$subtitle" :breadcrumbs="$module->isOwned() ? $module->adminBreadcrumbs('Rekap Nilai') : ['Modul Belajar' => null, 'Kelola Modul' => route('learning-module.index'), $module->title => null]">
            <x-slot:actions>
                <a href="{{ $module->managementUrl() }}" class="btn btn-default"><i class="fas fa-arrow-left"></i> Kembali</a>
                <a href="{{ $module->url('questions.index') }}" class="btn btn-default">Kelola Soal ({{ $module->questions_count }})</a>
                @unless($module->isOwned())
                    <a href="{{ route('learning-module.edit', $module) }}" class="btn btn-default">Edit Modul & Materi</a>
                @endunless
                <form action="{{ $module->url('export') }}" method="POST" class="m-0">
                    @csrf
                    <button type="submit" class="btn {{ $module->is_published || $module->isOwned() ? 'btn-primary' : 'btn-default' }}">
                        <i class="fas fa-file-excel"></i> Export Excel
                    </button>
                </form>
                @include('learning_module.partials.publish-button')
            </x-slot:actions>
        </x-page-header>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        @include('learning_module.partials.alerts')
        @unless($module->is_published)
            @include('learning_module.partials.setup-steps', ['page' => 'show'])
        @endunless

        <div class="row">
            <div class="col-md-3 col-6 mb-4">
                <div class="stat-tile">
                    <span class="stat-tile-icon"><i class="fas fa-users"></i></span>
                    <div class="min-w-0">
                        <p class="stat-tile-label">Peserta mulai</p>
                        <p class="stat-tile-value">{{ $participants->count() }}</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6 mb-4">
                <div class="stat-tile">
                    <span class="stat-tile-icon"><i class="fas fa-check"></i></span>
                    <div class="min-w-0">
                        <p class="stat-tile-label">Sudah lulus (KKM {{ $module->passing_score }})</p>
                        <p class="stat-tile-value">{{ $participants->filter->hasPassed()->count() }}</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6 mb-4">
                <div class="stat-tile">
                    <span class="stat-tile-icon"><i class="fas fa-clipboard-list"></i></span>
                    <div class="min-w-0">
                        <p class="stat-tile-label">Rata-rata pretest</p>
                        <p class="stat-tile-value">{{ round($participants->pluck('pretest')->filter()->avg('score') ?? 0, 1) }}</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6 mb-4">
                <div class="stat-tile">
                    <span class="stat-tile-icon"><i class="fas fa-chart-line"></i></span>
                    <div class="min-w-0">
                        <p class="stat-tile-label">Rata-rata posttest terbaik</p>
                        <p class="stat-tile-value">{{ round($participants->map->bestPosttest()->filter()->avg('score') ?? 0, 1) }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Rekap Nilai Peserta</h3>
                <div class="card-tools">
                    @if($module->is_published)
                        <span class="badge badge-success">Dipublikasikan</span>
                    @else
                        <span class="badge badge-secondary">Draft</span>
                    @endif
                </div>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th class="text-center" style="width: 50px;">No</th>
                            <th>Peserta</th>
                            <th class="text-center">Materi</th>
                            <th class="text-center">Pretest</th>
                            <th class="text-center">Jumlah Posttest</th>
                            <th class="text-center">Posttest Terbaik</th>
                            <th class="text-center">Posttest Terakhir</th>
                            <th class="text-center">Kenaikan</th>
                            <th class="text-center">Status</th>
                            <th class="text-right">Aksi</th>
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
                                <strong class="d-block">{{ $p->user->nama_lengkap }}</strong>
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
                                <span class="badge {{ match ($status) {
                                    'Lulus' => 'badge-success',
                                    'Belum Lulus' => 'badge-danger',
                                    'Belum Posttest' => 'badge-primary',
                                    default => 'badge-secondary',
                                } }}">{{ $status }}</span>
                            </td>
                            <td class="text-right align-middle">
                                <a href="{{ $module->url('participant', ['user' => $p->user]) }}" class="btn btn-default btn-xs">Riwayat</a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="10">
                                <x-empty-state icon="fa-user-graduate" title="Belum ada peserta" text="Belum ada peserta yang mengerjakan modul ini." />
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Materi ({{ $module->availableItems()->count() }})</h3>
            </div>
            <ul class="list-group list-group-flush">
                @forelse($module->items as $index => $item)
                    <li class="list-group-item d-flex align-items-center">
                        <span class="text-muted mr-2">{{ $index + 1 }}.</span>
                        <i class="fas {{ $item->isVideo() ? 'fa-play-circle' : 'fa-file-alt' }} text-muted mr-2"></i>
                        @if($item->materialable)
                            {{ $item->title() }}
                        @else
                            <span class="text-muted font-italic">Materi sudah dihapus (tidak lagi diwajibkan)</span>
                        @endif
                    </li>
                @empty
                    <li class="list-group-item p-0">
                        <x-empty-state icon="fa-photo-video" title="Belum ada materi" class="py-4" />
                    </li>
                @endforelse
            </ul>
        </div>
    </div>
</section>
@endsection
