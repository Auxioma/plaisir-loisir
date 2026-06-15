<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Catalog\Entity\Category;
use App\Catalog\Entity\Destination;
use App\Catalog\Entity\Media;
use App\Catalog\Entity\Service;
use App\Catalog\Entity\ServicePackage;
use App\Catalog\Enum\ActivityLevel;
use App\Catalog\Enum\BookingType;
use App\Catalog\Enum\CancellationPolicy;
use App\Catalog\Enum\PricingUnit;
use App\Catalog\Enum\ServiceStatus;
use App\Provider\Entity\ProviderProfile;
use App\Provider\Enum\ProviderStatus;
use App\User\Entity\User;
use App\User\Enum\UserStatus;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Données d'exemple pour le catalogue (dev) : un annonceur vérifié, des catégories,
 * une destination et deux activités publiées.
 */
final class CatalogFixtures extends Fixture
{
    public function __construct(private readonly UserPasswordHasherInterface $passwordHasher)
    {
    }

    public function load(ObjectManager $manager): void
    {
        $user = new User();
        $user->setEmail('annonceur@trouvemoi.test');
        $user->setFirstName('Camille');
        $user->setLastName('Diop');
        $user->setStatus(UserStatus::Active);
        $user->setPassword($this->passwordHasher->hashPassword($user, 'Password123'));
        $manager->persist($user);

        $provider = new ProviderProfile();
        $provider->setUser($user);
        $provider->setDisplayName('Camille Aventures');
        $provider->setCompanyName('Aventures SARL');
        $provider->setStatus(ProviderStatus::Verified);
        $manager->persist($provider);

        $bienEtre = (new Category())->setName('Bien-être')->setSlug('bien-etre')->setPosition(1);
        $massage = (new Category())->setName('Massage')->setSlug('massage')->setPosition(1)->setParent($bienEtre);
        $aventure = (new Category())->setName('Aventure')->setSlug('aventure')->setPosition(2);
        $manager->persist($bienEtre);
        $manager->persist($massage);
        $manager->persist($aventure);

        $dakar = (new Destination())
            ->setName('Dakar')->setSlug('dakar')->setCountry('SN')->setRegion('Dakar')
            ->setDescription('Capitale du Sénégal, entre océan et culture.');
        $manager->persist($dakar);

        $massageActivity = new Service();
        $massageActivity->setProvider($provider)->setCategory($massage)->setDestination($dakar)
            ->setTitle('Massage relaxant au bord de mer')->setSlug('massage-relaxant-bord-de-mer')
            ->setShortDescription('1h de détente face à l-océan')
            ->setDescription('Un massage relaxant complet pour évacuer le stress, face à l-océan.')
            ->setBookingType(BookingType::ServiceProduct)->setStatus(ServiceStatus::Published)
            ->setDurationMinutes(60)->setCapacity(1)->setLevel(ActivityLevel::AllLevels)
            ->setLanguages(['fr', 'en'])->setIncluded('Huiles essentielles, serviettes')
            ->setCancellationPolicy(CancellationPolicy::Flexible)
            ->setCity('Dakar')->setCountry('SN');
        $massageActivity->addPackage(
            (new ServicePackage())->setName('Standard')->setPrice('25000.00')->setCurrency('XOF')->setPricingUnit(PricingUnit::PerPerson)
        );
        $massageActivity->addMedia((new Media())->setPath('uploads/massage.jpg')->setType('image')->setPosition(0));
        $manager->persist($massageActivity);

        $kayakActivity = new Service();
        $kayakActivity->setProvider($provider)->setCategory($aventure)->setDestination($dakar)
            ->setTitle('Sortie kayak au lac rose')->setSlug('kayak-lac-rose')
            ->setShortDescription('2h d-aventure guidée')
            ->setDescription('Excursion guidée en kayak sur le célèbre lac rose.')
            ->setBookingType(BookingType::ServiceProduct)->setStatus(ServiceStatus::Published)
            ->setDurationMinutes(120)->setCapacity(8)->setLevel(ActivityLevel::Beginner)
            ->setLanguages(['fr'])->setCancellationPolicy(CancellationPolicy::Moderate)
            ->setCity('Dakar')->setCountry('SN');
        $kayakActivity->addPackage(
            (new ServicePackage())->setName('Découverte')->setPrice('15000.00')->setCurrency('XOF')->setPricingUnit(PricingUnit::PerPerson)
        );
        $manager->persist($kayakActivity);

        $manager->flush();
    }
}
