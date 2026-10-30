<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Contexto Atendimento: Ordem de Serviço, itens orçados (preços congelados) e histórico de status.
 * O histórico é só de inclusão (auditoria). As CHECK constraints repetem as invariantes do domínio.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignUuid('vehicle_id')->constrained('vehicles')->restrictOnDelete();
            $table->string('status', 25);
            $table->timestampTz('opened_at');
            $table->timestampTz('budget_sent_at')->nullable();
            $table->timestampTz('approval_deadline')->nullable();
            $table->timestampTz('approved_at')->nullable();
            $table->timestampTz('execution_started_at')->nullable();
            $table->timestampTz('execution_finished_at')->nullable();
            $table->timestampTz('delivered_at')->nullable();
            $table->timestampTz('canceled_at')->nullable();
            $table->string('cancellation_reason', 30)->nullable();
            $table->boolean('awaiting_additional_approval')->default(false);
            $table->timestamps();

            $table->index('customer_id');
            $table->index('vehicle_id');
            $table->index(['status', 'opened_at']);
            $table->index(['status', 'approval_deadline']);
            $table->index('execution_finished_at');
        });

        DB::statement("ALTER TABLE service_orders ADD CONSTRAINT service_orders_status_check CHECK (status IN ('RECEBIDA', 'EM_DIAGNOSTICO', 'AGUARDANDO_APROVACAO', 'EM_EXECUCAO', 'FINALIZADA', 'ENTREGUE', 'CANCELADA'))");
        DB::statement("ALTER TABLE service_orders ADD CONSTRAINT service_orders_cancellation_reason_check CHECK (cancellation_reason IS NULL OR cancellation_reason IN ('ORCAMENTO_REJEITADO', 'SEM_RESPOSTA_NO_PRAZO', 'CANCELADA_PELA_OFICINA'))");
        DB::statement("ALTER TABLE service_orders ADD CONSTRAINT service_orders_cancellation_check CHECK ((status = 'CANCELADA') = (canceled_at IS NOT NULL AND cancellation_reason IS NOT NULL))");
        DB::statement('ALTER TABLE service_orders ADD CONSTRAINT service_orders_execution_check CHECK (execution_finished_at IS NULL OR execution_finished_at >= execution_started_at)');

        Schema::create('service_order_services', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('service_order_id')->constrained('service_orders')->cascadeOnDelete();
            $table->foreignUuid('service_id')->constrained('services')->restrictOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('code', 30);
            $table->string('name', 120);
            $table->bigInteger('price_cents');
            $table->integer('estimated_minutes');
            $table->boolean('additional')->default(false);
            $table->boolean('approved')->default(false);

            $table->unique(['service_order_id', 'service_id']);
            $table->index('service_id');
        });

        DB::statement('ALTER TABLE service_order_services ADD CONSTRAINT service_order_services_price_check CHECK (price_cents >= 0 AND estimated_minutes > 0)');

        Schema::create('service_order_parts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('service_order_id')->constrained('service_orders')->cascadeOnDelete();
            $table->foreignUuid('inventory_item_id')->constrained('inventory_items')->restrictOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('sku', 40);
            $table->string('name', 120);
            $table->string('unit', 5);
            $table->decimal('quantity', 15, 3);
            $table->bigInteger('unit_price_cents');
            $table->boolean('additional')->default(false);
            $table->boolean('approved')->default(false);

            $table->index('service_order_id');
        });

        DB::statement('ALTER TABLE service_order_parts ADD CONSTRAINT service_order_parts_values_check CHECK (quantity > 0 AND unit_price_cents >= 0)');

        Schema::create('service_order_status_history', function (Blueprint $table) {
            $table->bigIncrements('sequence');
            $table->foreignUuid('service_order_id')->constrained('service_orders')->cascadeOnDelete();
            $table->string('status', 25);
            $table->timestampTz('occurred_at');

            $table->index(['service_order_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_order_status_history');
        Schema::dropIfExists('service_order_parts');
        Schema::dropIfExists('service_order_services');
        Schema::dropIfExists('service_orders');
    }
};
