<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Repoints custodianship from `users` to the new `employees` table. A custodian is a
 * person, not a login account — so every column that previously referenced users.id now
 * references employees.id. Existing custodian-users are backfilled as employee rows
 * (linked via employees.user_id) and the stored ids are remapped before the FK is swapped.
 *
 * On a fresh install the referenced tables are empty when this runs (seeders come after
 * migrations), so the backfill/remap are no-ops and only the FK constraint is swapped.
 */
return new class extends Migration
{
    /** [table, column] pairs that currently FK to users.id as a custodian. */
    private array $columns = [
        ['assets', 'custodian_id'],
        ['asset_movements', 'from_custodian_id'],
        ['asset_movements', 'to_custodian_id'],
        ['asset_movement_batches', 'to_custodian_id'],
        ['kit_assignments', 'to_custodian_id'],
        // M10 snapshots assets.custodian_id (now an employee id) into this column.
        ['audit_items', 'expected_custodian_id'],
    ];

    public function up(): void
    {
        $map = $this->backfillEmployees();

        foreach ($this->columns as [$table, $column]) {
            Schema::table($table, function (Blueprint $t) use ($column) {
                $t->dropForeign([$column]);
            });

            foreach ($map as $userId => $employeeId) {
                DB::table($table)->where($column, $userId)->update([$column => $employeeId]);
            }

            Schema::table($table, function (Blueprint $t) use ($column) {
                $t->foreign($column)->references('id')->on('employees')->nullOnDelete();
            });
        }
    }

    /**
     * Create an employee row for every distinct user currently referenced as a custodian.
     * @return array<int,int> user_id => employee_id
     */
    private function backfillEmployees(): array
    {
        $userIds = collect();
        foreach ($this->columns as [$table, $column]) {
            $userIds = $userIds->merge(
                DB::table($table)->whereNotNull($column)->distinct()->pluck($column)
            );
        }
        $userIds = $userIds->unique()->values();

        $map = [];
        foreach ($userIds as $userId) {
            $user = DB::table('users')->find($userId);
            if (! $user) {
                continue;
            }

            $employeeId = DB::table('employees')->insertGetId([
                'name'           => $user->name,
                'employee_code'  => 'EMP-U' . $user->id,
                'email'          => $user->email,
                'user_id'        => $user->id,
                'department_id'  => $user->department_id ?? null,
                'branch_id'      => $user->branch_id ?? null,
                'designation_id' => $user->designation_id ?? null,
                'is_active'      => true,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);

            $map[$userId] = $employeeId;
        }

        return $map;
    }

    public function down(): void
    {
        // Best-effort reverse: repoint the constraint back to users. Values are NOT
        // reverse-remapped (the user link lives on employees.user_id if needed).
        foreach ($this->columns as [$table, $column]) {
            Schema::table($table, function (Blueprint $t) use ($column) {
                $t->dropForeign([$column]);
                $t->foreign($column)->references('id')->on('users')->nullOnDelete();
            });
        }
    }
};
