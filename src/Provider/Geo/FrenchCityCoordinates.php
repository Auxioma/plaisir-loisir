<?php

declare(strict_types=1);

namespace App\Provider\Geo;

/**
 * Coordonnées (latitude, longitude) des principales villes françaises,
 * pour la recherche par rayon (§5, §9 du CDC).
 *
 * POURQUOI UNE TABLE STATIQUE PLUTÔT QU'UN GÉOCODAGE EN LIGNE
 * Un vrai géocodage (adresse précise → coordonnées) suppose un fournisseur
 * externe : clé d'API, coût ou quota, conditions d'utilisation à faire
 * valider — exactement le genre de décision (voir OAuth, Stripe) qui attend
 * l'aval du client plutôt qu'un choix pris seul en cours de développement.
 * Une table des villes principales ne demande ni clé ni réseau : elle
 * couvre la préfecture de chaque département et les plus grandes villes,
 * suffisant pour une recherche par rayon utile dès aujourd'hui.
 *
 * LIMITE ASSUMÉE, NON SILENCIEUSE
 * Une ville absente de cette table (bourg, hameau, orthographe inhabituelle)
 * ne participe pas à une recherche par rayon : ProviderProfileRepository
 * l'exclut plutôt que de deviner une distance. Sans rayon demandé, la
 * recherche reste la correspondance texte habituelle, qui elle fonctionne
 * pour n'importe quelle ville.
 *
 * @see HaversineDistance
 */
final class FrenchCityCoordinates
{
    /**
     * @var array<string, array{0: float, 1: float}>
     */
    private const COORDINATES = [
        'paris' => [48.8566, 2.3522],
        'marseille' => [43.2965, 5.3698],
        'lyon' => [45.7640, 4.8357],
        'toulouse' => [43.6047, 1.4442],
        'nice' => [43.7102, 7.2620],
        'nantes' => [47.2184, -1.5536],
        'montpellier' => [43.6108, 3.8767],
        'strasbourg' => [48.5734, 7.7521],
        'bordeaux' => [44.8378, -0.5792],
        'lille' => [50.6292, 3.0573],
        'rennes' => [48.1173, -1.6778],
        'reims' => [49.2583, 4.0317],
        'le havre' => [49.4944, 0.1079],
        'saint-etienne' => [45.4397, 4.3872],
        'toulon' => [43.1242, 5.9280],
        'grenoble' => [45.1885, 5.7245],
        'dijon' => [47.3220, 5.0415],
        'angers' => [47.4784, -0.5632],
        'nimes' => [43.8367, 4.3601],
        'villeurbanne' => [45.7667, 4.8794],
        'clermont-ferrand' => [45.7772, 3.0870],
        'aix-en-provence' => [43.5297, 5.4474],
        'brest' => [48.3904, -4.4861],
        'limoges' => [45.8336, 1.2611],
        'tours' => [47.3941, 0.6848],
        'amiens' => [49.8941, 2.2958],
        'perpignan' => [42.6886, 2.8948],
        'metz' => [49.1193, 6.1757],
        'besancon' => [47.2378, 6.0241],
        'orleans' => [47.9029, 1.9093],
        'mulhouse' => [47.7508, 7.3359],
        'rouen' => [49.4431, 1.0993],
        'caen' => [49.1829, -0.3707],
        'nancy' => [48.6921, 6.1844],
        'avignon' => [43.9493, 4.8055],
        'poitiers' => [46.5802, 0.3404],
        'versailles' => [48.8014, 2.1301],
        'pau' => [43.2951, -0.3708],
        'la rochelle' => [46.1603, -1.1511],
        'annecy' => [45.8992, 6.1294],
        'chambery' => [45.5646, 5.9178],
        'biarritz' => [43.4832, -1.5586],
        'cannes' => [43.5528, 7.0174],
        'antibes' => [43.5804, 7.1251],
        'saint-malo' => [48.6493, -2.0257],
        'bayonne' => [43.4933, -1.4748],
        'colmar' => [48.0794, 7.3585],
        'troyes' => [48.2973, 4.0744],
        'valence' => [44.9334, 4.8924],
        'bourges' => [47.0810, 2.3987],
        'niort' => [46.3230, -0.4587],
        'angouleme' => [45.6484, 0.1560],
        'chateauroux' => [46.8106, 1.6910],
        'blois' => [47.5861, 1.3359],
        'laval' => [48.0733, -0.7708],
        'le mans' => [48.0061, 0.1996],
        'quimper' => [47.9960, -4.1024],
        'vannes' => [47.6582, -2.7603],
        'lorient' => [47.7482, -3.3702],
        'evreux' => [49.0270, 1.1510],
        'chartres' => [48.4439, 1.4894],
        'auxerre' => [47.7982, 3.5730],
        'nevers' => [46.9896, 3.1591],
        'macon' => [46.3069, 4.8281],
        'bourg-en-bresse' => [46.2058, 5.2258],
        'privas' => [44.7355, 4.5989],
        'gap' => [44.5594, 6.0794],
        'digne-les-bains' => [44.0929, 6.2358],
        'ajaccio' => [41.9192, 8.7386],
        'bastia' => [42.6976, 9.4508],
        'foix' => [42.9653, 1.6053],
        'tarbes' => [43.2333, 0.0781],
        'mont-de-marsan' => [43.8904, -0.4997],
        'agen' => [44.2049, 0.6212],
        'cahors' => [44.4478, 1.4386],
        'rodez' => [44.3502, 2.5745],
        'albi' => [43.9298, 2.1480],
        'montauban' => [44.0181, 1.3550],
        'carcassonne' => [43.2130, 2.3491],
        'mende' => [44.5178, 3.4996],
        'gueret' => [46.1667, 1.8667],
        'tulle' => [45.2667, 1.7667],
        'moulins' => [46.5654, 3.3327],
        'le puy-en-velay' => [45.0430, 3.8850],
        'saint-brieuc' => [48.5138, -2.7654],
        'cherbourg' => [49.6337, -1.6222],
        'saint-lo' => [49.1147, -1.0906],
        'alencon' => [48.4322, 0.0913],
        'beauvais' => [49.4295, 2.0807],
        'laon' => [49.5641, 3.6217],
        'charleville-mezieres' => [49.7717, 4.7200],
        'chalons-en-champagne' => [48.9578, 4.3646],
        'epinal' => [48.1747, 6.4497],
        'bar-le-duc' => [48.7714, 5.1614],
        'vesoul' => [47.6236, 6.1547],
        'lons-le-saunier' => [46.6742, 5.5522],
        'chaumont' => [48.1122, 5.1391],
        'melun' => [48.5386, 2.6597],
        'evry' => [48.6297, 2.4394],
        'nanterre' => [48.8924, 2.2069],
        'pontoise' => [49.0511, 2.1000],
        'bobigny' => [48.9078, 2.4392],
        'creteil' => [48.7909, 2.4556],
    ];

    /**
     * @return array{0: float, 1: float}|null
     */
    public static function coordinatesFor(?string $city): ?array
    {
        if (null === $city || '' === trim($city)) {
            return null;
        }

        return self::COORDINATES[self::normalize($city)] ?? null;
    }

    private static function normalize(string $city): string
    {
        $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT', $city);
        $ascii = false !== $transliterated ? $transliterated : $city;

        $normalized = mb_strtolower(trim($ascii));
        $normalized = (string) preg_replace('/[\s_]+/', '-', $normalized);

        return (string) preg_replace('/-+/', '-', $normalized);
    }
}
