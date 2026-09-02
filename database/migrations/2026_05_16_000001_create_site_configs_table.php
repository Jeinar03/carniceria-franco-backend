<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * SQL equivalente:
 *
 * CREATE TABLE site_configs (
 *     id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 *     nombre        VARCHAR(100) NOT NULL,
 *     logo          VARCHAR(255) NULL,
 *     direccion     VARCHAR(255) NULL,
 *     correo        VARCHAR(100) NULL,
 *     telefono      VARCHAR(30)  NULL,
 *     facebook_url  VARCHAR(255) NULL,
 *     instagram_url VARCHAR(255) NULL,
 *     whatsapp      VARCHAR(30)  NULL,
 *     horarios      JSON         NULL COMMENT 'Horarios de atención por día',
 *     activo        TINYINT(1)   NOT NULL DEFAULT 0,
 *     created_at    TIMESTAMP    NULL,
 *     updated_at    TIMESTAMP    NULL
 * );
 */
class CreateSiteConfigsTable extends Migration
{
    public function up()
    {
        Schema::create('site_configs', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100);
            $table->string('logo', 255)->nullable();
            $table->string('direccion', 255)->nullable();
            $table->string('correo', 100)->nullable();
            $table->string('telefono', 30)->nullable();
            $table->string('facebook_url', 255)->nullable();
            $table->string('instagram_url', 255)->nullable();
            $table->string('whatsapp', 30)->nullable();
            $table->json('horarios')->nullable()->comment('Horarios de atención por día de la semana');
            $table->boolean('activo')->default(false);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('site_configs');
    }
}
