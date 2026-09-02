<?php

namespace Tests\Feature;

use App\Models\InventoryMovement;
use App\Models\InventoryMovementAudit;
use App\Models\Product;
use App\Services\InventoryService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InventoryEntryEditingTest extends TestCase
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

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->decimal('precio', 14, 2)->default(0);
            $table->string('unidad_venta')->default('pieza');
            $table->decimal('stock', 14, 3)->default(0);
            $table->decimal('stock_minimo', 14, 3)->default(0);
            $table->boolean('en_oferta')->default(false);
            $table->boolean('activo')->default(true);
            $table->boolean('destacado')->default(false);
            $table->boolean('refrigerado')->default(true);
            $table->timestamps();
        });

        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('sale_id')->nullable();
            $table->unsignedBigInteger('sale_detail_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('reception_id')->nullable();
            $table->string('type');
            $table->string('origin');
            $table->decimal('quantity', 14, 3);
            $table->decimal('balance_after', 14, 3);
            $table->string('unit');
            $table->decimal('unit_cost', 14, 2)->nullable();
            $table->decimal('total_cost', 16, 2)->nullable();
            $table->string('notes', 500)->nullable();
            $table->string('status')->default(InventoryMovement::STATUS_ACTIVE);
            $table->dateTime('cancelled_at')->nullable();
            $table->unsignedBigInteger('cancelled_by')->nullable();
            $table->string('cancellation_reason', 500)->nullable();
            $table->dateTime('moved_at');
            $table->timestamps();
        });

        Schema::create('inventory_movement_audits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('inventory_movement_id');
            $table->string('reception_id')->nullable();
            $table->string('action');
            $table->decimal('old_quantity', 14, 3);
            $table->decimal('new_quantity', 14, 3);
            $table->decimal('old_unit_cost', 14, 2)->nullable();
            $table->decimal('new_unit_cost', 14, 2)->nullable();
            $table->decimal('old_total_cost', 16, 2)->nullable();
            $table->decimal('new_total_cost', 16, 2)->nullable();
            $table->string('old_notes', 500)->nullable();
            $table->string('new_notes', 500)->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
        });
    }

    public function test_it_rejects_an_edit_that_would_make_a_later_sale_negative(): void
    {
        $product = Product::forceCreate([
            'nombre' => 'Bistec',
            'precio' => 120,
            'unidad_venta' => 'kilogramo',
            'activo' => true,
        ]);
        $service = app(InventoryService::class);

        $entry = DB::transaction(function () use ($service, $product) {
            return $service->addEntry($product, 10, 'Recepción inicial', null, 'receipt-1');
        });
        InventoryMovement::create([
            'product_id' => $product->id,
            'type' => InventoryMovement::TYPE_EXIT,
            'origin' => 'venta',
            'quantity' => 8,
            'balance_after' => 2,
            'unit' => 'kilogramo',
            'status' => InventoryMovement::STATUS_ACTIVE,
            'moved_at' => now()->addSecond(),
        ]);

        try {
            $service->editEntry($entry->id, 1, 'Intento inválido', null);
            $this->fail('La edición debía ser rechazada.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('No se puede reducir', $e->getMessage());
        }

        $this->assertEquals(10.0, (float) $entry->fresh()->quantity);
        $this->assertEquals(2.0, $service->currentStock($product->id));
        $this->assertSame(0, InventoryMovementAudit::count());

        try {
            $service->cancelEntry($entry->id, 'Cancelación inválida', null);
            $this->fail('La cancelación debía ser rechazada.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('No se puede cancelar', $e->getMessage());
        }

        $this->assertSame(InventoryMovement::STATUS_ACTIVE, $entry->fresh()->status);
        $this->assertEquals(2.0, $service->currentStock($product->id));
        $this->assertSame(0, InventoryMovementAudit::count());
    }

    public function test_it_edits_and_cancels_entries_with_an_audit_trail(): void
    {
        $product = Product::forceCreate([
            'nombre' => 'Pollo',
            'precio' => 80,
            'unidad_venta' => 'pieza',
            'activo' => true,
        ]);
        $service = app(InventoryService::class);

        $entry = DB::transaction(function () use ($service, $product) {
            return $service->addEntry($product, 5, 'Proveedor A', null, 'receipt-2');
        });

        $edited = $service->editEntry($entry->id, 4, 'Proveedor corregido', null);
        $this->assertEquals(4.0, (float) $edited->quantity);
        $this->assertEquals(320.0, (float) $edited->total_cost);
        $this->assertEquals(4.0, $service->currentStock($product->id));

        $cancelled = $service->cancelEntry($entry->id, 'Recepción duplicada', null);
        $this->assertSame(InventoryMovement::STATUS_CANCELLED, $cancelled->status);
        $this->assertEquals(0.0, $service->currentStock($product->id));
        $this->assertSame(['edited', 'cancelled'], InventoryMovementAudit::orderBy('id')->pluck('action')->all());
    }
}
