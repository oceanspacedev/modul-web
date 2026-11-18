<?php

namespace App\Services;

use App\Models\DocumentCategory;
use App\Models\OdDocument;
use App\Models\OdDocumentStatus;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Simpan Kategori Dokumen dan tahapannya. Nama tahap disimpan apa adanya di od_documents.stage, jadi tahap yang diganti
 * nama ikut diganti di dokumennya, dan tahap terakhir (= selesai) tetap sejalan dengan status dokumen.
 */
class DocumentCategoryService
{
    /**
     * @param  array{code: string, name: string}  $data
     */
    public function create(array $data): DocumentCategory
    {
        return DB::transaction(function () use ($data) {
            $category = DocumentCategory::create([
                ...$data,
                'is_active' => true,
                'sort' => (int) DocumentCategory::max('sort') + 1,
            ]);

            $sort = 0;
            foreach (DocumentCategory::DEFAULT_STAGES as $name => $description) {
                $category->stages()->create(['name' => $name, 'description' => $description, 'sort' => ++$sort]);
            }

            return $category;
        });
    }

    /**
     * @param  array<string, mixed>  $data  data tervalidasi dari DocumentCategoryRequest
     */
    public function update(DocumentCategory $category, array $data): void
    {
        DB::transaction(function () use ($category, $data) {
            $rows = array_values($data['stages']);
            $category->update([
                ...Arr::except($data, ['stages']),
                'sort' => $data['sort'] ?? $category->sort,
            ]);

            $existing = $category->stages()->get()->keyBy('id');

            // Catat dulu dokumen di tahap yang diganti nama, supaya tukar nama antar dua tahap tetap aman.
            $renames = [];
            foreach ($rows as $row) {
                $old = $existing->get($row['id'] ?? null);
                if ($old && $old->name !== $row['name']) {
                    $renames[$row['name']] = $this->documents($category)->where('stage', $old->name)->pluck('id');
                }
            }

            $kept = [];
            foreach ($rows as $index => $row) {
                // Form hanya berisi judul tahap. Penjelasan lama tetap dipakai halaman lacak, kecuali tahapnya diganti
                // nama (penjelasannya bisa tidak cocok lagi).
                $stage = $existing->get($row['id'] ?? null);
                $attributes = ['name' => $row['name'], 'sort' => $index + 1];
                if (! $stage || $stage->name !== $row['name']) {
                    $attributes['description'] = null;
                }
                $stage ? $stage->update($attributes) : $stage = $category->stages()->create($attributes);
                $kept[] = $stage->getKey();
            }
            $category->stages()->whereNotIn('id', $kept)->delete();

            foreach ($renames as $name => $ids) {
                $ids->chunk(500)->each(fn ($chunk) => $this->documents($category)->whereIn('id', $chunk)->update(['stage' => $name]));
            }

            $this->syncFinalStage($category, end($rows)['name']);
        });
    }

    /**
     * Dokumen dari pengajuan: status selesai = tahap terakhir. Setelah tahapan diubah, dokumen yang sudah selesai pindah
     * ke tahap terakhir yang baru, dan dokumen berjalan yang kini ada di tahap terakhir dianggap selesai.
     */
    private function syncFinalStage(DocumentCategory $category, string $final): void
    {
        $done = OdDocumentStatus::where('type', OdDocumentStatus::TYPE_DONE)->pluck('id');
        $open = OdDocumentStatus::where('type', OdDocumentStatus::TYPE_OPEN)->pluck('id');

        $this->documents($category)->whereNotNull('document_request_id')->whereIn('od_document_status_id', $done)
            ->where('stage', '!=', $final)->update(['stage' => $final]);

        if ($firstDone = OdDocumentStatus::firstOfType(OdDocumentStatus::TYPE_DONE)) {
            // Dokumen dari permintaan hanya boleh rilis bila sudah punya file.
            $this->documents($category)->whereNotNull('document_request_id')->whereNotNull('file_path')->whereIn('od_document_status_id', $open)
                ->where('stage', $final)->update(['od_document_status_id' => $firstDone->getKey(), 'received_at' => today()]);
        }
    }

    /**
     * Query dokumen kategori ini tanpa menyentuh updated_at.
     *
     * @return Builder
     */
    private function documents(DocumentCategory $category)
    {
        return OdDocument::withTrashed()->where('jenis', $category->code)->toBase();
    }
}
