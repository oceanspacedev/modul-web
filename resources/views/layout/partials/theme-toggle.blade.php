{{-- Tombol ganti tema terang/gelap (logikanya di layout.partials.theme). $class menambah kelas, mis. "topbar-toggle". --}}
<button type="button" class="theme-toggle {{ $class ?? '' }}" data-theme-toggle aria-label="Ganti ke mode gelap" title="Ganti ke mode gelap">
    <i class="fas fa-moon theme-toggle-dark" aria-hidden="true"></i>
    <i class="fas fa-sun theme-toggle-light" aria-hidden="true"></i>
</button>
