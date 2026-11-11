{{-- Tombol publikasi di header halaman modul (hanya Modul Belajar biasa). Variabel: $module (butuh items.materialable). --}}
@unless($module->isOwned())
    @if($module->is_published)
        <form action="{{ route('learning-module.publish', $module) }}" method="POST" class="m-0"
            onsubmit="return confirm('Tarik publikasi? Modul tidak lagi tampil untuk peserta, tapi nilai yang sudah ada tetap tersimpan.')">
            @csrf
            <button type="submit" class="btn btn-default"><i class="fas fa-eye-slash"></i> Tarik Publikasi</button>
        </form>
    @elseif(($missingForPublish = $module->missingForPublish()) === [])
        <form action="{{ route('learning-module.publish', $module) }}" method="POST" class="m-0">
            @csrf
            <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Publikasikan Modul</button>
        </form>
    @else
        <span class="d-inline-block" tabindex="0" title="Butuh minimal 1 {{ implode(' dan 1 ', $missingForPublish) }}">
            <button type="button" class="btn btn-default" disabled style="pointer-events: none;">
                <i class="fas fa-paper-plane"></i> Publikasikan Modul
            </button>
        </span>
    @endif
@endunless
