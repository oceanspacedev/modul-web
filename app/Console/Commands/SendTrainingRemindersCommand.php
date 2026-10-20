<?php

namespace App\Console\Commands;

use App\Enums\TrainingStatus;
use App\Jobs\SendTrainingInvitation;
use App\Models\Training;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendTrainingRemindersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'training:send-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Kirim notifikasi WhatsApp ke peserta pelatihan yang dijadwalkan hari ini';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $today = Carbon::today()->toDateString();
        $this->info("Memeriksa pelatihan untuk tanggal: {$today}");

        $trainings = Training::where('training_date', $today)
            ->whereIn('status', [TrainingStatus::Scheduled, TrainingStatus::Ongoing])
            ->get();

        if ($trainings->isEmpty()) {
            $this->info('Tidak ada pelatihan yang dijadwalkan hari ini.');

            return Command::SUCCESS;
        }

        foreach ($trainings as $training) {
            $queued = SendTrainingInvitation::dispatchForTraining($training, onlyUnnotified: true);
            $this->info("Memproses pelatihan: {$training->title} ({$queued} pengingat dimasukkan ke antrean)");
        }

        $this->info('Selesai memproses pengingat pelatihan.');

        return Command::SUCCESS;
    }
}
