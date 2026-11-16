@if($messages->isEmpty())
    <p class="text-muted mb-0">Belum ada chat publik.</p>
@else
    <div class="table-responsive">
        <table class="table table-sm">
            <thead><tr><th scope="col">Waktu</th><th scope="col">Pengirim</th><th scope="col">Pesan</th></tr></thead>
            <tbody>
                @foreach($messages as $message)
                    <tr>
                        <td class="text-nowrap">{{ $message->sent_time }}</td>
                        <td>{{ $message->sender }}</td>
                        <td style="white-space: pre-wrap; overflow-wrap: anywhere">{{ $message->message }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    {{ $messages->links('pagination::bootstrap-4') }}
@endif
