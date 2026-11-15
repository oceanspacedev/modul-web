<?php

namespace App\Http\Controllers;

use App\Models\Training;
use App\Models\TrainingMeetingChatMessage;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

#[Authorize('update', 'training')]
class TrainingMeetingChatController extends Controller
{
    public function store(Request $request, Training $training): RedirectResponse
    {
        $provider = $training->meetingProvider();
        $meetingCode = $training->meetingCode();
        abort_if($meetingCode === null || ! $provider->supportsChatImport(), 404);
        abort_unless($training->hasMeetingMonitoring(), 409, 'Monitoring meeting untuk pelatihan ini sedang nonaktif.');

        $validated = $request->validate([
            'account_revision' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]+$/D'],
            'chat_file' => ['required', 'file', 'extensions:txt', 'max:1024'],
        ], [], [
            'account_revision' => 'versi pengaturan meeting',
            'chat_file' => 'file chat',
        ]);
        $revision = $validated['account_revision'];
        $this->ensureCurrentRevision($training, $meetingCode, $revision);

        try {
            $contents = $validated['chat_file']->get();
            if ($contents === false) {
                throw new FileNotFoundException;
            }
            $messages = $provider->parseChat($contents);
        } catch (FileNotFoundException) {
            throw ValidationException::withMessages(['chat_file' => 'File chat tidak dapat dibaca. Silakan unggah ulang.']);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['chat_file' => $exception->getMessage()]);
        }

        $imported = DB::transaction(function () use ($training, $provider, $meetingCode, $revision, $messages): int {
            $currentTraining = Training::query()->lockForUpdate()->findOrFail($training->id);
            $this->ensureCurrentRevision($currentTraining, $meetingCode, $revision, lockAccount: true);

            $imported = 0;
            $now = now();
            foreach (array_chunk($messages, 100) as $batch) {
                $rows = array_map(fn (array $message): array => [
                    ...$message,
                    'training_id' => $training->id,
                    'provider' => $provider->key(),
                    'meeting_code' => $meetingCode,
                    'created_at' => $now,
                ], $batch);
                $imported += TrainingMeetingChatMessage::query()->insertOrIgnore($rows);
            }

            return $imported;
        });

        return to_route('training.meeting.show', $training)->with('success', match (true) {
            $messages === [] => 'File tidak berisi pesan yang ditujukan ke semua peserta.',
            $imported === 0 => 'Semua chat publik dari file ini sudah tersimpan.',
            default => "{$imported} chat publik ditambahkan.",
        });
    }

    private function ensureCurrentRevision(Training $training, string $meetingCode, string $revision, bool $lockAccount = false): void
    {
        $accountQuery = $training->meetingAccount();
        if ($lockAccount) {
            $accountQuery->lockForUpdate();
        }
        $account = $training->hasMeetingMonitoring() ? $accountQuery->first() : null;

        if ($account === null
            || $training->meetingCode() !== $meetingCode
            || ! hash_equals($training->meetingMonitorRevision($account), $revision)) {
            throw ValidationException::withMessages(['chat_file' => 'Pengaturan meeting telah berubah. Muat ulang halaman sebelum mengimpor chat.']);
        }
    }
}
