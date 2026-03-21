# Scroll Magnet

## Positionnement

Ne pas vendre le plugin comme un simple popup plugin.

Promesse marketing:

`Scroll Magnet` aide les sites WordPress a augmenter leurs conversions avec des appels a l'action declenches selon l'engagement reel du visiteur.

Angle commercial:

- comportement utilisateur
- timing intelligent
- conversion directe
- mise en place en moins de 2 minutes

## MVP a sortir vite

Objectif:

sortir une version gratuite simple, stable et facile a comprendre sur WordPress.org.

Fonctions MVP:

- 3 triggers de scroll
- types de rendu: bandeau, bouton flottant, popup
- message + bouton + lien
- ciblage simple: tout le site, articles, pages, pages specifiques
- chargement front uniquement si necessaire

Ce qui ne doit pas entrer dans le premier jet:

- analytics avances
- A/B testing
- conditions complexes par role, device, source
- integration email marketing

## Structure technique

### Free

- `scroll-magnet.php`
  coeur du plugin, options, ciblage, injection des assets
- `assets/js/scroll-magnet.js`
  ecoute du scroll, calcul du pourcentage, affichage des CTA
- `assets/css/scroll-magnet.css`
  styles front

### Evolution recommandee

Quand le plugin grandit, passer vers une structure modulaire:

- `includes/class-plugin.php`
- `includes/class-settings.php`
- `includes/class-targeting.php`
- `includes/class-render.php`
- `includes/class-analytics.php`
- `assets/admin/*`
- `assets/front/*`

## Roadmap produit

### V0.1

- plugin installable
- 3 triggers
- design correct mobile + desktop
- page d'options simple

### V0.2

- meilleure UI admin
- apercu live
- possibilite de desactiver certains triggers
- options de couleurs
- animation d'entree

### V0.3

- stats clics
- stats vues
- tableau de bord simple
- export CSV

### V1.0 Premium

- A/B testing
- combinaison temps + scroll
- exit intent + scroll
- bibliotheque de templates
- conditions par URL et taxonomy
- historique de performance

## Decoupage de travail conseille

### Etape 1

Valider l'offre et le nom.

Noms les plus solides:

- Scroll Magnet
- ScrollBoost
- ConvertScroll

`Scroll Magnet` est le plus memorable et le plus facile a brander.

### Etape 2

Finir le MVP front:

- eviter les doubles affichages
- regler les fermetures
- affiner les animations
- gerer les longs contenus

### Etape 3

Finir l'admin:

- microcopy orientee conversion
- meilleure organisation des champs
- preview

### Etape 4

Preparer WordPress.org:

- `readme.txt`
- captures d'ecran
- icone
- bannieres

### Etape 5

Preparer la monétisation:

- site vitrine
- checkout
- licence
- version Pro separee ou unlock par cle

## Modele free / premium

### Gratuit

- 1 ou 3 triggers max
- peu de styles
- pas de stats

### Premium

- analytics
- templates
- A/B tests
- conditions combinees
- animations et branding
- support prioritaire

## Pricing recommande

- `29 EUR / an` pour solo/PME
- `49 EUR lifetime` en offre de lancement
- plus tard: `79 EUR / an` multi-sites

## Ce qui fera vraiment vendre

Le meilleur differentiant n'est pas le popup.

C'est l'intelligence du message:

- 30%: decouverte
- 70%: reassurance
- 90%: urgence

Donc le vrai coeur produit est:

`des CTA evolutifs selon l'engagement du visiteur`

## Suite recommandees

Les 3 prochaines actions les plus rentables:

1. Finir le MVP gratuit propre.
2. Ajouter le tracking clic/vue.
3. Sortir une landing page avec une demo GIF et un comparatif Free vs Pro.
