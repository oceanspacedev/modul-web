<?php

namespace App\Http\Controllers;

use App\Exports\LearningModuleRekapExport;
use App\Http\Requests\LearningModuleRequest;
use App\Models\Divisi;
use App\Models\Document;
use App\Models\LearningModule;
use App\Models\Role;
use App\Models\User;
use App\Models\Video;
use App\Support\LearningModuleProgress;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

#[Authorize('viewAny', LearningModule::class, only: ['index'])]
#[Authorize('create', LearningModule::class, only: ['create', 'store'])]
#[Authorize('view', 'module', only: ['show', 'participant', 'export'])]
#[Authorize('update', 'module', only: ['edit', 'update', 'togglePublish'])]
#[Authorize('delete', 'module', only: ['destroy'])]
class LearningModuleController extends Controller
{
    public function index(Request $request): View
    {
        $modules = LearningModule::standalone()
            ->with(['divisis', 'roles'])
            ->withCount(['items', 'questions'])
            ->withCount(['attempts as participants_count' => fn ($query) => $query->select(DB::raw('count(distinct user_id)'))])
            ->when($request->filled('search'), fn ($query) => $query->where('title', 'like', '%'.$request->search.'%'))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('learning_module.index', [
            'title' => 'Modul Belajar',
            'active' => 'learning-module',
            'modules' => $modules,
        ]);
    }

    public function create(): View
    {
        return view('learning_module.form', [
            'title' => 'Tambah Modul Belajar',
            'active' => 'learning-module',
            'module' => new LearningModule(['passing_score' => 70, 'shuffle_questions' => true]),
            'selectedItems' => collect(),
            ...$this->formOptions(),
        ]);
    }

    public function store(LearningModuleRequest $request): RedirectResponse
    {
        $module = DB::transaction(function () use ($request) {
            $module = LearningModule::create([...$request->moduleData(), 'created_by' => $request->user()->id]);
            $this->syncRelations($module, $request);

            return $module;
        });

        return to_route('learning-module.questions.index', $module)
            ->with('success', 'Modul berhasil dibuat. Tambahkan soal pretest/posttest, lalu publikasikan modul.');
    }

    /**
     * Rekap nilai peserta.
     */
    public function show(LearningModule $module): View
    {
        $module->load(['items.materialable', 'divisis', 'roles'])->loadCount('questions');

        return view('learning_module.show', [
            'title' => 'Rekap Modul: '.$module->title,
            'active' => 'learning-module',
            'module' => $module,
            'participants' => LearningModuleProgress::forParticipants($module),
        ]);
    }

    /**
     * Riwayat semua percobaan seorang peserta.
     */
    public function participant(LearningModule $module, User $user): View
    {
        $module->load('items.materialable');
        $user->load(['divisi', 'roles']);

        return view('learning_module.participant', [
            'title' => 'Riwayat '.$user->nama_lengkap,
            'active' => 'learning-module',
            'module' => $module,
            'progress' => LearningModuleProgress::for($module, $user),
        ]);
    }

    public function edit(LearningModule $module): View
    {
        $this->abortIfOwned($module);
        $module->load(['items.materialable', 'divisis', 'roles']);

        return view('learning_module.form', [
            'title' => 'Edit Modul Belajar',
            'active' => 'learning-module',
            'module' => $module,
            'selectedItems' => $module->availableItems(),
            ...$this->formOptions(),
        ]);
    }

    public function update(LearningModuleRequest $request, LearningModule $module): RedirectResponse
    {
        $this->abortIfOwned($module);

        if ($module->is_published && $request->materialKeys() === []) {
            return back()->withInput()->withErrors(['items' => 'Modul yang sudah dipublikasikan harus memiliki minimal 1 materi.']);
        }

        DB::transaction(function () use ($request, $module) {
            $module->update($request->moduleData());
            $this->syncRelations($module, $request);
        });

        return to_route('learning-module.index')->with('success', 'Modul berhasil diperbarui.');
    }

    public function destroy(LearningModule $module): RedirectResponse
    {
        $module->delete();

        return to_route('learning-module.index')->with('success', 'Modul berhasil dihapus.');
    }

    /**
     * Publikasikan / tarik modul. Modul baru bisa dipublikasikan bila sudah punya soal dan materi.
     */
    public function togglePublish(LearningModule $module): RedirectResponse
    {
        $this->abortIfOwned($module);

        if (! $module->is_published) {
            $module->load('items.materialable')->loadCount('questions');

            if ($module->questions_count === 0 || $module->availableItems()->isEmpty()) {
                return back()->with('error', 'Modul belum bisa dipublikasikan: tambahkan minimal 1 materi dan 1 soal terlebih dahulu.');
            }
        }

        $module->update(['is_published' => ! $module->is_published]);

        return back()->with('success', $module->is_published
            ? 'Modul dipublikasikan dan sudah bisa dikerjakan peserta.'
            : 'Modul ditarik dan tidak lagi tampil untuk peserta.');
    }

    public function export(LearningModule $module): BinaryFileResponse
    {
        return Excel::download(new LearningModuleRekapExport($module), 'rekap_modul_'.Str::slug($module->title).'.xlsx');
    }

    /**
     * Kuis milik video/dokumen diatur dari form materi tersebut dan aktif otomatis saat sudah ada soal.
     */
    private function abortIfOwned(LearningModule $module): void
    {
        abort_if($module->isOwned(), 404);
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'divisis' => Divisi::orderBy('name')->get(),
            'roles' => Role::where('name', '!=', User::ROLE_ADMIN)->orderByDesc('level')->orderBy('name')->get(),
            'videos' => Video::orderBy('title')->get(),
            'documents' => Document::orderBy('name')->get(),
        ];
    }

    /**
     * Simpan sasaran dan materi. Materi yang tetap dipilih mempertahankan id-nya agar progres peserta tidak hilang.
     */
    private function syncRelations(LearningModule $module, LearningModuleRequest $request): void
    {
        $module->divisis()->sync($request->validated('divisi_ids') ?? []);
        $module->roles()->sync($request->validated('role_ids') ?? []);

        $keepIds = [];
        foreach ($request->materialKeys() as $order => [$type, $id]) {
            $keepIds[] = $module->items()->updateOrCreate(
                ['materialable_type' => $type, 'materialable_id' => $id],
                ['sort_order' => $order],
            )->id;
        }

        $module->items()->whereNotIn('id', $keepIds)->delete();
    }
}
