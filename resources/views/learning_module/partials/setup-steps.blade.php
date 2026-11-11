{{--
    Checklist penyiapan modul. Variabel: $module, $page ('create' | 'edit' | 'questions' | 'show').
    Modul Belajar biasa: informasi & sasaran → materi → soal → publikasikan.
    Kuis milik video/dokumen: materi → pretest/posttest nyala → soal → kuis aktif otomatis.
--}}
@php
    $page ??= 'show';

    if (! $module->exists) {
        $steps = [
            ['label' => 'Informasi, materi & sasaran', 'hint' => 'Isi formulir di halaman ini lalu klik "Simpan & Lanjut Buat Soal".', 'done' => false],
            ['label' => 'Buat soal', 'hint' => 'Lewat AI, import Excel, atau tulis manual.', 'done' => false],
            ['label' => 'Publikasikan', 'hint' => 'Modul baru muncul di "Modul Saya" peserta setelah dipublikasikan.', 'done' => false],
        ];
    } else {
        $module->loadMissing('items.materialable');
        $itemCount = $module->availableItems()->count();
        $questionCount = $module->questions_count ?? $module->questions()->count();
        $soalStep = [
            'label' => 'Buat soal pretest & posttest',
            'hint' => $questionCount ? $questionCount.' soal tersedia.' : 'Lewat AI, import Excel, atau tulis manual. Soal yang sama dipakai untuk pretest dan posttest.',
            'done' => $questionCount > 0,
        ] + ($page === 'questions'
            ? ($questionCount ? [] : ['action' => 'Buat Soal via AI', 'modal' => 'aiGenerateModal'])
            : ['action' => $questionCount ? 'Kelola Soal' : 'Buat Soal', 'url' => $module->url('questions.index')]);

        if ($module->isOwned()) {
            $sourceLabel = $module->isOwnedByVideo() ? 'video' : 'dokumen';
            $editSourceUrl = $module->isOwnedByVideo()
                ? route('video.edit', $module->source_id)
                : route('document.edit', $module->source_id);
            $phaseOn = $module->has_pretest || $module->has_posttest;
            $steps = [
                ['label' => 'Unggah '.$sourceLabel, 'hint' => $module->title, 'done' => true],
                [
                    'label' => 'Nyalakan pretest/posttest',
                    'hint' => $phaseOn ? 'Aktif: '.collect([$module->has_pretest ? 'pretest' : null, $module->has_posttest ? 'posttest' : null])->filter()->join(' & ').'.' : 'Nyalakan di formulir edit '.$sourceLabel.'.',
                    'done' => $phaseOn,
                ] + ($phaseOn ? [] : ['action' => 'Edit '.ucfirst($sourceLabel), 'url' => $editSourceUrl]),
                $soalStep,
                [
                    'label' => 'Kuis aktif',
                    'hint' => $module->is_published ? 'Peserta mengerjakan kuis saat membuka '.$sourceLabel.' ini.' : 'Aktif otomatis setelah ada soal dan pretest/posttest nyala.',
                    'done' => (bool) $module->is_published,
                ],
            ];
        } else {
            $missing = $module->missingForPublish();
            $steps = [
                [
                    'label' => 'Informasi & sasaran',
                    'hint' => $module->targetLabel(),
                    'done' => true,
                ] + ($page === 'edit' ? [] : ['action' => 'Ubah', 'url' => route('learning-module.edit', $module)]),
                [
                    'label' => 'Pilih materi',
                    'hint' => $itemCount ? $itemCount.' materi video/dokumen.' : 'Pilih video atau dokumen yang harus dipelajari peserta.',
                    'done' => $itemCount > 0,
                ] + ($page === 'edit' ? [] : ['action' => $itemCount ? 'Atur Materi' : 'Pilih Materi', 'url' => route('learning-module.edit', $module).'#materi']),
                $soalStep,
                [
                    'label' => 'Publikasikan',
                    'hint' => $module->is_published
                        ? 'Sudah tampil di "Modul Saya" peserta sasaran.'
                        : ($missing ? 'Bisa dipublikasikan setelah ada '.implode(' dan ', $missing).'.' : 'Siap. Modul akan muncul di "Modul Saya" peserta sasaran.'),
                    'done' => (bool) $module->is_published,
                ] + (! $module->is_published && ! $missing ? ['action' => 'Publikasikan Modul', 'post' => route('learning-module.publish', $module)] : []),
            ];
        }
    }
@endphp

<x-setup-steps :title="$module->isOwned() ? 'Langkah menyiapkan kuis' : 'Langkah menyiapkan modul'" :steps="$steps" />
