<?php

declare(strict_types=1);

namespace App\Legal;

/**
 * Texte initial des mentions légales et des CGU (sections), servi tant
 * qu'aucune version n'est publiée depuis le back-office. Auparavant dans
 * StaticCorporate (supprimée le 09/11). Version anglaise : InitialLegalTextsEn.
 */
final class InitialLegalSections
{
    /**
     * Mentions légales : sommaire + sections.
     *
     * @return list<array{title: string, intro: string, items: list<string>, paragraphs: list<string>}>
     */
    public static function legalSections(): array
    {
        return [
            [
                'title' => 'Éditeur du site',
                'intro' => 'Le site TrouveMoi Plaisirs & Loisirs est édité par :',
                'items' => [
                    'TrouveMoi',
                    'Société par actions simplifiée (SAS) au capital de 100.000 €',
                    '28 rue de la Paix, 75002 Paris, France',
                    'RCS Paris 123 456 789',
                    'Numéro de TVA intracommunautaire : FR12 123 456 789',
                    'Email : contact@trouvemoi.com',
                    'Téléphone : 07 45 15 54 51',
                ],
                'paragraphs' => [],
            ],
            [
                'title' => 'Hébergeur',
                'intro' => 'Le site est hébergé par :',
                'items' => [
                    'OVHcloud',
                    '2 rue Kellermann | 59100 Roubaix, France',
                    'Téléphone : 09 72 10 10 07',
                    'Site web : www.ovhcloud.com',
                ],
                'paragraphs' => [],
            ],
            [
                'title' => 'Propriété intellectuelle',
                'intro' => '',
                'items' => [],
                'paragraphs' => ["L'ensemble du contenu présent sur ce site (textes, images, logos, icônes, graphismes, etc.) est la propriété exclusive de TrouveMoi, sauf mention contraire, et est protégé par les lois en vigueur sur la propriété intellectuelle. Toute reproduction, représentation, modification, publication, adaptation totale ou partielle de tout ou partie des éléments du site, quel que soit le moyen ou le procédé utilisé, est interdite sans l'autorisation écrite préalable de TrouveMoi."],
            ],
            [
                'title' => 'Responsabilité',
                'intro' => '',
                'items' => [],
                'paragraphs' => ["TrouveMoi s'efforce de fournir sur le site des informations aussi précises que possible. Cependant, elle ne pourra être tenue responsable des omissions, des inexactitudes et des carences dans la mise à jour. L'utilisation des informations et contenus disponibles sur le site se fait sous l'entière responsabilité de l'utilisateur."],
            ],
            [
                'title' => 'Liens hypertextes',
                'intro' => '',
                'items' => [],
                'paragraphs' => ["Le site peut contenir des liens hypertextes vers d'autres sites. TrouveMoi n'exerce aucun contrôle sur ces sites et décline toute responsabilité quant à leur contenu."],
            ],
            [
                'title' => 'Droit applicable',
                'intro' => '',
                'items' => [],
                'paragraphs' => ['Les présentes mentions légales sont régies par le droit français. En cas de litige, une solution amiable sera recherchée avant toute action judiciaire. À défaut, les tribunaux compétents de Paris seront saisis.'],
            ],
        ];
    }

    /**
     * Conditions générales d'utilisation.
     *
     * La planche est nommée « Politiques de confidentialités » mais son contenu
     * est bien celui des CGU (voir docs/note-conformite-12-08.md).
     *
     * @return list<array{title: string, intro: string, items: list<string>, paragraphs: list<string>}>
     */
    public static function cguSections(): array
    {
        $textes = [
            'Objet' => "Les présentes CGU ont pour objet de définir les modalités d'accès et d'utilisation de la plateforme TrouveMoi Plaisirs & Loisirs, exploitée par la société TrouveMoi, ainsi que les droits et obligations des utilisateurs.",
            'Accès au service' => "La plateforme est accessible gratuitement à tout utilisateur disposant d'un accès Internet. Certains services peuvent nécessiter la création d'un compte utilisateur. L'utilisateur s'engage à fournir des informations exactes et à jour lors de son inscription.",
            'Utilisation de la plateforme' => "Vous vous engagez à utiliser la plateforme conformément à la loi et aux présentes CGU. Il est interdit d'utiliser la plateforme à des fins illégales, frauduleuses ou portant atteinte aux droits de tiers.",
            'Comptes utilisateurs' => 'Vous êtes responsable de la confidentialité de vos identifiants et de toutes les activités effectuées depuis votre compte. Vous devez nous informer immédiatement de toute utilisation non autorisée de votre compte.',
            'Contenus et activités' => "Les informations, descriptions, photos et avis présents sur la plateforme sont fournis par les utilisateurs ou nos partenaires. TrouveMoi Plaisirs & Loisirs ne peut être tenu responsable de l'exactitude ou de l'exhaustivité de ces informations.",
            'Réservations et paiements' => 'Les réservations effectuées via la plateforme sont soumises aux conditions des prestataires partenaires. Le prix des prestations est réglé directement au prestataire, par les moyens qu\'il propose : aucune somme liée à une prestation ne transite par TrouveMoi. Les abonnements des professionnels et les bons cadeaux sont payés à TrouveMoi par l\'intermédiaire de son partenaire de paiement agréé. Les conditions d\'annulation et de remboursement sont précisées avant chaque réservation.',
            'Responsabilités' => "TrouveMoi Plaisirs & Loisirs met tout en œuvre pour assurer la disponibilité et la fiabilité de la plateforme, mais ne garantit pas l'absence d'erreurs ou d'interruptions. Notre responsabilité ne saurait être engagée pour tout dommage indirect lié à l'utilisation de la plateforme.",
            'Données personnelles' => 'La collecte et le traitement de vos données personnelles sont effectués conformément à notre Politique de Confidentialité, disponible sur notre site.',
            'Modification des CGU' => 'TrouveMoi se réserve le droit de modifier les présentes CGU à tout moment. Les utilisateurs seront informés de toute modification substantielle.',
            'Droit applicable et litiges' => 'Les présentes CGU sont soumises au droit français. En cas de litige, une solution amiable sera recherchée avant toute action judiciaire.',
        ];

        $out = [];
        foreach ($textes as $title => $texte) {
            $out[] = ['title' => $title, 'intro' => '', 'items' => [], 'paragraphs' => [$texte]];
        }

        return $out;
    }
}
