<?php

namespace App\Http\Controllers;

use App\Models\DocumentBuilderQuestion;
use App\Models\DocumentCategory;
use App\Support\DocumentBuilderTemplates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Langkah form Dokumen Builder per Kategori Dokumen (OD Leader): tambah, ubah, hapus, dan urutkan pertanyaan yang
 * dijawab penyusun. Langkah judul selalu ada karena jadi judul dokumen.
 */
class DocumentBuilderStepController extends Controller
{
    public const MAX_STEPS = 20;

    public function edit(DocumentCategory $category): View
    {
        return view('document-builder.steps', [
            'title' => 'Langkah Builder '.$category->code,
            'active' => 'document-category',
            'category' => $category,
            'template' => DocumentBuilderTemplates::for($category),
        ]);
    }

    public function update(Request $request, DocumentCategory $category): RedirectResponse
    {
        $validated = $request->validate([
            'steps' => 'required|array|min:1|max:'.self::MAX_STEPS,
            'steps.*.key' => ['nullable', 'string', 'max:40', 'regex:/^[a-z0-9_]+$/'],
            'steps.*.label' => 'required|string|max:100',
            'steps.*.hint' => 'nullable|string|max:150',
            'steps.*.placeholder' => 'nullable|string|max:200',
            'steps.*.type' => ['required', Rule::in(array_keys(DocumentBuilderQuestion::TYPES))],
            'steps.*.required' => 'nullable|boolean',
        ], [], [
            'steps.*.label' => 'pertanyaan',
            'steps.*.hint' => 'keterangan',
            'steps.*.placeholder' => 'contoh isian',
        ]);

        // Urutan baris mengikuti urutan di form (kunci array bisa acak karena baris baru).
        $steps = array_values($validated['steps']);
        $keys = array_filter(array_column($steps, 'key'));

        if (! in_array('title', $keys, true)) {
            throw ValidationException::withMessages(['steps' => 'Langkah judul tidak boleh dihapus.']);
        }
        if (count($keys) !== count(array_unique($keys))) {
            throw ValidationException::withMessages(['steps' => 'Ada langkah ganda. Muat ulang halaman lalu coba lagi.']);
        }

        DB::transaction(function () use ($category, $steps, $keys) {
            $category->builderQuestions()->delete();

            foreach ($steps as $sort => $step) {
                $key = $step['key'] ?? null;
                if (! filled($key)) {
                    do {
                        $key = 'q_'.Str::lower(Str::random(8));
                    } while (in_array($key, $keys, true));
                    $keys[] = $key;
                }

                $category->builderQuestions()->create([
                    'key' => $key,
                    'label' => trim($step['label']),
                    'hint' => $step['hint'] ?? null,
                    'placeholder' => $step['placeholder'] ?? null,
                    'type' => $step['type'],
                    // Judul selalu wajib karena jadi judul dokumen.
                    'required' => $key === 'title' || (bool) ($step['required'] ?? false),
                    'sort' => $sort + 1,
                ]);
            }
        });

        return back()->with('success', 'Langkah builder disimpan.');
    }

    /** Hapus pengaturan sehingga kategori kembali memakai langkah bawaan. */
    public function reset(DocumentCategory $category): RedirectResponse
    {
        $category->builderQuestions()->delete();

        return back()->with('success', 'Langkah builder dikembalikan ke bawaan.');
    }
}
