<?php

declare(strict_types=1);

namespace App\Legal;

/**
 * Première rédaction des trois textes qui n'existaient pas : politique de
 * confidentialité, conditions générales de vente, politique de cookies.
 *
 * CE QUE CES TEXTES SONT, ET CE QU'ILS NE SONT PAS
 * Ce sont des PREMIÈRES VERSIONS, écrites à la demande du porteur de projet le
 * 31/08 pour que le site cesse de faire accepter des documents inexistants.
 * Elles sont conformes au RGPD, à la directive ePrivacy telle que la CNIL
 * l'applique, et au code de la consommation. Elles ne remplacent pas la
 * relecture d'un juriste : un texte juridique engage l'éditeur, et personne ici
 * n'est avocat.
 *
 * C'est précisément pour cela qu'elles sont PUBLIÉES EN BASE et non écrites en
 * dur : Loïc les corrige depuis le back-office, sans passer par un
 * déploiement, et chaque correction devient une nouvelle version — les
 * acceptations déjà enregistrées continuent de pointer vers celle qui était en
 * vigueur ce jour-là.
 *
 * LE CONTENU DÉCRIT LE SITE TEL QU'IL EST
 * Rien n'est promis que le code ne fasse. Les données citées sont celles des
 * entités réelles (User, Booking, Favorite, Review, Event, Message,
 * Notification), les sous-traitants sont ceux réellement employés (OVHcloud,
 * Stripe, Google, Facebook, Apple), la durée de conservation du consentement
 * aux cookies est celle de CookieConsentService (13 mois), et le nom du cookie
 * est bien `tm_consent`.
 *
 * DEUX RÉSERVES, SIGNALÉES DANS LE TEXTE
 *  - l'identité de l'éditeur reprend celle des mentions légales, qui est
 *    FICTIVE (« 28 rue de la Paix », RCS 123 456 789) ;
 *  - le médiateur de la consommation, obligatoire à l'article L616-1 du code
 *    de la consommation, reste à désigner par l'éditeur.
 */
final class InitialLegalTexts
{
    /**
     * Coordonnées reprises des mentions légales affichées sur le site.
     *
     * Volontairement centralisées : trois textes qui citent l'éditeur ne
     * doivent pas pouvoir le citer différemment.
     */
    public const EDITEUR = 'TrouveMoi, société par actions simplifiée au capital de 100 000 €, '
        .'dont le siège est situé 28 rue de la Paix, 75002 Paris, immatriculée au RCS de Paris '
        .'sous le numéro 123 456 789';

    public const CONTACT = 'contact@trouvemoi.com';

    public const TELEPHONE = '07 45 15 54 51';

    public static function privacyPolicy(): string
    {
        return self::assemble([
            [
                'Qui est responsable de vos données',
                [
                    '<p>Le responsable du traitement est '.self::EDITEUR.' (ci-après « TrouveMoi », « nous »), éditeur de la plateforme trouvemoi.eu.</p>',
                    '<p>Pour toute question relative à vos données personnelles, vous pouvez nous écrire à <a href="mailto:'.self::CONTACT.'">'.self::CONTACT.'</a> ou nous appeler au '.self::TELEPHONE.'.</p>',
                    '<p>Aucun délégué à la protection des données n\'a été désigné à ce jour ; cette page sera mise à jour si une désignation devient nécessaire.</p>',
                ],
            ],
            [
                'Les données que nous collectons',
                [
                    '<p>Nous ne collectons que les données nécessaires au fonctionnement des services que vous utilisez. Selon votre usage de la plateforme, il peut s\'agir :</p>',
                    '<ul>'
                    .'<li><strong>Compte</strong> : nom, prénom, adresse électronique, mot de passe (conservé sous forme chiffrée et jamais lisible par nos équipes), numéro de téléphone et photo de profil si vous en renseignez un ;</li>'
                    .'<li><strong>Connexion par Google, Facebook ou Apple</strong> : identifiant fourni par le service choisi, nom et adresse électronique associés. Nous ne recevons ni votre mot de passe, ni votre liste de contacts, ni vos publications ;</li>'
                    .'<li><strong>Réservations et paiements</strong> : prestations réservées, dates, nombre de participants, montants et statut du paiement. <strong>Vos coordonnées bancaires ne transitent jamais par nos serveurs</strong> : elles sont saisies directement chez notre prestataire de paiement ;</li>'
                    .'<li><strong>Adresses</strong> : adresse de facturation lorsque la prestation l\'exige ;</li>'
                    .'<li><strong>Vie sur la plateforme</strong> : favoris, listes de favoris, avis publiés, événements créés ou rejoints, groupes, albums de photos, messages échangés avec les autres membres ou avec un prestataire, préférences de notification ;</li>'
                    .'<li><strong>Candidatures et demandes</strong> : informations transmises via le formulaire de contact ou le dossier « Devenir partenaire », y compris les données d\'identification de votre entreprise ;</li>'
                    .'<li><strong>Données techniques</strong> : adresse IP, date et heure des connexions, pages consultées, type de navigateur. Elles servent à la sécurité du service et au diagnostic des pannes.</li>'
                    .'</ul>',
                ],
            ],
            [
                'Pourquoi nous les utilisons, et sur quel fondement',
                [
                    '<p>Le règlement général sur la protection des données impose d\'indiquer, pour chaque usage, la base juridique qui l\'autorise. Les nôtres sont les suivantes :</p>',
                    '<table>'
                    .'<thead><tr><th>Finalité</th><th>Base légale</th></tr></thead>'
                    .'<tbody>'
                    .'<tr><td>Créer et gérer votre compte, vous authentifier</td><td>Exécution du contrat</td></tr>'
                    .'<tr><td>Traiter vos réservations et vos paiements, émettre les justificatifs</td><td>Exécution du contrat</td></tr>'
                    .'<tr><td>Vous mettre en relation avec les prestataires et les autres participants</td><td>Exécution du contrat</td></tr>'
                    .'<tr><td>Répondre à vos demandes et assurer le support</td><td>Intérêt légitime à répondre aux personnes qui nous sollicitent</td></tr>'
                    .'<tr><td>Publier et modérer les avis</td><td>Intérêt légitime à garantir la fiabilité des avis</td></tr>'
                    .'<tr><td>Vous envoyer des informations commerciales</td><td>Votre consentement, révocable à tout moment</td></tr>'
                    .'<tr><td>Mesurer l\'audience du site et améliorer nos services</td><td>Votre consentement, recueilli via le bandeau cookies</td></tr>'
                    .'<tr><td>Prévenir la fraude et sécuriser la plateforme</td><td>Intérêt légitime à protéger le service et ses utilisateurs</td></tr>'
                    .'<tr><td>Conserver les pièces comptables et répondre aux demandes des autorités</td><td>Obligation légale</td></tr>'
                    .'</tbody></table>',
                    '<p>Lorsque le traitement repose sur votre consentement, le refus n\'a aucune conséquence sur l\'accès au service : vous pouvez réserver une activité sans accepter la mesure d\'audience ni les communications commerciales.</p>',
                ],
            ],
            [
                'Combien de temps nous les conservons',
                [
                    '<ul>'
                    .'<li><strong>Compte</strong> : pendant toute la durée de vie du compte, puis trois ans après votre dernière activité, délai au terme duquel il est supprimé ou anonymisé ;</li>'
                    .'<li><strong>Réservations et pièces comptables</strong> : dix ans à compter de la clôture de l\'exercice, conformément à l\'article L123-22 du code de commerce ;</li>'
                    .'<li><strong>Avis publiés</strong> : tant qu\'ils sont en ligne, puis trois ans ;</li>'
                    .'<li><strong>Messages échangés</strong> : trois ans après le dernier échange ;</li>'
                    .'<li><strong>Preuve de votre acceptation des conditions générales</strong> : cinq ans à compter de la fin de la relation, afin de pouvoir établir ce qui a été accepté et à quelle date ;</li>'
                    .'<li><strong>Journaux techniques</strong> : douze mois ;</li>'
                    .'<li><strong>Choix relatif aux cookies</strong> : treize mois, après quoi il vous est redemandé.</li>'
                    .'</ul>',
                ],
            ],
            [
                'Qui y a accès',
                [
                    '<p>Vos données ne sont ni vendues, ni louées, ni échangées. Y accèdent uniquement :</p>',
                    '<ul>'
                    .'<li>les équipes de TrouveMoi, dans la limite de ce que leur mission exige ;</li>'
                    .'<li><strong>le prestataire dont vous réservez l\'activité</strong>, qui reçoit les informations nécessaires à la réalisation de la prestation — votre nom, le nombre de participants et la date. Il est alors responsable du traitement pour ce qu\'il en fait ;</li>'
                    .'<li><strong>les participants d\'un événement ou d\'un groupe</strong> que vous rejoignez, qui voient le nom et la photo que vous avez choisi d\'afficher ;</li>'
                    .'<li>nos sous-traitants techniques, listés ci-dessous, liés par contrat et sans droit d\'usage propre sur vos données ;</li>'
                    .'<li>les autorités administratives ou judiciaires, sur réquisition régulière.</li>'
                    .'</ul>',
                    '<p>Nos sous-traitants sont : <strong>OVHcloud</strong> (hébergement, France) ; <strong>Stripe</strong> (paiement) ; <strong>Google</strong>, <strong>Facebook</strong> et <strong>Apple</strong>, uniquement si vous choisissez de vous connecter par leur intermédiaire ; <strong>Google Analytics</strong> (mesure d\'audience), uniquement si vous l\'acceptez via le bandeau cookies ; ainsi que notre service d\'envoi de courriers électroniques.</p>',
                ],
            ],
            [
                'Transferts hors de l\'Union européenne',
                [
                    '<p>Vos données sont hébergées en France. Certains de nos sous-traitants, notamment pour le paiement et la connexion par compte tiers, sont susceptibles de traiter des données depuis les États-Unis. Ces transferts sont encadrés par les clauses contractuelles types de la Commission européenne ou par une décision d\'adéquation.</p>',
                ],
            ],
            [
                'Vos droits',
                [
                    '<p>Vous disposez à tout moment des droits suivants sur vos données :</p>',
                    '<ul>'
                    .'<li><strong>accès</strong> : obtenir la confirmation que nous traitons vos données, et en recevoir une copie ;</li>'
                    .'<li><strong>rectification</strong> : faire corriger une information inexacte ;</li>'
                    .'<li><strong>effacement</strong> : demander la suppression de vos données, sous réserve de nos obligations comptables et probatoires ;</li>'
                    .'<li><strong>limitation</strong> : demander le gel d\'un traitement le temps d\'une vérification ;</li>'
                    .'<li><strong>opposition</strong> : vous opposer à un traitement fondé sur notre intérêt légitime, ainsi qu\'à la prospection commerciale, sans avoir à vous justifier ;</li>'
                    .'<li><strong>portabilité</strong> : recevoir dans un format lisible par machine les données que vous nous avez fournies ;</li>'
                    .'<li><strong>directives post mortem</strong> : définir le sort de vos données après votre décès.</li>'
                    .'</ul>',
                    '<p>Pour les exercer, écrivez à <a href="mailto:'.self::CONTACT.'">'.self::CONTACT.'</a>. Nous répondons dans un délai d\'un mois. Une pièce d\'identité peut vous être demandée en cas de doute sérieux sur votre identité, et elle est détruite dès la réponse apportée.</p>',
                    '<p>Si notre réponse ne vous satisfait pas, vous pouvez saisir la Commission nationale de l\'informatique et des libertés : CNIL, 3 place de Fontenoy, TSA 80715, 75334 Paris Cedex 07, ou <a href="https://www.cnil.fr" title="Site de la CNIL">www.cnil.fr</a>.</p>',
                ],
            ],
            [
                'Sécurité',
                [
                    '<p>Les échanges avec le site sont chiffrés. Les mots de passe sont stockés sous forme d\'empreintes calculées par une fonction de hachage moderne, et ne peuvent pas être retrouvés, y compris par nous. Les accès aux données sont limités aux personnes qui en ont besoin.</p>',
                    '<p>En cas de violation de données susceptible d\'engendrer un risque élevé pour vos droits, vous en seriez informé dans les meilleurs délais, conformément à l\'article 34 du règlement.</p>',
                ],
            ],
            [
                'Mineurs',
                [
                    '<p>La plateforme n\'est pas destinée aux personnes de moins de quinze ans. Si un compte devait être créé au nom d\'un mineur de moins de quinze ans sans le consentement du titulaire de l\'autorité parentale, il serait supprimé sur simple signalement à <a href="mailto:'.self::CONTACT.'">'.self::CONTACT.'</a>.</p>',
                ],
            ],
            [
                'Cookies',
                [
                    '<p>Le détail des traceurs déposés, de leur finalité et des moyens de revenir sur votre choix figure dans notre <a href="/politique-de-cookies">politique de cookies</a>.</p>',
                ],
            ],
            [
                'Modifications de cette politique',
                [
                    '<p>Cette politique peut évoluer avec les services proposés ou la réglementation. Chaque version est datée et numérotée en haut de cette page. En cas de changement substantiel dans l\'usage de vos données, vous en seriez informé et, si la loi l\'exige, votre accord vous serait redemandé.</p>',
                ],
            ],
        ]);
    }

    public static function termsOfSale(): string
    {
        return self::assemble([
            [
                'Objet',
                [
                    '<p>Les présentes conditions générales de vente régissent la réservation, sur la plateforme trouvemoi.eu, des activités et prestations de loisirs proposées par des prestataires professionnels, la vente de bons cadeaux et les abonnements souscrits par les professionnels.</p>',
                    '<p>Elles s\'appliquent à toute commande passée sur la plateforme. Toute commande vaut acceptation sans réserve des présentes conditions, dans leur version en vigueur au jour de la commande.</p>',
                ],
            ],
            [
                'Rôle de TrouveMoi',
                [
                    '<p><strong>TrouveMoi est une plateforme de mise en relation.</strong> Sauf mention contraire explicite, le contrat de prestation est conclu entre vous et le prestataire qui propose l\'activité, lequel en assure seul l\'exécution, la sécurité et la conformité à la description publiée.</p>',
                    '<p>TrouveMoi met à disposition la plateforme et transmet votre demande de réservation au prestataire. <strong>TrouveMoi n\'encaisse pas le prix des prestations</strong> : vous le réglez directement au prestataire, qui en est seul le bénéficiaire, et aucune somme liée à une prestation ne transite par les comptes de TrouveMoi. Une question sur le déroulement de l\'activité, son prix, son règlement ou son remboursement relève donc du prestataire ; une question sur le fonctionnement de la plateforme relève de nous.</p>',
                ],
            ],
            [
                'Prix',
                [
                    '<p>Les prix sont indiqués en euros, toutes taxes comprises. Ils comprennent la prestation décrite sur la fiche de l\'activité, à l\'exclusion de tout ce qui n\'y est pas expressément mentionné, notamment le transport jusqu\'au lieu de rendez-vous.</p>',
                    '<p>Le prix applicable est celui affiché au moment de la validation de la commande. Une modification ultérieure du tarif d\'une activité est sans effet sur les commandes déjà confirmées.</p>',
                ],
            ],
            [
                'Passation de la commande',
                [
                    '<p>La réservation suit les étapes suivantes : choix de l\'activité, de la date et du nombre de participants ; vérification du récapitulatif, qui peut être corrigé jusqu\'à la validation ; acceptation des présentes conditions ; envoi de la demande au prestataire ; confirmation par le prestataire.</p>',
                    '<p>Le contrat de prestation est formé entre vous et le prestataire lorsque celui-ci confirme votre réservation ; vous en êtes informé par notification et par courrier électronique. La confirmation récapitule la prestation, la date, le nombre de participants et le montant dû au prestataire.</p>',
                ],
            ],
            [
                'Paiement',
                [
                    '<p><strong>Le prix d\'une prestation se règle directement au prestataire</strong>, par l\'un des moyens qu\'il propose et qui vous sont indiqués lors de la réservation (lien de paiement en ligne sur son propre compte, PayPal, virement, paiement sur place…), selon les modalités et le calendrier qu\'il fixe. TrouveMoi n\'encaisse aucune somme à ce titre, ne prélève aucune commission sur les prestations et n\'intervient pas dans leur règlement.</p>',
                    '<p>Les bons cadeaux liés à une activité sont réglés directement au prestataire qui la propose, sur son compte de paiement Stripe Connect ou PayPal. Les abonnements professionnels sont réglés à TrouveMoi par l\'intermédiaire de Stripe. <strong>Vos coordonnées bancaires ne transitent pas par nos serveurs et n\'y sont jamais enregistrées</strong>.</p>',
                ],
            ],
            [
                'Bons cadeaux',
                [
                    '<p>Les bons cadeaux sont nominatifs, valables douze mois à compter de leur date d\'émission et utilisables uniquement pour l\'activité du prestataire indiquée lors de l\'achat. Le paiement du bon est versé directement à ce prestataire. Le code couvre une réservation du montant exact du bon ; il ne peut être utilisé qu\'une fois. Les conditions de remboursement applicables sont celles du prestataire et du moyen de paiement utilisé.</p>',
                ],
            ],
            [
                'Droit de rétractation',
                [
                    '<p><strong>Les prestations de loisirs réservées pour une date ou une période déterminée ne bénéficient pas du droit de rétractation de quatorze jours.</strong> Cette exclusion est prévue au 12° de l\'article L221-28 du code de la consommation, qui vise les prestations de services de loisirs fournies à une date ou selon une périodicité déterminée. Elle vous est rappelée avant la validation de la commande.</p>',
                    '<p>Le droit de rétractation de quatorze jours s\'applique en revanche à l\'achat d\'un bon cadeau non affecté à une date. Pour l\'exercer, adressez une déclaration dénuée d\'ambiguïté à <a href="mailto:'.self::CONTACT.'">'.self::CONTACT.'</a> dans les quatorze jours suivant l\'achat ; le remboursement intervient dans les quatorze jours suivant la réception de votre demande, par le même moyen de paiement.</p>',
                ],
            ],
            [
                'Annulation et modification',
                [
                    '<p>Les conditions d\'annulation propres à chaque activité figurent sur sa fiche et vous sont rappelées avant l\'envoi de votre demande. À défaut d\'indication contraire, une demande d\'annulation adressée plus de quarante-huit heures avant le début de la prestation donne lieu au remboursement intégral des sommes versées ; passé ce délai, la prestation reste due. Le remboursement est effectué par le prestataire, qui a reçu le paiement.</p>',
                    '<p>L\'absence au rendez-vous, sans annulation préalable, ne donne lieu à aucun remboursement.</p>',
                    '<p>Si le prestataire annule la prestation, quelle qu\'en soit la raison, il vous rembourse intégralement et sans délai les sommes versées, sans préjudice de toute autre demande que vous pourriez former contre lui.</p>',
                ],
            ],
            [
                'Abonnements des professionnels',
                [
                    '<p>Les professionnels peuvent souscrire un abonnement aux services de la plateforme. Son prix et sa périodicité (mensuelle ou annuelle) sont affichés avant la souscription. Il est payé à TrouveMoi, en ligne, par l\'intermédiaire de Stripe (carte bancaire ou autre moyen proposé sur la page de paiement), et se renouvelle tacitement à chaque échéance.</p>',
                    '<p>L\'abonnement peut être résilié à tout moment depuis l\'espace professionnel ; la résiliation prend effet à la fin de la période en cours, déjà payée. L\'abonnement ne donne lieu à aucune commission sur les prestations, que le professionnel encaisse directement auprès de ses clients.</p>',
                ],
            ],
            [
                'Obligations du participant',
                [
                    '<p>Vous vous présentez au lieu et à l\'heure indiqués, respectez les consignes de sécurité du prestataire et déclarez être en mesure de pratiquer l\'activité choisie. Le prestataire peut refuser la participation d\'une personne manifestement inapte ou dont le comportement met en danger le groupe, sans remboursement.</p>',
                ],
            ],
            [
                'Responsabilité',
                [
                    '<p>TrouveMoi répond du bon fonctionnement de la plateforme et de l\'exactitude de la retranscription des informations transmises par les prestataires. La responsabilité de l\'exécution de la prestation incombe au prestataire.</p>',
                    '<p>Aucune des parties ne saurait être tenue responsable d\'un manquement dû à un cas de force majeure au sens de l\'article 1218 du code civil.</p>',
                ],
            ],
            [
                'Réclamations et médiation',
                [
                    '<p>Toute réclamation peut être adressée à <a href="mailto:'.self::CONTACT.'">'.self::CONTACT.'</a> ou par téléphone au '.self::TELEPHONE.'. Nous accusons réception sous quarante-huit heures ouvrées.</p>',
                    '<p>Conformément à l\'article L612-1 du code de la consommation, vous pouvez recourir gratuitement à un médiateur de la consommation en vue de la résolution amiable d\'un litige, après avoir tenté de le résoudre directement avec nous. <em>Les coordonnées du médiateur retenu par l\'éditeur seront publiées ici dès sa désignation.</em></p>',
                    '<p>La plateforme européenne de règlement en ligne des litiges est par ailleurs accessible à l\'adresse <a href="https://ec.europa.eu/consumers/odr" title="Règlement en ligne des litiges">ec.europa.eu/consumers/odr</a>.</p>',
                ],
            ],
            [
                'Droit applicable',
                [
                    '<p>Les présentes conditions sont soumises au droit français. En cas de litige, les tribunaux français sont compétents dans les conditions prévues par le code de procédure civile et le code de la consommation. Ces stipulations ne privent pas le consommateur des protections impératives que lui accorde la loi de sa résidence habituelle.</p>',
                ],
            ],
        ]);
    }

    public static function cookiePolicy(): string
    {
        return self::assemble([
            [
                'Ce qu\'est un cookie',
                [
                    '<p>Un cookie est un petit fichier déposé sur votre appareil lorsque vous consultez un site. Il permet de vous reconnaître d\'une page à l\'autre, de mémoriser un choix ou de mesurer la fréquentation.</p>',
                    '<p>Le terme couvre ici tous les traceurs, quelle que soit leur technique : cookies proprement dits, stockage local du navigateur ou identifiants équivalents.</p>',
                ],
            ],
            [
                'Les traceurs que nous déposons',
                [
                    '<p><strong>Strictement nécessaires — déposés sans votre consentement, car le service ne peut pas fonctionner sans eux.</strong> Ils ne servent à aucun suivi publicitaire :</p>',
                    '<ul>'
                    .'<li>un cookie de session, qui vous maintient connecté d\'une page à l\'autre ;</li>'
                    .'<li>un jeton de sécurité, qui empêche qu\'un autre site soumette un formulaire à votre place ;</li>'
                    .'<li>le cookie <code>tm_consent</code>, qui mémorise précisément votre choix en matière de cookies. Le déposer est la seule façon de ne pas vous reposer la question à chaque page ;</li>'
                    .'<li>votre choix de langue.</li>'
                    .'</ul>',
                    '<p><strong>Préférences</strong> — soumis à votre consentement : mémorisation de vos filtres de recherche et de votre mode d\'affichage.</p>',
                    '<p><strong>Mesure d\'audience</strong> — soumise à votre consentement : <strong>Google Analytics</strong> (Google Ireland Limited) mesure la fréquentation du site, les pages consultées et les réservations effectuées, afin d\'améliorer nos services. Ses cookies (<code>_ga</code>, <code>_ga_*</code>) ne sont déposés qu\'après votre accord et sont conservés treize mois au plus. L\'adresse IP n\'est pas conservée par Google Analytics 4.</p>',
                    '<p><strong>Publicité et réseaux sociaux</strong> — soumis à votre consentement. <em>Aucun traceur publicitaire n\'est déposé à ce jour.</em></p>',
                ],
            ],
            [
                'Traceurs déposés par des tiers',
                [
                    '<p>Certaines fonctions font appel à des services extérieurs, qui déposent leurs propres traceurs :</p>',
                    '<ul>'
                    .'<li><strong>Stripe</strong>, lors du paiement, à des fins de sécurisation de la transaction et de prévention de la fraude ;</li>'
                    .'<li><strong>Google</strong>, <strong>Facebook</strong> ou <strong>Apple</strong>, uniquement si vous choisissez de vous connecter par leur intermédiaire.</li>'
                    .'</ul>',
                    '<p>Ces services agissent selon leurs propres politiques, sur lesquelles nous n\'avons pas la main.</p>',
                ],
            ],
            [
                'Votre choix, et comment en changer',
                [
                    '<p>Lors de votre première visite, un bandeau vous permet d\'accepter ou de refuser les traceurs facultatifs, en bloc ou catégorie par catégorie. <strong>Refuser est aussi simple qu\'accepter</strong> et n\'empêche pas d\'utiliser le site. Tant que vous n\'avez pas fait de choix, aucun traceur facultatif n\'est déposé.</p>',
                    '<p>Votre choix est conservé <strong>treize mois</strong>, durée recommandée par la CNIL, au terme desquels la question vous est reposée. Vous pouvez le modifier à tout moment grâce au lien <strong>« Gérer mes cookies »</strong> en bas de chaque page.</p>',
                    '<p>Vous pouvez également configurer votre navigateur pour refuser les cookies ou supprimer ceux déjà déposés. Cette voie est plus radicale : elle peut empêcher le maintien de votre connexion et le bon déroulement d\'un paiement.</p>',
                ],
            ],
            [
                'Vos droits',
                [
                    '<p>Les données associées à ces traceurs relèvent de notre <a href="/politique-de-confidentialite">politique de confidentialité</a>, qui détaille vos droits d\'accès, de rectification, d\'effacement et d\'opposition, ainsi que la possibilité de saisir la CNIL.</p>',
                ],
            ],
        ]);
    }

    /**
     * Assemble des articles en un seul texte HTML.
     *
     * Chaque titre devient un titre de niveau 2, c'est-à-dire un article : le
     * sommaire « Sur cette page » et la numérotation s'en déduisent à
     * l'affichage, sans que personne ait à les tenir.
     *
     * @param list<array{0: string, 1: list<string>}> $articles
     */
    private static function assemble(array $articles): string
    {
        $morceaux = [];

        foreach ($articles as [$titre, $paragraphes]) {
            $morceaux[] = '<h2>'.$titre.'</h2>'.implode('', $paragraphes);
        }

        return implode("\n", $morceaux);
    }
}
