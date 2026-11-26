{{-- Penanda draf yang disusun dari Permintaan Dokumen; butuh relasi odDocument.documentRequest. --}}
@if ($draft->odDocument?->documentRequest)
    <div class="small mt-1 text-break">
        <i class="fas fa-inbox text-muted"></i> Dari permintaan
        <a href="{{ route('od-report.requests.show', $draft->odDocument->documentRequest) }}" class="badge badge-info">{{ $draft->odDocument->documentRequest->code }}</a>
        <span class="text-muted">· {{ $draft->odDocument->documentRequest->nama }}</span>
    </div>
@endif
