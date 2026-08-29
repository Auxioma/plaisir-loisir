<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use App\Availability\Entity\Availability;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;

/**
 * Disponibilites : les jours et heures ou une activite est ouverte.
 *
 * POURQUOI CET ECRAN EXISTE
 * Le filtre « Date » de la barre de recherche s'appuie sur ces creneaux depuis
 * le 29/08. Le modele existait de longue date, mais AUCUN ecran ne permettait
 * d'en saisir : la table restait vide, et le filtre ne pouvait donc rien
 * exclure en production. C'etait le dernier maillon manquant.
 *
 * LA REGLE A COMPRENDRE AVANT DE SAISIR — elle surprend tout le monde :
 * tant qu'une activite n'a AUCUN creneau, elle reste proposee QUELLE QUE SOIT
 * la date demandee. Ne rien avoir declare veut dire « on ne sait pas », pas
 * « ferme » ; l'inverse viderait le catalogue des qu'un visiteur touche au
 * calendrier. Mais des qu'une activite a UN SEUL creneau, elle disparait de
 * tous les autres jours. Declarer partiellement est donc pire que ne rien
 * declarer : c'est le piege de cet ecran, et il est rappele en tete des
 * formulaires.
 *
 * CE QUI NE SE MODIFIE PAS A LA MAIN
 * Le nombre de places prises est une CONSEQUENCE des reservations, pas une
 * saisie. L'entite n'expose d'ailleurs aucun moyen de l'ecrire directement,
 * seulement de reserver ou de liberer des places. Le champ est donc affiche,
 * jamais propose au formulaire : le corriger a la main ferait vendre deux fois
 * la meme place.
 *
 * @extends AbstractCrudController<Availability>
 */
class AvailabilityCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Availability::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        $piege = 'Tant qu\'une activité n\'a AUCUN créneau, elle reste proposée à toutes les dates. '
            .'Dès qu\'elle en a un seul, elle disparaît de tous les autres jours : '
            .'mieux vaut ne rien déclarer que déclarer à moitié.';

        return $crud
            ->setEntityLabelInSingular('créneau')
            ->setEntityLabelInPlural('disponibilités')
            ->setPageTitle(Crud::PAGE_INDEX, 'Disponibilités')
            ->setHelp(Crud::PAGE_NEW, $piege)
            ->setHelp(Crud::PAGE_EDIT, $piege)
            // Le plus proche en premier : on ouvre cet écran pour vérifier ou
            // compléter les jours à venir, pas pour relire l'an dernier.
            ->setDefaultSort(['startsAt' => 'ASC'])
            ->setSearchFields(['service.title']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield AssociationField::new('service', 'Activité')
            ->setFormTypeOption('choice_label', 'title')
            ->formatValue(static fn (mixed $v, Availability $a): ?string => $a->getService()?->getTitle());

        yield DateTimeField::new('startsAt', 'Ouvre le')
            ->setHelp('Début du créneau, heure comprise.');

        yield DateTimeField::new('endsAt', 'Ferme le')
            ->setHelp('Doit venir après l\'ouverture. Pour une journée entière, mettre 9h00 puis 18h00.');

        yield IntegerField::new('capacity', 'Places')
            ->setHelp('Nombre de personnes acceptées sur ce créneau.');

        // Lecture seule : voir l'en-tête de classe.
        yield IntegerField::new('booked', 'Places prises')
            ->hideOnForm();

        yield IntegerField::new('remainingSeats', 'Places restantes')
            ->hideOnForm();
    }
}
