<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PooledConnectionTest extends TestCase
{
    public function test_bound_queries_do_not_leave_named_statement_state_in_a_transaction_pool(): void
    {
        DB::transaction(function (): void {
            $pdo = DB::connection()->getPdo();
            $statement = $pdo->prepare('select cast(? as text) as literal, cast(? as integer) as amount');
            $literal = "' ; select 42; --";
            $statement->execute([$literal, 123]);
            $row = $statement->fetch(\PDO::FETCH_ASSOC);
            $this->assertSame($literal, $row['literal']);
            $this->assertSame(123, $row['amount']);
            $this->assertSame(0, (int) DB::selectOne("select count(*) as count from pg_prepared_statements where name like 'pdo_stmt_%'")->count);
            unset($statement);
            $this->assertSame(456, DB::selectOne('select cast(? as integer) as amount', [456])->amount);
        });
    }
}
