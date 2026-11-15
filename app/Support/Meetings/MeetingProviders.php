<?php

namespace App\Support\Meetings;

class MeetingProviders
{
    /** @var array<string, MeetingProvider>|null */
    private static ?array $providers = null;

    /** @return array<string, MeetingProvider> */
    public static function all(): array
    {
        return self::$providers ??= collect([
            new ZoomProvider,
            new GoogleMeetProvider,
            new TeamsProvider,
            new OtherProvider,
        ])->keyBy(fn (MeetingProvider $provider) => $provider->key())->all();
    }

    public static function find(?string $key): ?MeetingProvider
    {
        return self::all()[$key ?? ''] ?? null;
    }

    /** Platform yang cocok dengan host tautan; tautan lain dianggap "Lainnya". Tanpa tautan berarti null. */
    public static function fromLink(?string $link): ?MeetingProvider
    {
        if (blank($link)) {
            return null;
        }

        $host = strtolower((string) parse_url($link, PHP_URL_HOST));
        foreach (self::all() as $provider) {
            if ($provider->matchesHost($host)) {
                return $provider;
            }
        }

        return self::find('other');
    }

    /** @return array<string, string> */
    public static function labels(): array
    {
        return array_map(fn (MeetingProvider $provider) => $provider->label(), self::all());
    }

    /**
     * Platform yang integrasinya membutuhkan akun penyelenggara.
     *
     * @return array<string, MeetingProvider>
     */
    public static function withAccounts(): array
    {
        return array_filter(self::all(), fn (MeetingProvider $provider) => $provider->credentialFields() !== []);
    }
}
