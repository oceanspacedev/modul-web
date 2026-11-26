@extends('layout.main_template')

@section('content')
<section class="content-header">
    <div class="container-fluid">
        <x-page-header title="Buat Dokumen" subtitle="Pilih jenis dokumen yang mau disusun"
            :breadcrumbs="['Dokumen Builder' => route('document-builder.index'), 'Buat' => null]" />
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        <x-flash />

        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-inbox mr-1 text-muted"></i> Dari Permintaan Dokumen</h3>
                <span class="card-tools small text-muted">Data pengajuan langsung terisi di form</span>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-hover mb-0">
                    <tbody>
                        @forelse ($processes as $process)
                            <tr>
                                <td style="white-space: normal; min-width: 220px;">
                                    <strong>{{ $process->title }}</strong>
                                    <div class="small text-muted">
                                        <span class="badge badge-info">{{ $process->documentRequest->code }}</span>
                                        {{ $process->documentRequest->nama }} · {{ $process->documentRequest->departemen }}
                                    </div>
                                </td>
                                <td class="align-middle"><span class="badge badge-light border">{{ $process->jenis }}</span></td>
                                <td class="align-middle small">{{ $process->prioritas ?: '-' }}</td>
                                <td class="align-middle text-right text-nowrap">
                                    @if ($process->builderDraft && $process->builderDraft->canBeManagedBy(auth()->user()))
                                        <a href="{{ route('document-builder.edit', $process->builderDraft) }}" class="btn btn-default btn-sm"><i class="fas fa-file-alt"></i> Lihat draf</a>
                                    @else
                                        <a href="{{ route('document-builder.form', ['category' => $process->category, 'permintaan' => $process->id]) }}" class="btn btn-primary btn-sm"><i class="fas fa-magic"></i> Susun</a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td class="text-muted small px-3">Belum ada permintaan yang sedang berjalan untuk Anda.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <h2 class="h6 font-weight-bold text-muted mb-2 mt-4">Atau mulai dari kosong</h2>
        <div class="row">
            @forelse ($categories as $category)
                <div class="col-sm-6 col-lg-4 d-flex">
                    <a href="{{ route('document-builder.form', $category) }}" class="card card-outline card-primary flex-fill text-reset builder-category">
                        <div class="card-body">
                            <div class="mb-2">
                                <span class="badge badge-primary mr-1">{{ $category->code }}</span>
                                <strong>{{ $category->name }}</strong>
                            </div>
                            @if ($category->description)
                                <p class="small text-muted mb-2">{{ $category->description }}</p>
                            @endif
                            <span class="small {{ App\Support\DocumentBuilderTemplates::hasSpecific($category) ? 'text-success' : 'text-muted' }}">
                                <i class="fas {{ App\Support\DocumentBuilderTemplates::hasSpecific($category) ? 'fa-check-circle' : 'fa-file-alt' }}"></i>
                                {{ App\Support\DocumentBuilderTemplates::hasSpecific($category) ? 'Template khusus' : 'Template umum' }}
                            </span>
                        </div>
                    </a>
                </div>
            @empty
                <div class="col-12">
                    <x-empty-state icon="fa-tags" title="Belum ada kategori aktif" text="Tambahkan kategori di menu Kategori Dokumen." />
                </div>
            @endforelse
        </div>
    </div>
</section>

<style>
    .builder-category { transition: box-shadow .15s, transform .15s; }
    .builder-category:hover { box-shadow: 0 4px 14px rgba(0, 0, 0, .12); transform: translateY(-1px); text-decoration: none; }
    [data-theme="dark"] .builder-category:hover { box-shadow: 0 4px 14px rgba(0, 0, 0, .55); }
</style>
@endsection
