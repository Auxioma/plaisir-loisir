<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Catalog\Entity\Category;
use App\Catalog\Enum\ActivityLevel;
use App\PrivateActivity\Entity\Participation;
use App\PrivateActivity\Entity\PrivateActivity;
use App\PrivateActivity\Enum\ParticipationMode;
use App\PrivateActivity\Enum\ParticipationStatus;
use App\PrivateActivity\Enum\PrivateActivityVisibility;
use App\User\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Activités gratuites entre membres (bloc « Sorties gratuites près de chez
 * vous » de la page d'accueil, maquette landing_page du 05/10), avec
 * quelques participants. Dates relatives : toujours à venir.
 */
final class PrivateActivityFixtures extends Fixture implements DependentFixtureInterface
{
    public function getDependencies(): array
    {
        return [CatalogFixtures::class, ProviderSpaceFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        $users = array_values(array_filter(
            $manager->getRepository(User::class)->findAll(),
            static fn (User $u): bool => str_ends_with($u->getEmail(), '@client.trouvemoi.test'),
        ));
        if (\count($users) < 4) {
            return;
        }
        $categories = $manager->getRepository(Category::class);

        // [titre, catégorie, ville, CP, lat, lng, jours, heure, durée (h), photo, description, participants, max]
        $rows = [
            ['Randonnée en forêt', 'natures-plein-air', 'Forges-les-Eaux', '76440', '49.6136', '1.5444', 4, '09:30', 4, 'images/events/ev-rando-clean.jpg', 'Boucle de 10 km dans la forêt domaniale, rythme tranquille, pause goûter au bord de l’étang.', 5, 12],
            ['Sortie vélo', 'sports-aventures', 'Rouen', '76000', '49.4431', '1.0993', 6, '10:00', 3, 'images/home/prive-2.jpg', 'Balade à vélo le long des quais de Seine puis vers la forêt de Roumare. 30 km, accessible à tous.', 8, 15],
            ['Pétanque entre amis', 'en-famille', 'Dieppe', '76200', '49.9229', '1.0775', 7, '15:00', 3, 'images/home/prive-3.jpg', 'Tournoi amical de pétanque sur l’esplanade, triplettes formées sur place. Boules prêtées si besoin.', 6, 18],
            ['Repas partagé', 'gastronomies', 'Le Havre', '76600', '49.4944', '0.1079', 5, '19:30', 3, 'images/home/prive-1.jpg', 'Chacun apporte un plat fait maison à partager. Ambiance conviviale, jeux de société en fin de soirée.', 4, 10],
            ['Pique-nique au bord du lac', 'natures-plein-air', 'Annecy', '74000', '45.8992', '6.1294', 9, '12:00', 3, 'images/events/ev-bbq-clean.jpg', 'Pique-nique tiré du sac sur la plage d’Albigny, baignade possible et partie de mölkky.', 7, 20],
            ['Séance de yoga au parc', 'bien-etre', 'Lyon', '69006', '45.7772', '4.8520', 3, '08:30', 1, 'images/events/ev-yoga-clean.jpg', 'Yoga doux au parc de la Tête d’Or, tous niveaux. Apportez votre tapis.', 9, 15],
        ];

        $today = new \DateTimeImmutable('today');
        foreach ($rows as $i => [$title, $slug, $city, $cp, $lat, $lng, $days, $time, $hours, $cover, $description, $going, $max]) {
            $category = $categories->findOneBy(['slug' => $slug]) ?? $categories->findOneBy([]);
            if (null === $category) {
                continue;
            }
            $start = new \DateTimeImmutable($today->modify(sprintf('+%d days', $days))->format('Y-m-d').' '.$time);
            $activity = (new PrivateActivity())
                ->setOrganizer($users[$i % \count($users)])
                ->setTitle($title)
                ->setCategory($category)
                ->setDescription($description)
                ->setScheduledAt($start)
                ->setEndsAt($start->modify(sprintf('+%d hours', $hours)))
                ->setCity($city)
                ->setPostalCode($cp)
                ->setLocation($city)
                ->setLatitude($lat)
                ->setLongitude($lng)
                ->setShowExactAddress(true)
                ->setVisibility(PrivateActivityVisibility::Public)
                ->setParticipationMode(0 === $i % 2 ? ParticipationMode::Automatic : ParticipationMode::Validation)
                ->setMaxParticipants($max)
                // Niveau affiché sur les annonces (07/10).
                ->setLevel([ActivityLevel::AllLevels, ActivityLevel::Intermediate, ActivityLevel::AllLevels, ActivityLevel::Beginner, ActivityLevel::AllLevels, ActivityLevel::Beginner][$i])
                ->setCoverImage($cover);
            $manager->persist($activity);

            for ($p = 1; $p <= min($going, \count($users) - 1); ++$p) {
                $participant = $users[($i + $p) % \count($users)];
                if ($participant === $activity->getOrganizer()) {
                    continue;
                }
                $participation = (new Participation())->setParticipant($participant)->setStatus(ParticipationStatus::Accepted);
                $activity->addParticipation($participation);
                $manager->persist($participation);
            }
        }

        $manager->flush();
    }
}
