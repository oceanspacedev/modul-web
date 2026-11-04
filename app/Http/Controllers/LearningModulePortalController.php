<?php

namespace App\Http\Controllers;

use App\Models\LearningModule;
use App\Models\LearningModuleAttempt;
use App\Models\LearningModuleItem;
use App\Models\LearningModuleItemProgress;
use App\Models\User;
use App\Support\LearningModuleProgress;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\View\View;

/**
 * Sisi peserta Modul Belajar: pretest sekali -> semua materi -> posttest yang bisa diulang.
 */
#[Authorize('take', 'module', except: ['index'])]
class LearningModulePortalController extends Controller
{
    public function index(Request $request): View
    {
        $modules = LearningModule::standalone()
            ->visibleTo($request->user())
            ->with('items.materialable')
            ->withCount('questions')
            ->orderBy('title')
            ->get();

        return view('learning_module.portal.index', [
            'title' => 'Modul Saya',
            'active' => 'my-learning-modules',
            'modules' => $modules,
            'progress' => LearningModuleProgress::forMany($modules, $request->user()),
        ]);
    }

    public function show(Request $request, LearningModule $module): View
    {
        $module->load('items.materialable')->loadCount('questions');

        return view('learning_module.portal.show', [
            'title' => $module->title,
            'active' => $module->menuKey('my-learning-modules'),
            'module' => $module,
            'progress' => LearningModuleProgress::for($module, $request->user()),
        ]);
    }

    public function quiz(Request $request, LearningModule $module, string $phase): View|RedirectResponse
    {
        $module->load(['items.materialable', 'questions']);

        if ($redirect = $this->guardPhase($module, $request->user(), $phase)) {
            return $redirect;
        }

        return view('learning_module.portal.quiz', [
            'title' => ($phase === LearningModuleAttempt::PHASE_PRETEST ? 'Pretest' : 'Posttest').': '.$module->title,
            'active' => $module->menuKey('my-learning-modules'),
            'module' => $module,
            'phase' => $phase,
            'questions' => $module->shuffle_questions ? $module->questions->shuffle() : $module->questions,
            'attemptNumber' => LearningModuleProgress::for($module, $request->user())->attemptsFor($phase)->count() + 1,
        ]);
    }

    public function submit(Request $request, LearningModule $module, string $phase): RedirectResponse
    {
        $module->load(['items.materialable', 'questions']);

        if ($redirect = $this->guardPhase($module, $request->user(), $phase)) {
            return $redirect;
        }

        $validated = $request->validate([
            'answers' => 'nullable|array',
            'answers.*' => 'nullable|in:a,b,c,d,e',
        ]);

        try {
            $attempt = LearningModuleAttempt::record($module, $request->user(), $phase, $validated['answers'] ?? []);
        } catch (UniqueConstraintViolationException) {
            // Kiriman ganda (klik dua kali / dua tab): percobaan yang pertama sudah tersimpan.
            return redirect($module->participantUrl())->with('error', 'Jawaban Anda sudah tersimpan sebelumnya.');
        }

        return redirect($module->url('attempt', ['attempt' => $attempt]))->with('success', 'Jawaban berhasil dikirim!');
    }

    public function attempt(Request $request, LearningModule $module, LearningModuleAttempt $attempt): View
    {
        abort_unless($attempt->user_id === $request->user()->id, 404);

        $module->load('items.materialable');

        return view('learning_module.portal.result', [
            'title' => 'Hasil '.($attempt->isPretest() ? 'Pretest' : 'Posttest'),
            'active' => $module->menuKey('my-learning-modules'),
            'module' => $module,
            'attempt' => $attempt,
            'progress' => LearningModuleProgress::for($module, $request->user()),
        ]);
    }

    /**
     * Halaman tonton video. Dokumen langsung dibuka lewat openDocument.
     */
    public function item(Request $request, LearningModule $module, LearningModuleItem $item): View|RedirectResponse
    {
        $module->load('items.materialable');
        $item->setRelation('materialable', $module->items->find($item->id)?->materialable);

        if ($redirect = $this->guardMaterials($module, $request->user(), $item)) {
            return $redirect;
        }

        if (! $item->isVideo()) {
            return redirect($module->url('items.open', ['item' => $item]));
        }

        $this->markProgress($item, $request->user(), completed: false);

        return view('learning_module.portal.video', [
            'title' => $item->title(),
            'active' => $module->menuKey('my-learning-modules'),
            'module' => $module,
            'item' => $item,
            'video' => $item->materialable,
            'progress' => LearningModuleProgress::for($module, $request->user()),
        ]);
    }

    /**
     * Membuka dokumen dihitung sebagai menyelesaikan materi dokumen.
     */
    public function openDocument(Request $request, LearningModule $module, LearningModuleItem $item): RedirectResponse
    {
        $module->load('items.materialable');
        $item->setRelation('materialable', $module->items->find($item->id)?->materialable);

        if ($redirect = $this->guardMaterials($module, $request->user(), $item)) {
            return $redirect;
        }

        abort_if($item->isVideo(), 404);

        $this->markProgress($item, $request->user(), completed: true);

        return redirect()->away($item->materialable->file_url);
    }

    /**
     * Dipanggil pemutar video saat video selesai ditonton.
     */
    public function completeItem(Request $request, LearningModule $module, LearningModuleItem $item): JsonResponse
    {
        $module->load('items.materialable');
        $item->setRelation('materialable', $module->items->find($item->id)?->materialable);

        abort_if($item->materialable === null, 404);

        // Dicek langsung (bukan guardMaterials) agar tidak meninggalkan pesan flash untuk halaman berikutnya.
        if (LearningModuleProgress::for($module, $request->user())->needsPretest()) {
            return response()->json(['success' => false, 'message' => 'Kerjakan pretest terlebih dahulu.'], 403);
        }

        $this->markProgress($item, $request->user(), completed: true);
        $progress = LearningModuleProgress::for($module, $request->user());

        return response()->json([
            'success' => true,
            'completed' => $progress->completedItemCount(),
            'total' => $progress->totalItemCount(),
            'posttest_open' => $progress->canTakePosttest(),
        ]);
    }

    /**
     * Pretest (bila nyala) dikerjakan sebelum materi; posttest (bila nyala) terbuka setelah semua materi selesai.
     * Keduanya dibatasi jumlah percobaan yang diatur pada modul.
     */
    private function guardPhase(LearningModule $module, User $user, string $phase): ?RedirectResponse
    {
        abort_unless(in_array($phase, LearningModuleAttempt::PHASES, true), 404);

        $back = redirect($module->participantUrl());
        $label = $phase === LearningModuleAttempt::PHASE_PRETEST ? 'Pretest' : 'Posttest';

        if (! $module->hasPhase($phase)) {
            return $back->with('error', "{$label} tidak diaktifkan untuk materi ini.");
        }

        if ($module->questions->isEmpty()) {
            return $back->with('error', 'Soal belum tersedia. Silakan hubungi trainer.');
        }

        $progress = LearningModuleProgress::for($module, $user);

        if ($phase === LearningModuleAttempt::PHASE_POSTTEST && ! $progress->isPosttestUnlocked()) {
            return $back->with('error', $progress->needsPretest()
                ? 'Kerjakan pretest terlebih dahulu.'
                : 'Selesaikan semua materi terlebih dahulu sebelum mengerjakan posttest.');
        }

        if ($progress->remainingAttempts($phase) === 0) {
            $max = $module->maxAttempts($phase);

            return $back->with('error', $max === 1
                ? "{$label} hanya dapat dikerjakan satu kali."
                : "Batas {$max} kali percobaan {$label} sudah habis.");
        }

        return null;
    }

    private function guardMaterials(LearningModule $module, User $user, LearningModuleItem $item): ?RedirectResponse
    {
        abort_if($item->materialable === null, 404);

        if (LearningModuleProgress::for($module, $user)->needsPretest()) {
            return redirect($module->participantUrl())->with('error', 'Kerjakan pretest terlebih dahulu sebelum membuka materi.');
        }

        return null;
    }

    private function markProgress(LearningModuleItem $item, User $user, bool $completed): void
    {
        $progress = LearningModuleItemProgress::createOrFirst(
            ['learning_module_item_id' => $item->id, 'user_id' => $user->id],
            ['opened_at' => now()],
        );

        if ($completed && ! $progress->completed_at) {
            $progress->update(['completed_at' => now()]);
        }
    }
}
