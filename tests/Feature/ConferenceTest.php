<?php

namespace Tests\Feature;

use App\Domain\Access\CurrentWorkspace;
use App\Domain\Conferences\CreateConference;
use App\Domain\Conferences\UpdateConference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class ConferenceTest extends TestCase
{
    use RefreshDatabase;

    private function workspace(): CurrentWorkspace
    {
        $id = (string) Str::uuid();
        DB::table('workspaces')->insert(['id' => $id, 'kind' => 'demo', 'expires_at' => DB::raw("clock_timestamp() + interval '24 hours'")]);

        return new CurrentWorkspace($id, null, 'demo', (string) Str::uuid());
    }

    public function test_rejects_an_inverted_period_without_creating_a_conference(): void
    {
        $scope = $this->workspace();
        try {
            app(CreateConference::class)->execute($scope, 'Maio', '2026-05-31', '2026-05-01');
            $this->fail('Inverted period accepted');
        } catch (ValidationException) {
            $this->assertSame(0, DB::table('conferences')->count());
        }
    }

    public function test_creates_and_updates_a_versioned_conference(): void
    {
        $scope = $this->workspace();
        $created = app(CreateConference::class)->execute($scope, 'Maio', '2026-05-01', '2026-05-31');
        $this->assertSame('LT-0001', $created['code']);
        $this->assertSame(1, $created['version']);
        $updated = app(UpdateConference::class)->execute($scope, $created['id'], 'Maio revisado', '2026-05-01', '2026-05-31', 1);
        $this->assertSame(2, $updated['version']);
        $this->assertSame('Maio revisado', $updated['name']);
        try {
            app(UpdateConference::class)->execute($scope, $created['id'], 'Antigo', '2026-05-01', '2026-05-31', 1);
            $this->fail('Stale version accepted');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
            $this->assertSame('Maio revisado', DB::table('conferences')->value('name'));
        }
    }

    public function test_demo_accepts_fifth_conference_and_rejects_sixth(): void
    {
        $scope = $this->workspace();
        for ($index = 1; $index <= 5; $index++) {
            app(CreateConference::class)->execute($scope, 'Maio '.$index, '2026-05-01', '2026-05-31');
        }
        try {
            app(CreateConference::class)->execute($scope, 'Excesso', '2026-05-01', '2026-05-31');
            $this->fail('Sixth conference accepted');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
            $this->assertSame(5, DB::table('conferences')->count());
        }
    }

    public function test_cannot_update_another_visitors_conference(): void
    {
        $first = $this->workspace();
        $second = $this->workspace();
        $conference = app(CreateConference::class)->execute($first, 'Maio', '2026-05-01', '2026-05-31');
        $this->expectException(NotFoundHttpException::class);
        app(UpdateConference::class)->execute($second, $conference['id'], 'Roubo', '2026-05-01', '2026-05-31', 1);
    }

    public function test_imported_period_is_locked_but_name_can_change(): void
    {
        $scope = $this->workspace();
        $conference = app(CreateConference::class)->execute($scope, 'Maio', '2026-05-01', '2026-05-31');
        DB::table('conferences')->where('id', $conference['id'])->update(['period_locked' => true]);
        $renamed = app(UpdateConference::class)->execute($scope, $conference['id'], 'Outro nome', '2026-05-01', '2026-05-31', 1);
        $this->assertSame('Outro nome', $renamed['name']);
        try {
            app(UpdateConference::class)->execute($scope, $conference['id'], 'Mudado', '2026-05-02', '2026-05-31', 2);
            $this->fail('Imported period changed');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
            $this->assertSame('2026-05-01', DB::table('conferences')->value('start_date'));
        }
    }
}
