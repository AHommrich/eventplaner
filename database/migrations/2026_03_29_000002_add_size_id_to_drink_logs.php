<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('drink_logs', 'size_id')) {
            Schema::table('drink_logs', function (Blueprint $table) {
                $table->unsignedBigInteger('size_id')->nullable()->after('drink_id');
                $table->double('amount_liter')->nullable()->after('size_id');
                $table->index('size_id', 'drink_logs_size_id_index');
                $table->foreign('size_id')->references('id')->on('drink_catalog_sizes')->nullOnDelete();
            });
        }

        // Backfill — nur auf MySQL/MariaDB sinnvoll, Test-DB ist leer.
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('
                UPDATE drink_logs dl
                JOIN drinks d ON d.id = dl.drink_id
                JOIN drink_catalog_sizes dcs ON dcs.catalog_id = d.drink_catalog_id AND dcs.is_default = 1
                SET dl.size_id = dcs.id, dl.amount_liter = dcs.amount_liter
                WHERE dl.size_id IS NULL
            ');
        }
    }

    public function down(): void
    {
        Schema::table('drink_logs', function (Blueprint $table) {
            try {
                $table->dropForeign('drink_logs_size_id_foreign');
            } catch (\Throwable $e) {
            }
            try {
                $table->dropIndex('drink_logs_size_id_index');
            } catch (\Throwable $e) {
            }
            $table->dropColumn(['size_id', 'amount_liter']);
        });
    }
};
