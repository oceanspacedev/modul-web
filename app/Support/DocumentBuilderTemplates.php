<?php

namespace App\Support;

use App\Models\DocumentCategory;

/**
 * Template Dokumen Builder per Kategori Dokumen: pertanyaan form bertahap, susunan bagian dokumen, dan arahan untuk AI.
 *
 * SOP, IK, JD, SE dan MEMO punya template khusus. Kategori lain (Dokumen Lainnya dan kategori baru buatan OD Leader)
 * memakai template umum yang menyebut nama dan keterangan kategorinya ke AI.
 *
 * Jenis bagian:
 * - text:  satu paragraf (string)
 * - list:  daftar poin (list<string>)
 * - items: daftar judul + uraian (list<array{title: string, text: string}>), mis. istilah, peran
 * - steps: sama dengan items tetapi bernomor, untuk langkah kerja / prosedur
 */
final class DocumentBuilderTemplates
{
    public const SECTION_TYPES = ['text', 'list', 'items', 'steps'];

    /** Kode kategori yang punya template khusus. */
    public const SPECIFIC = ['SOP', 'IK', 'JD', 'SE', 'MEMO'];

    /**
     * @return array{
     *     key: string,
     *     specific: bool,
     *     customized: bool,
     *     questions: list<array{key: string, label: string, hint: string, placeholder: string, type: string, required: bool}>,
     *     sections: list<array{key: string, label: string, type: string, hint: string, item_labels?: array{0: string, 1: string}}>,
     *     guidance: string,
     *     tips: list<string>
     * }
     */
    public static function for(DocumentCategory $category): array
    {
        $code = strtoupper($category->code);

        $template = match ($code) {
            'SOP' => self::sop(),
            'IK' => self::ik(),
            'JD' => self::jd(),
            'SE' => self::se(),
            'MEMO' => self::memo(),
            default => self::general($category),
        };

        $category->loadMissing('builderQuestions');
        $customized = $category->builderQuestions->isNotEmpty();

        return [
            'key' => in_array($code, self::SPECIFIC, true) ? $code : 'UMUM',
            'specific' => in_array($code, self::SPECIFIC, true),
            'customized' => $customized,
            ...$template,
            // Langkah form yang diatur OD Leader menggantikan langkah bawaan; bagian dokumen tetap dari template.
            'questions' => $customized
                ? $category->builderQuestions->map->toQuestion()->values()->all()
                : $template['questions'],
        ];
    }

    /**
     * Langkah form bawaan kategori, untuk tombol "Kembalikan ke bawaan".
     *
     * @return list<array{key: string, label: string, hint: string, placeholder: string, type: string, required: bool}>
     */
    public static function defaultQuestions(DocumentCategory $category): array
    {
        $template = match (strtoupper($category->code)) {
            'SOP' => self::sop(),
            'IK' => self::ik(),
            'JD' => self::jd(),
            'SE' => self::se(),
            'MEMO' => self::memo(),
            default => self::general($category),
        };

        return $template['questions'];
    }

    public static function hasSpecific(DocumentCategory $category): bool
    {
        return in_array(strtoupper($category->code), self::SPECIFIC, true);
    }

    private static function q(string $key, string $label, string $hint, string $placeholder, string $type = 'textarea', bool $required = false): array
    {
        return compact('key', 'label', 'hint', 'placeholder', 'type', 'required');
    }

    private static function s(string $key, string $label, string $type, string $hint, ?array $itemLabels = null): array
    {
        return array_filter(['key' => $key, 'label' => $label, 'type' => $type, 'hint' => $hint, 'item_labels' => $itemLabels]);
    }

    /** Dua pertanyaan pertama semua template; jawabannya juga jadi judul dan departemen dokumen. */
    private static function head(string $titleLabel, string $titlePlaceholder, string $departmentHint = 'Departemen yang bertanggung jawab'): array
    {
        return [
            self::q('title', $titleLabel, 'Nama yang jelas dan deskriptif', $titlePlaceholder, 'input', true),
            self::q('department', 'Departemen / Divisi', $departmentHint, 'Contoh: HSE, HRD, Operasional, Produksi', 'input', true),
        ];
    }

    private static function sop(): array
    {
        return [
            'questions' => [
                ...self::head('Judul SOP', 'Contoh: SOP Inspeksi HSE Lapangan'),
                self::q('objective', 'Tujuan', 'Apa yang ingin dicapai dengan SOP ini?', 'Contoh: Memastikan standar keselamatan, menjaga kualitas layanan', required: true),
                self::q('scope', 'Ruang Lingkup', 'Cakupan penerapan SOP', 'Contoh: Seluruh lokasi proyek, divisi tertentu, tugas spesifik'),
                self::q('roles', 'Peran yang Terlibat', 'Siapa saja yang terlibat dan tugasnya', 'Contoh: Admin: input data, Supervisor: verifikasi, Manager: approval'),
                self::q('process', 'Gambaran Alur', 'Urutan proses yang berjalan saat ini (boleh kasar)', 'Contoh: Pengajuan → verifikasi → approval → arsip'),
                self::q('tools', 'Tools atau Platform', 'Alat atau sistem yang dipakai', 'Contoh: HRIS, Google Workspace, WhatsApp, formulir manual'),
                self::q('standards', 'Standar atau Aturan', 'Standar yang harus dipatuhi', 'Contoh: ISO 9001, ISO 45001, SMK3, kebijakan internal'),
                self::q('sla', 'Batas Waktu / SLA', 'Tenggat tiap tahap bila ada', 'Contoh: Laporan maks. 1x24 jam, approval maks. 2 hari kerja'),
                self::q('risks', 'Risiko yang Ingin Dihindari', 'Kesalahan atau masalah yang sering terjadi', 'Contoh: Data tidak lengkap, approval terlambat'),
            ],
            'sections' => [
                self::s('objective', 'Tujuan', 'text', 'Mengapa SOP ini ada dan hasil yang ingin dicapai'),
                self::s('scope', 'Ruang Lingkup', 'text', 'Batas penerapan: lokasi, unit, kondisi yang termasuk dan tidak'),
                self::s('definitions', 'Definisi', 'items', 'Istilah penting dan artinya', ['Istilah', 'Pengertian']),
                self::s('references', 'Referensi', 'list', 'Peraturan, standar, atau dokumen acuan'),
                self::s('responsibilities', 'Penanggung Jawab', 'items', 'Setiap peran beserta tanggung jawabnya', ['Peran', 'Tanggung jawab']),
                self::s('qualifications', 'Kualifikasi Pelaksana', 'list', 'Syarat kompetensi pelaksana'),
                self::s('tools', 'Peralatan dan Sistem', 'list', 'Alat, aplikasi, formulir yang dipakai'),
                self::s('warnings', 'Peringatan', 'list', 'Hal yang wajib diperhatikan atau dilarang'),
                self::s('procedures', 'Prosedur', 'steps', 'Langkah kerja berurutan: siapa melakukan apa, kapan, dan hasilnya', ['Langkah', 'Uraian']),
                self::s('records', 'Dokumen dan Rekaman', 'list', 'Formulir, laporan, atau arsip yang dihasilkan'),
                self::s('kpi', 'Indikator Kinerja (KPI)', 'list', 'Ukuran keberhasilan yang terukur'),
            ],
            'guidance' => 'Standard Operating Procedure (SOP): prosedur baku lintas peran. Prosedur ditulis sebagai langkah berurutan yang menyebut pelaksana, tindakan, batas waktu (bila ada), dan keluaran setiap langkah.',
            'tips' => [
                'Judul: "SOP [Aktivitas] [Departemen]"',
                'Tujuan: jelaskan mengapa SOP ini ada',
                'Peran: tulis "Peran: tugas" per baris',
                'Gambaran alur boleh kasar, AI yang merapikan',
            ],
        ];
    }

    private static function ik(): array
    {
        return [
            'questions' => [
                ...self::head('Judul Instruksi Kerja', 'Contoh: IK Pengoperasian Mesin Forklift'),
                self::q('activity', 'Pekerjaan yang Diatur', 'Pekerjaan / proses apa yang dijelaskan?', 'Contoh: Mengangkat dan memindahkan palet di gudang', required: true),
                self::q('performer', 'Pelaksana', 'Siapa yang mengerjakan', 'Contoh: Operator forklift bersertifikat SIO'),
                self::q('steps', 'Langkah Kerja Saat Ini', 'Urutan kerja yang biasa dilakukan', 'Contoh: Cek kondisi unit → nyalakan mesin → angkat palet → parkir'),
                self::q('equipment', 'Alat dan Bahan', 'Peralatan, bahan, atau sistem yang dipakai', 'Contoh: Forklift, palet, checklist harian'),
                self::q('safety', 'K3 dan APD', 'Risiko keselamatan dan APD wajib', 'Contoh: Helm, sepatu safety, rompi; larangan membawa penumpang'),
                self::q('quality', 'Standar Hasil', 'Ciri pekerjaan yang dianggap benar', 'Contoh: Palet tidak miring, barang tidak rusak'),
                self::q('mistakes', 'Kesalahan yang Sering Terjadi', 'Apa yang sering salah dan akibatnya', 'Contoh: Garpu terlalu tinggi saat berjalan'),
            ],
            'sections' => [
                self::s('objective', 'Tujuan', 'text', 'Hasil yang ingin dicapai dari instruksi ini'),
                self::s('scope', 'Ruang Lingkup', 'text', 'Pekerjaan, lokasi, dan kondisi yang diatur'),
                self::s('performers', 'Pelaksana', 'list', 'Peran yang menjalankan instruksi'),
                self::s('equipment', 'Alat dan Bahan', 'list', 'Peralatan, bahan, dan sistem'),
                self::s('safety', 'Keselamatan Kerja (K3) dan APD', 'list', 'APD wajib dan larangan'),
                self::s('steps', 'Langkah Kerja', 'steps', 'Langkah detail dan praktis, satu tindakan per langkah', ['Langkah', 'Uraian']),
                self::s('quality', 'Standar Hasil', 'list', 'Kriteria pekerjaan dinyatakan benar'),
                self::s('notes', 'Hal yang Perlu Diperhatikan', 'list', 'Kesalahan umum dan cara mencegahnya'),
                self::s('references', 'Referensi', 'list', 'SOP induk, manual alat, atau standar terkait'),
            ],
            'guidance' => 'Instruksi Kerja (IK): panduan teknis langkah demi langkah untuk satu pekerjaan oleh satu pelaksana di lapangan. Bahasanya praktis dan perintah langsung (mis. "Periksa tekanan ban sebelum mesin dinyalakan"), lebih rinci daripada SOP.',
            'tips' => [
                'Satu IK untuk satu pekerjaan',
                'Tulis langkah kerja apa adanya, AI yang merapikan',
                'Sebutkan APD dan larangan dengan jelas',
            ],
        ];
    }

    private static function jd(): array
    {
        return [
            'questions' => [
                ...self::head('Nama Posisi', 'Contoh: Admin Operasional, HSE Inspector', 'Posisi ini berada di departemen mana?'),
                self::q('purpose', 'Tujuan Utama Posisi', 'Kontribusi utama posisi ini bagi perusahaan', 'Contoh: Memastikan administrasi proyek tertib dan tepat waktu', required: true),
                self::q('reports_to', 'Atasan dan Bawahan', 'Melapor ke siapa, membawahi siapa', 'Contoh: Melapor ke Supervisor Operasional, tidak membawahi staf', 'input'),
                self::q('daily', 'Aktivitas Harian', 'Gambaran pekerjaan sehari-hari', 'Contoh: Input data harian, rekap laporan, koordinasi lapangan'),
                self::q('responsibilities', 'Tanggung Jawab Utama', 'Tanggung jawab inti posisi', 'Contoh: Menjaga akurasi data, menyusun laporan bulanan'),
                self::q('skills', 'Kualifikasi dan Skill', 'Pendidikan, pengalaman, skill teknis dan non-teknis', 'Contoh: Min. D3, 1 tahun pengalaman, mahir Excel (Pivot, VLOOKUP)'),
                self::q('kpi', 'KPI', 'Indikator keberhasilan', 'Contoh: Laporan terkirim tepat waktu 100%'),
                self::q('coordination', 'Koordinasi', 'Bekerja sama dengan siapa saja', 'Contoh: Finance, HRD, vendor'),
                self::q('authority', 'Wewenang', 'Keputusan yang boleh diambil sendiri', 'Contoh: Menolak dokumen tidak lengkap'),
                self::q('schedule', 'Jam dan Pola Kerja', 'Jam kerja, shift, lokasi', 'Contoh: Senin-Jumat 08.00-17.00, sesekali ke site', 'input'),
            ],
            'sections' => [
                self::s('summary', 'Ringkasan Jabatan', 'text', 'Gambaran singkat posisi dan tujuannya'),
                self::s('position', 'Kedudukan dalam Organisasi', 'items', 'Atasan langsung, bawahan, dan rekan kerja', ['Hubungan', 'Jabatan']),
                self::s('responsibilities', 'Tanggung Jawab Utama', 'items', 'Tanggung jawab inti beserta uraiannya', ['Tanggung jawab', 'Uraian']),
                self::s('daily_activities', 'Aktivitas Rutin', 'list', 'Tugas harian / mingguan'),
                self::s('authority', 'Wewenang', 'list', 'Keputusan yang boleh diambil'),
                self::s('kpi', 'Indikator Kinerja (KPI)', 'list', 'Ukuran keberhasilan yang terukur'),
                self::s('qualifications', 'Kualifikasi', 'list', 'Pendidikan, pengalaman, sertifikasi'),
                self::s('competencies', 'Kompetensi', 'list', 'Skill teknis dan perilaku'),
                self::s('relations', 'Hubungan Kerja', 'items', 'Pihak internal dan eksternal serta keperluannya', ['Pihak', 'Keperluan']),
                self::s('working_conditions', 'Kondisi Kerja', 'text', 'Jam kerja, pola kerja, lokasi'),
            ],
            'guidance' => 'Job Description (JD): uraian jabatan satu posisi. Tanggung jawab diawali kata kerja aktif, KPI terukur, kualifikasi realistis.',
            'tips' => [
                'Pakai nama posisi yang umum',
                'Skill sebut spesifik, mis. "Excel (Pivot)"',
                'KPI sebaiknya terukur',
            ],
        ];
    }

    private static function se(): array
    {
        return [
            'questions' => [
                ...self::head('Perihal', 'Contoh: Ketentuan Jam Kerja Selama Ramadan', 'Departemen penerbit'),
                self::q('audience', 'Ditujukan Kepada', 'Siapa yang wajib membaca', 'Contoh: Seluruh karyawan kantor pusat dan site', 'input', true),
                self::q('background', 'Latar Belakang', 'Alasan surat edaran ini diterbitkan', 'Contoh: Menyesuaikan jam kerja selama bulan Ramadan', required: true),
                self::q('provisions', 'Ketentuan', 'Poin aturan atau imbauan yang disampaikan', 'Contoh: Jam kerja 08.00-16.00, istirahat 30 menit'),
                self::q('period', 'Masa Berlaku', 'Kapan mulai dan sampai kapan berlaku', 'Contoh: 1-30 Maret 2027', 'input'),
                self::q('contact', 'Narahubung', 'Ke mana karyawan bertanya', 'Contoh: HRD ext. 123', 'input'),
            ],
            'sections' => [
                self::s('recipients', 'Kepada', 'list', 'Pihak yang dituju'),
                self::s('background', 'Latar Belakang', 'text', 'Alasan diterbitkan'),
                self::s('purpose', 'Maksud dan Tujuan', 'text', 'Apa yang ingin dicapai'),
                self::s('provisions', 'Ketentuan', 'steps', 'Poin ketentuan bernomor', ['Ketentuan', 'Uraian']),
                self::s('period', 'Masa Berlaku', 'text', 'Tanggal mulai dan akhir berlaku'),
                self::s('closing', 'Penutup', 'text', 'Kalimat penutup dan narahubung'),
            ],
            'guidance' => 'Surat Edaran (SE): pemberitahuan resmi perusahaan kepada karyawan tentang ketentuan atau imbauan. Bahasanya formal, singkat, dan tegas.',
            'tips' => [
                'Perihal singkat dan jelas',
                'Tulis ketentuan per poin',
                'Cantumkan masa berlaku',
            ],
        ];
    }

    private static function memo(): array
    {
        return [
            'questions' => [
                ...self::head('Perihal Memo', 'Contoh: Pengumpulan Laporan Bulanan Lebih Awal', 'Departemen pengirim'),
                self::q('to', 'Kepada', 'Penerima memo', 'Contoh: Seluruh Kepala Departemen', 'input', true),
                self::q('from', 'Dari', 'Pengirim memo', 'Contoh: Manager HRD', 'input'),
                self::q('message', 'Isi Pokok', 'Informasi utama yang ingin disampaikan', 'Contoh: Laporan bulan ini dikumpulkan tanggal 25 karena audit', required: true),
                self::q('actions', 'Tindakan yang Diminta', 'Apa yang harus dilakukan penerima', 'Contoh: Kirim laporan ke email HRD'),
                self::q('deadline', 'Tenggat', 'Batas waktu bila ada', 'Contoh: 25 Maret 2027', 'input'),
            ],
            'sections' => [
                self::s('recipients', 'Kepada', 'list', 'Penerima memo'),
                self::s('sender', 'Dari', 'text', 'Pengirim'),
                self::s('opening', 'Pembuka', 'text', 'Kalimat pembuka singkat'),
                self::s('body', 'Isi', 'text', 'Inti informasi'),
                self::s('key_points', 'Poin Penting', 'list', 'Hal yang perlu diingat'),
                self::s('actions', 'Tindak Lanjut', 'list', 'Tindakan yang diminta beserta tenggat'),
                self::s('closing', 'Penutup', 'text', 'Kalimat penutup'),
            ],
            'guidance' => 'Memo Internal: pesan resmi singkat antarbagian. Langsung ke inti, ringkas, dan jelas tindak lanjutnya.',
            'tips' => [
                'Langsung ke inti',
                'Sebutkan tindakan dan tenggat',
            ],
        ];
    }

    private static function general(DocumentCategory $category): array
    {
        $kind = $category->name.($category->description ? ' ('.$category->description.')' : '');

        return [
            'questions' => [
                ...self::head('Judul Dokumen', 'Contoh: Pedoman Pengelolaan Aset Kantor'),
                self::q('objective', 'Tujuan', 'Apa yang ingin dicapai dengan dokumen ini?', 'Contoh: Menyeragamkan cara pencatatan aset', required: true),
                self::q('scope', 'Ruang Lingkup', 'Untuk siapa / bagian mana dokumen ini berlaku', 'Contoh: Seluruh kantor cabang'),
                self::q('content', 'Isi Pokok', 'Poin-poin yang harus dimuat', 'Contoh: Kategori aset, cara pencatatan, pemeriksaan berkala'),
                self::q('roles', 'Pihak Terkait', 'Siapa yang terlibat dan perannya', 'Contoh: GA: mencatat, Finance: verifikasi'),
                self::q('references', 'Acuan', 'Aturan atau dokumen yang dijadikan dasar', 'Contoh: Kebijakan aset perusahaan 2026'),
            ],
            'sections' => [
                self::s('objective', 'Tujuan', 'text', 'Hasil yang ingin dicapai'),
                self::s('scope', 'Ruang Lingkup', 'text', 'Batas penerapan'),
                self::s('definitions', 'Definisi', 'items', 'Istilah penting', ['Istilah', 'Pengertian']),
                self::s('content', 'Isi', 'items', 'Isi utama per pokok bahasan', ['Pokok bahasan', 'Uraian']),
                self::s('responsibilities', 'Pihak Terkait', 'items', 'Peran dan tanggung jawab', ['Pihak', 'Tanggung jawab']),
                self::s('references', 'Referensi', 'list', 'Dokumen atau aturan acuan'),
            ],
            'guidance' => "Dokumen jenis {$kind} untuk keperluan internal perusahaan. Susun dengan struktur yang lazim untuk jenis dokumen ini.",
            'tips' => [
                'Judul yang jelas dan deskriptif',
                'Tulis isi pokok per poin',
            ],
        ];
    }
}
