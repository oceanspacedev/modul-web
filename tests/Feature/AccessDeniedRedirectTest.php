<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class AccessDeniedRedirectTest extends TestCase
{
    private function staff(): User
    {
        return User::factory()->staff()->create();
    }

    public function test_forbidden_page_goes_back_to_the_previous_page(): void
    {
        $staff = $this->staff();

        $this->actingAs($staff)
            ->withSession(['_previous.url' => url('/panduan')])
            ->get('/user')
            ->assertRedirect(url('/panduan'))
            ->assertSessionHas('error');
    }

    public function test_forbidden_page_never_redirects_to_itself(): void
    {
        $staff = $this->staff();

        $this->actingAs($staff)
            ->withSession(['_previous.url' => url('/user')])
            ->get('/user')
            ->assertRedirect(route('training.my-trainings'))
            ->assertSessionHas('error');
    }

    public function test_forbidden_page_with_same_referer_falls_back_instead_of_looping(): void
    {
        $staff = $this->staff();

        $this->actingAs($staff)
            ->withSession(['_previous.url' => url('/user')])
            ->get('/user', ['referer' => url('/user')])
            ->assertRedirect(route('training.my-trainings'));
    }

    public function test_forbidden_page_without_history_lands_on_my_trainings(): void
    {
        $staff = $this->staff();

        $this->actingAs($staff)
            ->get('/user')
            ->assertRedirect(route('training.my-trainings'));
    }

    public function test_forbidden_json_request_still_returns_403(): void
    {
        $staff = $this->staff();

        $this->actingAs($staff)
            ->getJson('/user')
            ->assertStatus(403);
    }
}
