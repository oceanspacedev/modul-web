@extends('layout.main_template')

@section('content')
<section class="content-header">
    <div class="container-fluid">
        <x-page-header title="Ubah Dokumen" :subtitle="$document->title"
            :breadcrumbs="['Approval Dokumen' => route('od-report.documents.index'), 'Ubah' => null]" />
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        <x-flash />

        <div class="row">
            <div class="col-lg-7">
                <form action="{{ route('od-report.documents.update', $document) }}" method="POST" class="card" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')
                    <div class="card-body">
                        @include('od-report.documents._fields')

                        @if ($document->isFromRequest())
                            <hr>
                            <div class="form-group mb-0">
                                <label for="stage">Tahap <span class="badge badge-info">{{ $document->documentRequest->code }}</span></label>
                                <select class="custom-select" id="stage" name="stage" data-final="{{ $document->finalStage() }}">
                                    @foreach ($document->stageNames() as $stage)
                                        <option value="{{ $stage }}" @selected(old('stage', $document->stage) === $stage)>{{ $loop->iteration }}. {{ $stage }}</option>
                                    @endforeach
                                </select>
                                <small class="form-text text-muted">Tahap terakhir otomatis menjadikan status selesai. Pemohon dikabari lewat WhatsApp.</small>
                            </div>
                        @endif
                    </div>
                    <div class="card-footer d-flex flex-wrap justify-content-between gap-2">
                        <a href="{{ route('od-report.documents.index') }}" class="btn btn-default">Kembali</a>
                        <button type="submit" class="btn btn-primary">Simpan</button>
                    </div>
                </form>
            </div>

            <div class="col-lg-5">
                @if ($document->isFromRequest())
                    @php($request = $document->documentRequest)
                    <div class="card">
                        <div class="card-header"><h3 class="card-title">Dari permintaan</h3></div>
                        <div class="card-body small">
                            <p class="mb-1"><strong>{{ $request->nama }}</strong> &middot; {{ $request->departemen }}</p>
                            <p class="mb-1">Prioritas: <span class="badge {{ App\Models\DocumentRequest::priorityBadgeClass($document->prioritas) }}">{{ $document->prioritas ?? '-' }}</span></p>
                            @if ($document->sistem)
                                <p class="mb-1">Sistem/alat: {{ $document->sistem }}</p>
                            @endif
                            <p class="mb-2 text-break" style="white-space: pre-line;">{{ $document->tujuan }}</p>
                            @if (Route::has('od-report.requests.show'))
                                <a href="{{ route('od-report.requests.show', $request) }}">Buka permintaan &rarr;</a>
                            @endif
                        </div>
                    </div>
                @endif

                <div class="card">
                    <div class="card-header"><h3 class="card-title">Aktivitas terkait ({{ $document->activities->count() }})</h3></div>
                    <div class="card-body p-0">
                        @forelse ($document->activities as $activity)
                            <div class="px-3 py-2 border-bottom small">
                                <div class="d-flex justify-content-between align-items-start gap-2">
                                    <strong class="min-w-0 text-break">{{ $activity->name }}</strong>
                                    <span class="badge {{ $activity->badgeClass() }} flex-shrink-0">{{ $activity->result }}</span>
                                </div>
                                <span class="text-muted">{{ $activity->date->format('d/m/Y') }} &middot; {{ $activity->user->nama_lengkap }}</span>
                            </div>
                        @empty
                            <x-empty-state icon="fa-tasks" title="Belum ada aktivitas" text="Hubungkan aktivitas dari Daily Report." />
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
