<div class="modal fade" id="importDocumentModal" tabindex="-1" role="dialog" aria-labelledby="importDocumentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <form action="{{ route('training.questions.import-document', $training) }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="importDocumentModalLabel">Ambil Soal dari Dokumen</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Tutup">&times;</button>
                </div>
                <div class="modal-body">
                    @if($quizDocuments->isNotEmpty())
                        @php
                            $attachedIds = $documents->modelKeys();
                            [$attached, $other] = $quizDocuments->partition(fn ($doc) => in_array($doc->id, $attachedIds, true));
                        @endphp
                        <div class="form-group mb-0">
                            <label for="quizDocumentId">Dokumen sumber</label>
                            <select name="quiz_document_id" id="quizDocumentId" required
                                class="form-control @error('quiz_document_id') is-invalid @enderror"
                                aria-describedby="quizDocumentHelp{{ $errors->has('quiz_document_id') ? ' quizDocumentError' : '' }}"
                                @error('quiz_document_id') aria-invalid="true" @enderror>
                                <option value="" disabled @selected(!$selectedQuizDocumentId)>Pilih dokumen</option>
                                @foreach(['Dokumen pelatihan ini' => $attached, 'Dokumen lain' => $other] as $label => $group)
                                    @if($group->isNotEmpty())
                                        <optgroup label="{{ $label }}">
                                            @foreach($group as $doc)
                                                @php
                                                    $quiz = $doc->quizModule;
                                                    $phases = array_filter([$quiz->has_pretest ? 'Pretest' : null, $quiz->has_posttest ? 'Posttest' : null]);
                                                @endphp
                                                <option value="{{ $doc->id }}" @selected((string) $selectedQuizDocumentId === (string) $doc->id)>
                                                    {{ $doc->name }} · {{ implode(' & ', $phases) }} · {{ $quiz->questions_count }} soal
                                                </option>
                                            @endforeach
                                        </optgroup>
                                    @endif
                                @endforeach
                            </select>
                            @error('quiz_document_id')
                                <div id="quizDocumentError" class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small id="quizDocumentHelp" class="form-text text-muted">
                                Perubahan pada salinan soal hanya berlaku di pelatihan ini.
                                @if($training->hasBothPhases())
                                    Soal mengikuti fase kuis dokumen.
                                @endif
                            </small>
                        </div>
                    @else
                        @error('quiz_document_id')
                            <p class="text-danger">{{ $message }}</p>
                        @enderror
                        <p class="mb-0">Belum ada dokumen aktif yang berisi soal pretest atau posttest. Tambahkan soal kuis lewat menu Dokumen.</p>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">{{ $quizDocuments->isEmpty() ? 'Tutup' : 'Batal' }}</button>
                    @if($quizDocuments->isNotEmpty())
                        <button type="submit" class="btn btn-primary">Salin Soal</button>
                    @else
                        <a href="{{ route('document.index') }}" class="btn btn-primary">Buka Dokumen</a>
                    @endif
                </div>
            </form>
        </div>
    </div>
</div>
