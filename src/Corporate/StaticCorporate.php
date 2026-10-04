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
            ['icon' => 'users', 'tone' => 'blue', 'value' => '+10 000', 'label' => 'Activités disponibles'],
            ['icon' => 'users', 'tone' => 'violet', 'value' => '+2,5 millions', 'label' => 'Utilisateurs satisfaits'],
            ['icon' => 'store', 'tone' => 'orange', 'value' => '+50 000', 'label' => 'Partenaires & prestataires'],
            ['icon' => 'map_pin', 'tone' => 'green', 'value' => '+350', 'label' => 'Destinations partout en France'],
            ['icon' => 'star', 'tone' => 'yellow', 'value' => '4,8/5', 'label' => 'Note moyenne des utilisateurs'],
        ];
    }

    /**
     * « Pourquoi Nous Choisir ? » — 5 cartes numérotées, la 2e mise en avant.
     *
     * @return list<array{icon: string, tone: string, title: string, text: string}>
     */
    public static function values(): array
    {
        return [
            ['icon' => 'shield_check', 'tone' => 'blue', 'title' => 'Confiance', 'text' => 'Des partenaires vérifiés et des avis authentiques pour des expériences en toute sérénité.'],
            ['icon' => 'users', 'tone' => 'orange', 'title' => 'Partage', 'text' => "Nous croyons au partage d'expériences et à la création de souvenirs inoubliables."],
            ['icon' => 'leaf', 'tone' => 'green', 'title' => 'Authenticité', 'text' => 'Nous mettons en avant des activités locales, authentiques et respectueuses.'],
            ['icon' => 'star', 'tone' => 'yellow', 'title' => 'Qualité', 'text' => 'Nous sélectionnons les meilleures activités pour vous garantir le meilleur rapport qualité-prix.'],
            ['icon' => 'heart', 'tone' => 'red', 'title' => 'Passion', 'text' => "Notre équipe est passionnée par les loisirs et s'engage à vous faire vivre le meilleur."],
        ];
    }

    /**
     * Trombinoscope : 16 membres sur 4 rangées.
     *
     * @return list<array{name: string, role: string, photo: string}>
     */
    public static function team(): array
    {
        return [
            ['name' => 'Thomas Martin', 'role' => 'Fondateur & CEO', 'photo' => 'images/corporate/team-1.jpg'],
            ['name' => 'Sophie Bernard', 'role' => 'Directrice Marketing', 'photo' => 'images/corporate/team-5.jpg'],
            ['name' => 'Julien Petit', 'role' => 'Responsable Partenariats', 'photo' => 'images/corporate/team-6.jpg'],
            ['name' => 'Camille Durand', 'role' => 'Responsable Expérience Client', 'photo' => 'images/corporate/team-9.jpg'],
            ['name' => 'Alexandre Leroy', 'role' => 'Développeur Produits', 'photo' => 'images/corporate/team-10.jpg'],
        ];
    }

    /**
     * « Pourquoi devenir partenaire ? » — grille de 4 colonnes qui alterne
     * cartes de texte et photos, dans l'ordre exact de la maquette.
     *
     * @return list<array{icon: string, tone: string, title: string, text: string}>
     */
    public static function partnerBenefits(): array
    {
        return [
            ['icon' => 'rocket', 'tone' => 'violet', 'title' => 'Boostez votre visibilité', 'text' => "Sublimez votre activité auprès d'une audience qualifiée et passionnée."],
            ['icon' => 'calendar_check', 'tone' => 'blue', 'title' => 'Augmentez vos réservations', 'text' => 'Recevez plus de demandes et de réservations en ligne, facilement.'],
            ['icon' => 'megaphone', 'tone' => 'orange', 'title' => 'Gérez simplement', 'text' => 'Un espace partenaire intuitif pour gérer vos offres, disponibilités et réservations.'],
            ['icon' => 'chart', 'tone' => 'blue', 'title' => 'Suivez vos performances', 'text' => 'Accédez à des statistiques détaillées pour suivre et développer votre activité.'],
            ['icon' => 'handshake', 'tone' => 'red', 'title' => 'Un partenariat de confiance', 'text' => 'Une équipe à votre écoute et un accompagnement personnalisé.'],
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
            ['icon' => 'user_plus', 'title' => 'Inscription', 'text' => 'Créez votre compte partenaire gratuitement.'],
            ['icon' => 'store', 'title' => 'Ajoutez vos offres', 'text' => 'Présentez vos activités, services et disponibilités.'],
            ['icon' => 'calendar_check', 'title' => 'Recevez des réservations', 'text' => 'Vos clients réservent en ligne 24/7.'],
            ['icon' => 'card', 'title' => 'Soyez payé', 'text' => 'Nous nous occupons des paiements sécurisés.'],
            ['icon' => 'chart', 'title' => 'Développez votre activité', 'text' => 'Fidélisez vos clients et faites grandir votre business.'],
        ];
    }

    /**
     * « Ils nous font confiance » — deux témoignages (texte lorem de la
     * maquette, repris tel quel).
     *
     * @return list<array{rating: string, reviews: string, quote: string, author: string, role: string, photo: string}>
     */
    public static function testimonials(): array
    {
        return [
            ['rating' => '4.9', 'reviews' => '32 évaluations', 'quote' => 'Grâce à TrouveMoi, notre activité a gagné en visibilité et nos réservations ont augmenté de 40% en 6 mois !', 'author' => 'Laura Petrini', 'role' => 'Gérante – Aventure Nature', 'photo' => 'images/corporate/team-9.jpg'],
            ['rating' => '4.8', 'reviews' => '21 évaluations', 'quote' => 'L’espace partenaire est simple : je gère mes créneaux en quelques minutes et je suis payé sans relance.', 'author' => 'Marc Delorme', 'role' => 'Atelier pâtisserie à Paris', 'photo' => 'images/corporate/team-2.jpg'],
            ['rating' => '4.7', 'reviews' => '18 évaluations', 'quote' => 'Une équipe à l’écoute et des clients qui arrivent dès la première semaine. Je recommande.', 'author' => 'Inès Carpentier', 'role' => 'Kayak & paddle à Annecy', 'photo' => 'images/corporate/team-7.jpg'],
        ];
    }

    /**
     * Témoignages des collaborateurs (page Carrières).
     *
     * @return list<array{quote: string, author: string, role: string, photo: string}>
     */
    public static function employeeTestimonials(): array
    {
        return [
            ['quote' => 'Une équipe bienveillante, des projets stimulants et un vrai impact au quotidien. J’adore !', 'author' => 'Anne-Sophie', 'role' => 'Chef de projet', 'photo' => 'images/corporate/team-5.jpg'],
            ['quote' => 'On livre vite, on écoute les utilisateurs et on apprend tous les jours.', 'author' => 'Julien', 'role' => 'Développeur', 'photo' => 'images/corporate/team-6.jpg'],
            ['quote' => 'Travailler sur les loisirs des gens, c’est une source de motivation incroyable.', 'author' => 'Camille', 'role' => 'Expérience client', 'photo' => 'images/corporate/team-9.jpg'],
            ['quote' => 'Le télétravail est vraiment respecté, et les séminaires sont mémorables.', 'author' => 'Alexandre', 'role' => 'Data', 'photo' => 'images/corporate/team-10.jpg'],
        ];
    }

    /**
     * Bannière crème de bas de page : les trois arguments.
     *
     * @return list<array{icon: string, title: string, text: string}>
     */
    public static function partnerArguments(): array
    {
        return [
            ['icon' => 'edit', 'title' => 'Inscription gratuite', 'text' => "Sans frais d'entrée"],
            ['icon' => 'check', 'title' => 'Sans engagement', 'text' => 'Résiliez à tout moment'],
            ['icon' => 'person', 'title' => 'Accompagnement dédié', 'text' => 'Une équipe à votre écoute'],
        ];
    }

    /**
     * Offres d'emploi (Carrières). Le texte de description est le lorem de la
     * maquette, repris tel quel.
     *
     * @return list<array{slug: string, icon: string, tone: string, title: string, text: string, city: string, contract: string, time: string, dept: string}>
     */
    public static function jobs(): array
    {
        return [
            ['slug' => 'developpeur-full-stack', 'icon' => 'monitor', 'tone' => 'blue', 'title' => 'Développeur Full Stack (H/F)', 'text' => 'Concevez et faites évoluer la plateforme TrouveMoi (Symfony, PostgreSQL, Twig) au sein d’une équipe produit à taille humaine.', 'city' => 'Lyon ou télétravail', 'contract' => 'CDI', 'time' => 'Temps complet', 'dept' => 'Tech'],
            ['slug' => 'responsable-marketing-digital', 'icon' => 'megaphone', 'tone' => 'green', 'title' => 'Responsable Marketing Digital (H/F)', 'text' => 'Pilotez l’acquisition et la notoriété de TrouveMoi : SEO, réseaux sociaux, campagnes et partenariats de contenu.', 'city' => 'Paris', 'contract' => 'CDI', 'time' => 'Temps complet', 'dept' => 'Marketing'],
            ['slug' => 'charge-experience-client', 'icon' => 'headset', 'tone' => 'orange', 'title' => 'Chargé(e) Expérience Client (H/F)', 'text' => 'Accompagnez nos membres et nos partenaires au quotidien et faites de chaque échange une expérience réussie.', 'city' => 'Bordeaux', 'contract' => 'CDI', 'time' => 'Temps complet', 'dept' => 'Relation client'],
            ['slug' => 'data-analyst', 'icon' => 'chart', 'tone' => 'violet', 'title' => 'Data Analyst (H/F)', 'text' => 'Transformez nos données d’usage en décisions : tableaux de bord, études et recommandations pour toutes les équipes.', 'city' => 'Nantes ou télétravail', 'contract' => 'CDI', 'time' => 'Temps complet', 'dept' => 'Data'],
        ];
    }

    /**
     * « Nos valeurs au coeur de notre quotidien » — 6 cartes numérotées, la 2e
     * mise en avant ; les numéros changent de couleur (maquette).
     *
     * @return list<array{icon: string, num: string, tone: string, title: string, text: string, featured: bool}>
     */
    public static function careerValues(): array
    {
        return [
            ['num' => '01', 'tone' => 'violet', 'icon' => 'heart', 'title' => 'Passion', 'text' => 'Nous aimons les loisirs et les expériences inoubliables.', 'featured' => false],
            ['num' => '02', 'tone' => 'green', 'icon' => 'users', 'title' => "Esprit d'équipe", 'text' => 'Nous avançons ensemble, dans la confiance et la bienveillance.', 'featured' => true],
            ['num' => '03', 'tone' => 'orange', 'icon' => 'bulb', 'title' => 'Innovation', 'text' => 'Nous croyons en de nouvelles idées pour améliorer chaque jour l’expérience utilisateur.', 'featured' => false],
            ['num' => '04', 'tone' => 'blue', 'icon' => 'leaf', 'title' => 'Impact positif', 'text' => 'Nous valorisons le tourisme et les activités locales durablement.', 'featured' => false],
            ['num' => '05', 'tone' => 'amber', 'icon' => 'shield_check', 'title' => 'Confiance', 'text' => 'Nous agissons avec transparence et responsabilité.', 'featured' => false],
            ['num' => '06', 'tone' => 'navy', 'icon' => 'rocket', 'title' => 'Ambition', 'text' => 'Nous visons l’excellence pour devenir la référence des loisirs en France.', 'featured' => false],
        ];
    }

    /**
     * « Pourquoi postuler chez nous ? » — 5 cartes numérotées, la 2e en violet
     * plein ; le bloc de titre occupe la première case de la grille.
     *
     * @return list<array{icon: string, tone: string, num: string, title: string, text: string, featured: bool}>
     */
    public static function careerReasons(): array
    {
        return [
            ['num' => '01', 'icon' => 'person', 'tone' => 'blue', 'title' => 'Télétravail flexible', 'text' => 'Organisation du travail adaptée à votre quotidien', 'featured' => false],
            ['num' => '02', 'icon' => 'megaphone', 'tone' => 'green', 'title' => 'Évolution & formation', 'text' => 'Des opportunités pour grandir et apprendre', 'featured' => true],
            ['num' => '03', 'icon' => 'hand_heart', 'tone' => 'orange', 'title' => 'Équilibre vie pro/perso', 'text' => 'Nous respectons votre équilibre et votre bien-être', 'featured' => false],
            ['num' => '04', 'icon' => 'shield_check', 'tone' => 'red', 'title' => 'Avantages', 'text' => 'Tickets restaurant, mutuelle, avantages loisirs…', 'featured' => false],
            ['num' => '05', 'icon' => 'party', 'tone' => 'blue', 'title' => "Événements d'équipe", 'text' => 'Séminaires, activités et bons moments garantis !', 'featured' => false],
        ];
    }

    /**
     * Détail d'une offre (modale de l'écran « Toutes les offres »).
     *
     * Fiche d'une offre d'emploi, ou null si le slug est inconnu.
     *
     * @return array{slug: string, icon: string, tone: string, title: string, text: string, city: string, contract: string, time: string, dept: string, manager: string, division: string, mission: list<string>, description: list<string>, education: list<string>, experience: list<string>, place: string, note: string}|null
     */
    public static function jobDetail(string $slug): ?array
    {
        $details = [
            'developpeur-full-stack' => [
                'manager' => 'CTO', 'division' => 'Produit & Technique',
                'mission' => ['Développer de nouvelles fonctionnalités de bout en bout (Symfony 8, Twig, Stimulus).', 'Garantir la qualité : tests automatisés, revues de code, analyse statique.', 'Participer aux choix d’architecture et à l’amélioration continue de la plateforme.'],
                'description' => ['Vous rejoignez une équipe de 6 personnes qui livre chaque semaine.', 'Vous travaillez en lien direct avec le produit, le design et le support.', 'Deux jours de présence par mois à Lyon, le reste en télétravail si vous le souhaitez.'],
                'education' => ['Bac+3 à Bac+5 en informatique ou parcours équivalent.'],
                'experience' => ['3 ans minimum en PHP / Symfony.', 'À l’aise avec SQL (PostgreSQL) et le HTML/CSS.'],
            ],
            'responsable-marketing-digital' => [
                'manager' => 'Directrice Marketing', 'division' => 'Marketing & Communication',
                'mission' => ['Définir et piloter le plan d’acquisition (SEO, SEA, social, e-mailing).', 'Animer les communautés et les campagnes saisonnières.', 'Suivre les indicateurs et optimiser le coût d’acquisition.'],
                'description' => ['Vous encadrez un chargé de communication et travaillez avec des agences.', 'Poste basé à Paris, deux jours de télétravail par semaine.'],
                'education' => ['Bac+5 école de commerce ou université (marketing digital).'],
                'experience' => ['5 ans d’expérience en marketing digital B2C.', 'Une première expérience dans le tourisme ou les loisirs est un plus.'],
            ],
            'charge-experience-client' => [
                'manager' => 'Responsable Expérience Client', 'division' => 'Relation client',
                'mission' => ['Répondre aux demandes des membres et des partenaires (chat, e-mail, téléphone).', 'Suivre les réservations sensibles et les remboursements.', 'Remonter les irritants à l’équipe produit.'],
                'description' => ['Vous rejoignez une équipe de 4 conseillers, du lundi au vendredi.', 'Formation complète à la plateforme assurée à l’arrivée.'],
                'education' => ['Bac+2 minimum (relation client, tourisme, commerce).'],
                'experience' => ['Une première expérience en service client.', 'Excellente expression écrite.'],
            ],
            'data-analyst' => [
                'manager' => 'CTO', 'division' => 'Produit & Données',
                'mission' => ['Construire et maintenir les tableaux de bord de l’entreprise.', 'Mener des analyses ponctuelles (conversion, rétention, offres).', 'Fiabiliser la collecte et la qualité des données.'],
                'description' => ['Vous travaillez avec toutes les équipes, en autonomie.', 'Poste ouvert au télétravail complet.'],
                'education' => ['Bac+5 en statistiques, data ou école d’ingénieur.'],
                'experience' => ['2 ans en analyse de données, SQL avancé.', 'Python ou un outil de BI (Metabase, Looker…).'],
            ],
        ];

        foreach (self::jobs() as $job) {
            if ($job['slug'] === $slug && isset($details[$slug])) {
                return $job + $details[$slug] + ['place' => $job['city'], 'note' => 'Soyez parmi les premiers à postuler'];
            }
        }

        return null;
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
            'Réservations et paiements' => 'Les réservations effectuées via la plateforme sont soumises aux conditions des prestataires partenaires. Les paiements sont sécurisés et traités par nos partenaires de paiement agréés. Les conditions d\'annulation et de remboursement sont précisées avant chaque réservation.',
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
