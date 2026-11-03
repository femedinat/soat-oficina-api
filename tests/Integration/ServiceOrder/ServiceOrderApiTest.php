<?php

declare(strict_types=1);

namespace Tests\Integration\ServiceOrder;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\Mail;
use Oficina\Customer\Infrastructure\CustomerModel;
use Oficina\Customer\Infrastructure\Persistence\Eloquent\VehicleModel;
use Oficina\Inventory\Infrastructure\InventoryItemModel;
use Oficina\Shared\Application\Clock;
use Tests\Integration\ApiActors;
use Tests\Support\FrozenClock;
use Tests\TestCase;

final class ServiceOrderApiTest extends TestCase
{
    use ApiActors;
    use RefreshDatabase;

    private const string UNKNOWN = '2f6b8f4e-3b0e-4c1f-9d77-0a6f6f1f0c11';

    private const string CPF = '52998224725';

    private const string OTHER_CPF = '11144477735';

    private FrozenClock $clock;

    private string $customerId;

    private string $oilChange;

    private string $alignment;

    private string $oil;

    private string $filter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clock = new FrozenClock;
        $this->app->instance(Clock::class, $this->clock);

        $this->customerId = $this->createCustomer(self::CPF);
        $this->oil = $this->createPart('OLEO-5W30', '42.00', '10', 'L');
        $this->filter = $this->createPart('FILTRO-OLEO', '35.90', '3');
        $this->oilChange = $this->createService('TROCA-OLEO', 'Troca de óleo', '120.00', 60);
        $this->alignment = $this->createService('ALINHAMENTO', 'Alinhamento', '80.00', 30);
        $this->putJson("/api/services/{$this->oilChange}/standard-parts", ['inventory_item_id' => $this->oil, 'quantity' => '4.5'], $this->admin())->assertOk();
    }

    private function open(string $plate = 'ABC1D23', string $document = self::CPF): string
    {
        return (string) $this->postJson('/api/service-orders', [
            'document' => $document,
            'vehicle' => ['license_plate' => $plate, 'brand' => 'Fiat', 'model' => 'Argo', 'year' => 2022],
        ], $this->admin())->assertCreated()->json('data.id');
    }

    /** OS com troca de óleo + 4,5 L de óleo, orçamento enviado. */
    private function awaitingApproval(string $plate = 'ABC1D23'): string
    {
        $id = $this->open($plate);
        $this->postJson("/api/service-orders/$id/services", ['service_id' => $this->oilChange], $this->mechanic())->assertCreated();
        $this->postJson("/api/service-orders/$id/parts", ['inventory_item_id' => $this->oil, 'quantity' => '4.5'], $this->mechanic())->assertCreated();
        $this->postJson("/api/service-orders/$id/budget/send", [], $this->mechanic())->assertOk();

        return $id;
    }

    private function stock(string $itemId): InventoryItemModel
    {
        return InventoryItemModel::query()->findOrFail($itemId);
    }

    /** @return list<string> */
    private function sentSubjects(): array
    {
        $transport = Mail::mailer()->getSymfonyTransport();
        self::assertInstanceOf(ArrayTransport::class, $transport);

        return array_values(array_map(static fn ($message): string => (string) $message->getOriginalMessage()->getSubject(), iterator_to_array($transport->messages())));
    }

    public function test_routes_require_authentication(): void
    {
        $id = self::UNKNOWN;
        $this->guest();

        $this->getJson('/api/service-orders')->assertUnauthorized();
        $this->postJson('/api/service-orders', [])->assertUnauthorized();
        $this->getJson("/api/service-orders/$id")->assertUnauthorized();
        $this->postJson("/api/service-orders/$id/services", [])->assertUnauthorized();
        $this->postJson("/api/service-orders/$id/budget/approve")->assertUnauthorized();
        $this->postJson("/api/service-orders/$id/finish")->assertUnauthorized();
        $this->getJson('/api/service-orders/metrics/execution-time')->assertUnauthorized();
    }

    public function test_full_flow_from_opening_to_delivery(): void
    {
        // Fase 1: placa nova é cadastrada já vinculada ao cliente.
        $open = $this->postJson('/api/service-orders', [
            'document' => '529.982.247-25',
            'vehicle' => ['license_plate' => 'abc-1d23', 'brand' => 'Fiat', 'model' => 'Argo', 'year' => 2022, 'mileage' => 15000],
        ], $this->admin());
        $open->assertCreated()
            ->assertJsonPath('data.status', 'RECEBIDA')
            ->assertJsonPath('data.customer_id', $this->customerId);
        $id = (string) $open->json('data.id');
        $this->assertDatabaseHas('vehicles', ['license_plate' => 'ABC1D23', 'customer_id' => $this->customerId]);

        // Fase 2: diagnóstico, peças padrão sugeridas e orçamento.
        $this->postJson("/api/service-orders/$id/services", ['service_id' => $this->oilChange], $this->mechanic())
            ->assertCreated()
            ->assertJsonPath('data.status', 'EM_DIAGNOSTICO')
            ->assertJsonPath('data.services.0.price', '120.00')
            ->assertJsonPath('suggested_parts.0.inventory_item_id', $this->oil);
        $this->postJson("/api/service-orders/$id/parts", ['inventory_item_id' => $this->oil, 'quantity' => '4.5'], $this->mechanic())->assertCreated();

        // Preço congelado: mudar o catálogo depois não altera o orçamento.
        $this->patchJson("/api/services/{$this->oilChange}/price", ['labor_price' => '150.00'], $this->admin())->assertOk();

        $this->postJson("/api/service-orders/$id/budget/send", [], $this->mechanic())
            ->assertOk()
            ->assertJsonPath('data.status', 'AGUARDANDO_APROVACAO')
            ->assertJsonPath('data.budget.total', '309.00')
            ->assertJsonPath('data.pending_approval.type', 'ORCAMENTO_INICIAL')
            ->assertJsonPath('data.pending_approval.deadline', '2026-10-31T09:00:00+00:00');
        self::assertContains('Orçamento aguardando aprovação', $this->sentSubjects());

        // Fase 3: cliente aprova -> reserva de estoque e cronômetro.
        $this->postJson("/api/service-orders/$id/budget/approve", [], $this->customer($this->customerId))
            ->assertOk()
            ->assertJsonPath('data.status', 'EM_EXECUCAO')
            ->assertJsonPath('data.execution_started_at', '2026-10-29T09:00:00+00:00');
        self::assertSame('4.500', $this->stock($this->oil)->reserved_quantity);

        // Fase 4: reparo adicional autorizado reserva as peças extras.
        $this->clock->advance('+30 minutes');
        $this->postJson("/api/service-orders/$id/additional-repairs", [
            'services' => [$this->alignment],
            'parts' => [['inventory_item_id' => $this->filter, 'quantity' => '1']],
        ], $this->mechanic())
            ->assertOk()
            ->assertJsonPath('data.status', 'AGUARDANDO_APROVACAO')
            ->assertJsonPath('data.pending_approval.type', 'REPARO_ADICIONAL')
            ->assertJsonPath('data.pending_approval.total', '115.90');
        self::assertContains('Reparo adicional aguardando aprovação', $this->sentSubjects());

        $this->postJson("/api/service-orders/$id/additional-repairs/authorize", [], $this->customer($this->customerId))
            ->assertOk()
            ->assertJsonPath('data.status', 'EM_EXECUCAO')
            ->assertJsonPath('data.budget.total', '424.90');
        self::assertSame('1.000', $this->stock($this->filter)->reserved_quantity);

        // Finalizar: baixa automática do estoque e aviso de retirada.
        $this->clock->advance('+60 minutes');
        $this->postJson("/api/service-orders/$id/finish", [], $this->mechanic())
            ->assertOk()
            ->assertJsonPath('data.status', 'FINALIZADA')
            ->assertJsonPath('data.execution_minutes', 90);
        self::assertSame('5.500', $this->stock($this->oil)->physical_quantity);
        self::assertSame('0.000', $this->stock($this->oil)->reserved_quantity);
        self::assertSame('2.000', $this->stock($this->filter)->physical_quantity);
        self::assertContains('Veículo pronto para retirada', $this->sentSubjects());

        // Fase 5: entrega.
        $this->postJson("/api/service-orders/$id/deliver", [], $this->mechanic())->assertForbidden();
        $this->postJson("/api/service-orders/$id/deliver", [], $this->admin())
            ->assertOk()
            ->assertJsonPath('data.status', 'ENTREGUE')
            ->assertJsonCount(8, 'data.history');

        // Monitoramento do tempo de execução.
        $this->getJson('/api/service-orders/metrics/execution-time', $this->mechanic())->assertForbidden();
        $this->getJson('/api/service-orders/metrics/execution-time?from=2026-10-01&to=2026-11-30', $this->admin())
            ->assertOk()
            ->assertJsonPath('data.overall.orders', 1)
            ->assertJsonPath('data.overall.average_minutes', 90)
            ->assertJsonPath('data.overall.average_estimated_minutes', 90)
            ->assertJsonPath('data.services.0.code', 'ALINHAMENTO')
            ->assertJsonPath('data.services.1.deviation_minutes', 30);
        $this->getJson('/api/service-orders/metrics/execution-time?from=2026-12-01', $this->admin())
            ->assertOk()
            ->assertJsonPath('data.overall.orders', 0)
            ->assertJsonPath('data.overall.average_minutes', null);
        $this->getJson('/api/service-orders/metrics/execution-time?from=2026-12-01&to=2026-11-01', $this->admin())->assertUnprocessable();
    }

    public function test_opening_validates_customer_and_vehicle(): void
    {
        $other = $this->createCustomer(self::OTHER_CPF, 'João Souza');
        $this->open('XYZ9A87');

        // Placa existente: dispensa marca/modelo/ano.
        $this->postJson('/api/service-orders', ['document' => self::CPF, 'vehicle' => ['license_plate' => 'XYZ9A87']], $this->admin())->assertCreated();
        // Placa nova sem dados do veículo.
        $this->postJson('/api/service-orders', ['document' => self::CPF, 'vehicle' => ['license_plate' => 'NEW1A23']], $this->admin())->assertUnprocessable();
        // Veículo de outro cliente.
        $this->postJson('/api/service-orders', ['document' => self::OTHER_CPF, 'vehicle' => ['license_plate' => 'XYZ9A87']], $this->admin())
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Este veículo não pertence ao cliente informado. Transfira a propriedade no cadastro de veículos.');
        // Cliente inexistente e documento inválido.
        $this->postJson('/api/service-orders', ['document' => '39053344705', 'vehicle' => ['license_plate' => 'XYZ9A87']], $this->admin())->assertNotFound();
        $this->postJson('/api/service-orders', ['document' => '123', 'vehicle' => ['license_plate' => 'XYZ9A87']], $this->admin())->assertUnprocessable();
        $this->postJson('/api/service-orders', [], $this->admin())->assertUnprocessable()->assertJsonValidationErrors(['document', 'vehicle.license_plate']);
        // Veículo inativo.
        VehicleModel::query()->where('license_plate', 'XYZ9A87')->update(['active' => false]);
        $this->postJson('/api/service-orders', ['document' => self::CPF, 'vehicle' => ['license_plate' => 'XYZ9A87']], $this->admin())->assertUnprocessable();
        // Cliente inativo.
        CustomerModel::query()->whereKey($other)->update(['active' => false]);
        $this->postJson('/api/service-orders', ['document' => self::OTHER_CPF, 'vehicle' => ['license_plate' => 'QWE1R23', 'brand' => 'VW', 'model' => 'Gol', 'year' => 2010]], $this->admin())
            ->assertUnprocessable();
        // Só o administrador abre OS.
        $this->postJson('/api/service-orders', ['document' => self::CPF, 'vehicle' => ['license_plate' => 'XYZ9A87']], $this->mechanic())->assertForbidden();
    }

    public function test_diagnosis_rules(): void
    {
        $id = $this->open();

        $this->postJson("/api/service-orders/$id/budget/send", [], $this->mechanic())->assertUnprocessable();
        $this->postJson("/api/service-orders/$id/parts", ['inventory_item_id' => $this->filter, 'quantity' => '4'], $this->mechanic())
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Estoque insuficiente para: FILTRO-OLEO (solicitado 4.000, disponível 3.000).');
        $this->postJson("/api/service-orders/$id/parts", ['inventory_item_id' => self::UNKNOWN, 'quantity' => '1'], $this->mechanic())->assertNotFound();

        $added = $this->postJson("/api/service-orders/$id/services", ['service_id' => $this->alignment], $this->mechanic())->assertCreated();
        $this->postJson("/api/service-orders/$id/services", ['service_id' => $this->alignment], $this->mechanic())->assertUnprocessable();
        $this->postJson("/api/service-orders/$id/services", ['service_id' => self::UNKNOWN], $this->mechanic())->assertNotFound();
        $line = (string) $added->json('data.services.0.id');

        $part = $this->postJson("/api/service-orders/$id/parts", ['inventory_item_id' => $this->filter, 'quantity' => '1'], $this->mechanic())->assertCreated();
        $this->postJson("/api/service-orders/$id/parts", ['inventory_item_id' => $this->filter, 'quantity' => '1'], $this->mechanic())
            ->assertCreated()
            ->assertJsonCount(1, 'data.parts')
            ->assertJsonPath('data.parts.0.quantity', '2.000');
        $partLine = (string) $part->json('data.parts.0.id');

        $this->deleteJson("/api/service-orders/$id/parts/$partLine", [], $this->mechanic())->assertOk()->assertJsonCount(0, 'data.parts');
        $this->deleteJson("/api/service-orders/$id/services/$line", [], $this->mechanic())->assertOk()->assertJsonCount(0, 'data.services');
        $this->deleteJson("/api/service-orders/$id/services/$line", [], $this->mechanic())->assertNotFound();

        // Serviço inativo não entra em orçamento novo; peça inativa também não.
        $this->deleteJson("/api/services/{$this->alignment}", [], $this->admin())->assertNoContent();
        $this->postJson("/api/service-orders/$id/services", ['service_id' => $this->alignment], $this->mechanic())->assertUnprocessable();
        $this->deleteJson("/api/parts/{$this->filter}", [], $this->admin())->assertNoContent();
        $this->postJson("/api/service-orders/$id/parts", ['inventory_item_id' => $this->filter, 'quantity' => '1'], $this->mechanic())->assertUnprocessable();

        // Cliente não mexe no diagnóstico.
        $this->postJson("/api/service-orders/$id/services", ['service_id' => $this->oilChange], $this->customer($this->customerId))->assertForbidden();
    }

    public function test_customer_only_sees_and_decides_own_orders(): void
    {
        $mine = $this->awaitingApproval();
        $other = $this->createCustomer(self::OTHER_CPF, 'João Souza');
        $this->postJson('/api/service-orders', [
            'document' => self::OTHER_CPF,
            'vehicle' => ['license_plate' => 'JOA0A00', 'brand' => 'VW', 'model' => 'Gol', 'year' => 2010],
        ], $this->admin())->assertCreated();

        $intruder = $this->customer($other);
        $this->getJson("/api/service-orders/$mine", $intruder)->assertNotFound();
        $this->postJson("/api/service-orders/$mine/budget/approve", [], $intruder)->assertNotFound();
        $this->postJson("/api/service-orders/$mine/budget/reject", [], $intruder)->assertNotFound();
        $this->getJson("/api/service-orders?customer_id={$this->customerId}", $intruder)
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.customer_id', $other);

        $owner = $this->customer($this->customerId);
        $this->getJson("/api/service-orders/$mine", $owner)->assertOk()->assertJsonPath('data.id', $mine);
        $this->getJson('/api/service-orders?status=aguardando_aprovacao', $this->admin())->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson('/api/service-orders?status=PAGA', $this->admin())->assertUnprocessable();
        $this->postJson("/api/service-orders/$mine/budget/approve", [], $this->mechanic())->assertForbidden();
    }

    public function test_rejected_budget_cancels_order_and_notifies(): void
    {
        $id = $this->awaitingApproval();

        $this->postJson("/api/service-orders/$id/budget/reject", [], $this->customer($this->customerId))
            ->assertOk()
            ->assertJsonPath('data.status', 'CANCELADA')
            ->assertJsonPath('data.cancellation_reason', 'Orçamento rejeitado pelo cliente')
            ->assertJsonPath('data.pending_approval', null);
        self::assertContains('Ordem de serviço cancelada', $this->sentSubjects());
        self::assertSame('0.000', $this->stock($this->oil)->reserved_quantity);
        $this->postJson("/api/service-orders/$id/budget/approve", [], $this->customer($this->customerId))->assertUnprocessable();
    }

    public function test_approval_without_stock_keeps_order_waiting(): void
    {
        $id = $this->awaitingApproval();
        $this->postJson("/api/parts/{$this->oil}/adjustments", ['counted_quantity' => '2', 'reason' => 'Inventário'], $this->admin())->assertOk();

        $this->postJson("/api/service-orders/$id/budget/approve", [], $this->customer($this->customerId))->assertUnprocessable();
        $this->getJson("/api/service-orders/$id", $this->admin())->assertJsonPath('data.status', 'AGUARDANDO_APROVACAO');

        // Mecânico devolve ao diagnóstico, ajusta e reenvia.
        $view = $this->postJson("/api/service-orders/$id/budget/return-to-diagnosis", [], $this->mechanic())
            ->assertOk()
            ->assertJsonPath('data.status', 'EM_DIAGNOSTICO');
        $this->deleteJson("/api/service-orders/$id/parts/".$view->json('data.parts.0.id'), [], $this->mechanic())->assertOk();
        $this->postJson("/api/service-orders/$id/budget/send", [], $this->mechanic())->assertOk()->assertJsonPath('data.budget.total', '120.00');
        $this->postJson("/api/service-orders/$id/budget/approve", [], $this->admin())->assertOk()->assertJsonPath('data.status', 'EM_EXECUCAO');
    }

    public function test_cancel_after_approval_releases_reservation(): void
    {
        $id = $this->awaitingApproval();
        $this->postJson("/api/service-orders/$id/budget/approve", [], $this->admin())->assertOk();
        self::assertSame('4.500', $this->stock($this->oil)->reserved_quantity);

        $this->postJson("/api/service-orders/$id/cancel", [], $this->mechanic())->assertForbidden();
        $this->postJson("/api/service-orders/$id/cancel", [], $this->admin())
            ->assertOk()
            ->assertJsonPath('data.status', 'CANCELADA')
            ->assertJsonPath('data.cancellation_reason', 'Cancelada pela oficina');
        self::assertSame('0.000', $this->stock($this->oil)->reserved_quantity);
        self::assertSame('10.000', $this->stock($this->oil)->physical_quantity);
        $this->postJson("/api/service-orders/$id/cancel", [], $this->admin())->assertUnprocessable();
    }

    public function test_refused_additional_repair_keeps_scope(): void
    {
        $id = $this->awaitingApproval();
        $this->postJson("/api/service-orders/$id/budget/approve", [], $this->admin())->assertOk();
        $this->postJson("/api/service-orders/$id/additional-repairs", [], $this->mechanic())->assertUnprocessable();
        $this->postJson("/api/service-orders/$id/additional-repairs", ['services' => [$this->alignment]], $this->mechanic())->assertOk();

        $this->postJson("/api/service-orders/$id/additional-repairs/refuse", [], $this->customer($this->customerId))
            ->assertOk()
            ->assertJsonPath('data.status', 'EM_EXECUCAO')
            ->assertJsonCount(1, 'data.services');
    }

    public function test_expiry_job_cancels_initial_and_refuses_additional(): void
    {
        $initial = $this->awaitingApproval();
        $additional = $this->awaitingApproval('DEF4G56');
        $this->postJson("/api/service-orders/$additional/budget/approve", [], $this->admin())->assertOk();
        $this->postJson("/api/service-orders/$additional/additional-repairs", ['services' => [$this->alignment]], $this->mechanic())->assertOk();
        $fresh = $this->awaitingApproval('GHI7J89');

        $this->artisan('service-orders:expire-approvals')->expectsOutputToContain('0 OS')->assertSuccessful();

        $this->clock->advance('+48 hours');
        $this->postJson("/api/service-orders/$fresh/budget/approve", [], $this->admin())->assertUnprocessable();
        $this->artisan('service-orders:expire-approvals')->expectsOutputToContain('3 OS')->assertSuccessful();

        $this->getJson("/api/service-orders/$initial", $this->admin())
            ->assertJsonPath('data.status', 'CANCELADA')
            ->assertJsonPath('data.cancellation_reason', 'Orçamento sem resposta no prazo de aprovação');
        $this->getJson("/api/service-orders/$additional", $this->admin())
            ->assertJsonPath('data.status', 'EM_EXECUCAO')
            ->assertJsonCount(1, 'data.services');
        self::assertSame('4.500', $this->stock($this->oil)->reserved_quantity);
    }

    public function test_public_tracking_by_order_and_document(): void
    {
        $id = $this->awaitingApproval();

        $this->getJson("/api/public/service-orders/$id?document=529.982.247-25")
            ->assertOk()
            ->assertJsonPath('data.status', 'AGUARDANDO_APROVACAO')
            ->assertJsonPath('data.license_plate', 'ABC1D23')
            ->assertJsonPath('data.vehicle', 'Fiat Argo 2022')
            ->assertJsonPath('data.total', '309.00')
            ->assertJsonMissingPath('data.customer_id')
            ->assertJsonMissingPath('data.services');

        $this->getJson("/api/public/service-orders/$id?document=".self::OTHER_CPF)->assertNotFound();
        $this->getJson("/api/public/service-orders/$id?document=123")->assertNotFound();
        $this->getJson('/api/public/service-orders/'.self::UNKNOWN.'?document='.self::CPF)->assertNotFound();
        $this->getJson("/api/public/service-orders/$id")->assertUnprocessable();
    }

    public function test_unknown_or_malformed_order_is_404(): void
    {
        $this->getJson('/api/service-orders/'.self::UNKNOWN, $this->admin())->assertNotFound();
        $this->getJson('/api/service-orders/abc', $this->admin())->assertNotFound();
        $this->postJson('/api/service-orders/'.self::UNKNOWN.'/finish', [], $this->admin())->assertNotFound();
    }
}
