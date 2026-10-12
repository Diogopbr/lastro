<?php

namespace Database\Seeders;

use App\Domain\Access\CurrentWorkspace;
use App\Domain\Conferences\CreateConference;
use App\Domain\Demo\ConferenceExamples;
use App\Domain\Imports\ConfirmSource;
use App\Domain\Imports\CsvImportValidator;
use App\Domain\Imports\SourceKind;
use App\Domain\Reconciliation\ReconcileConference;
use DateTimeImmutable;

final class DemoSeeder
{
    public function __construct(private CreateConference $createConference, private ConfirmSource $confirmSource, private CsvImportValidator $validator, private ReconcileConference $reconcile) {}

    public function seed(CurrentWorkspace $scope): void
    {
        foreach (['marketplace' => 'Marketplace — maio', 'store' => 'Loja online — maio', 'loose' => 'Pedidos avulsos — maio'] as $scenario => $name) {
            $conference = $this->createConference->execute($scope, $name, '2026-05-01', '2026-05-31');
            if (! config('lastro.demo_examples')) {
                continue;
            }
            $version = $conference['version'];
            foreach (SourceKind::cases() as $kind) {
                $csv = ConferenceExamples::csv($kind, $scenario);
                $preview = $this->validator->validate($kind, $csv, new DateTimeImmutable('2026-05-01'), new DateTimeImmutable('2026-05-31'));
                $this->confirmSource->execute($scope, $conference['id'], $kind, $csv, $preview->normalizedHash, $version, $scenario.'-'.$kind->value.'-exemplo.csv');
                $version++;
            }
            $this->reconcile->execute($scope, $conference['id']);
        }
    }
}
