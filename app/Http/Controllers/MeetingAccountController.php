<?php

namespace App\Http\Controllers;

use App\Models\MeetingAccount;
use App\Models\Training;
use App\Support\Meetings\MeetingProvider;
use App\Support\Meetings\MeetingProviders;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\ForeignKeyConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Akun penyelenggara platform meeting milik pengguna (Zoom, dan platform lain yang punya integrasi).
 */
#[Authorize('viewAny', Training::class)]
class MeetingAccountController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        $accounts = $this->ownedAccounts($request)->select(['id', 'name', 'provider'])->orderBy('name')->orderBy('id');

        if ($request->expectsJson()) {
            return response()->json(['accounts' => $accounts->get()])
                ->header('Cache-Control', 'no-store, private');
        }

        return view('training.meeting-accounts', [
            'active' => 'training',
            'title' => 'Akun Meeting',
            'providers' => MeetingProviders::withAccounts(),
            'accounts' => $accounts->withExists(['trainings' => fn ($trainings) => $trainings->withTrashed()])
                ->paginate(15),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $provider = MeetingProviders::find(Validator::make($request->only('provider'), [
            'provider' => ['required', Rule::in(array_keys(MeetingProviders::withAccounts()))],
        ], [], ['provider' => 'platform'])->validate()['provider']);

        MeetingAccount::create([
            ...$this->validateAccount($request, $provider, creating: true),
            'provider' => $provider->key(),
            'user_id' => $request->user()->getKey(),
        ]);

        return to_route('training.meeting-accounts.index')->with('success', "Akun {$provider->label()} ditambahkan.");
    }

    public function update(Request $request, string $meetingAccount): RedirectResponse
    {
        $account = $this->ownedAccounts($request)->findOrFail($meetingAccount);
        $provider = $account->meetingProvider();
        abort_if($provider === null, 404);
        $account->update($this->validateAccount($request, $provider, creating: false));

        return to_route('training.meeting-accounts.index')->with('success', "Akun {$provider->label()} diperbarui.");
    }

    public function destroy(Request $request, string $meetingAccount): RedirectResponse
    {
        try {
            $deleted = DB::transaction(function () use ($request, $meetingAccount): bool {
                $account = $this->ownedAccounts($request)->lockForUpdate()->findOrFail($meetingAccount);
                if ($account->trainings()->withTrashed()->exists()) {
                    return false;
                }

                return (bool) $account->delete();
            });
        } catch (ForeignKeyConstraintViolationException) {
            $deleted = false;
        }

        if (! $deleted) {
            return to_route('training.meeting-accounts.index')->with('error', 'Akun masih dipakai pada pelatihan.');
        }

        return to_route('training.meeting-accounts.index')->with('success', 'Akun dihapus.');
    }

    private function ownedAccounts(Request $request): Builder
    {
        return MeetingAccount::where('user_id', $request->user()->getKey());
    }

    /**
     * Nama akun dan kredensial sesuai platformnya. Saat memperbarui, kredensial boleh dikosongkan seluruhnya
     * (tidak diganti) atau diisi seluruhnya.
     *
     * @return array{name: string, credentials?: array<string, string>}
     */
    private function validateAccount(Request $request, MeetingProvider $provider, bool $creating): array
    {
        $body = $request->isJson() ? $request->json() : $request->request;
        $data = $body->all();

        // Keep credentials out of the old input Laravel flashes after a validation failure.
        $body->remove('credentials');
        $request->query->remove('credentials');

        $fields = $provider->credentialFields();
        $rules = ['name' => ['required', 'string', 'max:100'], 'credentials' => ['nullable', 'array']];
        $attributes = ['name' => 'nama akun'];
        foreach ($fields as $field => $definition) {
            $others = array_map(fn (string $other) => "credentials.$other", array_diff(array_keys($fields), [$field]));
            $rules["credentials.$field"] = [
                $creating ? 'required' : 'required_with:'.implode(',', $others),
                'nullable', 'string', 'max:'.($definition['max'] ?? 512),
            ];
            $attributes["credentials.$field"] = $definition['label'];
        }

        $validated = Validator::make($data, $rules, [], $attributes)->validate();
        $credentials = array_filter(
            array_intersect_key($validated['credentials'] ?? [], $fields),
            fn ($value) => $value !== null,
        );
        if (($errors = $provider->credentialErrors($credentials)) !== []) {
            throw ValidationException::withMessages(Arr::prependKeysWith($errors, 'credentials.'));
        }

        return ['name' => $validated['name'], ...($credentials === [] ? [] : ['credentials' => $credentials])];
    }
}
