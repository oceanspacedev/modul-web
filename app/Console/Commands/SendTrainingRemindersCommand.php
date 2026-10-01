<?php

namespace App\Console\Commands;

use App\Models\Training;
use App\Services\WhatsAppService;
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
            ->whereIn('status', ['scheduled', 'ongoing'])
            ->with(['participants.user', 'trainer'])
            ->get();

        if ($trainings->isEmpty()) {
            $this->info("Tidak ada pelatihan yang dijadwalkan hari ini.");
            return Command::SUCCESS;
        }

        foreach ($trainings as $training) {
            $this->info("Memproses pelatihan: {$training->title}");
            $unnotified = $training->participants()->whereNull('wa_sent_at')->get();

            foreach ($unnotified as $participant) {
                $res = WhatsAppService::sendToParticipant($participant);
                if ($res['status']) {
                    $this->info("  [✓] WA terkirim ke {$participant->user->full_name}");
                } else {
                    $this->warn("  [✗] Gagal kirim ke {$participant->user->full_name}: {$res['message']}");
                }
            }
        }

        $this->info("Selesai memproses pengingat pelatihan.");
        return Command::SUCCESS;
    }
}
