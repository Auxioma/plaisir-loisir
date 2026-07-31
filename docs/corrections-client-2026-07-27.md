# Corrections client / CTO — 27 juillet 2026

Retours transmis après la mise en ligne de la démo sur trouvemoi.eu.

## 1. Connexion Client ou Pro — ✅ fait (front)

Les écrans de connexion et d'inscription proposent un sélecteur
« Client / Professionnel » (`templates/security/_account_type.html.twig`).
Le champ `accountType` sera traité côté back lors du câblage de l'auth.

## 2. Statut juridique des professionnels — ✅ fait (front)

À l'inscription, quand « Professionnel » est sélectionné, un champ
« Statut juridique » apparaît : EI, Micro-entreprise, EURL, SARL, SAS,
SASU, Association, Autre. (Révélé en CSS pur via `:has()`, sans JS.)

## 3. Types d'activités — « Option 1 » validée par le client — ✅ fait (front)

Distinction affichée dans les filtres (Activités et Destinations) :

- 🎟️ Activités professionnelles
- 👥 Activités entre particuliers « Gratuite »

## 4. Album photos des activités gratuites — 📋 spécifié, à maquetter

Règles annoncées par le client :

- chaque activité gratuite (entre particuliers) possède un album de groupe ;
- le créateur de l'activité ET chaque membre peuvent y déposer des photos ;
- limite : **25 photos par album**.

À prévoir au câblage back : entité `Album`/`Photo` (activité gratuite 1—1 album,
album 1—n photos avec auteur), contrainte de 25 photos côté validation et côté
UI (bouton d'ajout désactivé + compteur « x/25 »). L'écran n'existe pas encore
dans la maquette Figma : demander l'écran à la designer avant de coder l'UI.
