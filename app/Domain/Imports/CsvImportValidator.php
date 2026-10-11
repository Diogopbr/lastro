<?php

namespace App\Domain\Imports;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use League\Csv\Reader;

final class CsvImportValidator
{
    public function __construct(private readonly CsvStructureValidator $structure = new CsvStructureValidator) {}

    public function validate(SourceKind $kind, string $bytes, DateTimeImmutable $start, DateTimeImmutable $end): ImportPreview
    {
        $startDate = $start->format('Y-m-d');
        $endDate = $end->format('Y-m-d');
        if ($startDate > $endDate) {
            return $this->failure(new CsvValidationException(1, 'period', 'invalid_period', 'O início do período deve ser anterior ou igual ao fim.'));
        }

        try {
            $structure = $this->structure->inspect($bytes);
        } catch (CsvValidationException $exception) {
            return $this->failure($exception);
        }

        $rowCount = count($structure) - 1;
        if ($rowCount < 1) {
            return $this->failure(new CsvValidationException(1, 'file', 'record_count', 'A fonte deve conter entre 1 e 100 registros de dados.'));
        }

        $headers = $kind->headers();
        if ($structure[0]['fieldCount'] !== count($headers)) {
            return $this->failure(new CsvValidationException(1, 'header', 'invalid_header', 'O cabeçalho deve ser exatamente: '.implode(',', $headers).'.'), $rowCount);
        }
        $widthErrors = [];
        foreach (array_slice($structure, 1) as $record) {
            if ($record['fieldCount'] !== count($headers)) {
                $widthErrors[] = $this->error($record['line'], 'record', 'field_count', 'O registro tem quantidade de campos diferente do cabeçalho.');
            }
        }
        if ($widthErrors !== []) {
            return new ImportPreview(false, null, $rowCount, 0, [], $widthErrors);
        }

        $reader = Reader::fromString($bytes)->setEscape('')->includeEmptyRecords();
        $records = iterator_to_array($reader->getRecords(), false);
        if ($records[0] !== $headers) {
            return $this->failure(new CsvValidationException(1, 'header', 'invalid_header', 'O cabeçalho deve ser exatamente: '.implode(',', $headers).'.'), $rowCount);
        }

        $rows = [];
        $errors = [];
        $seen = [];
        $total = 0;
        foreach (array_slice($records, 1) as $index => $record) {
            $line = $structure[$index + 1]['line'];
            $rowErrors = [];
            if ($record === $headers) {
                $errors[] = $this->error($line, 'record', 'repeated_header', 'O cabeçalho não pode ser repetido como registro.');

                continue;
            }
            $fields = [];
            foreach ($headers as $column => $field) {
                $value = $record[$column] ?? '';
                if (preg_match('/[\p{Cc}\p{Cf}\p{Zl}\p{Zp}]/u', $value) === 1) {
                    $rowErrors[] = $this->error($line, $field, 'invalid_control', 'O campo não pode conter controles ou quebras de linha.');
                } elseif (trim($value, ' ') === '') {
                    $rowErrors[] = $this->error($line, $field, 'required', 'O campo é obrigatório.');
                }
                $fields[$field] = trim($value, ' ');
            }

            foreach (['order_id', ...($kind === SourceKind::Payouts ? ['payout_id'] : [])] as $field) {
                if (! $this->hasError($rowErrors, $field) && mb_strlen($fields[$field], 'UTF-8') > 100) {
                    $rowErrors[] = $this->error($line, $field, 'identifier_length', 'O identificador deve ter entre 1 e 100 caracteres.');
                }
            }
            if (! $this->hasError($rowErrors, 'order_id')) {
                $key = 'id:'.$fields['order_id'];
                if (isset($seen[$key])) {
                    $rowErrors[] = $this->error($line, 'order_id', 'duplicate_order_id', 'O order_id não pode repetir na mesma fonte.');
                }
                $seen[$key] = true;
            }

            $dateField = $kind === SourceKind::Orders ? 'ordered_at' : 'payout_date';
            if (! $this->hasError($rowErrors, $dateField)) {
                $date = $fields[$dateField];
                $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone('UTC'));
                if (preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/', $date) !== 1 || $parsed === false || $parsed->format('Y-m-d') !== $date || substr($date, 0, 4) === '0000') {
                    $rowErrors[] = $this->error($line, $dateField, 'invalid_date', 'Informe uma data ISO válida no formato YYYY-MM-DD.');
                } elseif ($date < $startDate || $date > $endDate) {
                    $rowErrors[] = $this->error($line, $dateField, 'outside_period', 'A data deve estar dentro do período da conferência, inclusive os extremos.');
                }
            }
            if (! $this->hasError($rowErrors, 'currency') && $fields['currency'] !== 'BRL') {
                $rowErrors[] = $this->error($line, 'currency', 'invalid_currency', 'A moeda deve ser BRL.');
            }

            $money = [];
            foreach (['gross_amount', ...($kind === SourceKind::Payouts ? ['fee_amount', 'net_amount'] : [])] as $field) {
                if ($this->hasError($rowErrors, $field)) {
                    continue;
                }
                try {
                    $money[$field] = Money::parse($fields[$field]);
                } catch (InvalidArgumentException $exception) {
                    $rowErrors[] = $this->error($line, $field, 'invalid_money', $exception->getMessage());
                }
            }
            if (isset($money['gross_amount'])) {
                if ($money['gross_amount'] === 0) {
                    $rowErrors[] = $this->error($line, 'gross_amount', 'gross_not_positive', 'O valor bruto deve ser positivo.');
                }
                if ($kind === SourceKind::Payouts && isset($money['fee_amount'])) {
                    if ($money['fee_amount'] > $money['gross_amount']) {
                        $rowErrors[] = $this->error($line, 'fee_amount', 'fee_exceeds_gross', 'A taxa não pode exceder o valor bruto.');
                    } elseif (isset($money['net_amount']) && $money['net_amount'] !== $money['gross_amount'] - $money['fee_amount']) {
                        $rowErrors[] = $this->error($line, 'net_amount', 'net_mismatch', 'O líquido deve ser igual ao bruto menos a taxa.');
                    }
                }
            }
            if ($rowErrors !== []) {
                array_push($errors, ...$rowErrors);

                continue;
            }
            $row = new ImportRow(
                $line, $fields['order_id'], $fields['payout_id'] ?? null, $fields[$dateField],
                $money['gross_amount'], $money['fee_amount'] ?? null, $money['net_amount'] ?? null,
            );
            $rows[] = $row;
            $total += $row->grossCents;
        }

        $valid = $errors === [];
        $hash = $valid ? hash('sha256', json_encode(
            ['kind' => $kind->value, 'rows' => array_map(fn (ImportRow $row): array => $row->canonical(), $rows)],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        )) : null;

        return new ImportPreview($valid, $hash, $rowCount, $total, $rows, array_slice($errors, 0, 100));
    }

    /** @param list<array{line:int,field:string,code:string,message:string}> $errors */
    private function hasError(array $errors, string $field): bool
    {
        foreach ($errors as $error) {
            if ($error['field'] === $field) {
                return true;
            }
        }

        return false;
    }

    /** @return array{line:int,field:string,code:string,message:string} */
    private function error(int $line, string $field, string $code, string $message): array
    {
        return ['line' => $line, 'field' => $field, 'code' => $code, 'message' => $message];
    }

    private function failure(CsvValidationException $exception, int $rowCount = 0): ImportPreview
    {
        return new ImportPreview(false, null, $rowCount, 0, [], [$exception->toArray()]);
    }
}
