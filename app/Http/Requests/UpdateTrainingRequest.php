<?php

namespace App\Http\Requests;

use App\Enums\TrainingStatus;
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
    public function trainingData(): array
    {
        return [...parent::trainingData(), 'status' => $this->enum('status', TrainingStatus::class)];
    }
}
