<?php

namespace App\Http\Requests;

use App\Enums\TrainingStatus;
use App\Models\Training;
use Illuminate\Validation\Rule;

class UpdateTrainingRequest extends StoreTrainingRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
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
