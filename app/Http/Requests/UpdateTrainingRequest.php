<?php

namespace App\Http\Requests;

use App\Enums\TrainingStatus;
use App\Models\Training;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class UpdateTrainingRequest extends StoreTrainingRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // Impor Excel saat edit lewat Kelola peserta di halaman detail, jadi form edit tetap wajib memilih peserta.
        return [
            ...Arr::except(parent::rules(), ['participant_file']),
            'participants' => ['required', 'array', 'min:1'],
            'status' => ['required', Rule::enum(TrainingStatus::class)],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function trainingData(?Training $training = null): array
    {
        return [...parent::trainingData($training ?? $this->route('training')), 'status' => $this->enum('status', TrainingStatus::class)];
    }
}
