<?php

declare(strict_types=1);

namespace Oficina\ServiceOrder\Application;

/** Porta para o contexto Veículos (camada anticorrupção na Infraestrutura). */
interface VehicleRegistry
{
    /**
     * Identifica o veículo pela placa. Se a placa não existir, cadastra o veículo já vinculado ao cliente.
     * Veículo de outro cliente ou inativo é recusado (a transferência é feita no cadastro de veículos).
     */
    public function identifyOrRegister(string $customerId, VehicleData $data): VehicleSnapshot;

    public function findById(string $vehicleId): ?VehicleSnapshot;
}
