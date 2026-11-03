<?php

declare(strict_types=1);

namespace Oficina\Customer\Infrastructure;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modelo de persistência (detalhe de infraestrutura — o agregado é Oficina\Customer\Domain\Customer).
 *
 * @property string $id
 * @property string $name
 * @property string $document
 * @property string $email
 * @property string|null $phone
 * @property bool $active
 * @property CarbonImmutable|null $deleted_at
 */
#[Fillable(['name', 'document', 'email', 'phone', 'active'])]
final class CustomerModel extends Model
{
    use SoftDeletes;

    public $incrementing = false;

    protected $table = 'customers';

    protected $keyType = 'string';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    /**
     * Usuário de acesso vinculado (1:1), se existir.
     *
     * @return HasOne<User, $this>
     */
    public function user(): HasOne
    {
        return $this->hasOne(User::class, 'customer_id');
    }
}
