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
                <a href="{{ route('document-builder.answers', $draft) }}" class="btn btn-primary btn-sm">Coba Lagi</a>
                <a href="{{ route('document-builder.edit', $draft) }}" class="btn btn-default btn-sm">Isi Manual</a>
            </div>
        </div>
    </div>
</section>
@endsection

@section('footer')
<script>
    (function () {
        const messages = ['Membaca jawaban Anda...', 'Merumuskan tujuan dan ruang lingkup...', 'Menyusun isi per bagian...', 'Merapikan bahasa...', 'Hampir selesai...'];
        let tick = 0;
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
            try {
                const response = await fetch(@json(route('document-builder.ai-status', $draft)), { headers: { Accept: 'application/json' } });
                const result = await response.json();
                if (result.status === 'done') { window.location = @json(route('document-builder.edit', $draft)); return; }
                if (result.status === 'failed') { fail(result.message); return; }
            } catch (e) {
                // Jaringan putus sebentar: coba lagi di putaran berikutnya.
            }
            setTimeout(poll, 2500);
        }

        poll();
    })();
</script>
@endsection
