<?php

namespace App\Console\Commands;

use App\Domain\Access\CleanupWorkspaces;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('lastro:cleanup {--batches=1000 : Máximo de lotes de dez espaços nesta execução}')]
#[Description('Remove dados de espaços de demonstração vencidos ou revogados')]
class CleanupDemoWorkspaces extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(CleanupWorkspaces $cleanup): int
    {
        $budget = filter_var($this->option('batches'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000]]);
        if ($budget === false) {
            $this->error('Informe batches entre 1 e 1000.');

            return self::FAILURE;
        }
        $total = 0;
        for ($index = 0; $index < $budget; $index++) {
            $count = $cleanup->batch();
            $total += $count;
            if ($count < 10) {
                break;
            }
        }
        $this->line('Espaços removidos: '.$total);

        return self::SUCCESS;
    }
}
