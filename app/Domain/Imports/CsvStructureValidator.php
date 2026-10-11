<?php

namespace App\Domain\Imports;

final class CsvStructureValidator
{
    public const MAX_BYTES = 1048576;

    public const MAX_RECORDS = 100;

    /** @return list<array{line:int,fieldCount:int}> */
    public function inspect(string $bytes): array
    {
        if (strlen($bytes) > self::MAX_BYTES) {
            throw new CsvValidationException(1, 'file', 'file_too_large', 'O arquivo deve ter no máximo 1048576 bytes.');
        }

        if (str_starts_with($bytes, "\xEF\xBB\xBF")) {
            $bytes = substr($bytes, 3);
        }
        if ($bytes === '') {
            throw new CsvValidationException(1, 'file', 'empty_file', 'O arquivo CSV está vazio.');
        }

        $records = [];
        $state = 'start';
        $line = 1;
        $recordLine = 1;
        $recordStart = 0;
        $fieldCount = 1;
        $length = strlen($bytes);

        for ($offset = 0; $offset < $length; $offset++) {
            $character = $bytes[$offset];
            if ($character === "\0") {
                throw new CsvValidationException($recordLine, 'file', 'invalid_control', 'O arquivo contém um caractere NUL.');
            }
            if ($character === "\r" || $character === "\n") {
                $recordEnd = $offset;
                if ($character === "\r") {
                    if ($offset + 1 >= $length || $bytes[$offset + 1] !== "\n") {
                        throw new CsvValidationException($recordLine, 'record', 'invalid_newline', 'Use quebras de linha LF ou CRLF.');
                    }
                    $offset++;
                }
                $line++;
                if ($state === 'quoted') {
                    continue;
                }
                $this->appendRecord($records, substr($bytes, $recordStart, $recordEnd - $recordStart), $recordLine, $fieldCount);
                $recordStart = $offset + 1;
                $recordLine = $line;
                $fieldCount = 1;
                $state = 'start';

                continue;
            }
            if ($state === 'quoted') {
                if ($character === '"') {
                    if ($offset + 1 < $length && $bytes[$offset + 1] === '"') {
                        $offset++;
                    } else {
                        $state = 'closed';
                    }
                }

                continue;
            }
            if ($character === ',') {
                $fieldCount++;
                $state = 'start';

                continue;
            }
            if ($character === '"') {
                if ($state !== 'start') {
                    throw new CsvValidationException($recordLine, 'record', 'invalid_quotes', 'As aspas do registro estão malformadas.');
                }
                $state = 'quoted';

                continue;
            }
            if ($state === 'closed') {
                throw new CsvValidationException($recordLine, 'record', 'invalid_quotes', 'Após fechar aspas, use vírgula ou fim de registro.');
            }
            $state = 'unquoted';
        }

        if ($state === 'quoted') {
            throw new CsvValidationException($recordLine, 'record', 'invalid_quotes', 'O registro contém aspas não fechadas.');
        }
        if ($recordStart < $length) {
            $this->appendRecord($records, substr($bytes, $recordStart), $recordLine, $fieldCount);
        }

        return $records;
    }

    /** @param list<array{line:int,fieldCount:int}> $records */
    private function appendRecord(array &$records, string $record, int $line, int $fieldCount): void
    {
        if (! mb_check_encoding($record, 'UTF-8')) {
            throw new CsvValidationException($line, 'file', 'invalid_utf8', 'O arquivo deve usar UTF-8 válido.');
        }
        if (trim($record, ' ') === '') {
            throw new CsvValidationException($line, 'record', 'blank_record', 'O arquivo contém um registro vazio.');
        }
        if (count($records) >= self::MAX_RECORDS + 1) {
            throw new CsvValidationException($line, 'file', 'record_count', 'A fonte deve conter entre 1 e 100 registros de dados.');
        }
        $records[] = ['line' => $line, 'fieldCount' => $fieldCount];
    }
}
