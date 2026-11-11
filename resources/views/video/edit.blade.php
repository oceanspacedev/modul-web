@extends('layout.main_template')

@section('content')
<section class="content-header">
    <div class="container-fluid">
        <x-page-header title="Edit Video" :subtitle="$video->title" :breadcrumbs="['Video Materi' => route('video.index'), $video->title => route('video.show', ['video' => $video->id, 'preview' => 1]), 'Edit' => null]">
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

        <form id="editVideoForm" action="{{ route('video.update', $video) }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')
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
                                    value="{{ old('title', $video->title) }}" required autofocus>
                                @error('title')
                                    <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="form-group mb-0">
                                <label for="description">Deskripsi</label>
                                <textarea name="description" id="description" rows="3" class="form-control @error('description') is-invalid @enderror">{{ old('description', $video->description) }}</textarea>
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
                                <label class="btn btn-default {{ $video->video_type === 'file' ? 'active' : '' }} w-50 py-2" id="labelOptionFile" onclick="switchVideoType('file')">
                                    <input type="radio" name="video_type" id="option_file" value="file" autocomplete="off" {{ $video->video_type === 'file' ? 'checked' : '' }}>
                                    <i class="fas fa-file-upload mr-1"></i> Upload File
                                </label>
                                <label class="btn btn-default {{ $video->video_type === 'link' ? 'active' : '' }} w-50 py-2" id="labelOptionLink" onclick="switchVideoType('link')">
                                    <input type="radio" name="video_type" id="option_link" value="link" autocomplete="off" {{ $video->video_type === 'link' ? 'checked' : '' }}>
                                    <i class="fas fa-link mr-1"></i> Link Video
                                </label>
                            </div>

                            {{-- SECTION FILE --}}
                            <div id="sectionFile" style="{{ $video->video_type === 'link' ? 'display: none;' : '' }}">
                                @if($video->isFile() && $video->video_file)
                                    <div class="mb-3" style="max-width: 480px;">
                                        <label class="d-block text-muted small">Video saat ini</label>
                                        <div class="embed-responsive embed-responsive-16by9 bg-black rounded">
                                            <video class="embed-responsive-item" controls preload="metadata">
                                                <source src="{{ $video->video_url }}" type="video/mp4">
                                            </video>
                                        </div>
                                                                            </div>
                                @endif

                                <label for="video_file">Ganti file <span class="text-muted">(opsional)</span></label>
                                <div class="custom-file">
                                    <input type="file" name="video_file" id="video_file" class="custom-file-input @error('video_file') is-invalid @enderror"
                                        accept="video/mp4,video/webm,video/ogg,video/quicktime,video/x-matroska" onchange="displayNewVideo(this)">
                                    <label class="custom-file-label" for="video_file" id="video_file_label">Pilih file video...</label>
                                </div>
                                <small class="form-text text-muted">MP4, WebM, MOV, atau MKV. Maks. 2 GB.</small>
                            </div>

                            {{-- SECTION LINK --}}
                            <div id="sectionLink" style="{{ $video->video_type === 'file' ? 'display: none;' : '' }}">
                                                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fas fa-link"></i></span>
                                    </div>
                                    <input type="url" name="video_link" id="video_link" class="form-control @error('video_link') is-invalid @enderror"
                                        placeholder="https://..."
                                        value="{{ old('video_link', $video->video_link) }}">
                                </div>
                                <small class="form-text text-muted">YouTube, Google Drive (akses publik), Vimeo, atau link .mp4.</small>
                            </div>
                        </div>
                    </div>

                    @include('learning_module.partials.quiz-rules-fields', ['quiz' => $quiz, 'card' => true])
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
                                    <div id="thumbnailPlaceholder" class="{{ $video->thumbnail_url ? 'w-100 h-100' : 'cover-placeholder' }}">
                                        @if($video->thumbnail_url)
                                            <img src="{{ $video->thumbnail_url }}" alt="Cover saat ini">
                                        @else
                                            <i class="far fa-image"></i>
                                            Belum ada cover
                                        @endif
                                    </div>
                                    <div id="thumbnailPreviewContainer" class="w-100 h-100" style="display: none;">
                                        <img id="thumbnailPreview" src="#" alt="Preview cover">
                                    </div>
                                </div>
                                <div class="custom-file">
                                    <input type="file" name="thumbnail" id="thumbnail" class="custom-file-input @error('thumbnail') is-invalid @enderror"
                                        accept="image/png,image/jpeg,image/webp" onchange="displayNewThumbnail(this)">
                                    <label class="custom-file-label" for="thumbnail" id="thumbnail_label">Pilih gambar...</label>
                                </div>
                                @if($video->isFile() && $video->video_file)
                                    <button type="button" class="btn btn-default btn-sm btn-block mt-2" id="btnCaptureCover">
                                        <i class="fas fa-camera"></i> Ambil cover dari video
                                    </button>
                                    <small class="form-text text-muted" id="captureCoverStatus"></small>
                                @endif
                            </div>
                        </div>

                        {{-- SIMPAN --}}
                        <div class="card">
                            <div class="card-body">
                                <button type="submit" class="btn btn-primary btn-block">
                                    <i class="fas fa-save"></i> Simpan Perubahan
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
        document.getElementById('thumbnailPlaceholder').style.display = 'none';
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
        status.innerText = 'Cover diambil. Klik Simpan untuk menyimpan.';
    }).catch(function () {
        status.innerText = 'Cover tidak bisa diambil dari video ini. Pilih gambar manual.';
    }).finally(function () {
        button.disabled = false;
    });
});
@endif
</script>
@endsection
