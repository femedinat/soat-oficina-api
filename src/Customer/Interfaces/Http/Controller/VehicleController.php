<?php

declare(strict_types=1);

namespace Oficina\Customer\Interfaces\Http\Controller;

use App\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Oficina\Customer\Application\DTO\CreateVehicleInputDto;
use Oficina\Customer\Application\DTO\UpdateVehicleInputDto;
use Oficina\Customer\Application\UseCase\ActivateVehicleUseCase;
use Oficina\Customer\Application\UseCase\CreateVehicleUseCase;
use Oficina\Customer\Application\UseCase\DeactivateVehicleUseCase;
use Oficina\Customer\Application\UseCase\GetVehicleByIdUseCase;
use Oficina\Customer\Application\UseCase\GetVehicleByLicensePlateUseCase;
use Oficina\Customer\Application\UseCase\ListVehiclesUseCase;
use Oficina\Customer\Application\UseCase\RegisterVehicleMileageUseCase;
use Oficina\Customer\Application\UseCase\TransferVehicleOwnershipUseCase;
use Oficina\Customer\Application\UseCase\UpdateVehicleUseCase;
use Oficina\Customer\Interfaces\Http\Request\RegisterVehicleMileageRequest;
use Oficina\Customer\Interfaces\Http\Request\StoreVehicleRequest;
use Oficina\Customer\Interfaces\Http\Request\TransferVehicleOwnershipRequest;
use Oficina\Customer\Interfaces\Http\Request\UpdateVehicleRequest;
use Oficina\Customer\Interfaces\Http\Resource\VehicleResource;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

#[Group('Veículos', description: 'Cadastro, consulta, atualização de quilometragem, transferência de propriedade e inativação de veículos.')]
final class VehicleController extends Controller
{
    /**
     * Lista veículos cadastrados (com filtro opcional por cliente e status ativo).
     */
    #[QueryParameter('customer_id', description: 'Filtra veículos pelo UUID do cliente proprietário.', type: 'string', format: 'uuid', example: '0195f3a0-1234-7000-8000-000000000001')]
    #[QueryParameter('only_active', description: 'Filtra pelo status do veículo: true para apenas ativos, false para apenas inativos, ou vazio/omitido para retornar todos.', type: 'boolean', example: true)]
    public function index(Request $request, ListVehiclesUseCase $useCase): AnonymousResourceCollection
    {
        /** @var array{customer_id?: string|null} $validated */
        $validated = $request->validate(
            rules: [
                'customer_id' => ['nullable', 'uuid'],
            ],
            messages: [
                'uuid' => 'O campo :attribute deve ser um UUID válido.',
            ],
            attributes: [
                'customer_id' => 'identificador do cliente',
            ],
        );

        $customerId = $validated['customer_id'] ?? null;
        $onlyActive = $request->filled('only_active')
            ? $request->boolean('only_active')
            : null;

        $vehicles = $useCase->execute(
            customerId: $customerId,
            onlyActive: $onlyActive,
        );

        return VehicleResource::collection($vehicles);
    }

    /**
     * Cadastra um novo veículo na oficina.
     */
    public function store(StoreVehicleRequest $request, CreateVehicleUseCase $useCase): JsonResponse
    {
        /** @var array{license_plate: string, brand: string, model: string, year: int|string, customer_id?: string|null, color?: string|null, mileage?: int|string|null} $validated */
        $validated = $request->validated();

        $output = $useCase->execute(new CreateVehicleInputDto(
            licensePlate: $validated['license_plate'],
            brand: $validated['brand'],
            model: $validated['model'],
            year: (int) $validated['year'],
            customerId: $validated['customer_id'] ?? null,
            color: $validated['color'] ?? null,
            mileage: isset($validated['mileage']) ? (int) $validated['mileage'] : 0,
        ));

        return (new VehicleResource($output))
            ->response()
            ->setStatusCode(SymfonyResponse::HTTP_CREATED);
    }

    /**
     * Consulta os detalhes de um veículo pelo ID (UUID).
     */
    #[PathParameter('id', description: 'Identificador UUID do veículo.', format: 'uuid', example: '0195f3a0-1234-7000-8000-000000000010')]
    public function show(string $id, GetVehicleByIdUseCase $useCase): VehicleResource
    {
        return new VehicleResource($useCase->execute($id));
    }

    /**
     * Consulta um veículo pela placa (antiga ou Mercosul).
     */
    #[PathParameter('plate', description: 'Placa do veículo (padrão antigo ABC1234 ou Mercosul ABC1D23).', example: 'ABC1D23')]
    public function showByLicensePlate(string $plate, GetVehicleByLicensePlateUseCase $useCase): VehicleResource
    {
        return new VehicleResource($useCase->execute($plate));
    }

    /**
     * Atualiza os dados cadastrais de um veículo.
     */
    #[PathParameter('id', description: 'Identificador UUID do veículo.', format: 'uuid', example: '0195f3a0-1234-7000-8000-000000000010')]
    public function update(
        UpdateVehicleRequest $request,
        string $id,
        UpdateVehicleUseCase $useCase,
    ): VehicleResource {
        /** @var array{brand: string, model: string, year: int|string, color?: string|null, license_plate?: string|null} $validated */
        $validated = $request->validated();

        $output = $useCase->execute(new UpdateVehicleInputDto(
            id: $id,
            brand: $validated['brand'],
            model: $validated['model'],
            year: (int) $validated['year'],
            color: $validated['color'] ?? null,
            licensePlate: $validated['license_plate'] ?? null,
        ));

        return new VehicleResource($output);
    }

    /**
     * Registra a quilometragem atual do veículo.
     */
    #[PathParameter('id', description: 'Identificador UUID do veículo.', format: 'uuid', example: '0195f3a0-1234-7000-8000-000000000010')]
    public function registerMileage(
        RegisterVehicleMileageRequest $request,
        string $id,
        RegisterVehicleMileageUseCase $useCase,
    ): VehicleResource {
        /** @var array{mileage: int|string} $validated */
        $validated = $request->validated();

        $output = $useCase->execute($id, (int) $validated['mileage']);

        return new VehicleResource($output);
    }

    /**
     * Transfere a propriedade do veículo para outro cliente.
     */
    #[PathParameter('id', description: 'Identificador UUID do veículo.', format: 'uuid', example: '0195f3a0-1234-7000-8000-000000000010')]
    public function transferOwnership(
        TransferVehicleOwnershipRequest $request,
        string $id,
        TransferVehicleOwnershipUseCase $useCase,
    ): VehicleResource {
        /** @var array{customer_id: string} $validated */
        $validated = $request->validated();

        $output = $useCase->execute($id, $validated['customer_id']);

        return new VehicleResource($output);
    }

    /**
     * Reativa um veículo inativo.
     */
    #[PathParameter('id', description: 'Identificador UUID do veículo.', format: 'uuid', example: '0195f3a0-1234-7000-8000-000000000010')]
    public function activate(string $id, ActivateVehicleUseCase $useCase): VehicleResource
    {
        return new VehicleResource($useCase->execute($id));
    }

    /**
     * Inativa um veículo preservando seu histórico.
     */
    #[PathParameter('id', description: 'Identificador UUID do veículo.', format: 'uuid', example: '0195f3a0-1234-7000-8000-000000000010')]
    public function destroy(string $id, DeactivateVehicleUseCase $useCase): Response
    {
        $useCase->execute($id);

        return response()->noContent();
    }
}
