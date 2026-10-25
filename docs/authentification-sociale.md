# Connexions Google et Facebook des clients

Les liens des pages d'authentification client utilisent les routes existantes
`/connexion/google` et `/connexion/facebook`. Les routes Apple sont désactivées.
Les formulaires classiques, la vérification d'inscription, le mot de passe oublié
et les pages professionnelles gardent leurs parcours et leurs contrôles.

## Configuration nécessaire pour l'activation réelle

Renseigner les secrets dans l'environnement du serveur, hors du dépôt :

- `GOOGLE_OAUTH_CLIENT_ID` et `GOOGLE_OAUTH_CLIENT_SECRET` ;
- `FACEBOOK_OAUTH_CLIENT_ID` et `FACEBOOK_OAUTH_CLIENT_SECRET` ;
- `OAUTH_REDIRECT_BASE` : origine HTTPS publique du site, sans chemin.

Déclarer exactement les URL de retour suivantes dans les consoles fournisseurs :

- `${OAUTH_REDIRECT_BASE}/connexion/google/retour` ;
- `${OAUTH_REDIRECT_BASE}/connexion/facebook/retour`.

Côté Google, créer un client OAuth de type application Web et configurer l'écran
de consentement (scopes `openid email profile`). Côté Meta, configurer Facebook
Login, les permissions `email` et `public_profile`, les domaines, les URL de
confidentialité/suppression des données et rendre l'application accessible au
public selon les exigences de la console. Une application limitée aux testeurs
ne permet pas la connexion de tous les clients.

Le transport e-mail du site doit fonctionner pour la confirmation d'adresse.
Les identifiants préfixés `test-` ne déclenchent aucune connexion réelle : le site
revient au formulaire avec un message explicite. Les tests HTTP simulés valident
le code ; ils ne remplacent pas un essai sur le domaine avec les vraies applications.

Références : [Google OpenID Connect](https://developers.google.com/identity/openid-connect/openid-connect),
[Facebook Login](https://developers.facebook.com/docs/facebook-login/).

## Comportement

- Nouveau compte : création d'un client uniquement, avec mot de passe aléatoire.
  Il peut ensuite définir son mot de passe par le parcours habituel « oublié ».
- Compte client existant : conservation de l'identifiant, du mot de passe et des
  données. Liaison seulement après preuve de possession de l'adresse e-mail.
- Google avec adresse vérifiée : connexion directe ; une inscription client en
  attente de vérification peut être confirmée par cette preuve.
- Facebook : Graph ne fournit pas de booléen `email_verified`. À la première
  liaison ou création, un code envoyé à l'adresse reçue est donc demandé. Code
  valable 15 minutes, cinq essais, utilisation unique et lié à la session.
- Identité déjà liée : connexion par son identifiant externe stable, même si le
  fournisseur ne renvoie plus l'adresse ; l'adresse locale n'est pas remplacée.
- Compte prestataire, administrateur, suspendu ou supprimé : connexion sociale
  refusée avant toute nouvelle liaison. Sa connexion classique reste inchangée.
- Adresse absente : retour au formulaire avec une explication et possibilité de
  continuer par le parcours classique.
- Annulation, retour expiré, state incorrect/rejoué ou panne du fournisseur :
  retour au formulaire, sans ouverture de session sociale.

Le contrôle du compte et la redirection utilisent explicitement `form_login`
du firewall `main`. Cela conserve la reprise d'une réservation locale et le
tableau de bord client. Le state OAuth expire après dix minutes et ne sert
qu'une fois. Les départs sont limités à dix par IP en quinze minutes.

## Vérification avant mise en service

Avec les applications configurées sur le domaine cible, essayer les deux
fournisseurs : nouveau client, client existant, seconde connexion, annulation,
Facebook sans e-mail, confirmation e-mail et réservation commencée en visiteur.
Vérifier aussi le formulaire classique avec un compte client et un prestataire.
Ne pas recopier de jetons ou secrets dans les journaux ou les captures.
