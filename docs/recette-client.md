# Recette de la plateforme TrouveMoi

Document de travail — 22 octobre 2026. La recette avec le porteur du projet n’a pas encore commencé. Ce document sera complété pendant les essais en local, puis reformulé pour remise au client. Il ne constitue pas une validation de tous les parcours.

## Points à vérifier ensemble en premier

| Parcours | Résultat attendu | Recette manuelle |
| --- | --- | --- |
| Connexion client depuis le menu | Ouverture du tableau de bord client | À faire |
| Réservation sans être connecté | Après connexion, reprise de la sélection et affichage du récapitulatif | À faire |
| Réservation avec création de compte | Vérification de l’e-mail, connexion, puis reprise de la réservation | À faire |
| Retour sur un paiement après expiration de connexion | Après connexion, retour à la page de paiement de la réservation concernée | À faire |
| Créneau devenu indisponible pendant l’inscription | Message explicite et possibilité de choisir un autre créneau | À faire |
| Activité retirée pendant l’inscription | Message explicite et retour au catalogue | À faire |
| Reprise sans sélection conservée | Retour au catalogue avec une explication | À faire |
| Lisibilité du tableau de bord client | Indicateurs, accès rapides et légende du graphique lisibles, sans débordement | À faire |
| Logo depuis l’espace professionnel, ordinateur et mobile | Retour au tableau de bord professionnel | À faire |
| Paiement Stripe réussi | Réservation confirmée et paiement enregistré, indépendamment du délai de disponibilité du solde Stripe | À faire |

La sélection avant inscription est conservée dans la session du navigateur. La reprise après plusieurs heures, fermeture du navigateur, perte de session ou changement d’appareil devra être testée explicitement ; aucune conservation illimitée n’est promise.

## Paiement Stripe : explication à communiquer

Dans le relevé transmis le 22 octobre, le paiement de 50,00 € est indiqué comme réussi. Après 1,83 € de frais Stripe, le solde net est de 48,17 €. Stripe annonce sa disponibilité au 29 octobre.

Il faut distinguer le règlement de la réservation et la disponibilité des fonds pour le professionnel. Un paiement réussi peut alimenter un solde Stripe « en attente » jusqu’à la date de règlement fixée par Stripe. Ce libellé ne signifie pas que le client doit encore payer sa réservation. Le compte rendu fourni concerne un environnement de test, sans mouvement d’argent réel.

Le code actuel crée un Checkout par carte sans capture manuelle et valide le règlement à partir de l’état du paiement renvoyé par Stripe. Changer le code pour supprimer toute attente du solde ne permettrait pas de supprimer les délais de Stripe. Les virements instantanés sont une possibilité distincte, soumise à éligibilité et à frais ; ils ne font pas partie des corrections demandées ici.

Références : [Soldes et délais de règlement](https://docs.stripe.com/payments/balances), [Cartes et comportements de test](https://docs.stripe.com/testing).

## Suite de la recette commune

Nous vérifierons ensuite les comptes client, professionnel et administrateur, la vérification d’e-mail et sa reprise tardive, les mots de passe oubliés, les droits d’accès, les activités, les disponibilités, les réservations et leur annulation, les paiements Stripe et PayPal, le paiement sur place, les justificatifs, les notifications et les autres fonctions de la plateforme.

Pour chaque essai, nous noterons l’action réalisée, le résultat observé et les éventuelles corrections nécessaires. Les essais abandonnés, les retours arrière, les liens expirés et les erreurs de saisie devront aussi être parcourus.

## Vérifications techniques réalisées avant la recette

Le 22 octobre, les tests automatisés ciblant les réservations, les redirections, l’inscription, la vérification d’e-mail, l’espace professionnel et les notifications de paiement Stripe ont abouti : **40 tests, 464 assertions**. Le test de rendu du tableau de bord client passe également : **1 test, 3 assertions**.

Les parcours vérifiés comprennent la reprise d’une réservation après connexion, puis après inscription et vérification d’e-mail, ainsi que le retour à une page de paiement après perte de connexion. La sélection déjà utilisée ne peut pas être rejouée via la page de reprise. Les services Stripe sont simulés dans ces tests ; il ne s’agit pas d’un nouveau paiement réel.

Un contrôle dans Chromium du tableau de bord local a été effectué aux largeurs 320, 390, 768, 1 024, 1 280, 1 440, 1 600 et 1 920 px, avec notamment le montant de 1 066 €. Aucun débordement horizontal n’a été mesuré sur la page ou dans les indicateurs, accès rapides et lignes de légende. Les captures ordinateur et mobile ont aussi été examinées.

Ces contrôles techniques préparent les essais communs ; ils ne remplacent pas la recette manuelle de l’ensemble de la plateforme.

## Bons cadeaux — constat et corrections du 22 octobre

Le parcours signalé était incomplet. Le retour après paiement attendait uniquement le webhook Stripe. De plus, changer le statut dans EasyAdmin ne déclenchait pas l’envoi des e-mails et ne renseignait pas les dates de paiement et de validité. Cette manipulation n’est pas la procédure normale.

Le retour navigateur vérifie maintenant auprès de Stripe que la session correspond au bon acheté, au bon montant et à la bonne devise, avec un paiement réglé. Le webhook conserve le même contrôle pour les acheteurs qui ferment le navigateur. Les statuts ne sont plus modifiables librement dans EasyAdmin.

Après confirmation, l’acheteur reçoit sa copie et le bénéficiaire reçoit le bon si l’envoi par e-mail a été choisi. Chaque envoi transmis est suivi séparément. Une panne d’envoi peut être réessayée en rouvrant la confirmation, sans renvoyer l’autre message déjà suivi comme transmis. Cette trace atteste la transmission au système d’envoi, pas la réception en boîte principale ; l’adresse, la configuration de messagerie et les courriers indésirables restent à vérifier lors de la recette réelle.

Un téléchargement PDF remplace l’impression de toute la page. Il conserve la présentation violette, le message personnel, les noms, le montant, le code et la date de validité. Un bon non réglé ne donne pas accès au PDF.

**Utilisation sur une activité : parcours raccordé au paiement direct prestataire.** Un nouveau bon est lié à une seule activité et payé sur le compte Stripe Connect ou PayPal configuré par le professionnel. Sa fiche, son PDF et ses e-mails indiquent l’activité et le prestataire. À la réservation de cette activité, le bénéficiaire saisit le code au lieu d’un paiement en ligne. Le code ne confirme que le total exact, s’il est payé, non expiré et non utilisé ; la réservation est alors enregistrée comme payée, puis le bon est marqué utilisé sur cette réservation. Le verrou en base évite une double utilisation concurrente. Le bon ne couvre pas un complément et n’est pas fractionnable dans ce parcours.

Les bons émis avant le paiement direct prestataire ne peuvent pas être utilisés pour régler une réservation : le prestataire n’a pas reçu leur montant. Ils doivent être régularisés (remboursement ou transfert convenu avec le prestataire) avant de réactiver leur usage. Aucun transfert ou remboursement automatique des anciens bons n’est exécuté. Cette migration doit aussi être appliquée sur l’environnement local avant le test du parcours.

Le texte juridique initial de la plateforme doit être mis à jour puis vérifié par le responsable avant publication : l’ancien modèle indiquait que les bons étaient payés à TrouveMoi. Les règles définitives de remboursement, d’annulation de réservation payée par bon et de bon inutilisé doivent aussi être confirmées avant mise en production.

## Autres retours du client — 22 octobre

- L’export « Demander mes données » renvoie désormais un PDF lisible au lieu d’un fichier JSON. À vérifier avec plusieurs profils et des champs longs.
- L’espace support client affiche son entrée de menu active et un bouton d’envoi visible avec son libellé. Le ticket et ses réponses restent dans le même fil que celui affiché au support. Le fil EasyAdmin est présenté message par message et le champ de réponse notifie le demandeur. À vérifier avec un ticket client et un ticket professionnel.
- Le formulaire « Devenir partenaire » a maintenant des choix pour le secteur et le trafic mensuel, une explication de la différence entre partenariat de visibilité et compte professionnel, ainsi qu’une mise en page adaptée au mobile. Cette page reste à revoir visuellement avec le client avant de valider son style.
- Le suivi du parrainage n’est pas implémenté : l’inscription, la première réservation payée, le crédit promis et son utilisation ne sont actuellement pas reliés. La page l’indique maintenant sans promettre une récompense. L’activation demande de définir qui finance le crédit, puisque le paiement de l’activité va directement au prestataire. Tant que ce choix manque, aucun débit de récompense ne doit apparaître et aucun crédit n’est disponible à dépenser.
- Le paiement de réservation a été regroupé sur une page après « Réserver », avec les moyens acceptés par le prestataire. La validation technique complète du test de paiement reste à refaire avant clôture.

Vérification technique du 22 octobre après ces changements d’interface : **50 tests, 481 assertions** sur les pages compte, le back-office, les réservations et l’espace professionnel. PHPStan ciblé, Twig et le conteneur passent. Le test du PDF vérifie le téléchargement `application/pdf`. Ces tests utilisent la base locale et des paiements simulés ; la conversation dans l’interface et les parcours commerciaux restent à confirmer pendant la recette manuelle.

Vérifications réalisées : **19 tests, 98 assertions**, couvrant les bons cadeaux, les contrôles Stripe et les non-régressions sur les abonnements et webhooks existants. L’analyse statique ciblée et les validations Twig et du conteneur passent. Le PDF de démonstration a été examiné visuellement et tient sur une page. La migration de suivi des e-mails est appliquée en local et en test. Aucun paiement réel ni envoi groupé de bons existants n’a été effectué pendant ces vérifications.

Pour reprendre un bon ancien marqué « Payé » manuellement sans date de paiement, rouvrir sa confirmation déclenche une vérification Stripe avant sa régularisation. Les anciens e-mails sans trace d’envoi ne peuvent pas être distingués automatiquement des messages jamais envoyés ; le premier retour peut donc transmettre une nouvelle copie. Cette reprise sera à tester ensemble sur le bon concerné.

## Journal des essais manuels

| Date | Compte et parcours | Actions réalisées | Résultat observé | Décision |
| --- | --- | --- | --- | --- |
| — | — | Aucun essai commun réalisé pour le moment | — | — |
