<?php

declare(strict_types=1);

namespace App\Tests\Provider\Entity;

use App\Legal\Entity\CompanyIdentity;
use App\Provider\Entity\ProviderProfile;
use App\Provider\Enum\ProviderStatus;
use PHPUnit\Framework\TestCase;

final class ProviderProfileTest extends TestCase
{
    public function testDefaultStatusIsDraft(): void
    {
        self::assertSame(ProviderStatus::Draft, (new ProviderProfile())->getStatus());
    }

    public function testGetMarkingReflectsStatusValue(): void
    {
        $profile = new ProviderProfile();
        self::assertSame('draft', $profile->getMarking());

        $profile->setStatus(ProviderStatus::Verified);
        self::assertSame('verified', $profile->getMarking());
    }

    public function testSetMarkingUpdatesStatusFromString(): void
    {
        $profile = new ProviderProfile();

        $profile->setMarking('pending_verification');

        self::assertSame(ProviderStatus::PendingVerification, $profile->getStatus());
    }

    public function testSetMarkingWithUnknownPlaceThrows(): void
    {
        // La place inconnue passe par ProviderStatus::from() qui lève \ValueError.
        $this->expectException(\ValueError::class);

        (new ProviderProfile())->setMarking('not-a-real-place');
    }

    public function testSocialFieldsDefaultToNull(): void
    {
        $profile = new ProviderProfile();

        self::assertNull($profile->getFacebookUrl());
        self::assertNull($profile->getInstagramUrl());
        self::assertNull($profile->getLinkedinUrl());
        self::assertNull($profile->getWebsiteUrl());
    }

    /**
     * Les informations fiscales ne vivent plus ici.
     *
     * Elles portaient trois champs que personne ne lisait et qui ne
     * suffisaient a aucun dossier reel ; elles ont ete remplacees par
     * App\Legal\Entity\CompanyIdentity, bien plus complete (forme juridique,
     * SIRET, TVA, siege, representant legal, assurance). Ce test constate le
     * deplacement : si ces accesseurs reapparaissaient sur le profil, ce serait
     * une duplication.
     */
    public function testFiscalDataMovedToCompanyIdentity(): void
    {
        self::assertFalse(method_exists(ProviderProfile::class, 'getFiscalStatus'));
        self::assertFalse(method_exists(ProviderProfile::class, 'getFiscalAddress'));
        self::assertFalse(method_exists(ProviderProfile::class, 'getFiscalCountry'));

        self::assertTrue(method_exists(CompanyIdentity::class, 'getLegalForm'));
        self::assertTrue(method_exists(CompanyIdentity::class, 'getSiret'));
    }
}
