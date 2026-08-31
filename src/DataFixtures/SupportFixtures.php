<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Support\Entity\FaqEntry;
use App\Support\Enum\FaqCategory;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Jeu de départ de la FAQ.
 *
 * POURQUOI CE CONTENU EST DANS LES FIXTURES ET NON DANS UNE COMMANDE
 * Les fixtures sont une dépendance de DÉVELOPPEMENT : ce qui est écrit ici ne
 * partira jamais en production. C'est voulu. Une FAQ vide ne se démontre pas —
 * le Centre d'aide n'afficherait que six cartes « Bientôt disponible » — mais
 * publier des réponses écrites par l'équipe technique serait pire : une phrase
 * sur les remboursements ou les délais d'annulation engage l'éditeur du site,
 * et n'est pas à nous d'écrire.
 *
 * Ces questions décrivent donc UNIQUEMENT le fonctionnement observable du
 * site, jamais une règle commerciale. Elles servent à voir l'écran vivre et à
 * faire tourner les tests. En production, la FAQ démarre vide et se remplit
 * depuis le back-office.
 */
class SupportFixtures extends Fixture implements FixtureGroupInterface
{
    /**
     * Un groupe à part, pour pouvoir charger ces questions SEULES :
     *
     *     php bin/console doctrine:fixtures:load --append --group=support
     *
     * Sans cela, remplir la FAQ d'une base existante imposerait de recharger
     * toutes les fixtures, donc de purger la base — et d'effacer au passage
     * les textes juridiques publiés depuis le back-office, qui eux ne sont pas
     * des fixtures.
     *
     * @return list<string>
     */
    public static function getGroups(): array
    {
        return ['support'];
    }

    public function load(ObjectManager $manager): void
    {
        foreach ($this->questions() as $rang => $donnees) {
            $question = new FaqEntry();
            $question
                ->setCategory($donnees['rubrique'])
                ->setLocale('fr')
                ->setQuestion($donnees['question'])
                ->setAnswer($donnees['reponse'])
                ->setPosition($rang)
                ->setPublished(true)
                ->setFeatured($donnees['avant'] ?? false);

            $manager->persist($question);
        }

        $manager->flush();
    }

    /**
     * @return list<array{rubrique: FaqCategory, question: string, reponse: string, avant?: bool}>
     */
    private function questions(): array
    {
        return [
            [
                'rubrique' => FaqCategory::Booking,
                'question' => 'Comment trouver une activité près de chez moi ?',
                'reponse' => '<p>La barre de recherche de la page d\'accueil accepte une destination, un type d\'activité, une date et un nombre de participants. Chaque champ restreint la liste : une date écarte les activités dont aucun créneau n\'est ouvert ce jour-là, et un nombre de participants écarte celles dont la capacité est inférieure.</p><p>La page <strong>Activités</strong> permet ensuite d\'affiner par ville, par thème ou par budget.</p>',
                'avant' => true,
            ],
            [
                'rubrique' => FaqCategory::Booking,
                'question' => 'Dois-je créer un compte pour consulter les activités ?',
                'reponse' => '<p>Non. La consultation du catalogue, des destinations, des offres du moment et des bons cadeaux est ouverte à tous.</p><p>Un compte devient nécessaire pour enregistrer des favoris, recevoir des notifications, rejoindre un groupe ou créer un événement.</p>',
                'avant' => true,
            ],
            [
                'rubrique' => FaqCategory::Account,
                'question' => 'Comment créer un compte ?',
                'reponse' => '<p>Depuis le bouton <strong>S\'inscrire</strong> en haut de page. Le formulaire demande de choisir entre un compte particulier et un compte professionnel : le second donne accès à la publication d\'activités.</p><p>L\'inscription est aussi possible avec un compte Google ou Facebook.</p>',
            ],
            [
                'rubrique' => FaqCategory::Account,
                'question' => 'J\'ai oublié mon mot de passe, que faire ?',
                'reponse' => '<p>Sur l\'écran de connexion, le lien <strong>Mot de passe oublié</strong> envoie un code à usage unique sur votre adresse e-mail. Ce code permet de choisir un nouveau mot de passe.</p>',
            ],
            [
                'rubrique' => FaqCategory::Account,
                'question' => 'Où retrouver mes favoris ?',
                'reponse' => '<p>Le cœur présent sur chaque activité et chaque destination les ajoute à vos favoris. Ils se retrouvent dans votre espace compte, sous <strong>Mes favoris</strong>, classés en trois onglets : activités, destinations et prestataires.</p>',
            ],
            [
                'rubrique' => FaqCategory::Activities,
                'question' => 'Quelle est la différence entre une activité et un événement ?',
                'reponse' => '<p>Une <strong>activité</strong> est proposée par un prestataire et se réserve à la date de votre choix parmi ses créneaux ouverts.</p><p>Un <strong>événement</strong> est organisé par un membre, à une date unique, et rassemble un groupe de participants. Chacun peut en créer un depuis la rubrique Événements.</p>',
                'avant' => true,
            ],
            [
                'rubrique' => FaqCategory::Activities,
                'question' => 'Comment créer un événement ?',
                'reponse' => '<p>Depuis la rubrique <strong>Événements</strong>, le bouton « Créer un événement » ouvre un formulaire en huit étapes : type, description, lieu, date, participants, tarif, photos et récapitulatif. L\'événement peut être public ou réservé aux personnes invitées.</p>',
            ],
            [
                'rubrique' => FaqCategory::Gifts,
                'question' => 'Comment fonctionne un bon cadeau ?',
                'reponse' => '<p>La rubrique <strong>Bons cadeaux</strong> permet de choisir un montant ou un coffret thématique, d\'y joindre un message et de l\'envoyer par e-mail à la date de votre choix.</p>',
                'avant' => true,
            ],
            [
                'rubrique' => FaqCategory::Providers,
                'question' => 'Je propose des activités, comment les publier ?',
                'reponse' => '<p>La page <strong>Devenir partenaire</strong> présente la démarche et conduit à un formulaire de candidature. Notre équipe revient vers vous pour la mise en place de votre espace professionnel.</p>',
            ],
        ];
    }
}
