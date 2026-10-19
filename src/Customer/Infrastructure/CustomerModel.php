<?php

declare(strict_types=1);

namespace Oficina\Customer\Infrastructure;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Modelo de persistência (detalhe de infraestrutura — o agregado é Oficina\Customer\Domain\Customer).
 *
 * @property string $id
 * @property string $name
 * @property string $document
 * @property string $email
 * @property string|null $phone
 */
#[Fillable(['name', 'document', 'email', 'phone'])]
final class CustomerModel extends Model
{
    public $incrementing = false;

    protected $table = 'customers';

    protected $keyType = 'string';

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
