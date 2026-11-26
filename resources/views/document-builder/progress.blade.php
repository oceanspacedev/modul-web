@extends('layout.main_template')

@section('content')
<section class="content-header">
    <div class="container-fluid">
        <x-page-header :title="$draft->title" :subtitle="$draft->category->label()"
            :breadcrumbs="['Dokumen Builder' => route('document-builder.index'), 'Menyusun' => null]" />
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        <div class="card mx-auto" style="max-width: 560px;">
            <div class="card-body text-center py-5" data-pending>
                <div class="spinner-border text-primary mb-3" role="status" aria-hidden="true"></div>
                <h5 class="mb-1">AI sedang menyusun dokumen</h5>
                <p class="text-muted small mb-0" data-message aria-live="polite">Membaca jawaban Anda...</p>
            </div>
            <div class="card-body text-center py-5 d-none" data-failed>
                <i class="fas fa-exclamation-circle text-danger fa-2x mb-3"></i>
                <h5 class="mb-1">Dokumen gagal disusun</h5>
                <p class="text-muted small" data-error></p>
                <div class="d-flex flex-wrap justify-content-center gap-2">
                    <a href="{{ route('document-builder.answers', $draft) }}" class="btn btn-primary btn-sm">Coba Lagi</a>
                    <a href="{{ route('document-builder.edit', $draft) }}" class="btn btn-default btn-sm">Isi Manual</a>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

@section('footer')
<script>
    (function () {
        const messages = ['Membaca jawaban Anda...', 'Merumuskan tujuan dan ruang lingkup...', 'Menyusun isi per bagian...', 'Merapikan bahasa...', 'Hampir selesai...'];
        const statusUrl = @json(route('document-builder.ai-status', $draft));
        const editUrl = @json(route('document-builder.edit', $draft));
        const interval = 2500;
        const maxPolls = 48; // ± 2 menit; sesudahnya jangan menunggu terus tanpa kepastian
        let tick = 0;
        let polls = 0;
        const rotate = setInterval(() => {
            tick = Math.min(tick + 1, messages.length - 1);
            document.querySelector('[data-message]').textContent = messages[tick];
        }, 6000);

        function fail(message) {
            clearInterval(rotate);
            document.querySelector('[data-pending]').classList.add('d-none');
            document.querySelector('[data-failed]').classList.remove('d-none');
            document.querySelector('[data-error]').textContent = message || 'Silakan coba lagi.';
        }

        async function poll() {
            if (++polls > maxPolls) {
                fail('AI belum juga selesai dalam batas tunggu. Silakan muat ulang halaman ini atau isi dokumen manual.');
                return;
            }

            let response;
            try {
                response = await fetch(statusUrl, { headers: { Accept: 'application/json' } });
            } catch (e) {
                // Jaringan putus sebentar: coba lagi di putaran berikutnya.
                setTimeout(poll, interval);
                return;
            }

            let result;
            try {
                result = await response.json();
            } catch (e) {
                // Halaman jawabannya bukan JSON (draf terhapus, sesi berakhir): hentikan polling, jangan muter-muter.
                fail(response.status === 404
                    ? 'Draf dokumen ini sudah dihapus atau tidak bisa dibuka lagi.'
                    : 'Status proses AI tidak bisa dibaca (kesalahan '+response.status+'). Muat ulang halaman ini lalu coba lagi.');
                return;
            }

            if (result.status === 'done') { window.location = editUrl; return; }
            if (result.status === 'failed') { fail(result.message); return; }

            setTimeout(poll, interval);
        }

        poll();
    })();
</script>
@endsection
