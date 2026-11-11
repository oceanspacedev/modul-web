{{--
    Langkah penyiapan modul. Variabel: $module, $page ('create' | 'edit' | 'questions' | 'show').
    Modul Belajar biasa: informasi → materi → soal → publikasi.
    Kuis milik video/dokumen: unggah → pretest/posttest → soal → aktif.
--}}
@php
    $page ??= 'show';

    if (! $module->exists) {
        $steps = [
            ['label' => 'Informasi', 'hint' => 'isi formulir ini, lalu klik Simpan & Lanjut.', 'done' => false],
            ['label' => 'Materi', 'done' => false],
            ['label' => 'Soal', 'done' => false],
            ['label' => 'Publikasi', 'done' => false],
        ];
    } else {
        $module->loadMissing('items.materialable');
        $itemCount = $module->availableItems()->count();
        $questionCount = $module->questions_count ?? $module->questions()->count();
        $soalStep = [
            'label' => 'Soal',
            'meta' => $questionCount.' soal',
            'hint' => $page === 'questions' ? 'buat soal lewat AI, import, atau manual.' : 'buat soal pretest/posttest.',
            'done' => $questionCount > 0,
        ] + ($page === 'questions' ? [] : ['action' => 'Buat Soal', 'url' => $module->url('questions.index')]);

        if ($module->isOwned()) {
            $sourceLabel = $module->isOwnedByVideo() ? 'video' : 'dokumen';
            $phaseOn = $module->has_pretest || $module->has_posttest;
            $steps = [
                ['label' => 'Unggah', 'done' => true],
                [
                    'label' => 'Aturan Kuis',
                    'hint' => 'nyalakan pretest atau posttest di formulir '.$sourceLabel.'.',
                    'done' => $phaseOn,
                    'action' => 'Edit '.ucfirst($sourceLabel),
                    'url' => $module->isOwnedByVideo() ? route('video.edit', $module->source_id) : route('document.edit', $module->source_id),
                ],
                $soalStep,
                ['label' => 'Kuis aktif', 'hint' => 'kuis aktif otomatis.', 'done' => (bool) $module->is_published],
            ];
        } else {
            $steps = [
                ['label' => 'Informasi', 'meta' => $module->targetLabel(), 'done' => true],
                [
                    'label' => 'Materi',
                    'meta' => $itemCount.' materi',
                    'hint' => 'pilih video/dokumen yang dipelajari peserta.',
                    'done' => $itemCount > 0,
                ] + ($page === 'edit' ? [] : ['action' => 'Pilih Materi', 'url' => route('learning-module.edit', $module).'#materi']),
                // Modul tanpa pretest dan posttest tidak butuh soal.
                ...($module->hasQuiz() ? [$soalStep] : []),
                [
                    'label' => 'Publikasi',
                    'hint' => 'publikasikan agar modul muncul di Modul Saya peserta.',
                    'done' => (bool) $module->is_published,
                    'action' => 'Publikasikan',
                    'post' => route('learning-module.publish', $module),
                ],
            ];
        }
    }
@endphp

<x-setup-steps :title="$module->isOwned() ? 'Langkah menyiapkan kuis' : 'Langkah menyiapkan modul'" :steps="$steps" />
