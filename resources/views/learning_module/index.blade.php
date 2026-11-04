@extends('layout.main_template')

@section('content')
<section class="content-header">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h1 class="m-0 text-dark font-weight-bold" style="font-size: 1.5rem;">
                    Kelola Modul Belajar
                </h1>
                <span class="text-muted small">Pretest &rarr; materi video/dokumen &rarr; posttest (bisa diulang)</span>
            </div>
            <a href="{{ route('learning-module.create') }}" class="btn btn-sm btn-primary">
                <i class="fas fa-plus mr-1"></i> Tambah Modul
            </a>
        </div>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        @include('learning_module.partials.alerts')

        <div class="card mb-4">
            <div class="card-header py-3">
                <form action="{{ route('learning-module.index') }}" method="GET" class="d-flex justify-content-end">
                    <div class="input-group" style="width: 300px; max-width: 100%;">
                        <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="Cari judul modul...">
                        <div class="input-group-append">
                            <button type="submit" class="btn btn-default">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="card-body p-0 table-responsive" style="min-height: 260px;">
                <table class="table table-hover mb-0" style="font-size: 0.92rem;">
                    <thead class="bg-light text-muted">
                        <tr>
                            <th style="width: 60px; padding: 14px 16px;" class="text-center">No</th>
                            <th style="padding: 14px 16px;">Judul Modul</th>
                            <th style="padding: 14px 16px;">Sasaran</th>
                            <th style="padding: 14px 16px;" class="text-center">Materi</th>
                            <th style="padding: 14px 16px;" class="text-center">Soal</th>
                            <th style="padding: 14px 16px;" class="text-center">KKM</th>
                            <th style="padding: 14px 16px;" class="text-center">Peserta</th>
                            <th style="padding: 14px 16px;" class="text-center">Status</th>
                            <th style="width: 140px; padding: 14px 16px;" class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($modules as $index => $module)
                        <tr>
                            <td class="text-center align-middle text-muted" style="padding: 16px;">
                                {{ $modules->firstItem() + $index }}
                            </td>
                            <td class="align-middle" style="padding: 16px;">
                                <a href="{{ route('learning-module.show', $module) }}" class="font-weight-bold text-dark d-block mb-1">
                                    {{ $module->title }}
                                </a>
                                @if($module->description)
                                    <span class="text-muted small d-block text-truncate" style="max-width: 320px;">
                                        {{ $module->description }}
                                    </span>
                                @endif
                            </td>
                            <td class="align-middle text-muted small" style="padding: 16px; max-width: 220px;">
                                {{ $module->targetLabel() }}
                            </td>
                            <td class="text-center align-middle" style="padding: 16px;">{{ $module->items_count }}</td>
                            <td class="text-center align-middle" style="padding: 16px;">{{ $module->questions_count }}</td>
                            <td class="text-center align-middle" style="padding: 16px;">{{ $module->passing_score }}</td>
                            <td class="text-center align-middle" style="padding: 16px;">
                                <span class="badge bg-light border px-2 py-1 text-dark">{{ $module->participants_count }} orang</span>
                            </td>
                            <td class="text-center align-middle" style="padding: 16px;">
                                @if($module->is_published)
                                    <span class="badge badge-success px-2 py-1">Dipublikasikan</span>
                                @else
                                    <span class="badge badge-secondary px-2 py-1">Draft</span>
                                @endif
                            </td>
                            <td class="text-center align-middle" style="padding: 14px;">
                                <div class="d-inline-flex align-items-center" style="gap: 4px;">
                                    <a href="{{ route('learning-module.show', $module) }}" class="btn btn-default btn-xs">Rekap</a>
                                    <div class="dropdown">
                                        <button class="btn btn-default btn-xs dropdown-toggle" type="button" data-toggle="dropdown" data-boundary="window" aria-haspopup="true" aria-expanded="false">
                                            Aksi
                                        </button>
                                        <div class="dropdown-menu dropdown-menu-right shadow-sm border text-sm" style="font-size: 0.85rem;">
                                            <a class="dropdown-item py-1" href="{{ route('learning-module.questions.index', $module) }}">Kelola Soal</a>
                                            <a class="dropdown-item py-1" href="{{ route('learning-module.edit', $module) }}">Edit Modul & Materi</a>
                                            <form action="{{ route('learning-module.publish', $module) }}" method="POST" class="m-0">
                                                @csrf
                                                <button type="submit" class="dropdown-item py-1">
                                                    {{ $module->is_published ? 'Tarik Publikasi' : 'Publikasikan' }}
                                                </button>
                                            </form>
                                            <a class="dropdown-item py-1" href="{{ route('learning-module.export', $module) }}">Export Rekap Excel</a>
                                            <div class="dropdown-divider"></div>
                                            <form action="{{ route('learning-module.destroy', $module) }}" method="POST" class="m-0" onsubmit="return confirm('Hapus modul ini beserta riwayat nilainya dari daftar?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="dropdown-item py-1 text-danger">Hapus Modul</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">
                                <p class="mb-3">Belum ada modul belajar.</p>
                                <a href="{{ route('learning-module.create') }}" class="btn btn-sm btn-primary">Tambah Modul Pertama</a>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($modules->hasPages())
                <div class="card-footer bg-white border-top py-3 d-flex justify-content-end">
                    {{ $modules->links() }}
                </div>
            @endif
        </div>
    </div>
</section>
@endsection
