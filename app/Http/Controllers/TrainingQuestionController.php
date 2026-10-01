<?php

namespace App\Http\Controllers;

use App\Models\Training;
use App\Models\TrainingQuestion;
use Illuminate\Http\Request;

class TrainingQuestionController extends Controller
{
    /**
     * Show quiz questions management for a training
     */
    public function index($trainingId)
    {
        $training = Training::with('questions')->findOrFail($trainingId);

        return view('training.questions.index', [
            'title' => 'Kelola Kuis Pelatihan: ' . $training->title,
            'active' => 'training',
            'training' => $training,
            'questions' => $training->questions,
        ]);
    }

    /**
     * Store a new question for the training
     */
    public function store(Request $request, $trainingId)
    {
        $training = Training::findOrFail($trainingId);

        $validated = $request->validate([
            'question' => 'required|string',
            'option_a' => 'required|string',
            'option_b' => 'required|string',
            'option_c' => 'required|string',
            'option_d' => 'required|string',
            'correct_answer' => 'required|in:a,b,c,d',
            'explanation' => 'nullable|string',
        ]);

        $training->questions()->create($validated);

        return back()->with('success', 'Soal kuis baru berhasil ditambahkan!');
    }

    /**
     * Update an existing question
     */
    public function update(Request $request, $trainingId, $questionId)
    {
        $question = TrainingQuestion::where('training_id', $trainingId)->findOrFail($questionId);

        $validated = $request->validate([
            'question' => 'required|string',
            'option_a' => 'required|string',
            'option_b' => 'required|string',
            'option_c' => 'required|string',
            'option_d' => 'required|string',
            'correct_answer' => 'required|in:a,b,c,d',
            'explanation' => 'nullable|string',
        ]);

        $question->update($validated);

        return back()->with('success', 'Soal kuis berhasil diperbarui!');
    }

    /**
     * Delete a question
     */
    public function destroy($trainingId, $questionId)
    {
        $question = TrainingQuestion::where('training_id', $trainingId)->findOrFail($questionId);
        $question->delete();

        return back()->with('success', 'Soal kuis berhasil dihapus!');
    }
}
