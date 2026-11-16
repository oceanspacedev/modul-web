<?php

namespace Tests\Unit;

use App\Support\Meetings\MeetingProviders;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MeetingProvidersTest extends TestCase
{
    #[DataProvider('links')]
    public function test_a_link_is_matched_to_its_platform_and_meeting_code(string $link, string $platform, ?string $code): void
    {
        $provider = MeetingProviders::fromLink($link);

        $this->assertSame($platform, $provider->key());
        $this->assertSame($code, $provider->meetingCode($link));
    }

    public static function links(): array
    {
        return [
            'zoom join link' => ['https://company.zoom.us/j/81234567890?pwd=x', 'zoom', '81234567890'],
            'zoom web client link' => ['https://zoom.us/wc/join/123456789', 'zoom', '123456789'],
            'zoom personal link has no code' => ['https://zoom.us/my/pelatih', 'zoom', null],
            'zoom on another zoom domain' => ['https://example.zoomgov.com/j/1234567890', 'zoom', null],
            'lookalike zoom host' => ['https://zoom.us.example.org/j/81234567890', 'other', null],
            'google meet' => ['https://meet.google.com/abc-defg-hij?authuser=0', 'google_meet', 'abc-defg-hij'],
            'google meet lookup link has no code' => ['https://meet.google.com/lookup/abc123', 'google_meet', null],
            'teams short link' => ['https://teams.microsoft.com/meet/2345678901234?p=x', 'teams', '2345678901234'],
            'teams long join link' => ['https://teams.microsoft.com/l/meetup-join/19%3ameeting_MGQ4MDQy%40thread.v2/0?context=%7b%7d', 'teams', '19:meeting_MGQ4MDQy@thread.v2'],
            'teams link without a meeting' => ['https://teams.microsoft.com/l/channel/19%3aabc/General', 'teams', null],
            'other platform' => ['https://meet.jit.si/pelatihan', 'other', null],
        ];
    }

    public function test_each_platform_declares_its_own_integration(): void
    {
        $this->assertNull(MeetingProviders::fromLink(null));
        $this->assertSame(['zoom', 'google_meet', 'teams', 'other'], array_keys(MeetingProviders::all()));
        $this->assertSame(['zoom', 'google_meet', 'teams'], array_keys(MeetingProviders::withAccounts()));
        $this->assertFalse(MeetingProviders::find('other')->hasIntegration());

        // Zoom dipantau langsung dari browser; Meet dan Teams ditarik dari API setelah rapat.
        foreach (['zoom' => [true, false], 'google_meet' => [false, true], 'teams' => [false, true]] as $key => [$live, $sync]) {
            $this->assertSame($live, MeetingProviders::find($key)->supportsMonitoring(), $key);
            $this->assertSame($sync, MeetingProviders::find($key)->supportsAttendanceSync(), $key);
        }
    }
}
