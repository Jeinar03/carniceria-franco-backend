<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * SQL equivalente:
 *
 * CREATE TABLE site_alerts (
 *     id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 *     titulo         VARCHAR(150) NOT NULL,
 *     descripcion    TEXT         NULL,
 *     imagen         VARCHAR(255) NULL,
 *     link_url       VARCHAR(255) NULL,
 *     link_texto     VARCHAR(100) NULL,
 *     fecha_inicio   DATETIME     NULL COMMENT 'Fecha y hora programada de inicio',
 *     dias_duracion  INT UNSIGNED NOT NULL DEFAULT 1,
 *     tipo           ENUM('oferta','alerta','novedad','anuncio') NOT NULL DEFAULT 'anuncio',
 *     activo         TINYINT(1)   NOT NULL DEFAULT 1,
 *     created_at     TIMESTAMP    NULL,
 *     updated_at     TIMESTAMP    NULL
 * );
 */
class CreateSiteAlertsTable extends Migration
{
    public function up()
    {
        Schema::create('site_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('titulo', 150);
            $table->text('descripcion')->nullable();
            $table->string('imagen', 255)->nullable();
            $table->string('link_url', 255)->nullable();
            $table->string('link_texto', 100)->nullable();
            $table->dateTime('fecha_inicio')->nullable()->comment('Fecha y hora programada de inicio');
            $table->unsignedInteger('dias_duracion')->default(1)->comment('Duración en días desde fecha_inicio');
            $table->enum('tipo', ['oferta', 'alerta', 'novedad', 'anuncio'])->default('anuncio');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('site_alerts');
    }
}
