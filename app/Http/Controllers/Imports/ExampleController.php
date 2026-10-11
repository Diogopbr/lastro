<?php

namespace App\Http\Controllers\Imports;

use App\Domain\Demo\ConferenceExamples;
use App\Domain\Imports\SourceKind;
use App\Http\Controllers\Controller;
use Symfony\Component\HttpFoundation\Response;

class ExampleController extends Controller
{
    public function template(string $kind): Response
    {
        return $this->download(SourceKind::from($kind), false);
    }

    public function example(string $kind): Response
    {
        return $this->download(SourceKind::from($kind), true);
    }

    private function download(SourceKind $kind, bool $example): Response
    {
        $content = $example ? ConferenceExamples::csv($kind) : ConferenceExamples::template($kind);
        $type = $example ? 'exemplo' : 'modelo';

        return response($content, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="lastro-'.$type.'-'.$kind->value.'.csv"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
