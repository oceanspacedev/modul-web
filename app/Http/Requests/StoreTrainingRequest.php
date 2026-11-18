<?php

namespace App\Http\Requests;

use App\Models\MeetingAccount;
use App\Models\Training;
use App\Support\Meetings\MeetingProviders;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StoreTrainingRequest extends FormRequest
{
    /**
     * Authorization is handled by TrainingPolicy on the controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'trainer_id' => ['required', 'integer', 'exists:users,id'],
            'description' => ['nullable', 'string'],
            'training_date' => ['required', 'date'],
            'start_time' => ['required'],
            'end_time' => ['required'],
            'meeting_link' => ['nullable', 'url:http,https', 'max:2048'],
            'meeting_platform' => ['nullable', Rule::in(array_keys(MeetingProviders::all()))],
            'meeting_monitoring_enabled' => ['nullable', 'boolean'],
            'meeting_account_id' => ['nullable', 'integer', 'min:1'],
            // Peserta boleh dipilih manual, diimpor dari Excel, atau keduanya; minimal salah satu.
            'participants' => ['required_without:participant_file', 'array', 'min:1'],
            'participants.*' => ['integer', 'exists:users,id'],
            'participant_file' => ['nullable', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
            'documents' => ['nullable', 'array'],
            'documents.*' => ['integer', 'exists:documents,id'],
            'send_wa_now' => ['nullable', 'boolean'],
            'quiz_mode' => ['nullable', 'in:formal,game'],
            ...Training::quizRuleValidation(),
            'has_evaluation' => ['nullable', 'boolean'],
            'has_attendance' => ['nullable', 'boolean'],
            'is_attendance_active' => ['nullable', 'boolean'],
            'require_attendance_proof' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Columns written to the trainings table.
     *
     * @return array<string, mixed>
     */
    public function trainingData(?Training $training = null): array
    {
        return [
            ...$this->safe()->only(['title', 'trainer_id', 'description', 'training_date', 'start_time', 'end_time']),
            ...$this->meetingData($training),
        ];
    }

    /**
     * Keep existing callers that only send meeting_link compatible with older training records.
     *
     * @return array<string, mixed>
     */
    private function meetingData(?Training $training): array
    {
        $input = $this->safe()->only(['meeting_platform', 'meeting_link', 'meeting_monitoring_enabled', 'meeting_account_id']);
        $link = array_key_exists('meeting_link', $input) ? $input['meeting_link'] : $training?->meeting_link;
        $explicitPlatform = array_key_exists('meeting_platform', $input);
        $platform = $explicitPlatform
            ? $input['meeting_platform']
            : ($link === $training?->meeting_link ? $training?->meeting_platform : null);

        if ($explicitPlatform && $platform === null) {
            $link = null;
        }

        $inferredPlatform = MeetingProviders::fromLink($link)?->key();
        if ($platform !== null && $inferredPlatform === null) {
            throw ValidationException::withMessages(['meeting_link' => 'Isi tautan untuk platform meeting ini.']);
        }
        if ($explicitPlatform && $platform !== null && $platform !== 'other' && $platform !== $inferredPlatform) {
            throw ValidationException::withMessages(['meeting_link' => 'Tautan tidak sesuai dengan platform meeting yang dipilih.']);
        }

        // Pemantauan dan akun penyelenggara hanya berlaku pada platform yang integrasinya tersedia.
        $provider = MeetingProviders::find($platform ?? $inferredPlatform);
        $enabled = false;
        $accountId = null;
        if ($provider?->hasIntegration()) {
            $enabled = (bool) (array_key_exists('meeting_monitoring_enabled', $input)
                ? $input['meeting_monitoring_enabled']
                : $training?->meeting_monitoring_enabled);
            $accountId = array_key_exists('meeting_account_id', $input) ? $input['meeting_account_id'] : $training?->meeting_account_id;
            $accountId = $accountId === null ? null : (int) $accountId;
            if ($enabled && $provider->meetingCode($link) === null) {
                throw ValidationException::withMessages(['meeting_link' => "Monitoring memerlukan tautan gabung {$provider->label()} yang memuat kode rapat."]);
            }
            if ($enabled && $accountId === null) {
                throw ValidationException::withMessages(['meeting_account_id' => "Pilih akun {$provider->label()} penyelenggara untuk mengaktifkan monitoring."]);
            }
            if ($accountId !== null) {
                $this->ensureAccountIsAvailable($training, $accountId, $provider->key(), $link, $enabled);
            }
        }

        return [
            'meeting_platform' => $platform,
            'meeting_link' => $link,
            'meeting_monitoring_enabled' => $enabled,
            'meeting_account_id' => $accountId,
        ];
    }

    private function ensureAccountIsAvailable(?Training $training, int $accountId, string $provider, string $link, bool $enabled): void
    {
        $account = MeetingAccount::query()->lockForUpdate()->find($accountId, ['id', 'user_id', 'provider']);
        $retainingLinkedAccount = $training?->meeting_account_id === $accountId
            && $training->meeting_link === $link
            && (! $enabled || $training->meeting_monitoring_enabled);

        if ($account === null
            || $account->provider !== $provider
            || ($account->user_id !== $this->user()->getKey() && ! $retainingLinkedAccount)) {
            throw ValidationException::withMessages(['meeting_account_id' => 'Pilih akun milik Anda yang sesuai dengan platform meeting ini.']);
        }
    }

    /**
     * Kolom aturan kuis dari blok Aturan Kuis (quiz[...]); kosong bila form tidak mengirimnya.
     *
     * @return array<string, mixed>
     */
    public function quizRules(): array
    {
        $quiz = $this->validated('quiz');

        return is_array($quiz) ? Training::quizRulesFromInput($quiz) : [];
    }

    /**
     * @return array<int, int>
     */
    public function participantIds(): array
    {
        return array_values(array_unique(array_map('intval', $this->validated('participants') ?? [])));
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'participants.required_without' => 'Pilih minimal satu peserta atau impor peserta dari file Excel.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'participant_file' => 'file peserta',
        ];
    }
}
