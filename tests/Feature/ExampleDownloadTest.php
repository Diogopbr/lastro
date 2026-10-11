<?php

namespace Tests\Feature;

use App\Domain\Imports\CsvImportValidator;
use App\Domain\Imports\SourceKind;
use DateTimeImmutable;
use Tests\TestCase;

class ExampleDownloadTest extends TestCase
{
    public function test_templates_have_exact_headers_and_fixed_download_names(): void
    {
        foreach (SourceKind::cases() as $kind) {
            $response = $this->get('/modelos/'.$kind->value.'.csv')->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8')->assertHeader('Content-Disposition', 'attachment; filename="lastro-modelo-'.$kind->value.'.csv"');
            $this->assertSame(implode(',', $kind->headers())."\n", $response->getContent());
        }
        $this->get('/modelos/../../settings.csv')->assertNotFound();
        $this->get('/exemplos/unknown.csv')->assertNotFound();
    }

    public function test_examples_are_fixed_fictitious_data_accepted_by_the_real_parser(): void
    {
        foreach (SourceKind::cases() as $kind) {
            $response = $this->get('/exemplos/'.$kind->value.'.csv')->assertOk();
            $preview = app(CsvImportValidator::class)->validate($kind, $response->getContent(), new DateTimeImmutable('2026-05-01'), new DateTimeImmutable('2026-05-31'));
            $this->assertTrue($preview->valid);
            $this->assertSame(3, $preview->rowCount);
            $this->assertSame($kind === SourceKind::Orders ? '600.00' : '690.00', $preview->grossTotal);
            $this->assertSame('0001', $preview->rows[0]->orderId);
            $this->assertSame($response->getContent(), $this->get('/exemplos/'.$kind->value.'.csv')->assertOk()->getContent());
        }
    }
}
