<?php

namespace App\Http\Controllers\Imports;

use App\Domain\Access\CurrentWorkspace;
use App\Domain\Access\WorkspaceTransaction;
use App\Domain\Conferences\ConferenceProjection;
use App\Domain\Imports\ConfirmSource;
use App\Domain\Imports\CsvImportValidator;
use App\Domain\Imports\CsvStructureValidator;
use App\Domain\Imports\SourceKind;
use App\Http\Controllers\Controller;
use DateTimeImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class SourceController extends Controller
{
    public function show(Request $request, string $id, string $kind, WorkspaceTransaction $transaction): Response
    {
        $scope = $this->scope($request);
        $props = $transaction->run($scope, function () use ($scope, $id, $kind): array {
            $conference = $this->conference($scope, $id);

            return ['conference' => ConferenceProjection::fromRow($conference), 'kind' => $kind, 'preview' => null];
        });

        return Inertia::render('Imports/Preview', $props)->toResponse($request);
    }

    public function preview(Request $request, string $id, string $kind, WorkspaceTransaction $transaction, CsvImportValidator $validator): Response
    {
        $scope = $this->scope($request);
        $preview = $transaction->run($scope, function () use ($scope, $id, $kind, $request, $validator): array {
            $conference = $this->conference($scope, $id);
            $bytes = $this->bytes($request);

            return $validator->validate(SourceKind::from($kind), $bytes, new DateTimeImmutable($conference->start_date), new DateTimeImmutable($conference->end_date))->toArray();
        });

        return response()->json($preview);
    }

    public function confirm(Request $request, string $id, string $kind, ConfirmSource $confirm): Response
    {
        $input = $request->validate(['expectedHash' => 'required|string|regex:/\A[0-9a-f]{64}\z/', 'expectedVersion' => 'required|integer|min:1']);
        $bytes = $this->bytes($request);
        $source = $confirm->execute($this->scope($request), $id, SourceKind::from($kind), $bytes, $input['expectedHash'], (int) $input['expectedVersion'], $request->file('file')->getClientOriginalName());

        return $request->expectsJson() ? response()->json($source->toArray(), $source->created ? 201 : 200) : redirect()->route('conferences.show', ['id' => $id], 303);
    }

    private function bytes(Request $request): string
    {
        $request->validate(['file' => 'required|file|max:1024']);
        $file = $request->file('file');
        if ($file->getSize() > CsvStructureValidator::MAX_BYTES) {
            throw ValidationException::withMessages(['file' => 'O arquivo deve ter no máximo 1048576 bytes.']);
        }
        $bytes = file_get_contents($file->getRealPath(), false, null, 0, CsvStructureValidator::MAX_BYTES + 1);
        if ($bytes === false || strlen($bytes) > CsvStructureValidator::MAX_BYTES) {
            throw ValidationException::withMessages(['file' => 'Não foi possível ler um arquivo de até 1048576 bytes.']);
        }

        return $bytes;
    }

    private function conference(CurrentWorkspace $scope, string $id): object
    {
        $conference = DB::table('conferences')->where('workspace_id', $scope->id)->where('id', $id)->first();
        abort_if($conference === null, 404);

        return $conference;
    }

    private function scope(Request $request): CurrentWorkspace
    {
        return $request->attributes->get('workspace');
    }
}
