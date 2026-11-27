@extends('layout.main_template')

@section('content')
<section class="content-header">
    <div class="container-fluid">
        <x-page-header title="Jadwal Pelatihan" subtitle="Daftar sesi pelatihan beserta pemateri, jadwal, dan pesertanya." eyebrow="Pelatihan / Jadwal Pelatihan">
            <x-slot:actions>
                <a href="{{ route('training.meeting-accounts.index') }}" class="btn btn-default">Akun Meeting</a>
                <a href="/training/create" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Tambah Pelatihan
                </a>
            </x-slot:actions>
        </x-page-header>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        <x-flash />

        <div class="card">
            <div class="card-header filter-toolbar">
                <div class="btn-group btn-group-sm filter-tabs flex-wrap">
                    <a href="/training" class="btn {{ !request('status') ? 'btn-secondary active' : 'btn-default' }}">Semua ({{ $stats['total'] }})</a>
                    <a href="/training?status=scheduled" class="btn {{ request('status') === 'scheduled' ? 'btn-secondary active' : 'btn-default' }}">Dijadwalkan ({{ $stats['scheduled'] }})</a>
                    <a href="/training?status=ongoing" class="btn {{ request('status') === 'ongoing' ? 'btn-secondary active' : 'btn-default' }}">Berlangsung ({{ $stats['ongoing'] }})</a>
                    <a href="/training?status=completed" class="btn {{ request('status') === 'completed' ? 'btn-secondary active' : 'btn-default' }}">Selesai ({{ $stats['completed'] }})</a>
                </div>
                <form action="/training" method="GET" class="ml-auto" role="search" style="width: 280px; max-width: 100%;">
                    @if(request('status'))
                        <input type="hidden" name="status" value="{{ request('status') }}">
                    @endif
                    <x-search-field placeholder="Cari pelatihan atau pemateri..."
                        :clear-url="url('/training').(request('status') ? '?status='.request('status') : '')" />
                </form>
            </div>

            <div class="card-body table-responsive p-0">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th style="width: 60px;" class="text-center">No</th>
                            <th>Topik Pelatihan</th>
                            <th>Pemateri</th>
                            <th>Jadwal</th>
                            <th>Tautan Sesi</th>
                            <th class="text-center">Peserta</th>
                            <th class="text-center">Status</th>
                            <th style="width: 140px;" class="text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($trainings as $index => $t)
                        <tr>
                            <td class="text-center text-muted">
                                {{ $trainings->firstItem() + $index }}
                            </td>
                            <td>
                                <a href="/training/{{ $t->id }}" class="font-weight-bold text-dark d-block">
                                    {{ $t->title }}
                                </a>
                                @if($t->description)
                                    <span class="text-muted small d-block text-truncate" style="max-width: 320px;">
                                        {{ $t->description }}
                                    </span>
                                @endif
                            </td>
                            <td>
                                <span class="d-block">{{ $t->trainer->nama_lengkap ?? '-' }}</span>
                                <span class="text-muted small">{{ $t->trainer->divisi->name ?? 'Divisi Umum' }}</span>
                            </td>
                            <td>
                                <span class="d-block">{{ \Carbon\Carbon::parse($t->training_date)->format('d M Y') }}</span>
                                <span class="text-muted small">{{ substr($t->start_time, 0, 5) }} - {{ substr($t->end_time, 0, 5) }} WIB</span>
                            </td>
                            <td>
                                @if($t->meeting_link)
                                    <a href="{{ $t->meeting_link }}" target="_blank" rel="noopener" class="btn btn-default btn-xs">
                                        <i class="fas fa-video" aria-hidden="true"></i> Buka {{ $t->meetingPlatformLabel() }}
                                    </a>
                                @else
                                    <span class="text-muted small">-</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <span class="badge badge-secondary">
                                    {{ $t->participants_count }} orang
                                </span>
                            </td>
                            <td class="text-center">
                                <x-training-status-badge :status="$t->status" />
                            </td>
                            <td class="text-right">
                                <div class="d-inline-flex align-items-center gap-1">
                                    <a href="/training/{{ $t->id }}" class="btn btn-default btn-xs">
                                        Detail
                                    </a>
                                    <div class="dropdown">
                                        <button class="btn btn-default btn-xs dropdown-toggle" type="button" data-toggle="dropdown" data-boundary="window" aria-haspopup="true" aria-expanded="false">
                                            Aksi
                                        </button>
                                        <div class="dropdown-menu dropdown-menu-right">
                                            <a class="dropdown-item" href="/training/{{ $t->id }}/questions">
                                                Kelola Soal Kuis
                                            </a>
                                            <a class="dropdown-item btn-upload-video-shortcut" href="javascript:void(0)" data-toggle="modal" data-target="#uploadTrainingVideoModal" data-id="{{ $t->id }}" data-title="{{ $t->title }}" data-date="{{ \Carbon\Carbon::parse($t->training_date)->format('d F Y') }}">
                                                Upload Video Materi
                                            </a>
                                            <a class="dropdown-item" href="/training/{{ $t->id }}/edit">
                                                Edit Pelatihan
                                            </a>
                                            <div class="dropdown-divider"></div>
                                            <form action="{{ route('training.destroy', $t) }}" method="POST" class="m-0" onsubmit="return confirm('Hapus jadwal pelatihan ini?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="dropdown-item text-danger">Hapus Pelatihan</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8">
                                <x-empty-state icon="fa-chalkboard-teacher" title="Belum ada jadwal pelatihan" text="Buat sesi pelatihan pertama untuk mulai mengundang peserta.">
                                    <a href="/training/create" class="btn btn-primary">
                                        <i class="fas fa-plus"></i> Tambah Pelatihan
                                    </a>
                                </x-empty-state>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($trainings->hasPages())
                <div class="card-footer d-flex justify-content-end">
                    {{ $trainings->withQueryString()->links('pagination::bootstrap-4') }}
                </div>
            @endif
        </div>
    </div>
</section>

{{-- MODAL UPLOAD VIDEO MATERI PELATIHAN (INDEX SHORTCUT) --}}
<div class="modal fade" id="uploadTrainingVideoModal" tabindex="-1" role="dialog" aria-labelledby="uploadTrainingVideoModalLabel" aria-hidden="true" data-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <div class="min-w-0 pr-2">
                    <h5 class="modal-title" id="uploadTrainingVideoModalLabel">Upload Video Materi Pelatihan</h5>
                    <small class="text-muted">Video akan otomatis masuk ke Galeri Video Materi dengan judul pelatihan ini.</small>
                </div>
                <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">&times;</button>
            </div>
            <form action="{{ route('video.store') }}" method="POST" enctype="multipart/form-data" id="uploadTrainingVideoIndexForm">
                @csrf
                <input type="hidden" name="training_id" id="indexModalTrainingId" value="">
                <input type="hidden" name="redirect_to" value="{{ request()->fullUrl() }}">

                <div class="modal-body">
                    {{-- Judul Video Otomatis --}}
                    <div class="form-group">
                        <label for="indexModalVideoTitle">Judul Video Materi <span class="text-danger">*</span></label>
                        <input type="text" name="title" id="indexModalVideoTitle" class="form-control" required placeholder="Judul video...">
                        <small class="text-muted">Terisi otomatis sesuai judul sesi pelatihan yang dipilih.</small>
                    </div>

                    {{-- Pilihan Tipe Video: Upload File vs Tautan Link --}}
                    <div class="form-group">
                        <label class="d-block">Sumber Video <span class="text-danger">*</span></label>
                        <div class="d-flex flex-wrap gap-3">
                            <div class="custom-control custom-radio">
                                <input type="radio" id="indexTypeFile" name="video_type" value="file" class="custom-control-input" checked onchange="toggleVideoTypeInIndex('file')">
                                <label class="custom-control-label" for="indexTypeFile">Upload file video</label>
                            </div>
                            <div class="custom-control custom-radio">
                                <input type="radio" id="indexTypeLink" name="video_type" value="link" class="custom-control-input" onchange="toggleVideoTypeInIndex('link')">
                                <label class="custom-control-label" for="indexTypeLink">Tautan / link (YouTube, Drive, Zoom)</label>
                            </div>
                        </div>
                    </div>

                    {{-- Group 1: File Input --}}
                    <div class="form-group" id="indexFileInputGroup">
                        <label for="indexVideoFileInput">File Video Rekaman <span class="text-danger">*</span></label>
                        <x-file-dropzone name="video_file" id="indexVideoFileInput" :extensions="['mp4', 'webm', 'ogg', 'mov', 'mkv']"
                            :max-mb="2048" types="MP4, WebM, OGG, MOV, atau MKV" compact required />
                    </div>

                    {{-- Group 2: Link Input --}}
                    <div class="form-group" id="indexLinkInputGroup" style="display: none;">
                        <label for="indexVideoLinkInput">URL / Tautan Video <span class="text-danger">*</span></label>
                        <input type="url" name="video_link" id="indexVideoLinkInput" class="form-control" placeholder="https://www.youtube.com/watch?v=... atau https://drive.google.com/file/d/...">
                        <small class="text-muted">Mendukung link YouTube, Zoom Cloud Recording, atau Google Drive.</small>
                    </div>

                    {{-- Deskripsi Otomatis --}}
                    <div class="form-group">
                        <label for="indexModalVideoDesc">Deskripsi & Catatan Sesi <span class="text-muted">(opsional)</span></label>
                        <textarea name="description" id="indexModalVideoDesc" class="form-control" rows="3" placeholder="Rangkuman materi atau topik yang dibahas..."></textarea>
                    </div>

                    {{-- Thumbnail Cover (Opsional) --}}
                    <div class="form-group mb-0">
                        <label for="indexThumbInput">Cover / Thumbnail Video <span class="text-muted">(opsional)</span></label>
                        <x-file-dropzone name="thumbnail" id="indexThumbInput" :extensions="['jpg', 'jpeg', 'png', 'webp']"
                            :max-mb="5" types="JPG, PNG, atau WebP" compact />
                        <small class="text-muted">Biarkan kosong jika ingin menggunakan cover video bawaan sistem.</small>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary" id="btnIndexSubmitVideo">
                        <i class="fas fa-cloud-upload-alt"></i> Simpan & Publikasikan
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

document.addEventListener('DOMContentLoaded', function() {
    // When clicking shortcut upload video button in table
    $('.btn-upload-video-shortcut').on('click', function() {
        var id = $(this).attr('data-id') || '';
        var title = $(this).attr('data-title') || '';
        var date = $(this).attr('data-date') || '';

        $('#indexModalTrainingId').val(id);
        $('#indexModalVideoTitle').val(title);
        $('#indexModalVideoDesc').val('Rekaman video materi sesi pelatihan ' + title + ' yang dilaksanakan pada ' + date + '.');
    });

    var form = document.getElementById('uploadTrainingVideoIndexForm');
    if (form) {
        var isSubmitting = false;
        var btn = document.getElementById('btnIndexSubmitVideo');
        var defaultBtnHtml = btn ? btn.innerHTML : '';

        function resetUploadSubmitState() {
            isSubmitting = false;
            form.style.pointerEvents = '';
            if (btn) {
                btn.disabled = false;
                btn.style.pointerEvents = '';
                btn.innerHTML = defaultBtnHtml;
            }
        }

        window.addEventListener('pageshow', resetUploadSubmitState);
        if (typeof $ !== 'undefined') {
            $('#uploadTrainingVideoModal').on('show.bs.modal', resetUploadSubmitState);
        }

        form.addEventListener('submit', function(e) {
            if (!form.checkValidity()) {
                return;
            }
            if (isSubmitting) {
                e.preventDefault();
                return false;
            }
            isSubmitting = true;
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
