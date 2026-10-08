@extends('layout.main_template')

@section('content')
<section class="content-header">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="m-0 text-dark font-weight-bold" style="font-size: 1.5rem;">
                Jadwal Pelatihan
            </h1>
            <a href="/training/create" class="btn btn-sm btn-success shadow-sm">
                <i class="fas fa-plus mr-1"></i> Tambah Pelatihan
            </a>
        </div>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
                {{ session('success') }}
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
                {{ session('error') }}
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        @endif

        <!-- MAIN CARD -->
        <div class="card mb-4">
            <div class="card-header py-3">
                <div class="row align-items-center">
                    <div class="col-md-7 mb-2 mb-md-0">
                        <div class="btn-group btn-group-sm">
                            <a href="/training" class="btn {{ !request('status') ? 'btn-secondary active' : 'btn-default' }}">Semua ({{ $stats['total'] }})</a>
                            <a href="/training?status=scheduled" class="btn {{ request('status') === 'scheduled' ? 'btn-secondary active' : 'btn-default' }}">Dijadwalkan ({{ $stats['scheduled'] }})</a>
                            <a href="/training?status=ongoing" class="btn {{ request('status') === 'ongoing' ? 'btn-secondary active' : 'btn-default' }}">Berlangsung ({{ $stats['ongoing'] }})</a>
                            <a href="/training?status=completed" class="btn {{ request('status') === 'completed' ? 'btn-secondary active' : 'btn-default' }}">Selesai ({{ $stats['completed'] }})</a>
                        </div>
                    </div>
                    <div class="col-md-5">
                        <form action="/training" method="GET" class="d-flex justify-content-md-end">
                            @if(request('status'))
                                <input type="hidden" name="status" value="{{ request('status') }}">
                            @endif
                            <div class="input-group input-group-sm" style="max-width: 280px;">
                                <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="Cari topik atau pemateri...">
                                <div class="input-group-append">
                                    <button type="submit" class="btn btn-default">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="card-body p-0 table-responsive" style="min-height: 260px;">
                <table class="table table-hover mb-0" style="font-size: 0.92rem;">
                    <thead class="bg-light text-muted">
                        <tr>
                            <th style="width: 60px; padding: 14px 16px;" class="text-center">No</th>
                            <th style="padding: 14px 16px;">Topik Pelatihan</th>
                            <th style="padding: 14px 16px;">Pemateri</th>
                            <th style="padding: 14px 16px;">Jadwal</th>
                            <th style="padding: 14px 16px;">Tautan Sesi</th>
                            <th style="padding: 14px 16px;" class="text-center">Peserta</th>
                            <th style="padding: 14px 16px;" class="text-center">Status</th>
                            <th style="width: 140px; padding: 14px 16px;" class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($trainings as $index => $t)
                        <tr>
                            <td class="text-center align-middle text-muted" style="padding: 16px;">
                                {{ $trainings->firstItem() + $index }}
                            </td>
                            <td class="align-middle" style="padding: 16px;">
                                <a href="/training/{{ $t->id }}" class="font-weight-bold text-dark d-block mb-1">
                                    {{ $t->title }}
                                </a>
                                @if($t->description)
                                    <span class="text-muted small d-block text-truncate" style="max-width: 320px;">
                                        {{ $t->description }}
                                    </span>
                                @endif
                            </td>
                            <td class="align-middle" style="padding: 16px;">
                                <span class="font-weight-600 text-dark d-block">{{ $t->trainer->full_name ?? '-' }}</span>
                                <span class="text-muted small">{{ $t->trainer->divisi->name ?? 'Divisi Umum' }}</span>
                            </td>
                            <td class="align-middle" style="padding: 16px;">
                                <span class="d-block">{{ \Carbon\Carbon::parse($t->training_date)->format('d M Y') }}</span>
                                <span class="text-muted small">{{ substr($t->start_time, 0, 5) }} - {{ substr($t->end_time, 0, 5) }} WIB</span>
                            </td>
                            <td class="align-middle" style="padding: 16px;">
                                @if($t->zoom_link)
                                    <a href="{{ $t->zoom_link }}" target="_blank" class="btn btn-xs btn-outline-primary px-2 py-1">
                                        Buka Zoom
                                    </a>
                                @else
                                    <span class="text-muted small">-</span>
                                @endif
                            </td>
                            <td class="text-center align-middle" style="padding: 16px;">
                                <span class="badge bg-light border px-2 py-1 text-dark">
                                    {{ $t->participants->count() }} orang
                                </span>
                            </td>
                            <td class="text-center align-middle" style="padding: 16px;">
                                @if($t->status === 'scheduled')
                                    <span class="badge badge-warning text-white px-2 py-1">Dijadwalkan</span>
                                @elseif($t->status === 'ongoing')
                                    <span class="badge badge-success px-2 py-1">Berlangsung</span>
                                @elseif($t->status === 'completed')
                                    <span class="badge badge-secondary px-2 py-1">Selesai</span>
                                @else
                                    <span class="badge badge-danger px-2 py-1">Dibatalkan</span>
                                @endif
                            </td>
                            <td class="text-center align-middle" style="padding: 14px;">
                                <div class="d-inline-flex align-items-center" style="gap: 4px;">
                                    <a href="/training/{{ $t->id }}" class="btn btn-default btn-xs">
                                        Detail
                                    </a>
                                    <div class="dropdown">
                                        <button class="btn btn-default btn-xs dropdown-toggle" type="button" data-toggle="dropdown" data-boundary="window" aria-haspopup="true" aria-expanded="false">
                                            Aksi
                                        </button>
                                        <div class="dropdown-menu dropdown-menu-right shadow-sm border text-sm" style="font-size: 0.85rem;">
                                            <a class="dropdown-item py-1" href="/training/{{ $t->id }}/questions">
                                                Kelola Soal Kuis
                                            </a>
                                            <a class="dropdown-item py-1 btn-upload-video-shortcut" href="javascript:void(0)" data-toggle="modal" data-target="#uploadTrainingVideoModal" data-id="{{ $t->id }}" data-title="{{ addslashes($t->title) }}" data-date="{{ \Carbon\Carbon::parse($t->training_date)->format('d F Y') }}">
                                                Upload Video Materi
                                            </a>
                                            <a class="dropdown-item py-1" href="/training/{{ $t->id }}/edit">
                                                Edit Pelatihan
                                            </a>
                                            <div class="dropdown-divider my-1"></div>
                                            <a class="dropdown-item py-1 text-danger" href="/training/delete/{{ $t->id }}" onclick="return confirm('Hapus jadwal pelatihan ini?')">
                                                Hapus Pelatihan
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <p class="mb-2">Belum ada data jadwal pelatihan yang tersedia.</p>
                                <a href="/training/create" class="btn btn-sm btn-primary">
                                    Tambah Pelatihan Baru
                                </a>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($trainings->hasPages())
                <div class="card-footer bg-white border-top py-3 d-flex justify-content-end">
                    {{ $trainings->withQueryString()->links() }}
                </div>
            @endif
        </div>
    </div>
</section>

{{-- MODAL UPLOAD VIDEO MATERI PELATIHAN (INDEX SHORTCUT) --}}
<div class="modal fade" id="uploadTrainingVideoModal" tabindex="-1" role="dialog" aria-labelledby="uploadTrainingVideoModalLabel" aria-hidden="true" data-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content shadow border-0" style="border-radius: 14px;">
            <div class="modal-header bg-white border-bottom py-3">
                <div class="d-flex align-items-center">
                    <div class="rounded-circle d-flex align-items-center justify-content-center bg-danger text-white mr-2" style="width: 38px; height: 38px;">
                        <i class="fas fa-video"></i>
                    </div>
                    <div>
                        <h5 class="modal-title font-weight-bold text-dark mb-0" id="uploadTrainingVideoModalLabel">Upload Video Materi Pelatihan</h5>
                        <small class="text-muted">Video akan otomatis masuk ke Galeri Video Materi dengan judul pelatihan ini.</small>
                    </div>
                </div>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="{{ route('video.store') }}" method="POST" enctype="multipart/form-data" id="uploadTrainingVideoIndexForm">
                @csrf
                <input type="hidden" name="training_id" id="indexModalTrainingId" value="">
                <input type="hidden" name="redirect_to" value="{{ request()->fullUrl() }}">

                <div class="modal-body p-4">
                    {{-- Judul Video Otomatis --}}
                    <div class="form-group mb-3">
                        <label class="font-weight-bold text-dark">
                            Judul Video Materi <span class="text-danger">*</span>
                        </label>
                        <input type="text" name="title" id="indexModalVideoTitle" class="form-control" required placeholder="Judul video...">
                        <small class="text-muted">
                            <i class="fas fa-magic text-primary mr-1"></i> Terisi otomatis sesuai judul sesi pelatihan yang dipilih.
                        </small>
                    </div>

                    {{-- Pilihan Tipe Video: Upload File vs Tautan Link --}}
                    <div class="form-group mb-3">
                        <label class="font-weight-bold text-dark d-block">Sumber Video <span class="text-danger">*</span></label>
                        <div class="d-flex p-2 bg-light rounded border" style="gap: 20px;">
                            <div class="custom-control custom-radio">
                                <input type="radio" id="indexTypeFile" name="video_type" value="file" class="custom-control-input" checked onchange="toggleVideoTypeInIndex('file')">
                                <label class="custom-control-label font-weight-bold" for="indexTypeFile" style="cursor: pointer;">
                                    <i class="fas fa-file-video text-danger mr-1"></i> Upload File Video
                                </label>
                            </div>
                            <div class="custom-control custom-radio">
                                <input type="radio" id="indexTypeLink" name="video_type" value="link" class="custom-control-input" onchange="toggleVideoTypeInIndex('link')">
                                <label class="custom-control-label font-weight-bold" for="indexTypeLink" style="cursor: pointer;">
                                    <i class="fab fa-youtube text-danger mr-1"></i> Tautan / Link (YouTube / Drive / Zoom)
                                </label>
                            </div>
                        </div>
                    </div>

                    {{-- Group 1: File Input --}}
                    <div class="form-group mb-3" id="indexFileInputGroup">
                        <label class="font-weight-bold text-dark">Pilih File Video Rekaman <span class="text-danger">*</span></label>
                        <div class="custom-file">
                            <input type="file" name="video_file" id="indexVideoFileInput" class="custom-file-input" accept="video/mp4,video/webm,video/ogg,video/quicktime,video/x-matroska" required onchange="updateIndexFileName(this)">
                            <label class="custom-file-label" id="indexVideoFileLabel" for="indexVideoFileInput">Pilih file video (MP4, MKV, WEBM, MOV)...</label>
                        </div>
                        <small class="text-muted">Maksimal ukuran file video: 2 GB.</small>
                    </div>

                    {{-- Group 2: Link Input --}}
                    <div class="form-group mb-3" id="indexLinkInputGroup" style="display: none;">
                        <label class="font-weight-bold text-dark">URL / Tautan Video <span class="text-danger">*</span></label>
                        <input type="url" name="video_link" id="indexVideoLinkInput" class="form-control" placeholder="https://www.youtube.com/watch?v=... atau https://drive.google.com/file/d/...">
                        <small class="text-muted">Mendukung link YouTube, Zoom Cloud Recording, atau Google Drive.</small>
                    </div>

                    {{-- Deskripsi Otomatis --}}
                    <div class="form-group mb-3">
                        <label class="font-weight-bold text-dark">Deskripsi & Catatan Sesi (Opsional)</label>
                        <textarea name="description" id="indexModalVideoDesc" class="form-control" rows="3" placeholder="Rangkuman materi atau topik yang dibahas..."></textarea>
                    </div>

                    {{-- Thumbnail Cover (Opsional) --}}
                    <div class="form-group mb-0">
                        <label class="font-weight-bold text-dark">Cover / Thumbnail Video (Opsional)</label>
                        <div class="custom-file">
                            <input type="file" name="thumbnail" id="indexThumbInput" class="custom-file-input" accept="image/png,image/jpeg,image/webp" onchange="updateIndexThumbLabel(this)">
                            <label class="custom-file-label" id="indexThumbLabel" for="indexThumbInput">Pilih gambar thumbnail (JPG, PNG, WEBP)...</label>
                        </div>
                        <small class="text-muted">Biarkan kosong jika ingin menggunakan cover video bawaan sistem.</small>
                    </div>
                </div>

                <div class="modal-footer bg-light py-3 border-top d-flex justify-content-between">
                    <button type="button" class="btn btn-secondary px-3" data-dismiss="modal">
                        Batal
                    </button>
                    <button type="submit" class="btn btn-danger font-weight-bold px-4 shadow-sm" id="btnIndexSubmitVideo">
                        <i class="fas fa-cloud-upload-alt mr-1"></i> Simpan & Publikasikan ke Video Materi
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function toggleVideoTypeInIndex(type) {
    var fileGroup = document.getElementById('indexFileInputGroup');
    var linkGroup = document.getElementById('indexLinkInputGroup');
    var fileInput = document.getElementById('indexVideoFileInput');
    var linkInput = document.getElementById('indexVideoLinkInput');

    if (type === 'file') {
        fileGroup.style.display = 'block';
        linkGroup.style.display = 'none';
        fileInput.setAttribute('required', 'required');
        linkInput.removeAttribute('required');
    } else {
        fileGroup.style.display = 'none';
        linkGroup.style.display = 'block';
        fileInput.removeAttribute('required');
        linkInput.setAttribute('required', 'required');
    }
}

function updateIndexFileName(input) {
    if (input.files && input.files[0]) {
        document.getElementById('indexVideoFileLabel').innerText = input.files[0].name;
    }
}

function updateIndexThumbLabel(input) {
    if (input.files && input.files[0]) {
        document.getElementById('indexThumbLabel').innerText = input.files[0].name;
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // When clicking shortcut upload video button in table
    $('.btn-upload-video-shortcut').on('click', function() {
        var id = $(this).data('id');
        var title = $(this).data('title');
        var date = $(this).data('date');

        $('#indexModalTrainingId').val(id);
        $('#indexModalVideoTitle').val(title);
        $('#indexModalVideoDesc').val('Rekaman video materi sesi pelatihan ' + title + ' yang dilaksanakan pada ' + date + '.');
    });

    var form = document.getElementById('uploadTrainingVideoIndexForm');
    if (form) {
        var isSubmitting = false;
        form.addEventListener('submit', function(e) {
            if (isSubmitting) {
                e.preventDefault();
                return false;
            }
            isSubmitting = true;
            var btn = document.getElementById('btnIndexSubmitVideo');
            if (btn) {
                btn.style.pointerEvents = 'none';
                btn.innerHTML = '<span class="spinner-border spinner-border-sm mr-1" role="status" aria-hidden="true"></span> Mengunggah Video, mohon tunggu...';
                setTimeout(function() {
                    btn.disabled = true;
                }, 50);
            }
            form.style.pointerEvents = 'none';
        });
    }
});
</script>
@endsection
