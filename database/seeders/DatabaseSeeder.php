<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Oficina\Catalog\Application\LinkStandardPart;
use Oficina\Catalog\Application\RegisterService;
use Oficina\Catalog\Application\RegisterServiceInput;
use Oficina\Catalog\Domain\ServiceCode;
use Oficina\Catalog\Domain\ServiceRepository;
use Oficina\Catalog\Infrastructure\ServiceModel;
use Oficina\Customer\Infrastructure\CustomerModel;
use Oficina\Identity\Domain\Role;
use Oficina\Inventory\Application\RegisterInventoryItem;
use Oficina\Inventory\Application\RegisterInventoryItemInput;
use Oficina\Inventory\Domain\InventoryItemRepository;
use Oficina\Inventory\Domain\Sku;
use Oficina\ServiceOrder\Application\AddPartToOrder;
use Oficina\ServiceOrder\Application\AddServiceToOrder;
use Oficina\ServiceOrder\Application\OpenServiceOrder;
use Oficina\ServiceOrder\Application\SendBudget;
use Oficina\ServiceOrder\Application\VehicleData;
use Oficina\ServiceOrder\Infrastructure\ServiceOrderModel;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /** CPF fictício (dígitos verificadores válidos) do cliente de demonstração. */
    public const string DEMO_CUSTOMER_DOCUMENT = '52998224725';

    /** Placa (padrão Mercosul) do veículo do cliente de demonstração. */
    public const string DEMO_LICENSE_PLATE = 'BRA2E19';

    /**
     * Dados de demonstração: SOMENTE ambiente local/testes. A senha vem do .env e vale para os três perfis.
     * Idempotente: rodar de novo não duplica nada.
     */
    public function run(): void
    {
        if (app()->environment('production')) {
            return;
        }

        $password = (string) config('app.admin_seed_password');
        if ($password === '') {
            throw new RuntimeException('A variável ADMIN_SEED_PASSWORD deve ser informada no .env para executar o seeder.');
        }

        $this->users($password);
        $this->catalog();
        $this->serviceOrder();
    }

    private function users(string $password): void
    {
        User::query()->updateOrCreate(
            ['email' => 'admin@oficina.local'],
            ['name' => 'Administrador', 'password' => $password, 'role' => Role::ADMINISTRATOR],
        );

        User::query()->updateOrCreate(
            ['email' => 'mecanico@oficina.local'],
            ['name' => 'Mecânico', 'password' => $password, 'role' => Role::MECHANIC],
        );

        $customer = CustomerModel::query()->where('document', self::DEMO_CUSTOMER_DOCUMENT)->first();
        if ($customer === null) {
            $customer = new CustomerModel;
            $customer->id = (string) Str::uuid();
            $customer->fill([
                'name' => 'Cliente Demonstração',
                'document' => self::DEMO_CUSTOMER_DOCUMENT,
                'email' => 'cliente@oficina.local',
                'active' => true,
            ]);
            $customer->save();
        }

        $user = User::query()->firstOrNew(['email' => 'cliente@oficina.local']);
        $user->fill(['name' => 'Cliente Demonstração', 'password' => $password]);
        $user->role = Role::CUSTOMER;
        $user->customer_id = $customer->id;
        $user->save();
    }

    /** Peças e serviços para demonstrar o fluxo da OS sem cadastro manual. */
    private function catalog(): void
    {
        $items = app(InventoryItemRepository::class);
        $parts = [];
        foreach ([
            ['OLEO-5W30', 'Óleo de motor 5W30', 'INSUMO', 'L', '42.00', '40', '8'],
            ['FILTRO-OLEO', 'Filtro de óleo', 'PECA', 'UN', '35.90', '10', '2'],
        ] as [$sku, $name, $type, $unit, $price, $initial, $minimum]) {
            $existing = $items->findBySku(Sku::from($sku));
            $parts[$sku] = $existing !== null
                ? $existing->id->value
                : app(RegisterInventoryItem::class)->execute(new RegisterInventoryItemInput($sku, $name, $type, $unit, $price, $initial, $minimum))->id;
        }

        $services = app(ServiceRepository::class);
        if (! $services->codeExists(ServiceCode::from('TROCA-OLEO'))) {
            $oilChange = app(RegisterService::class)->execute(new RegisterServiceInput('TROCA-OLEO', 'Troca de óleo', 'Óleo e filtro', 'Preventiva', '120.00', 60))->id;
            app(LinkStandardPart::class)->execute($oilChange, $parts['OLEO-5W30'], '4.5');
            app(LinkStandardPart::class)->execute($oilChange, $parts['FILTRO-OLEO'], '1');
        }

        if (! $services->codeExists(ServiceCode::from('ALINHAMENTO'))) {
            app(RegisterService::class)->execute(new RegisterServiceInput('ALINHAMENTO', 'Alinhamento e balanceamento', null, 'Rodas', '80.00', 40));
        }
    }

    /**
     * Uma OS do cliente demo já com orçamento enviado (Aguardando aprovação), pronta para aprovar/recusar e acompanhar.
     * Percorre os mesmos casos de uso da API; o prazo de 48h corre a partir do seed.
     */
    private function serviceOrder(): void
    {
        $customerId = CustomerModel::query()->where('document', self::DEMO_CUSTOMER_DOCUMENT)->value('id');
        if (ServiceOrderModel::query()->where('customer_id', $customerId)->exists()) {
            return;
        }

        $orderId = app(OpenServiceOrder::class)->execute(
            self::DEMO_CUSTOMER_DOCUMENT,
            new VehicleData(self::DEMO_LICENSE_PLATE, 'Volkswagen', 'Gol', 2019, 'Prata', 85000),
        )->id;

        $oilChange = (string) ServiceModel::query()->where('code', 'TROCA-OLEO')->value('id');
        $result = app(AddServiceToOrder::class)->execute($orderId, $oilChange);
        foreach ($result['suggested_parts'] as $part) {
            app(AddPartToOrder::class)->execute($orderId, $part['inventory_item_id'], $part['quantity']);
        }

        app(SendBudget::class)->execute($orderId);
    }
}
