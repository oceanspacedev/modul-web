@extends('layout.main_template')

@section('content')
<section class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <a href="{{ route('video.index') }}" class="btn btn-outline-secondary btn-sm mb-2">
                    <i class="fas fa-arrow-left mr-1"></i> Kembali ke Galeri
                </a>
                <h1 class="m-0 font-weight-bold">
                    <i class="fas fa-cloud-upload-alt text-danger mr-2"></i>Tambah Video Baru
                </h1>
            </div>
        </div>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        <div class="row justify-content-center">
                <div class="card shadow-sm border">
                    <div class="card-header bg-white py-3">
                        <h3 class="card-title font-weight-bold m-0" style="font-size: 1.15rem;">Form Materi Video Baru</h3>
                    </div>

                    <form id="uploadVideoForm" action="{{ route('video.store') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="card-body">
                            {{-- Errors summary --}}
                            @if ($errors->any())
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <ul class="mb-0">
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            {{-- Judul Video --}}
                            <div class="form-group mb-3">
                                <label for="title" class="font-weight-bold">Judul Video <span class="text-danger">*</span></label>
                                <input type="text" name="title" id="title" class="form-control @error('title') is-invalid @enderror"
                                    placeholder="Contoh: Tutorial Penggunaan Modul Part 1" value="{{ old('title') }}" required autofocus>
                                @error('title')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                            </div>

                            {{-- Deskripsi Video --}}
                            <div class="form-group mb-3">
                                <label for="description" class="font-weight-bold">Deskripsi / Keterangan Video</label>
                                <textarea name="description" id="description" rows="3" class="form-control @error('description') is-invalid @enderror"
                                    placeholder="Tuliskan ringkasan materi atau poin-poin yang dipelajari dalam video ini...">{{ old('description') }}</textarea>
                                @error('description')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                            </div>

                            {{-- Pilihan Sumber Video --}}
                            <div class="form-group mb-3">
                                <label class="font-weight-bold d-block">Sumber Video <span class="text-danger">*</span></label>
                                <div class="btn-group btn-group-toggle w-100" data-toggle="buttons">
                                    <label class="btn btn-outline-danger active w-50 py-2" id="labelOptionFile" onclick="switchVideoType('file')">
                                        <input type="radio" name="video_type" id="option_file" value="file" autocomplete="off" checked>
                                        <i class="fas fa-file-upload mr-1"></i> <strong>Upload File Video</strong>
                                        <div class="small text-muted font-weight-normal">Dari Komputer/Laptop</div>
                                    </label>
                                    <label class="btn btn-outline-danger w-50 py-2" id="labelOptionLink" onclick="switchVideoType('link')">
                                        <input type="radio" name="video_type" id="option_link" value="link" autocomplete="off">
                                        <i class="fas fa-link mr-1"></i> <strong>Gunakan Link Video</strong>
                                        <div class="small text-muted font-weight-normal">YouTube / Google Drive / URL</div>
                                    </label>
                                </div>
                            </div>

                            {{-- SECTION 1: UPLOAD FILE LOKAL --}}
                            <div id="sectionFile" class="p-3 mb-3 bg-light rounded border">
                                <label for="video_file" class="font-weight-bold text-dark">
                                    <i class="fas fa-file-video text-danger mr-1"></i> Pilih File Video <span class="text-danger">*</span>
                                </label>
                                <div class="custom-file">
                                    <input type="file" name="video_file" id="video_file" class="custom-file-input @error('video_file') is-invalid @enderror"
                                        accept="video/mp4,video/webm,video/ogg,video/quicktime,video/x-matroska" onchange="displaySelectedVideo(this)">
                                    <label class="custom-file-label" for="video_file" id="video_file_label">Pilih file video (MP4, WebM, MOV, MKV)...</label>
                                </div>
                                <small class="form-text text-muted mt-2">
                                    Maksimal ukuran file: <strong>2 GB</strong>. Format disarankan: <strong>.mp4</strong> (H.264).
                                </small>
                                @error('video_file')
                                    <span class="text-danger small">{{ $message }}</span>
                                @enderror

                                {{-- Video Preview Container --}}
                                <div id="videoPreviewContainer" class="mt-3 p-2 bg-white rounded border text-center" style="display: none;">
                                    <div class="embed-responsive embed-responsive-16by9 bg-dark rounded">
                                        <video id="videoPreview" class="embed-responsive-item" controls></video>
                                    </div>
                                    <p id="videoFileInfo" class="small text-muted mt-2 mb-0"></p>
                                </div>
                            </div>

                            {{-- SECTION 2: INPUT LINK EKSTERNAL --}}
                            <div id="sectionLink" class="p-3 mb-3 bg-light rounded border" style="display: none;">
                                <label for="video_link" class="font-weight-bold text-dark">
                                    <i class="fas fa-globe text-primary mr-1"></i> Tautan / Link Video <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text bg-white"><i class="fas fa-link text-muted"></i></span>
                                    </div>
                                    <input type="url" name="video_link" id="video_link" class="form-control @error('video_link') is-invalid @enderror"
                                        placeholder="https://www.youtube.com/watch?v=... atau Google Drive / direct link"
                                        value="{{ old('video_link') }}" oninput="previewVideoLink(this.value)">
                                </div>
                                <small class="form-text text-muted mt-2">
                                    Mendukung: <strong>YouTube</strong> (otomatis cover & embed), <strong>Google Drive</strong> (pastikan link sharing: Siapa saja yang memiliki link), <strong>Vimeo</strong>, dan link video langsung (URL .mp4).
                                </small>
                                @error('video_link')
                                    <span class="text-danger small">{{ $message }}</span>
                                @enderror

                                {{-- Link Preview --}}
                                <div id="linkPreviewContainer" class="mt-3 p-2 bg-white rounded border text-center" style="display: none;">
                                    <h6 class="font-weight-bold text-left mb-2 text-primary"><i class="fas fa-eye mr-1"></i> Pratinjau Link Video:</h6>
                                    <div class="embed-responsive embed-responsive-16by9 bg-black rounded">
                                        <iframe id="linkPreviewIframe" class="embed-responsive-item" src="" frameborder="0" allowfullscreen allow="autoplay"></iframe>
                                    </div>
                                </div>
                            </div>

                            {{-- Thumbnail Input (Opsional) --}}
                            <div class="form-group mb-4">
                                <label for="thumbnail" class="font-weight-bold">Cover / Thumbnail Gambar <span class="text-muted font-weight-normal">(Opsional)</span></label>
                                <div class="custom-file">
                                    <input type="file" name="thumbnail" id="thumbnail" class="custom-file-input @error('thumbnail') is-invalid @enderror"
                                        accept="image/png,image/jpeg,image/webp" onchange="displaySelectedThumbnail(this)">
                                    <label class="custom-file-label" for="thumbnail" id="thumbnail_label">Pilih gambar thumbnail (JPG, PNG, WebP)...</label>
                                </div>
                                <small class="form-text text-muted">Jika menggunakan link YouTube, cover akan otomatis diambil dari thumbnail YouTube jika tidak diunggah.</small>
                                @error('thumbnail')
                                    <span class="text-danger small">{{ $message }}</span>
                                @enderror
                                <div id="thumbnailPreviewContainer" class="mt-2 text-left" style="display: none;">
                                    <img id="thumbnailPreview" src="#" alt="Thumbnail Preview" class="img-thumbnail" style="max-height: 120px;">
                                </div>
                            </div>

                            {{-- Upload Progress Bar --}}
                            <div id="uploadProgressBarContainer" class="mb-3" style="display: none;">
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="font-weight-bold small text-primary"><i class="fas fa-spinner fa-spin mr-1"></i> Sedang Menyimpan Video...</span>
                                    <span id="uploadPercent" class="font-weight-bold small text-primary">0%</span>
                                </div>
                                <div class="progress" style="height: 20px;">
                                    <div id="uploadProgressBar" class="progress-bar progress-bar-striped progress-bar-animated bg-primary" role="progressbar" style="width: 0%"></div>
                                </div>
                                <small class="text-muted mt-1 d-block">Mohon tunggu hingga proses selesai dan jangan menutup halaman ini.</small>
                            </div>
                        </div>

                        <div class="card-footer bg-light d-flex justify-content-between">
                            <a href="{{ route('video.index') }}" class="btn btn-secondary">Batal</a>
                            <button type="submit" id="submitBtn" class="btn btn-primary font-weight-bold px-4">
                                <i class="fas fa-save mr-1"></i> Simpan Video
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

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
