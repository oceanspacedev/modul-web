@extends('layout.main_template')

@section('content')
<section class="content-header">
    <div class="container-fluid">
        <x-page-header title="Tambah Video" subtitle="Upload file atau tempel link video." eyebrow="Konten / Video">
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

        <form id="uploadVideoForm" action="{{ route('video.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="row">
                <div class="col-lg-8">
                    {{-- INFORMASI VIDEO --}}
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Informasi Video</h3>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label for="title">Judul <span class="text-danger">*</span></label>
                                <input type="text" name="title" id="title" class="form-control @error('title') is-invalid @enderror"
                                    placeholder="Contoh: Tutorial Modul Part 1" value="{{ old('title') }}" required autofocus>
                                @error('title')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="form-group mb-0">
                                <label for="description">Deskripsi</label>
                                <textarea name="description" id="description" rows="3" class="form-control @error('description') is-invalid @enderror"
                                    placeholder="Opsional">{{ old('description') }}</textarea>
                                @error('description')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                    </div>

                    {{-- SUMBER VIDEO --}}
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Sumber Video</h3>
                        </div>
                        <div class="card-body">
                            <div class="btn-group btn-group-toggle w-100 mb-3" data-toggle="buttons">
                                <label class="btn btn-default active w-50 py-2" id="labelOptionFile" onclick="switchVideoType('file')">
                                    <input type="radio" name="video_type" id="option_file" value="file" autocomplete="off" checked>
                                    <i class="fas fa-file-upload mr-1"></i> Upload File
                                </label>
                                <label class="btn btn-default w-50 py-2" id="labelOptionLink" onclick="switchVideoType('link')">
                                    <input type="radio" name="video_type" id="option_link" value="link" autocomplete="off">
                                    <i class="fas fa-link mr-1"></i> Link Video
                                </label>
                            </div>

                            {{-- SECTION 1: UPLOAD FILE LOKAL --}}
                            <div id="sectionFile">
                                                                <div class="custom-file">
                                    <input type="file" name="video_file" id="video_file" class="custom-file-input @error('video_file') is-invalid @enderror"
                                        accept="video/mp4,video/webm,video/ogg,video/quicktime,video/x-matroska" onchange="displaySelectedVideo(this)">
                                    <label class="custom-file-label" for="video_file" id="video_file_label">Pilih file video...</label>
                                </div>
                                <small class="form-text text-muted">MP4, WebM, MOV, atau MKV. Maks. 2 GB.</small>
                                @error('video_file')
                                    <span class="d-block text-danger small mt-1">{{ $message }}</span>
                                @enderror

                                {{-- Video Preview Container --}}
                                <div id="videoPreviewContainer" class="mt-3" style="display: none;">
                                    <div class="embed-responsive embed-responsive-16by9 bg-black rounded">
                                        <video id="videoPreview" class="embed-responsive-item" controls></video>
                                    </div>
                                    <p id="videoFileInfo" class="small text-muted mt-2 mb-0"></p>
                                </div>
                            </div>

                            {{-- SECTION 2: INPUT LINK EKSTERNAL --}}
                            <div id="sectionLink" style="display: none;">
                                                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fas fa-link"></i></span>
                                    </div>
                                    <input type="url" name="video_link" id="video_link" class="form-control @error('video_link') is-invalid @enderror"
                                        placeholder="https://..."
                                        value="{{ old('video_link') }}" oninput="previewVideoLink(this.value)">
                                </div>
                                <small class="form-text text-muted">YouTube, Google Drive (akses publik), Vimeo, atau link .mp4.</small>
                                @error('video_link')
                                    <span class="d-block text-danger small mt-1">{{ $message }}</span>
                                @enderror

                                {{-- Link Preview --}}
                                <div id="linkPreviewContainer" class="mt-3" style="display: none;">
                                                                        <div class="embed-responsive embed-responsive-16by9 bg-black rounded">
                                        <iframe id="linkPreviewIframe" class="embed-responsive-item" src="" frameborder="0" allowfullscreen allow="autoplay"></iframe>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    @include('learning_module.partials.material-quiz-fields', ['quiz' => null, 'card' => true])
                </div>

                <div class="col-lg-4">
                    <div class="form-aside">
                        {{-- COVER / THUMBNAIL --}}
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Cover</h3>
                                <span class="card-tools badge badge-secondary">Opsional</span>
                            </div>
                            <div class="card-body">
                                <div class="cover-frame mb-3">
                                    <div id="thumbnailPlaceholder" class="cover-placeholder">
                                        <i class="far fa-image"></i>
                                        Belum ada cover
                                    </div>
                                    <div id="thumbnailPreviewContainer" class="w-100 h-100" style="display: none;">
                                        <img id="thumbnailPreview" src="#" alt="Thumbnail Preview">
                                    </div>
                                </div>
                                <div class="custom-file">
                                    <input type="file" name="thumbnail" id="thumbnail" class="custom-file-input @error('thumbnail') is-invalid @enderror"
                                        accept="image/png,image/jpeg,image/webp" onchange="displaySelectedThumbnail(this)">
                                    <label class="custom-file-label" for="thumbnail" id="thumbnail_label">Pilih gambar...</label>
                                </div>
                                <small class="form-text text-muted">Kosongkan untuk cover otomatis.</small>
                                @error('thumbnail')
                                    <span class="d-block text-danger small mt-1">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                        {{-- SIMPAN --}}
                        <div class="card">
                            <div class="card-body">
                                {{-- Upload Progress Bar --}}
                                <div id="uploadProgressBarContainer" class="mb-3" style="display: none;">
                                    <div class="d-flex justify-content-between mb-1">
                                        <span class="small"><i class="fas fa-spinner fa-spin mr-1 text-muted"></i> Menyimpan...</span>
                                        <span id="uploadPercent" class="small text-muted">0%</span>
                                    </div>
                                    <div class="progress">
                                        <div id="uploadProgressBar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div>
                                    </div>
                                    <small class="text-muted mt-1 d-block">Jangan tutup halaman ini.</small>
                                </div>

                                <button type="submit" id="submitBtn" class="btn btn-primary btn-block">
                                    <i class="fas fa-save"></i> Simpan Video
                                </button>
                                <a href="{{ route('video.index') }}" class="btn btn-default btn-block">Batal</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
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
        document.getElementById('thumbnailPlaceholder').style.display = 'none';
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
