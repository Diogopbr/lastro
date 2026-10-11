<?php

namespace Database\Seeders;

use App\Domain\Access\CurrentWorkspace;
use App\Domain\Conferences\CreateConference;

final class DemoSeeder
{
    public function __construct(private CreateConference $createConference) {}

    public function seed(CurrentWorkspace $scope): void
    {
        foreach (['Marketplace — maio', 'Loja online — maio', 'Pedidos avulsos — maio'] as $name) {
            $this->createConference->execute($scope, $name, '2026-05-01', '2026-05-31');
        }
    }
}
