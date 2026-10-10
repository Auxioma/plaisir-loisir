<?php

declare(strict_types=1);

namespace App\Corporate\Service;

use App\Corporate\Repository\CompanyContactRepository;

/**
 * Coordonnées publiques à afficher : celles saisies dans le back-office
 * (« Coordonnées du site »), sinon les valeurs par défaut ci-dessous.
 */
final class CompanyContactProvider
{
    public const DEFAULT_EMAIL = 'contact@trouvemoi.fr';
    public const DEFAULT_PHONE = '07 45 15 54 51';
    public const DEFAULT_HOURS = 'Lun. – Ven. 9h – 18h';

    /** @var array{email: string, phone: ?string, hours: ?string, address: ?string}|null */
    private ?array $contact = null;

    public function __construct(private readonly CompanyContactRepository $repository)
    {
    }

    /**
     * @return array{email: string, phone: ?string, hours: ?string, address: ?string}
     */
    public function get(): array
    {
        if (null !== $this->contact) {
            return $this->contact;
        }

        $saved = $this->repository->findCurrent();

        return $this->contact = null === $saved
            ? ['email' => self::DEFAULT_EMAIL, 'phone' => self::DEFAULT_PHONE, 'hours' => self::DEFAULT_HOURS, 'address' => null]
            : ['email' => $saved->getEmail(), 'phone' => $saved->getPhone(), 'hours' => $saved->getOpeningHours(), 'address' => $saved->getAddress()];
    }
}
