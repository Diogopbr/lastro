<?php

namespace Tests\Feature;

use App\Domain\Access\CleanupWorkspaces;
use App\Domain\Access\CurrentWorkspace;
use App\Domain\Access\DemoWorkspace;
use App\Domain\Conferences\CreateConference;
use App\Domain\Imports\ConfirmSource;
use App\Domain\Imports\SourceKind;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class SourceImportTest extends TestCase
{
    use RefreshDatabase;

    private array $conference;

    private string $workspaceId;

    private const ORDERS = "order_id,ordered_at,gross_amount,currency\n0001,2026-05-01,100.00,BRL\n";

    protected function setUp(): void
    {
        parent::setUp();
        $this->workspaceId = (string) Str::uuid();
        DB::table('workspaces')->insert(['id' => $this->workspaceId, 'kind' => 'demo', 'expires_at' => DB::raw("clock_timestamp() + interval '1 day'")]);
        $user = User::factory()->create(['workspace_id' => $this->workspaceId]);
        $this->actingAs($user);
        $this->conference = app(CreateConference::class)->execute(new CurrentWorkspace($this->workspaceId, null, 'demo', $user->id), 'Maio', '2026-05-01', '2026-05-31');
    }

    private function endpoint(string $action): string
    {
        return '/conferencias/'.$this->conference['id'].'/fontes/orders/'.$action;
    }

    public function test_preview_reports_invalid_rows_without_persisting_anything(): void
    {
        $invalid = "order_id,ordered_at,gross_amount,currency\n0001,2026-05-01,100.00,BRL,extra\n";
        $this->postJson($this->endpoint('preview'), ['file' => UploadedFile::fake()->createWithContent('pedidos.csv', $invalid)])
            ->assertOk()->assertJsonPath('valid', false)->assertJsonPath('errors.0.line', 2);
        $this->assertSame(0, DB::table('sources')->count());
        $this->postJson($this->endpoint('confirm'), ['file' => UploadedFile::fake()->createWithContent('pedidos.csv', $invalid), 'expectedHash' => str_repeat('0', 64), 'expectedVersion' => 1])->assertUnprocessable();
        $this->assertSame(0, DB::table('source_rows')->count());
        $this->assertSame(0, DB::table('source_events')->count());
    }

    public function test_confirmation_is_idempotent_across_filename_bom_newlines_and_outer_spaces(): void
    {
        $preview = $this->postJson($this->endpoint('preview'), ['file' => UploadedFile::fake()->createWithContent('pedidos.csv', self::ORDERS)])
            ->assertOk()->assertJsonPath('valid', true)->assertJsonPath('rows.0.orderId', '0001')->assertJsonPath('grossTotal', '100.00')->json();
        $source = $this->postJson($this->endpoint('confirm'), ['file' => UploadedFile::fake()->createWithContent('original.csv', self::ORDERS), 'expectedHash' => $preview['normalizedHash'], 'expectedVersion' => 1])
            ->assertCreated()->assertJsonPath('displayFilename', 'original.csv')->json();
        $equivalent = "\xEF\xBB\xBForder_id,ordered_at,gross_amount,currency\r\n 0001 ,2026-05-01,100.00, BRL \r\n";
        $this->postJson($this->endpoint('confirm'), ['file' => UploadedFile::fake()->createWithContent('renomeado.csv', $equivalent), 'expectedHash' => $preview['normalizedHash'], 'expectedVersion' => 1])
            ->assertOk()->assertJsonPath('id', $source['id'])->assertJsonPath('displayFilename', 'original.csv');
        $this->assertSame(1, DB::table('sources')->count());
        $this->assertSame(1, DB::table('source_rows')->count());
        $this->assertSame(1, DB::table('source_events')->count());
        $this->getJson('/conferencias/'.$this->conference['id'])->assertOk()->assertJsonPath('version', 2)->assertJsonPath('sources.orders.id', $source['id']);
    }

    public function test_file_changed_after_preview_is_rejected_without_partial_import(): void
    {
        $preview = $this->postJson($this->endpoint('preview'), ['file' => UploadedFile::fake()->createWithContent('pedidos.csv', self::ORDERS)])->assertOk()->json();
        $changed = str_replace('100.00', '110.00', self::ORDERS);
        $this->postJson($this->endpoint('confirm'), ['file' => UploadedFile::fake()->createWithContent('pedidos.csv', $changed), 'expectedHash' => $preview['normalizedHash'], 'expectedVersion' => 1])->assertConflict();
        $this->assertSame(0, DB::table('sources')->count());
        $this->assertSame(0, DB::table('source_events')->count());
    }

    public function test_confirmed_source_locks_period_and_rejects_different_replacement(): void
    {
        $preview = $this->postJson($this->endpoint('preview'), ['file' => UploadedFile::fake()->createWithContent('pedidos.csv', self::ORDERS)])->assertOk()->json();
        $this->postJson($this->endpoint('confirm'), ['file' => UploadedFile::fake()->createWithContent('pedidos.csv', self::ORDERS), 'expectedHash' => $preview['normalizedHash'], 'expectedVersion' => 1])->assertCreated();
        $this->patchJson('/conferencias/'.$this->conference['id'], ['name' => 'Maio', 'startDate' => '2026-05-02', 'endDate' => '2026-05-31', 'expectedVersion' => 2])->assertConflict();
        $changed = str_replace('100.00', '110.00', self::ORDERS);
        $newPreview = $this->postJson($this->endpoint('preview'), ['file' => UploadedFile::fake()->createWithContent('novos.csv', $changed)])->assertOk()->json();
        $this->postJson($this->endpoint('confirm'), ['file' => UploadedFile::fake()->createWithContent('novos.csv', $changed), 'expectedHash' => $newPreview['normalizedHash'], 'expectedVersion' => 2])->assertConflict();
        $this->assertSame(1, DB::table('sources')->count());
        $this->assertSame(1, DB::table('source_events')->count());
    }

    public function test_expired_workspace_cannot_confirm_a_previously_valid_preview(): void
    {
        $preview = $this->postJson($this->endpoint('preview'), ['file' => UploadedFile::fake()->createWithContent('pedidos.csv', self::ORDERS)])->assertOk()->json();
        DB::table('workspaces')->where('id', $this->workspaceId)->update(['expires_at' => DB::raw("clock_timestamp() - interval '1 second'")]);
        $this->postJson($this->endpoint('confirm'), ['file' => UploadedFile::fake()->createWithContent('pedidos.csv', self::ORDERS), 'expectedHash' => $preview['normalizedHash'], 'expectedVersion' => 1])->assertNotFound();
        $this->assertSame(0, DB::table('sources')->count());
    }

    public function test_another_workspace_cannot_preview_or_confirm(): void
    {
        $other = (string) Str::uuid();
        DB::table('workspaces')->insert(['id' => $other, 'kind' => 'normal']);
        $this->actingAs(User::factory()->create(['workspace_id' => $other]));
        $this->postJson($this->endpoint('preview'), ['file' => UploadedFile::fake()->createWithContent('pedidos.csv', self::ORDERS)])->assertNotFound()->assertJsonPath('code', 'not_found');
        $this->postJson($this->endpoint('confirm'), ['file' => UploadedFile::fake()->createWithContent('pedidos.csv', self::ORDERS), 'expectedHash' => str_repeat('0', 64), 'expectedVersion' => 1])->assertNotFound()->assertJsonPath('code', 'not_found');
        $this->assertSame(0, DB::table('sources')->count());
    }

    public function test_missing_or_oversized_file_is_rejected_before_import(): void
    {
        $this->postJson($this->endpoint('preview'), [])->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->postJson($this->endpoint('preview'), ['file' => UploadedFile::fake()->createWithContent('large.csv', str_repeat('x', 1048577))])->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->assertSame(0, DB::table('sources')->count());
    }

    public function test_preview_page_uses_the_current_scoped_conference(): void
    {
        $this->get($this->endpoint('preview'))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->component('Imports/Preview')->where('conference.id', $this->conference['id'])->where('kind', 'orders')->where('preview', null));
    }

    public function test_payout_import_preserves_fees_net_and_safe_filename(): void
    {
        $csv = "order_id,payout_id,payout_date,gross_amount,fee_amount,net_amount,currency\n0001,RP-1,2026-05-01,100.00,5.00,95.00,BRL\n";
        $endpoint = str_replace('/orders/', '/payouts/', $this->endpoint('preview'));
        $preview = $this->postJson($endpoint, ['file' => UploadedFile::fake()->createWithContent('repasses.csv', $csv)])->assertOk()->assertJsonPath('rows.0.fee', '5.00')->assertJsonPath('rows.0.net', '95.00')->json();
        $scope = new CurrentWorkspace($this->workspaceId, null, 'demo', auth()->id());
        $source = app(ConfirmSource::class)->execute($scope, $this->conference['id'], SourceKind::Payouts, $csv, $preview['normalizedHash'], 1, "C:\\privado\\repasses\r\n.csv");
        $this->assertSame('repasses.csv', $source->toArray()['displayFilename']);
        $row = DB::table('source_rows')->first();
        $this->assertSame(500, $row->fee_cents);
        $this->assertSame(9500, $row->net_cents);
        DB::table('workspaces')->where('id', $this->workspaceId)->update(['expires_at' => DB::raw("clock_timestamp() - interval '1 second'")]);
        $this->assertSame(1, app(CleanupWorkspaces::class)->batch());
        $this->assertSame(0, DB::table('sources')->count());
        $this->assertSame(0, DB::table('source_rows')->count());
        $this->assertSame(0, DB::table('source_events')->count());
    }

    public function test_stale_version_cannot_confirm_a_new_source(): void
    {
        $preview = $this->postJson($this->endpoint('preview'), ['file' => UploadedFile::fake()->createWithContent('pedidos.csv', self::ORDERS)])->assertOk()->json();
        $this->patchJson('/conferencias/'.$this->conference['id'], ['name' => 'Maio atualizado', 'startDate' => '2026-05-01', 'endDate' => '2026-05-31', 'expectedVersion' => 1])->assertOk();
        $this->postJson($this->endpoint('confirm'), ['file' => UploadedFile::fake()->createWithContent('pedidos.csv', self::ORDERS), 'expectedHash' => $preview['normalizedHash'], 'expectedVersion' => 1])->assertConflict();
        $this->assertSame(0, DB::table('sources')->count());
    }

    public function test_import_limit_survives_reset_of_the_same_visitor(): void
    {
        $token = str_repeat('e', 64);
        $demo = app(DemoWorkspace::class);
        $user = $demo->start($token);
        $this->actingAs($user);
        $conference = DB::table('conferences')->where('workspace_id', $user->workspace_id)->first();
        $endpoint = '/conferencias/'.$conference->id.'/fontes/orders/preview';
        for ($index = 0; $index < 20; $index++) {
            $this->postJson($endpoint, ['file' => UploadedFile::fake()->createWithContent('pedidos.csv', self::ORDERS)])->assertOk();
        }
        $this->postJson($endpoint, ['file' => UploadedFile::fake()->createWithContent('pedidos.csv', self::ORDERS)])->assertTooManyRequests()->assertHeader('Retry-After');
        $replacement = $demo->reset(new CurrentWorkspace($user->workspace_id, null, 'demo', $user->id), $token, '127.0.0.24');
        $this->actingAs($replacement);
        $newConference = DB::table('conferences')->where('workspace_id', $replacement->workspace_id)->first();
        $this->postJson('/conferencias/'.$newConference->id.'/fontes/orders/preview', ['file' => UploadedFile::fake()->createWithContent('pedidos.csv', self::ORDERS)])->assertTooManyRequests()->assertHeader('Retry-After');
        $this->assertSame(0, DB::table('sources')->count());
    }

    public function test_failure_after_rows_insert_rolls_back_the_entire_confirmation(): void
    {
        DB::statement("CREATE FUNCTION reject_source_event() RETURNS trigger LANGUAGE plpgsql AS \$\$ BEGIN RAISE EXCEPTION 'injected import fault'; END; \$\$");
        DB::statement('CREATE TRIGGER reject_source_event BEFORE INSERT ON source_events FOR EACH ROW EXECUTE FUNCTION reject_source_event()');
        $preview = $this->postJson($this->endpoint('preview'), ['file' => UploadedFile::fake()->createWithContent('pedidos.csv', self::ORDERS)])->assertOk()->json();
        $this->postJson($this->endpoint('confirm'), ['file' => UploadedFile::fake()->createWithContent('pedidos.csv', self::ORDERS), 'expectedHash' => $preview['normalizedHash'], 'expectedVersion' => 1])->assertStatus(500);
        $this->assertSame(0, DB::table('sources')->count());
        $this->assertSame(0, DB::table('source_rows')->count());
        $this->assertSame(0, DB::table('source_events')->count());
        $conference = DB::table('conferences')->where('id', $this->conference['id'])->first();
        $this->assertFalse($conference->period_locked);
        $this->assertSame(1, $conference->version);
    }
}
