<?php

declare(strict_types=1);

namespace Oficina\ServiceOrder\Interfaces\Http;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Oficina\ServiceOrder\Application\TrackServiceOrder;

/**
 * Acompanhamento público da OS (sem login, com limite de requisições por IP).
 *
 * @tags Acompanhamento público
 */
final class TrackServiceOrderController extends Controller
{
    /**
     * Acompanhar ordem de serviço
     *
     * Informe o número da OS e o CPF/CNPJ do cliente. Qualquer divergência responde 404.
     * Retorna só status, veículo, total, aprovação pendente e histórico (sem itens nem dados pessoais).
     *
     * @unauthenticated
     *
     * @response array{data: array{id: string, status: string, status_label: string, license_plate: string|null, vehicle: string|null, total: string, pending_approval: array{type: string, total: string, deadline: string|null}|null, history: list<array{status: string, label: string, occurred_at: string}>}}
     */
    public function __invoke(string $order, Request $request, TrackServiceOrder $track): JsonResponse
    {
        $validated = $request->validate([
            /** @example 52998224725 */
            'document' => ['required', 'string', 'max:20'],
        ]);

        $view = $track->execute($order, (string) $validated['document']);

        return response()->json(['data' => [
            'id' => $view->id,
            'status' => $view->status,
            'status_label' => $view->statusLabel,
            'license_plate' => $view->licensePlate,
            'vehicle' => $view->vehicle,
            'total' => $view->total,
            'pending_approval' => $view->pendingApproval,
            'history' => $view->history,
        ]]);
    }
}
