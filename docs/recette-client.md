# TrouveMoi — guide de recette et d’utilisation

**Version du 22 octobre 2026**

Document destiné aux équipes TrouveMoi et aux utilisateurs pilotes.

## Objet du guide

Ce guide récapitule les parcours vérifiés ensemble sur l’environnement local et décrit le comportement attendu des principaux espaces de TrouveMoi. Il sert de repère pendant la recette et pour accompagner les premiers utilisateurs. Les paiements Stripe évoqués pendant les essais ont été réalisés en mode test : aucun fonds réel n’a été déplacé.

Les essais locaux réalisés ensemble ont abouti. Les étapes ci-dessous décrivent le résultat observé et les vérifications à faire lorsqu’une situation particulière se présente.

## Se connecter au bon espace

Un compte client et un compte professionnel ouvrent des espaces distincts.

| Action | Résultat attendu |
| --- | --- |
| Un client se connecte depuis la navigation générale | Il arrive sur son tableau de bord client. |
| Un professionnel ouvre son espace | Il arrive sur son tableau de bord professionnel. Le logo de cet espace le ramène à ce tableau de bord. |
| Un visiteur choisit de réserver une activité | La plateforme lui demande de se connecter ou de créer un compte, puis reprend la réservation en cours. |

Si le lien de vérification du compte est utilisé plus tard, le client peut vérifier son adresse puis se connecter pour reprendre le parcours. Si la sélection n’est plus disponible ou si le créneau a été pris entre-temps, la plateforme l’en informe et lui permet de choisir une autre activité ou une autre date. Si aucune réservation n’est conservée, il revient au catalogue avec une explication.

La reprise dépend de la session du navigateur. Elle n’est pas garantie après un changement d’appareil, l’effacement des données du navigateur ou une durée de conservation illimitée.

## Réserver une activité

Après avoir choisi l’activité, la date et le nombre de participants, le client vérifie le récapitulatif puis choisit un moyen de paiement sur la même page. Seuls les moyens configurés et proposés par le professionnel sont affichés. Le bouton d’action s’adapte au choix : payer en ligne, envoyer la demande de réservation ou utiliser un bon cadeau.

Pour un paiement par carte, le client est redirigé vers la page sécurisée Stripe du professionnel. Après confirmation du paiement, il revient sur TrouveMoi et voit la confirmation de réservation ainsi que ses informations de réservation.

Le statut « En attente » affiché dans le solde Stripe concerne la disponibilité des fonds pour le professionnel. Il ne signifie pas que le client doit encore payer lorsque le paiement est indiqué comme réussi et que sa réservation est confirmée. La date de disponibilité et le versement dépendent des délais et réglages Stripe. Les essais en mode test ne constituent pas des paiements réels.

Selon les réglages du professionnel, la réservation peut également être envoyée comme une demande à confirmer ou être réglée sur place. Dans ces cas, l’écran indique l’étape à suivre et la réservation n’est pas présentée comme payée en ligne.

## Acheter et utiliser un bon cadeau

### Achat

Le bon est associé à une activité précise et au professionnel qui la propose. L’acheteur indique le nombre de personnes, le nom du bénéficiaire et le mode de remise. Le montant est calculé à partir du tarif de l’activité et du nombre de personnes. Le paiement se fait sur le compte Stripe Connect ou PayPal configuré par le professionnel ; TrouveMoi ne reçoit pas le montant de l’activité.

Une fois le paiement confirmé, le code du bon est disponible sur l’écran de confirmation. Selon le mode de remise choisi, il est envoyé au bénéficiaire ou l’acheteur peut télécharger la carte en PDF pour la remettre lui-même. Le PDF et les informations d’envoi rappellent l’activité concernée, le professionnel et la date de validité.

### Utilisation à la réservation

Le bénéficiaire réserve l’activité indiquée sur le bon, choisit la date et les participants, puis sélectionne « Bon cadeau » parmi les moyens de paiement. Le champ de code apparaît alors. Le bon est accepté s’il est payé, en cours de validité, inutilisé, associé à cette activité et couvre exactement le total de la réservation. La plateforme confirme la réservation sans effectuer un second débit, puis marque le bon comme utilisé.

Un bon ne peut servir qu’une fois. Il ne peut pas être fractionné, utilisé pour une autre activité ou complété par un paiement supplémentaire dans le parcours actuel. Si le total ne correspond pas, le bénéficiaire doit modifier sa réservation ou choisir un autre moyen de paiement.

Les bons créés avant la mise en place du paiement direct au professionnel doivent être examinés au cas par cas avant leur utilisation : leur paiement a pu être encaissé selon l’ancien parcours. Ils ne doivent pas être considérés comme réglés au professionnel sans vérification.

## Espace client et demandes d’aide

Le tableau de bord client regroupe les indicateurs, les accès rapides et les statistiques de réservation. Sa mise en page a été vérifiée sur ordinateur, tablette et téléphone ; les valeurs et libellés restent lisibles sans débordement.

L’export des données personnelles depuis les paramètres du profil est fourni sous forme de PDF. Pour une demande de support, le client ouvre le ticket depuis son espace et envoie sa réponse dans la conversation du ticket. Le support retrouve le même fil dans le back-office et peut y répondre. Le menu « Support » est accessible dans l’espace client ; l’espace professionnel dispose de sa propre entrée de support.

## Devenir partenaire et parrainage

Le formulaire « Devenir partenaire » sert à demander un partenariat de visibilité pour un site ou une entreprise. Il distingue cette demande de l’ouverture d’un compte professionnel permettant de proposer des activités à la réservation. Les choix de secteur et de trafic mensuel sont proposés dans le formulaire.

Le programme de parrainage n’est pas encore actif : aucun crédit n’est attribué ni débité. Avant de l’ouvrir, TrouveMoi doit décider qui finance la récompense et comment elle s’applique à une activité dont le paiement est versé directement au professionnel. La page ne doit donc pas être interprétée comme la confirmation d’un crédit disponible.

## Résultats de la recette

Les parcours présentés dans ce guide ont été testés ensemble en local et ont fonctionné. Cela comprend la connexion client et professionnelle, la reprise d’une réservation après authentification, le récapitulatif et les moyens de paiement, le paiement Stripe en mode test, le bon cadeau de son achat à son utilisation, le tableau de bord, l’export PDF, les échanges de support et les formulaires de partenariat.

Les essais ont également couvert les retours d’erreur utiles : sélection ou créneau indisponible, informations de formulaire manquantes et bon cadeau invalide ou qui ne correspond pas au total. La plateforme indique alors l’action à corriger au lieu de marquer la réservation comme payée.

Les résultats locaux valident les parcours essayés dans la configuration utilisée. Les délais de versement Stripe, la réception effective des e-mails (notamment dans les courriers indésirables), les éventuels changements de configuration des professionnels et les données d’un environnement de production dépendent des services et comptes réellement utilisés.

## Points à confirmer avant une mise en production élargie

- Valider les conditions applicables aux remboursements, annulations et bons cadeaux avec le responsable de la plateforme, puis mettre à jour les textes présentés aux utilisateurs si nécessaire.
- Pour tout bon émis sous l’ancien parcours, vérifier le paiement et convenir avec le professionnel d’une régularisation avant utilisation.
- Définir le financement et les règles du programme de parrainage avant d’activer les récompenses.
- Vérifier sur les comptes de production les moyens de paiement activés, les e-mails d’envoi et les informations propres à chaque professionnel.

---

**TrouveMoi — Guide de recette et d’utilisation**

En cas de difficulté pendant un parcours, conserver le numéro de réservation ou de ticket et contacter le support TrouveMoi depuis l’espace concerné.
