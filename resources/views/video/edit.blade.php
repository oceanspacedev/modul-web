@extends('layout.main_template')

@section('content')
<section class="content-header">
    <div class="container-fluid">
        <div class="row mb-2 align-items-center">
            <div class="col-sm-6">
                <h1 class="m-0 font-weight-bold">
                    <i class="fas fa-edit text-info mr-2"></i>Edit Video
                </h1>
            </div>
            <div class="col-sm-6 text-sm-right mt-2 mt-sm-0">
                <a href="{{ route('video.index') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-arrow-left mr-1"></i> Kembali ke Galeri
                </a>
            </div>
        </div>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        <div class="row justify-content-center">
                <div class="card shadow-sm border">
                    <div class="card-header bg-white py-3">
                        <h3 class="card-title font-weight-bold m-0" style="font-size: 1.15rem;">Ubah Informasi Video</h3>
                    </div>

                    <form id="editVideoForm" action="{{ route('video.update', $video) }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')
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
                                    value="{{ old('title', $video->title) }}" required autofocus>
                                @error('title')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                            </div>

                            {{-- Deskripsi Video --}}
                            <div class="form-group mb-3">
                                <label for="description" class="font-weight-bold">Deskripsi / Keterangan Video</label>
                                <textarea name="description" id="description" rows="3" class="form-control @error('description') is-invalid @enderror">{{ old('description', $video->description) }}</textarea>
                                @error('description')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                            </div>

                            {{-- Pilihan Sumber Video --}}
                            <div class="form-group mb-3">
                                <label class="font-weight-bold d-block">Sumber Video <span class="text-danger">*</span></label>
                                <div class="btn-group btn-group-toggle w-100" data-toggle="buttons">
                                    <label class="btn btn-outline-info {{ $video->video_type === 'file' ? 'active' : '' }} w-50 py-2" id="labelOptionFile" onclick="switchVideoType('file')">
                                        <input type="radio" name="video_type" id="option_file" value="file" autocomplete="off" {{ $video->video_type === 'file' ? 'checked' : '' }}>
                                        <i class="fas fa-file-upload mr-1"></i> <strong>File Video Komputer</strong>
                                    </label>
                                    <label class="btn btn-outline-info {{ $video->video_type === 'link' ? 'active' : '' }} w-50 py-2" id="labelOptionLink" onclick="switchVideoType('link')">
                                        <input type="radio" name="video_type" id="option_link" value="link" autocomplete="off" {{ $video->video_type === 'link' ? 'checked' : '' }}>
                                        <i class="fas fa-link mr-1"></i> <strong>Link Video Eksternal</strong>
                                    </label>
                                </div>
                            </div>

                            {{-- SECTION FILE --}}
                            <div id="sectionFile" class="p-3 mb-3 bg-light rounded border" style="{{ $video->video_type === 'link' ? 'display: none;' : '' }}">
                                @if($video->isFile() && $video->video_file)
                                    <div class="mb-3">
                                        <label class="font-weight-bold d-block text-muted small">Video Saat Ini:</label>
                                        <div class="embed-responsive embed-responsive-16by9 bg-dark rounded" style="max-height: 220px;">
                                            <video class="embed-responsive-item" controls preload="metadata">
                                                <source src="{{ $video->video_url }}" type="video/mp4">
                                            </video>
                                        </div>
                                        <small class="text-muted">Ukuran: {{ $video->file_size ?? 'N/A' }}</small>
                                    </div>
                                @endif

                                <label for="video_file" class="font-weight-bold">Ganti File Video <span class="text-muted font-weight-normal">(Kosongkan jika tidak ingin mengganti)</span></label>
                                <div class="custom-file">
                                    <input type="file" name="video_file" id="video_file" class="custom-file-input @error('video_file') is-invalid @enderror"
                                        accept="video/mp4,video/webm,video/ogg,video/quicktime,video/x-matroska" onchange="displayNewVideo(this)">
                                    <label class="custom-file-label" for="video_file" id="video_file_label">Pilih file video baru jika ingin mengganti...</label>
                                </div>
                                <small class="form-text text-muted mt-2">Maksimal 2 GB. Format: MP4, WebM, MOV, MKV.</small>
                            </div>

                            {{-- SECTION LINK --}}
                            <div id="sectionLink" class="p-3 mb-3 bg-light rounded border" style="{{ $video->video_type === 'file' ? 'display: none;' : '' }}">
                                <label for="video_link" class="font-weight-bold text-dark">Tautan / Link Video</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text bg-white"><i class="fas fa-link text-muted"></i></span>
                                    </div>
                                    <input type="url" name="video_link" id="video_link" class="form-control @error('video_link') is-invalid @enderror"
                                        placeholder="https://www.youtube.com/watch?v=... atau Google Drive link"
                                        value="{{ old('video_link', $video->video_link) }}">
                                </div>
                                <small class="form-text text-muted mt-2">Mendukung YouTube, Google Drive, Vimeo, atau link video langsung.</small>
                            </div>

                            {{-- Thumbnail Input (Opsional) --}}
                            <div class="form-group mb-4">
                                <label for="thumbnail" class="font-weight-bold">Cover / Thumbnail <span class="text-muted font-weight-normal">(Opsional)</span></label>
                                @if($video->thumbnail_url)
                                    <div class="mb-2">
                                        <img src="{{ $video->thumbnail_url }}" alt="Thumbnail" class="img-thumbnail" style="max-height: 100px;">
                                    </div>
                                @endif
                                <div class="custom-file">
                                    <input type="file" name="thumbnail" id="thumbnail" class="custom-file-input @error('thumbnail') is-invalid @enderror"
                                        accept="image/png,image/jpeg,image/webp" onchange="displayNewThumbnail(this)">
                                    <label class="custom-file-label" for="thumbnail" id="thumbnail_label">Pilih gambar thumbnail baru...</label>
                                </div>
                                @if($video->isFile() && $video->video_file)
                                    <button type="button" class="btn btn-default btn-sm mt-2" id="btnCaptureCover">
                                        <i class="fas fa-camera mr-1"></i> Ambil cover dari video
                                    </button>
                                    <small class="text-muted d-block mt-1" id="captureCoverStatus">Mengambil frame di detik pertama video saat ini sebagai cover.</small>
                                @endif
                                <div id="thumbnailPreviewContainer" class="mt-2" style="display: none;">
                                    <span class="small text-muted d-block mb-1">Cover baru (tersimpan setelah klik Simpan):</span>
                                    <img id="thumbnailPreview" src="#" alt="Preview cover" class="img-thumbnail" style="max-height: 120px;">
                                </div>
                            </div>

                            @include('learning_module.partials.material-quiz-fields', ['quiz' => $quiz])
                        </div>

                        <div class="card-footer bg-light d-flex justify-content-between">
                            <a href="{{ route('video.index') }}" class="btn btn-secondary">Batal</a>
                            <button type="submit" class="btn btn-primary font-weight-bold px-4">
                                <i class="fas fa-save mr-1"></i> Simpan Perubahan
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
    } else {
        document.getElementById('sectionFile').style.display = 'none';
        document.getElementById('sectionLink').style.display = 'block';
    }
}

function displayNewVideo(input) {
    if (input.files && input.files[0]) {
        document.getElementById('video_file_label').innerText = input.files[0].name;
        // Video diganti dan belum ada cover: ambil cover dari file baru.
        @unless($video->thumbnail)
            videoThumbnail.fromSelectedFile(input.files[0]);
        @endunless
    }
}

function displayNewThumbnail(input) {
    if (input.files && input.files[0]) {
        videoThumbnail.manual = true;
        document.getElementById('thumbnail_label').innerText = input.files[0].name;
        document.getElementById('thumbnailPreview').src = URL.createObjectURL(input.files[0]);
        document.getElementById('thumbnailPreviewContainer').style.display = 'block';
    }
}

@if($video->isFile() && $video->video_file)
// File di storage dibaca lewat aplikasi (satu domain) agar frame-nya boleh disalin ke canvas.
document.getElementById('btnCaptureCover').addEventListener('click', function () {
    var button = this;
    var status = document.getElementById('captureCoverStatus');
    button.disabled = true;
    status.innerText = 'Mengambil cover dari video...';
    videoThumbnail.capture(@json(route('video.source', $video))).then(function (cover) {
        videoThumbnail.apply(cover);
        status.innerText = 'Cover diambil. Klik Simpan Perubahan untuk menyimpannya.';
    }).catch(function () {
        status.innerText = 'Cover tidak bisa diambil dari video ini (format tidak didukung browser). Silakan pilih gambar cover manual.';
    }).finally(function () {
        button.disabled = false;
    });
});
@endif
</script>
@endsection
