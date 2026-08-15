<?php

declare(strict_types=1);

namespace App\Corporate;

/**
 * Contenus statiques des pages institutionnelles (« corporate »).
 *
 * Comme pour les autres flows, tout vient de la maquette : aucun texte n'est
 * inventé. Les coquilles de la maquette (« activivté », « Regions »…) sont
 * corrigées et listées dans docs/note-conformite-12-08.md.
 */
final class StaticCorporate
{
    /**
     * Barre de statistiques de l'écran « À propos » (5 entrées).
     *
     * @return list<array{icon: string, tone: string, value: string, label: string}>
     */
    public static function stats(): array
    {
        return [
            ['icon' => 'book_open', 'tone' => 'violet', 'value' => '+10.000', 'label' => 'Activités disponibles'],
            ['icon' => 'users', 'tone' => 'blue', 'value' => '+2,5 millions', 'label' => 'Utilisateurs satisfaits'],
            ['icon' => 'handshake', 'tone' => 'orange', 'value' => '+50.000', 'label' => 'Partenaires & prestataires'],
            ['icon' => 'map_pin', 'tone' => 'green', 'value' => '+350', 'label' => 'Destinations en France'],
            ['icon' => 'map_pin', 'tone' => 'green', 'value' => '4.8/5', 'label' => 'Note moyenne'],
        ];
    }

    /**
     * « Pourquoi Nous Choisir ? » — 5 cartes numérotées, la 2e mise en avant.
     *
     * @return list<array{num: string, title: string, text: string, featured: bool}>
     */
    public static function values(): array
    {
        $partage = "Nous croyons au partage d'expériences et à la création de souvenirs inoubliables.";
        $equipe = "Notre équipe est passionnée par les loisirs et s'engage à vous faire vivre le meilleur.";

        return [
            ['num' => '01', 'title' => 'La confiance', 'text' => $partage, 'featured' => false],
            ['num' => '02', 'title' => 'Le partage', 'text' => $partage, 'featured' => true],
            ['num' => '03', 'title' => "L'authenticité", 'text' => $equipe, 'featured' => false],
            ['num' => '04', 'title' => 'La passion', 'text' => $equipe, 'featured' => false],
            ['num' => '05', 'title' => 'La qualité', 'text' => 'Nous sélectionnons les meilleures activités pour vous garantir le meilleur rapport qualité-prix.', 'featured' => false],
        ];
    }

    /**
     * Trombinoscope : 16 membres sur 4 rangées.
     *
     * @return list<array{name: string, role: string, photo: string}>
     */
    public static function team(): array
    {
        $members = [
            ['Thomas Martin', 'Cofondateur & CEO'],
            ['Maxime', 'Cofondateur & COO'],
            ['Charlotte', 'Créatrice de contenus'],
            ['Ferdinand', 'Head of Brand & communications'],
            ['Sophie Bernard', 'Directrice Marketing'],
            ['Julien Petit', 'Chargé de Partenariats'],
            ['Charlotte Alice', 'Responsable Partenaires'],
            ['Ferdinand', 'Head of Brand & communications'],
            ['Camille Durand', 'Responsable Expérience Client'],
            ['Alexandre Leroy', 'Développeur Produits'],
            ['Charlotte', 'Créatrice de contenus'],
            ['Bernard', 'Event Manager B2B'],
            ['Thomas', 'Cofondateur & CEO'],
            ['Maxime', 'Cofondateur & COO'],
            ['Charlotte', 'Créatrice de contenus'],
            ['Ferdinand', 'Head of Brand & communications'],
        ];

        $out = [];
        foreach ($members as $i => [$name, $role]) {
            $out[] = [
                'name' => $name,
                'role' => $role,
                'photo' => sprintf('images/corporate/team-%d.jpg', $i + 1),
            ];
        }

        return $out;
    }

    /**
     * « Pourquoi devenir partenaire ? » — grille de 4 colonnes qui alterne
     * cartes de texte et photos, dans l'ordre exact de la maquette.
     *
     * @return list<array{type: string, icon?: string, tone?: string, title?: string, text?: string, photo?: string}>
     */
    public static function partnerBenefits(): array
    {
        $audience = "Sublimez votre activité auprès d'une audience qualifiée et passionnée.";

        return [
            ['type' => 'card', 'icon' => 'rocket', 'tone' => 'violet', 'title' => 'Boostez votre visibilité', 'text' => $audience],
            ['type' => 'card', 'icon' => 'megaphone', 'tone' => 'orange', 'title' => 'Gérez simplement', 'text' => 'Un espace partenaire intuitif pour gérer vos offres, disponibilités et réservations.'],
            ['type' => 'photo', 'photo' => 'images/corporate/partner-p1.jpg'],
            ['type' => 'card', 'icon' => 'chart_up', 'tone' => 'orange', 'title' => 'Suivez vos performances', 'text' => 'Accédez à des statistiques détaillées pour suivre et développer votre activité.'],
            ['type' => 'card', 'icon' => 'calendar_check', 'tone' => 'blue', 'title' => 'Augmentez vos réservations', 'text' => $audience],
            ['type' => 'photo', 'photo' => 'images/corporate/partner-p2.jpg'],
            ['type' => 'card', 'icon' => 'handshake_solid', 'tone' => 'green', 'title' => 'Un partenariat de confiance', 'text' => 'Une équipe à votre écoute et un accompagnement personnalisé.'],
            ['type' => 'photo', 'photo' => 'images/corporate/partner-p3.jpg'],
        ];
    }

    /**
     * « Comment ça marche ? » — les cinq étapes du parcours partenaire.
     *
     * @return list<array{icon: string, title: string, text: string}>
     */
    public static function partnerSteps(): array
    {
        return [
            ['icon' => 'user_plus', 'title' => 'Inscrivez-vous', 'text' => 'Créez votre compte partenaire gratuitement.'],
            ['icon' => 'store', 'title' => 'Ajoutez vos offres', 'text' => 'Présentez vos activités, services et disponibilités.'],
            ['icon' => 'calendar_check', 'title' => 'Recevez des réservations', 'text' => 'Vos clients réservent en ligne 24/7.'],
            ['icon' => 'banknote', 'title' => 'Soyez payé', 'text' => 'Nous nous occupons des paiements sécurisés.'],
            ['icon' => 'chart_up', 'title' => 'Développez votre activité', 'text' => 'Fidélisez vos clients et faites grandir votre business.'],
        ];
    }

    /**
     * « Ils nous font confiance » — deux témoignages (texte lorem de la
     * maquette, repris tel quel).
     *
     * @return list<array{rating: string, reviews: string, quote: string, author: string, role: string}>
     */
    public static function testimonials(): array
    {
        $quote = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Vivamus tincidunt sem eget magna gravida consequat. Nunc dictum facilisis dolor ac luctus.';

        return [
            ['rating' => '4.5', 'reviews' => '14 évaluations', 'quote' => $quote, 'author' => 'Laure Petrini', 'role' => 'Gérante - Aventure Nature à Lyon'],
            ['rating' => '4.5', 'reviews' => '14 évaluations', 'quote' => $quote, 'author' => 'Laure Petrini', 'role' => 'Atelier pâtisserie à Paris'],
        ];
    }

    /**
     * Bannière crème de bas de page : les trois arguments.
     *
     * @return list<array{title: string, text: string}>
     */
    public static function partnerArguments(): array
    {
        return [
            ['title' => 'Inscription gratuite', 'text' => "Sans frais d'entrée"],
            ['title' => 'Sans engagement', 'text' => 'Résiliez à tout moment'],
            ['title' => 'Accompagnement dédié', 'text' => 'Une équipe à votre écoute'],
        ];
    }

    /**
     * Offres d'emploi (Carrières). Le texte de description est le lorem de la
     * maquette, repris tel quel.
     *
     * @return list<array{title: string, text: string, city: string, contract: string, time: string, dept: string}>
     */
    public static function jobs(): array
    {
        $lorem = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Vivamus tincidunt sem eget magna gravida consequat. Nunc dictum facilisis dolor ac luctus.';

        return [
            ['title' => 'Développeur Full Stack (H/F)', 'text' => $lorem, 'city' => 'Lyon, Rue5892', 'contract' => 'CDD', 'time' => 'Temps complet', 'dept' => 'Technique'],
            ['title' => 'Responsable Marketing Digital (H/F)', 'text' => $lorem, 'city' => 'Lyon, Rue5892', 'contract' => 'CDD', 'time' => 'Temps complet', 'dept' => 'Marketing'],
            ['title' => 'Chargé(e) Expériences client (H/F)', 'text' => $lorem, 'city' => 'Lyon, Rue5892', 'contract' => 'CDI', 'time' => 'Temps complet', 'dept' => 'Relation client'],
        ];
    }

    /**
     * « Nos valeurs au coeur de notre quotidien » — 6 cartes numérotées, la 2e
     * mise en avant ; les numéros changent de couleur (maquette).
     *
     * @return list<array{num: string, tone: string, title: string, text: string, featured: bool}>
     */
    public static function careerValues(): array
    {
        return [
            ['num' => '01', 'tone' => 'violet', 'title' => 'Passion', 'text' => 'Nous aimons les loisirs et les expériences inoubliables.', 'featured' => false],
            ['num' => '02', 'tone' => 'green', 'title' => "Esprit d'équipe", 'text' => 'Nous avançons ensemble, dans la confiance et la bienveillance.', 'featured' => true],
            ['num' => '03', 'tone' => 'orange', 'title' => 'Innovation', 'text' => 'Nous croyons en de nouvelles idées pour améliorer chaque jour l’expérience utilisateur.', 'featured' => false],
            ['num' => '04', 'tone' => 'blue', 'title' => 'Impact positif', 'text' => 'Nous valorisons le tourisme et les activités locales durablement.', 'featured' => false],
            ['num' => '05', 'tone' => 'amber', 'title' => 'Confiance', 'text' => 'Nous agissons avec transparence et responsabilité.', 'featured' => false],
            ['num' => '06', 'tone' => 'navy', 'title' => 'Ambition', 'text' => 'Nous visons l’excellence pour devenir la référence des loisirs en France.', 'featured' => false],
        ];
    }

    /**
     * « Pourquoi postuler chez nous ? » — 5 cartes numérotées, la 2e en violet
     * plein ; le bloc de titre occupe la première case de la grille.
     *
     * @return list<array{num: string, title: string, text: string, featured: bool}>
     */
    public static function careerReasons(): array
    {
        return [
            ['num' => '01', 'title' => 'Télétravail flexible', 'text' => 'Organisation du travail adaptée à votre quotidien', 'featured' => false],
            ['num' => '02', 'title' => 'Evolution & formation', 'text' => 'Des opportunités pour grandir et apprendre', 'featured' => true],
            ['num' => '03', 'title' => 'Équilibre vie pro/perso', 'text' => 'Nous respectons votre équilibre et votre bien-être', 'featured' => false],
            ['num' => '04', 'title' => 'Avantages', 'text' => 'Tickets restaurant, mutuelle, avantages loisirs…', 'featured' => false],
            ['num' => '05', 'title' => "Événements d'équipe", 'text' => 'éminaires, activités et bons moments garantis !', 'featured' => false],
        ];
    }

    /**
     * Détail d'une offre (modale de l'écran « Toutes les offres »).
     *
     * @return array{title: string, place: string, note: string, manager: string, division: string, mission: list<string>, description: list<string>, education: list<string>, experience: list<string>}
     */
    public static function jobDetail(): array
    {
        $a = 'Neque porro quisquam est qui dolorem ipsum quia dolor sit amet, consecteturNeque';
        $b = 'porro quisquam est qui dolorem ipsum quia dolor sit amet, consectetur';
        $c = 'Neque porro quisquam est qui dolorem ipsum quia dolor sit amet, consectetur Neque porro quisquam est qui dolorem ipsum quia dolor sit amet, consectetur';
        $court = [
            'Neque porro quisquam est qui dolorem ipsu',
            'porro quisquam est qui dolorem ipsum quia dolo',
            'Neque porro quisquam est qui dolorem ipsum quia',
        ];

        return [
            'title' => 'Développeur Full Stack (H/F)',
            'place' => 'Pays de la Loire ,Nantes France',
            'note' => 'Soyez le premier à postuler',
            'manager' => 'Responsable senior - IT,Design et communication',
            'division' => 'IT, Design et communication',
            'mission' => [$a, $b, $c],
            'description' => [$a, $b, $c, $a, $b, $c, $a, $b, $c],
            'education' => $court,
            'experience' => $court,
        ];
    }

    /**
     * « Contactez-nous » — les trois moyens de contact de la colonne droite.
     *
     * @return list<array{icon: string, tone: string, title: string, text: string, link: string, strong: string, sub: string}>
     */
    public static function contactMethods(): array
    {
        return [
            ['icon' => 'rocket', 'tone' => 'blue', 'title' => 'FAQ', 'text' => 'Trouvez des réponses à vos questions courantes', 'link' => 'Voir la FAQ', 'strong' => '', 'sub' => ''],
            ['icon' => 'phone', 'tone' => 'green', 'title' => 'Par Téléphone', 'text' => '', 'link' => '', 'strong' => 'Contact@trouvemoi.fr', 'sub' => 'Lun. - Vend. 9h - 18h'],
            ['icon' => 'comment', 'tone' => 'orange', 'title' => 'Chat en ligne', 'text' => 'Discutez avec notre équipe en direct', 'link' => 'Démarrez le chat', 'strong' => '', 'sub' => ''],
        ];
    }

    /**
     * Les quatre arguments en pied de « Contactez-nous ».
     *
     * @return list<array{icon: string, title: string, text: string}>
     */
    public static function contactArguments(): array
    {
        return [
            ['icon' => 'calendar_check', 'title' => 'Support 7j/7', 'text' => 'Notre équipe est disponible tous les jours'],
            ['icon' => 'headset_mic', 'title' => 'Une équipe à votre écoute', 'text' => 'Des conseillers passionnés pour vous aider'],
            ['icon' => 'clock', 'title' => 'Réponse rapide', 'text' => 'Nous nous engageons à vous répondre sous 24h'],
            ['icon' => 'thumbs_up', 'title' => 'Votre satisfaction', 'text' => 'Votre satisfaction est notre priorité'],
        ];
    }

    /**
     * « Comment nous protégeons vos paiements » — 4 cartes.
     *
     * @return list<array{icon: string, tone: string, title: string, text: string}>
     */
    public static function paymentCards(): array
    {
        return [
            ['icon' => 'card', 'tone' => 'blue', 'title' => 'Cryptage SSL 256 bits', 'text' => 'Toutes les données échangées entre votre navigateur et notre site sont cryptées avec la technologie SSL 256 bits.'],
            ['icon' => 'handshake_solid', 'tone' => 'violet', 'title' => 'Partenaires de confiance', 'text' => 'Nous collaborons avec des prestataires de paiement reconnus et certifiés pour garantir la sécurité de vos transactions.'],
            ['icon' => 'shield', 'tone' => 'orange', 'title' => 'Conformité PCI DSS', 'text' => 'Notre plateforme est conforme à la norme PCI DSS, la référence mondiale en matière de sécurité des données de paiement.'],
            ['icon' => 'eye_off', 'tone' => 'green', 'title' => 'Aucune donnée stockée', 'text' => 'Nous ne stockons jamais vos informations bancaires sur nos serveurs.'],
        ];
    }

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
                    'Email : contact@trouvemoi.fr',
                    'Téléphone : 01 84 80 37 37',
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
            'Réservations et paiements' => 'Les réservations effectuées via la plateforme sont soumises aux conditions particulières du prestataire concerné. Les paiements sont sécurisés et traités par nos partenaires certifiés.',
            'Responsabilités' => "TrouveMoi agit en qualité d'intermédiaire entre les utilisateurs et les prestataires. Sa responsabilité ne saurait être engagée en cas de manquement du prestataire à ses obligations.",
            'Données personnelles' => 'Les données personnelles collectées sont traitées conformément à notre politique de confidentialité et à la réglementation en vigueur.',
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
