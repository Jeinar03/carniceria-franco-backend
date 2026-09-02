<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TransferEvidencePreviewTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('metodo_pago');
            $table->string('transferencia_evidencia_path')->nullable();
            $table->timestamps();
        });
    }

    public function test_authenticated_admin_evidence_can_be_embedded_from_same_origin(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('transferencias/evidencia.pdf', "%PDF-1.4\n% test evidence\n");

        $user = User::forceCreate(['name' => 'Administrador']);
        DB::table('sales')->insert([
            'id' => 26,
            'metodo_pago' => 'transferencia',
            'transferencia_evidencia_path' => 'transferencias/evidencia.pdf',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('admin.transfer-evidence.show', ['saleId' => 26]));

        $response->assertOk();
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('inline;', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_other_pages_keep_frame_embedding_denied(): void
    {
        $response = $this->get('/');

        $response->assertHeader('X-Frame-Options', 'DENY');
    }

    public function test_transfer_image_is_returned_inline_with_its_real_mime_type(): void
    {
        Storage::fake('public');
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
        Storage::disk('public')->put('transferencias/evidencia.png', $png);

        $user = User::forceCreate(['name' => 'Administrador']);
        DB::table('sales')->insert([
            'id' => 27,
            'metodo_pago' => 'transferencia',
            'transferencia_evidencia_path' => 'transferencias/evidencia.png',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('admin.transfer-evidence.show', ['saleId' => 27]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/png');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $this->assertStringContainsString('inline;', (string) $response->headers->get('Content-Disposition'));
    }
}
