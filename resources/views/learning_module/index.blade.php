@extends('layout.main_template')

@section('content')
<section class="content-header">
    <div class="container-fluid">
        <x-page-header title="Kelola Modul" subtitle="Pretest → materi video/dokumen → posttest." eyebrow="Modul Belajar / Kelola Modul">
            <x-slot:actions>
                <a href="{{ route('learning-module.create') }}" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Tambah Modul
                </a>
            </x-slot:actions>
        </x-page-header>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        @include('learning_module.partials.alerts')

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Daftar Modul</h3>
                <div class="card-tools">
                    <form action="{{ route('learning-module.index') }}" method="GET" class="m-0" role="search" style="width: 260px; max-width: 100%;">
                        <x-search-field placeholder="Cari modul..." :clear-url="route('learning-module.index')" />
                    </form>
                </div>
            </div>

            <div class="card-body table-responsive p-0" style="min-height: 260px;">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th style="width: 60px;" class="text-center">No</th>
                            <th>Judul Modul</th>
                            <th>Sasaran</th>
                            <th class="text-center">Materi</th>
                            <th class="text-center">Soal</th>
                            <th class="text-center">KKM</th>
                            <th class="text-center" title="Jumlah peserta yang sudah mulai mengerjakan">Sudah Mulai</th>
                            <th class="text-center">Status</th>
                            <th style="width: 140px;" class="text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($modules as $index => $module)
                        <tr>
                            <td class="text-center align-middle text-muted">
                                {{ $modules->firstItem() + $index }}
                            </td>
                            <td class="align-middle">
                                <a href="{{ route('learning-module.show', $module) }}" class="font-weight-bold text-dark d-block">
                                    {{ $module->title }}
                                </a>
                                @if($module->description)
                                    <span class="text-muted small d-block text-truncate" style="max-width: 320px;">
                                        {{ $module->description }}
                                    </span>
                                @endif
                            </td>
                            <td class="align-middle text-muted small" style="max-width: 220px;">
                                {{ $module->targetLabel() }}
                            </td>
                            <td class="text-center align-middle">{{ $module->items_count }}</td>
                            <td class="text-center align-middle">{{ $module->questions_count }}</td>
                            <td class="text-center align-middle">{{ $module->passing_score }}</td>
                            <td class="text-center align-middle">
                                <span class="badge badge-secondary">{{ $module->participants_count }} orang</span>
                            </td>
                            <td class="text-center align-middle">
                                @if($module->is_published)
                                    <span class="badge badge-success">Dipublikasikan</span>
                                @else
                                    <span class="badge badge-secondary">Draft</span>
                                    @php
                                        $missing = array_filter([$module->items_count ? null : 'materi', $module->questions_count ? null : 'soal']);
                                    @endphp
                                    @if($missing)
                                        <span class="d-block text-muted text-xs mt-1">Belum ada {{ implode(' & ', $missing) }}</span>
                                    @endif
                                @endif
                            </td>
                            <td class="text-right align-middle">
                                <div class="d-inline-flex align-items-center gap-1">
                                    @if($module->is_published)
                                        <a href="{{ route('learning-module.show', $module) }}" class="btn btn-default btn-xs">Rekap</a>
                                    @else
                                        <a href="{{ route('learning-module.show', $module) }}" class="btn btn-primary btn-xs">Lanjutkan</a>
                                    @endif
                                    <div class="dropdown">
                                        <button class="btn btn-default btn-xs dropdown-toggle" type="button" data-toggle="dropdown" data-boundary="window" aria-haspopup="true" aria-expanded="false">
                                            Aksi
                                        </button>
                                        <div class="dropdown-menu dropdown-menu-right">
                                            <a class="dropdown-item" href="{{ route('learning-module.edit', $module) }}">Edit Modul & Materi</a>
                                            <a class="dropdown-item" href="{{ route('learning-module.questions.index', $module) }}">Kelola Soal</a>
                                            <form action="{{ route('learning-module.publish', $module) }}" method="POST" class="m-0"
                                                @if($module->is_published) onsubmit="return confirm('Tarik publikasi? Modul tidak lagi tampil untuk peserta.')" @endif>
                                                @csrf
                                                <button type="submit" class="dropdown-item">
                                                    {{ $module->is_published ? 'Tarik Publikasi' : 'Publikasikan' }}
                                                </button>
                                            </form>
                                            <form action="{{ route('learning-module.export', $module) }}" method="POST" class="m-0">
                                                @csrf
                                                <button type="submit" class="dropdown-item">Export Rekap Excel</button>
                                            </form>
                                            <div class="dropdown-divider"></div>
                                            <form action="{{ route('learning-module.destroy', $module) }}" method="POST" class="m-0" onsubmit="return confirm('Hapus modul ini beserta riwayat nilainya dari daftar?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="dropdown-item text-danger">Hapus Modul</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9">
                                <x-empty-state icon="fa-layer-group" title="Belum ada modul belajar" text="Susun pretest, materi video/dokumen, dan posttest dalam satu modul.">
                                    <a href="{{ route('learning-module.create') }}" class="btn btn-primary btn-sm">Tambah Modul Pertama</a>
                                </x-empty-state>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($modules->hasPages())
                <div class="card-footer d-flex justify-content-end">
                    {{ $modules->links() }}
                </div>
            @endif
        </div>
    </div>
</section>
@endsection
