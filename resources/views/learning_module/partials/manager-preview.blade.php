{{-- Tombol pratinjau materi tanpa pretest; hanya untuk pengelola video/dokumen, peserta tetap wajib pretest. --}}
@if($module->isOwned() && $module->has_pretest && auth()->user()->can('update', $module) && ($previewUrl = $module->previewUrl()))
    @php $materialLabel = $module->isOwnedByVideo() ? 'video' : 'dokumen'; @endphp
    <div class="alert alert-light border d-flex justify-content-between align-items-center flex-wrap" style="gap: 8px;">
        <span class="text-muted small">Anda pengelola {{ $materialLabel }} ini. Pratinjau tidak mengisi nilai pretest Anda.</span>
        <a href="{{ $previewUrl }}" class="btn btn-default btn-sm" @unless($module->isOwnedByVideo()) target="_blank" rel="noopener" @endunless>
            Pratinjau {{ $materialLabel }} tanpa pretest
        </a>
    </div>
@endif
