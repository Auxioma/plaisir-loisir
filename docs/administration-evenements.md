# Administration des événements

Dans EasyAdmin, la rubrique **Événements** donne accès à :

- **Événements** : création, consultation, modification et suppression, avec
  recherche et filtres ;
- **Catégories des événements** : noms, identifiants, couleurs et classement ;
- **Inscriptions aux événements** : ajout d'un membre, confirmation d'une place,
  mise en attente et retrait d'une inscription.

La fiche événement regroupe présentation, organisateur, catégorie, couverture,
galerie, publication, dates, lieu, visibilité et options de participation.
L'administrateur peut créer au nom d'un client ou administrer un événement
appartenant à un autre organisateur. Un lien de la fiche ouvre directement les
inscriptions filtrées pour cet événement ; un autre permet sa prévisualisation,
y compris s'il est privé ou non publié.

## Publication et modération

Le champ **Statut** permet de choisir Brouillon, Programmé, Publié ou Suspendu
par l'administration. La programmation exige une date future. Les dates de fin,
capacités et liens de connexion sont contrôlés avant enregistrement.

**Suspendu** retire l'événement des listes publiques et bloque sa republication
par l'organisateur, même si celui-ci avait déjà ouvert l'assistant. Seul un
administrateur peut le rétablir. Le compte client affiche la suspension.

La suppression définitive utilise la confirmation et le jeton CSRF d'EasyAdmin.
Elle supprime également les inscriptions, invitations et favoris associés par
les relations existantes, puis écrit dans le journal d'audit. L'organisateur et
les membres inscrits sont notifiés des suppressions. Les changements de statut,
de date ou de lieu sont également notifiés aux personnes concernées.

## Inscriptions

Les prestataires ne sont pas proposés comme organisateurs ou participants :
la règle client du site reste en place. L'administrateur peut néanmoins intervenir
sur tous les événements depuis le back-office protégé par `ROLE_ADMIN`.

Les doublons et les confirmations au-delà de la capacité sont refusés. Le nombre
d'inscrits est calculé automatiquement ; il ne se modifie pas à la main. Retirer
une participation confirmée fait avancer le premier membre en attente si une
place existe. Pour retirer l'organisateur, il faut d'abord changer l'organisateur
de l'événement. Un membre inscrit manuellement à un événement privé reçoit aussi
l'accès nécessaire à sa fiche.

Aucune migration n'est nécessaire : les entités et relations existent déjà,
et le statut est stocké dans une colonne texte.
