<section class="training-panel" id="materi" aria-labelledby="training-materials-title" data-training-panel @if($activeSection !== 'materi') hidden @endif>
    <header class="training-panel-header">
        <h2 class="training-section-title" id="training-materials-title">Materi pelatihan</h2>
        <div class="training-panel-actions">
            @if($training->hasQuiz())
                <a href="{{ route('training.questions.index', [$training, 'open_ai' => 1]) }}" class="btn btn-sm btn-default">
                    Buat Soal AI
                </a>
            @endif
            <button type="button" class="btn btn-sm btn-default" data-toggle="modal" data-target="#attachDocumentModal">
                Lampirkan Dokumen
            </button>
        </div>
    </header>

    <div class="training-panel-body">
        @if($training->description)
            <p class="mb-3 text-break" style="white-space: pre-line;">{{ $training->description }}</p>
        @endif

        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 pb-3 mb-3 border-bottom">
            <div class="min-w-0">
                <h3 class="text-sm font-weight-bold mb-1">Rekaman video</h3>
                @if($training->video)
                    <a href="{{ route('video.show', $training->video) }}" class="d-block text-break">{{ $training->video->title }}</a>
                    <span class="text-muted text-sm">{{ $training->video->views_count }} kali ditonton · {{ $training->video->created_at->format('d M Y') }}</span>
                @else
                    <span class="text-muted text-sm">Belum ada rekaman.</span>
                @endif
            </div>
            <button type="button" class="btn btn-sm btn-default" data-toggle="modal" data-target="#uploadTrainingVideoModal">
                {{ $training->video ? 'Ganti Video' : 'Unggah Video' }}
            </button>
        </div>

        <h3 class="text-sm font-weight-bold mb-1">Dokumen</h3>
        @if($training->documents->isEmpty())
            <p class="text-muted text-sm mb-0">Belum ada dokumen terlampir.</p>
        @else
            <ul class="list-group list-group-flush mb-0">
                @foreach($training->documents as $doc)
                    @php
                        $latestVersion = $doc->versions->first();
                        $fileUrl = $latestVersion?->file_url ?? $doc->file_url;
                    @endphp
                    <li class="list-group-item px-0 py-2 d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div class="min-w-0">
                            <a href="{{ $fileUrl }}" target="_blank" rel="noopener" class="d-block text-break">{{ $doc->name }}</a>
                            <span class="text-muted text-sm">{{ $doc->dokumentype->name ?? 'Dokumen' }} · v{{ $doc->version ?? $latestVersion?->version_number ?? 1 }}</span>
                        </div>
                        <div class="d-flex align-items-center flex-wrap gap-2">
                            @if($canImportDocumentQuestions && $doc->quizModule?->hasQuiz() && $doc->quizModule->questions_count > 0)
                                <a href="{{ route('training.questions.index', ['training' => $training, 'quiz_document' => $doc->id]) }}" class="btn btn-sm btn-default">
                                    Ambil Soal
                                </a>
                            @endif
                            <form action="{{ route('training.detach-document', [$training, $doc]) }}" method="POST" class="m-0" onsubmit="return confirm(@js('Lepas dokumen '.$doc->name.' dari pelatihan ini?'))">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-default text-danger" aria-label="Lepas {{ $doc->name }}">Lepas</button>
                            </form>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</section>
