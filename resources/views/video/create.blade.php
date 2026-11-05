@extends('layout.main_template')

@section('content')
<section class="content-header">
    <div class="container-fluid">
        <x-page-header title="Tambah Video" subtitle="Unggah file video atau tautkan video dari YouTube, Google Drive, atau URL lain." eyebrow="Konten / Video">
            <x-slot:actions>
                <a href="{{ route('video.index') }}" class="btn btn-default">
                    <i class="fas fa-arrow-left"></i> Kembali ke Galeri
                </a>
            </x-slot:actions>
        </x-page-header>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        <x-flash />

        <div class="row">
            <div class="col-xl-9">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Form Materi Video</h3>
                    </div>

                    <form id="uploadVideoForm" action="{{ route('video.store') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="card-body">
                            {{-- Judul Video --}}
                            <div class="form-group">
                                <label for="title">Judul Video <span class="text-danger">*</span></label>
                                <input type="text" name="title" id="title" class="form-control @error('title') is-invalid @enderror"
                                    placeholder="Contoh: Tutorial Penggunaan Modul Part 1" value="{{ old('title') }}" required autofocus>
                                @error('title')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                            </div>

                            {{-- Deskripsi Video --}}
                            <div class="form-group">
                                <label for="description">Deskripsi / Keterangan Video</label>
                                <textarea name="description" id="description" rows="3" class="form-control @error('description') is-invalid @enderror"
                                    placeholder="Tuliskan ringkasan materi atau poin-poin yang dipelajari dalam video ini...">{{ old('description') }}</textarea>
                                @error('description')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                            </div>

                            {{-- Pilihan Sumber Video --}}
                            <div class="form-group">
                                <label class="d-block">Sumber Video <span class="text-danger">*</span></label>
                                <div class="btn-group btn-group-toggle w-100" data-toggle="buttons">
                                    <label class="btn btn-default active w-50 py-2" id="labelOptionFile" onclick="switchVideoType('file')">
                                        <input type="radio" name="video_type" id="option_file" value="file" autocomplete="off" checked>
                                        <i class="fas fa-file-upload mr-1"></i> <strong>Upload File Video</strong>
                                        <div class="small text-muted">Dari Komputer/Laptop</div>
                                    </label>
                                    <label class="btn btn-default w-50 py-2" id="labelOptionLink" onclick="switchVideoType('link')">
                                        <input type="radio" name="video_type" id="option_link" value="link" autocomplete="off">
                                        <i class="fas fa-link mr-1"></i> <strong>Gunakan Link Video</strong>
                                        <div class="small text-muted">YouTube / Google Drive / URL</div>
                                    </label>
                                </div>
                            </div>

                            {{-- SECTION 1: UPLOAD FILE LOKAL --}}
                            <div id="sectionFile" class="p-3 mb-3 bg-light rounded border">
                                <label for="video_file">
                                    Pilih File Video <span class="text-danger">*</span>
                                </label>
                                <div class="custom-file">
                                    <input type="file" name="video_file" id="video_file" class="custom-file-input @error('video_file') is-invalid @enderror"
                                        accept="video/mp4,video/webm,video/ogg,video/quicktime,video/x-matroska" onchange="displaySelectedVideo(this)">
                                    <label class="custom-file-label" for="video_file" id="video_file_label">Pilih file video (MP4, WebM, MOV, MKV)...</label>
                                </div>
                                <small class="form-text text-muted">
                                    Maksimal ukuran file: <strong>2 GB</strong>. Format disarankan: <strong>.mp4</strong> (H.264).
                                </small>
                                @error('video_file')
                                    <span class="d-block text-danger small mt-1">{{ $message }}</span>
                                @enderror

                                {{-- Video Preview Container --}}
                                <div id="videoPreviewContainer" class="mt-3 p-2 bg-white rounded border text-center" style="display: none;">
                                    <div class="embed-responsive embed-responsive-16by9 bg-black rounded">
                                        <video id="videoPreview" class="embed-responsive-item" controls></video>
                                    </div>
                                    <p id="videoFileInfo" class="small text-muted mt-2 mb-0"></p>
                                </div>
                            </div>

                            {{-- SECTION 2: INPUT LINK EKSTERNAL --}}
                            <div id="sectionLink" class="p-3 mb-3 bg-light rounded border" style="display: none;">
                                <label for="video_link">
                                    Tautan / Link Video <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fas fa-link"></i></span>
                                    </div>
                                    <input type="url" name="video_link" id="video_link" class="form-control @error('video_link') is-invalid @enderror"
                                        placeholder="https://www.youtube.com/watch?v=... atau Google Drive / direct link"
                                        value="{{ old('video_link') }}" oninput="previewVideoLink(this.value)">
                                </div>
                                <small class="form-text text-muted">
                                    Mendukung: <strong>YouTube</strong> (otomatis cover & embed), <strong>Google Drive</strong> (pastikan link sharing: Siapa saja yang memiliki link), <strong>Vimeo</strong>, dan link video langsung (URL .mp4).
                                </small>
                                @error('video_link')
                                    <span class="d-block text-danger small mt-1">{{ $message }}</span>
                                @enderror

                                {{-- Link Preview --}}
                                <div id="linkPreviewContainer" class="mt-3 p-2 bg-white rounded border text-center" style="display: none;">
                                    <p class="eyebrow text-left mb-2">Pratinjau link video</p>
                                    <div class="embed-responsive embed-responsive-16by9 bg-black rounded">
                                        <iframe id="linkPreviewIframe" class="embed-responsive-item" src="" frameborder="0" allowfullscreen allow="autoplay"></iframe>
                                    </div>
                                </div>
                            </div>

                            {{-- Thumbnail Input (Opsional) --}}
                            <div class="form-group">
                                <label for="thumbnail">Cover / Thumbnail Gambar <span class="text-muted">(Opsional)</span></label>
                                <div class="custom-file">
                                    <input type="file" name="thumbnail" id="thumbnail" class="custom-file-input @error('thumbnail') is-invalid @enderror"
                                        accept="image/png,image/jpeg,image/webp" onchange="displaySelectedThumbnail(this)">
                                    <label class="custom-file-label" for="thumbnail" id="thumbnail_label">Pilih gambar thumbnail (JPG, PNG, WebP)...</label>
                                </div>
                                <small class="form-text text-muted">Jika tidak diunggah: video file memakai cover otomatis dari detik pertama video, link YouTube memakai thumbnail YouTube.</small>
                                @error('thumbnail')
                                    <span class="d-block text-danger small mt-1">{{ $message }}</span>
                                @enderror
                                <div id="thumbnailPreviewContainer" class="mt-2 text-left" style="display: none;">
                                    <img id="thumbnailPreview" src="#" alt="Thumbnail Preview" class="img-thumbnail" style="max-height: 120px;">
                                </div>
                            </div>

                            @include('learning_module.partials.material-quiz-fields', ['quiz' => null])

                            {{-- Upload Progress Bar --}}
                            <div id="uploadProgressBarContainer" class="mb-3" style="display: none;">
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="small"><i class="fas fa-spinner fa-spin mr-1 text-muted"></i> Sedang Menyimpan Video...</span>
                                    <span id="uploadPercent" class="small text-muted">0%</span>
                                </div>
                                <div class="progress">
                                    <div id="uploadProgressBar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div>
                                </div>
                                <small class="text-muted mt-1 d-block">Mohon tunggu hingga proses selesai dan jangan menutup halaman ini.</small>
                            </div>
                        </div>

                        <div class="card-footer d-flex justify-content-end gap-2">
                            <a href="{{ route('video.index') }}" class="btn btn-default">Batal</a>
                            <button type="submit" id="submitBtn" class="btn btn-primary">
                                <i class="fas fa-save"></i> Simpan Video
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

@include('video.partials.thumbnail-capture')
<script>
function switchVideoType(type) {
    if (type === 'file') {
        document.getElementById('sectionFile').style.display = 'block';
        document.getElementById('sectionLink').style.display = 'none';
        document.getElementById('video_file').required = true;
        document.getElementById('video_link').required = false;
    } else {
        document.getElementById('sectionFile').style.display = 'none';
        document.getElementById('sectionLink').style.display = 'block';
        document.getElementById('video_file').required = false;
        document.getElementById('video_link').required = true;
    }
}

function displaySelectedVideo(input) {
    if (input.files && input.files[0]) {
        var file = input.files[0];
        document.getElementById('video_file_label').innerText = file.name;
        
        var sizeMB = (file.size / (1024 * 1024)).toFixed(2);
        document.getElementById('videoFileInfo').innerText = 'Nama: ' + file.name + ' | Ukuran: ' + sizeMB + ' MB';
        
        var preview = document.getElementById('videoPreview');
        preview.src = URL.createObjectURL(file);
        document.getElementById('videoPreviewContainer').style.display = 'block';

        videoThumbnail.fromSelectedFile(file);
    }
}

function previewVideoLink(url) {
    url = url.trim();
    var iframe = document.getElementById('linkPreviewIframe');
    var container = document.getElementById('linkPreviewContainer');

    var embedUrl = null;

    // YouTube regex
    var ytMatch = url.match(/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/|youtube\.com\/shorts\/)([^"&?\/ ]{11})/i);
    if (ytMatch && ytMatch[1]) {
        embedUrl = 'https://www.youtube.com/embed/' + ytMatch[1];
    } else if (url.includes('drive.google.com/file/d/')) {
        var gDriveMatch = url.match(/drive\.google\.com\/file\/d\/([a-zA-Z0-9_-]+)/i);
        if (gDriveMatch && gDriveMatch[1]) {
            embedUrl = 'https://drive.google.com/file/d/' + gDriveMatch[1] + '/preview';
        }
    } else if (url.includes('vimeo.com/')) {
        var vimeoMatch = url.match(/vimeo\.com\/(\d+)/i);
        if (vimeoMatch && vimeoMatch[1]) {
            embedUrl = 'https://player.vimeo.com/video/' + vimeoMatch[1];
        }
    }

    if (embedUrl) {
        iframe.src = embedUrl;
        container.style.display = 'block';
    } else {
        container.style.display = 'none';
        iframe.src = '';
    }
}

function displaySelectedThumbnail(input) {
    if (input.files && input.files[0]) {
        videoThumbnail.manual = true;
        var file = input.files[0];
        document.getElementById('thumbnail_label').innerText = file.name;
        
        var preview = document.getElementById('thumbnailPreview');
        preview.src = URL.createObjectURL(file);
        document.getElementById('thumbnailPreviewContainer').style.display = 'block';
    }
}

// Upload progress tracking
var isVideoSubmitting = false;
document.getElementById('uploadVideoForm').addEventListener('submit', function(e) {
    if (isVideoSubmitting) {
        e.preventDefault();
        return false;
    }
    isVideoSubmitting = true;
    var submitBtn = document.getElementById('submitBtn');
    submitBtn.style.pointerEvents = 'none';
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Menyimpan...';
    setTimeout(function() {
        submitBtn.disabled = true;
    }, 50);

    var isFile = document.getElementById('option_file').checked;
    if (isFile && document.getElementById('video_file').files.length) {
        document.getElementById('uploadProgressBarContainer').style.display = 'block';
        var progressBar = document.getElementById('uploadProgressBar');
        var uploadPercent = document.getElementById('uploadPercent');
        var progress = 10;
        setInterval(function() {
            if (progress < 90) {
                progress += 5;
                progressBar.style.width = progress + '%';
                uploadPercent.innerText = progress + '%';
            }
        }, 500);
    }
});

// Check if old input selected link
@if(old('video_type') === 'link')
    document.getElementById('option_link').checked = true;
    document.getElementById('labelOptionLink').click();
@else
    document.getElementById('video_file').required = true;
@endif
</script>
@endsection
