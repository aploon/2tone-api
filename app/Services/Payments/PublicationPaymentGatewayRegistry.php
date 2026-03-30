<?php

namespace App\Services\Payments;

use App\Contracts\Payments\PublicationPaymentGatewayInterface;
use InvalidArgumentException;

class PublicationPaymentGatewayRegistry
{
    /** @var array<string, PublicationPaymentGatewayInterface> */
    private array $byId = [];

    /**
     * @param  iterable<PublicationPaymentGatewayInterface>  $gateways
     */
    public function __construct(iterable $gateways)
    {
        foreach ($gateways as $gateway) {
            $this->byId[$gateway->getId()] = $gateway;
        }
    }

    /**
     * @return list<PublicationPaymentGatewayInterface>
     */
    public function all(): array
    {
        return array_values($this->byId);
    }

    public function get(string $id): ?PublicationPaymentGatewayInterface
    {
        return $this->byId[$id] ?? null;
    }

    public function getOrFail(string $id): PublicationPaymentGatewayInterface
    {
        $g = $this->get($id);
        if ($g === null) {
            throw new InvalidArgumentException("Passerelle de paiement inconnue : {$id}");
        }

        return $g;
    }
}
