<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            if (!Schema::hasColumn('sales', 'transferencia_estado')) {
                $table->string('transferencia_estado')->nullable()->after('mercadopago_status');
            }

            if (!Schema::hasColumn('sales', 'transferencia_evidencia_path')) {
                $table->string('transferencia_evidencia_path')->nullable()->after('transferencia_estado');
            }

            if (!Schema::hasColumn('sales', 'transferencia_subida_at')) {
                $table->timestamp('transferencia_subida_at')->nullable()->after('transferencia_evidencia_path');
            }

            if (!Schema::hasColumn('sales', 'transferencia_validada_at')) {
                $table->timestamp('transferencia_validada_at')->nullable()->after('transferencia_subida_at');
            }

            if (!Schema::hasColumn('sales', 'transferencia_validada_por')) {
                $table->unsignedBigInteger('transferencia_validada_por')->nullable()->after('transferencia_validada_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $columns = [
                'transferencia_estado',
                'transferencia_evidencia_path',
                'transferencia_subida_at',
                'transferencia_validada_at',
                'transferencia_validada_por',
            ];

            $existing = array_filter($columns, static function ($column) {
                return Schema::hasColumn('sales', $column);
            });

            if (!empty($existing)) {
                $table->dropColumn($existing);
            }
        });
    }
};
