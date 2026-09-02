<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('indicador_preguntas', function (Blueprint $table) {
            $table->id();
            $table->text('pregunta');
            $table->text('descripcion')->nullable();
            $table->boolean('activo')->default(true);
            $table->boolean('mostrar_al_finalizar_pedido')->default(true);
            $table->unsignedInteger('orden')->default(0);
            $table->timestamps();

            $table->index(['activo', 'mostrar_al_finalizar_pedido', 'orden'], 'idx_indicador_preguntas_estado');
        });

        Schema::create('indicador_respuestas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pregunta_id');
            $table->unsignedBigInteger('sale_id');
            $table->unsignedBigInteger('customer_id');
            $table->unsignedTinyInteger('respuesta');
            $table->text('comentario')->nullable();
            $table->timestamps();

            $table->foreign('pregunta_id')->references('id')->on('indicador_preguntas')->onDelete('cascade');
            $table->foreign('sale_id')->references('id')->on('sales')->onDelete('cascade');
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            $table->unique(['pregunta_id', 'sale_id', 'customer_id'], 'uq_indicador_respuesta_pedido_cliente');
            $table->index(['sale_id', 'customer_id'], 'idx_indicador_respuestas_pedido_cliente');
        });

        DB::table('indicador_preguntas')->insert([
            [
                'pregunta' => 'Que tan satisfecho estas con la rapidez del proceso al realizar tu pedido?',
                'descripcion' => 'Rapidez del proceso de compra',
                'activo' => true,
                'mostrar_al_finalizar_pedido' => true,
                'orden' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'pregunta' => 'Los cortes de carne, el peso y la preparacion recibida coincidieron exactamente con lo que solicitaste.',
                'descripcion' => 'Precision del pedido recibido',
                'activo' => true,
                'mostrar_al_finalizar_pedido' => true,
                'orden' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'pregunta' => 'Las sugerencias y recomendaciones de cortes mostradas en la plataforma fueron acertadas y facilitaron tu compra.',
                'descripcion' => 'Calidad de recomendaciones',
                'activo' => true,
                'mostrar_al_finalizar_pedido' => true,
                'orden' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'pregunta' => 'La informacion sobre el estado de tu pedido (preparacion y entrega) fue clara y oportuna.',
                'descripcion' => 'Claridad del seguimiento',
                'activo' => true,
                'mostrar_al_finalizar_pedido' => true,
                'orden' => 4,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'pregunta' => 'En general, como evaluas tu experiencia con este proceso de pedidos frente a la atencion tradicional?',
                'descripcion' => 'Experiencia general contra atencion tradicional',
                'activo' => true,
                'mostrar_al_finalizar_pedido' => true,
                'orden' => 5,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('indicador_respuestas');
        Schema::dropIfExists('indicador_preguntas');
    }
};
