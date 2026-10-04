<?php

declare(strict_types=1);

namespace App\Provider\Controller\Space;

use App\Catalog\Repository\CategoryRepository;
use App\Legal\Repository\CompanyIdentityRepository;
use App\Notification\Entity\NotificationPreference;
use App\Notification\Repository\NotificationPreferenceRepository;
use App\Payment\Service\SubscriptionService;
use App\Shared\Service\PublicImageStorage;
use App\User\Service\AvatarStorageService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * « Paramètres » — maquette profil_parametres_professionnel.jpeg (02/10) :
 * sous-menu à gauche (informations, profil pro, préférences, notifications,
 * moyens de paiement, sécurité, abonnement, intégrations, suppression),
 * photo de profil, couverture et réseaux sociaux à droite.
 *
 * Chaque formulaire poste vers save() avec un `form` qui dit quoi
 * enregistrer ; le profil professionnel garde son point historique
 * (/pro/profil, ProviderDashboardController::editProfile).
 */
#[IsGranted('ROLE_PROVIDER')]
final class ProviderSettingsController extends AbstractProviderSpaceController
{
    private const SECTIONS = ['infos', 'profil-pro', 'preferences', 'notifications', 'paiement', 'securite', 'abonnement', 'integrations', 'suppression'];

    public const TIMEZONES = ['Europe/Paris' => '(GMT+01:00) Paris', 'Europe/London' => '(GMT+00:00) Londres', 'Europe/Brussels' => '(GMT+01:00) Bruxelles', 'Europe/Zurich' => '(GMT+01:00) Zurich', 'Africa/Dakar' => '(GMT+00:00) Dakar', 'Africa/Casablanca' => '(GMT+01:00) Casablanca', 'America/Montreal' => '(GMT-05:00) Montréal', 'Indian/Reunion' => '(GMT+04:00) La Réunion', 'America/Guadeloupe' => '(GMT-04:00) Guadeloupe'];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CategoryRepository $categories,
        private readonly CompanyIdentityRepository $companyIdentities,
        private readonly NotificationPreferenceRepository $notificationPreferences,
        private readonly SubscriptionService $subscriptions,
        private readonly AvatarStorageService $avatars,
        private readonly PublicImageStorage $images,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    #[Route(path: ['fr' => '/pro/parametres', 'en' => '/en/pro/settings'], name: 'app_pro_settings')]
    public function index(Request $request): Response
    {
        $provider = $this->currentProvider();
        $section = (string) $request->query->get('section', 'infos');
        if (!\in_array($section, self::SECTIONS, true)) {
            $section = 'infos';
        }

        return $this->renderSpace('provider/space/settings.html.twig', 'Paramètres', [
            'section' => $section,
            'editing' => $request->query->getBoolean('modifier'),
            'me' => $this->currentUser(),
            'company' => $this->companyIdentities->findOneByProvider($provider),
            'categories' => $this->categories->findRoots(),
            'preference' => $this->notificationPreferences->findOneByUser($this->currentUser()),
            'subscription' => $this->subscriptions->currentFor($provider),
            'timezones' => self::TIMEZONES,
            'promo' => ['title' => 'Boostez votre visibilité', 'text' => 'Mettez en avant vos activités et attirez plus de clients.', 'cta' => 'Découvrir nos offres', 'href' => $this->generateUrl('app_pro_subscription')],
        ]);
    }

    #[Route(path: ['fr' => '/pro/parametres/enregistrer', 'en' => '/en/pro/settings/save'], name: 'app_pro_settings_save', methods: ['POST'])]
    public function save(Request $request): Response
    {
        $form = (string) $request->request->get('form', '');
        $section = (string) $request->request->get('section', 'infos');
        if (!$this->csrfOk($request)) {
            return $this->redirectToRoute('app_pro_settings', ['section' => $section]);
        }

        $user = $this->currentUser();
        $provider = $this->currentProvider();
        $r = $request->request;

        try {
            switch ($form) {
                case 'infos':
                    $first = trim((string) $r->get('firstName', ''));
                    $last = trim((string) $r->get('lastName', ''));
                    if ('' === $first || '' === $last) {
                        throw new \InvalidArgumentException('Le prénom et le nom sont obligatoires.');
                    }
                    $user->setFirstName(mb_substr($first, 0, 100))->setLastName(mb_substr($last, 0, 100))->setPhone(self::nullIfEmpty($r->get('phone'), 30));
                    $provider->setAddress(self::nullIfEmpty($r->get('address')))
                        ->setWebsiteUrl(self::nullIfEmpty($r->get('websiteUrl')))
                        ->setBio(self::nullIfEmpty($r->get('bio'), 4000));
                    $this->applyPreferences($request);
                    $message = 'Vos informations ont été mises à jour.';
                    break;

                case 'preferences':
                    $this->applyPreferences($request);
                    $provider->setAvailabilityLabel(self::nullIfEmpty($r->get('availabilityLabel'), 120));
                    $message = 'Vos préférences ont été enregistrées.';
                    break;

                case 'notifications':
                    $preference = $this->notificationPreferences->findOneByUser($user);
                    if (null === $preference) {
                        $preference = (new NotificationPreference())->setUser($user);
                        $this->entityManager->persist($preference);
                    }
                    $preference->setEmailEnabled($r->getBoolean('email_enabled'))->setPushEnabled($r->getBoolean('push_enabled'));
                    $message = 'Vos préférences de notifications ont été enregistrées.';
                    break;

                case 'securite':
                    $current = (string) $r->get('current', '');
                    $new = (string) $r->get('new', '');
                    if (!$this->passwordHasher->isPasswordValid($user, $current)) {
                        throw new \InvalidArgumentException('Votre mot de passe actuel est incorrect.');
                    }
                    if (mb_strlen($new) < 8 || !preg_match('/\d/', $new) || !preg_match('/[A-Za-z]/', $new)) {
                        throw new \InvalidArgumentException('Le nouveau mot de passe doit contenir au moins 8 caractères, dont une lettre et un chiffre.');
                    }
                    if ($new !== (string) $r->get('confirm', '')) {
                        throw new \InvalidArgumentException('La confirmation ne correspond pas au nouveau mot de passe.');
                    }
                    $user->setPassword($this->passwordHasher->hashPassword($user, $new));
                    $message = 'Votre mot de passe a été modifié.';
                    break;

                case 'reseaux':
                    foreach (['facebookUrl' => 'setFacebookUrl', 'instagramUrl' => 'setInstagramUrl', 'linkedinUrl' => 'setLinkedinUrl', 'youtubeUrl' => 'setYoutubeUrl'] as $field => $setter) {
                        if ($r->has($field)) {
                            $value = self::nullIfEmpty($r->get($field));
                            if (null !== $value && !preg_match('#^https?://#i', $value)) {
                                $value = 'https://'.ltrim($value, '@/');
                            }
                            $provider->{$setter}($value);
                        }
                    }
                    $message = 'Réseaux sociaux mis à jour.';
                    break;

                case 'photo':
                    $file = $request->files->get('photo');
                    if ('supprimer' === $r->get('action')) {
                        $this->avatars->remove($user);
                        $message = 'Photo de profil supprimée.';
                    } elseif ($file instanceof UploadedFile) {
                        $this->avatars->store($user, $file);
                        $message = 'Photo de profil mise à jour.';
                    } else {
                        throw new \InvalidArgumentException('Choisissez une image.');
                    }
                    break;

                case 'couverture':
                    $file = $request->files->get('cover');
                    if ('supprimer' === $r->get('action')) {
                        $this->images->delete($provider->getCoverPath());
                        $provider->setCoverPath(null);
                        $message = 'Couverture supprimée.';
                    } elseif ($file instanceof UploadedFile) {
                        $path = $this->images->store($file, 'covers');
                        $this->images->delete($provider->getCoverPath());
                        $provider->setCoverPath($path);
                        $message = 'Couverture mise à jour.';
                    } else {
                        throw new \InvalidArgumentException('Choisissez une image.');
                    }
                    break;

                default:
                    throw new \InvalidArgumentException('Formulaire inconnu.');
            }

            $this->entityManager->flush();
            $this->addFlash('success', $message);
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_pro_settings', ['section' => $section]);
    }

    private function applyPreferences(Request $request): void
    {
        $locale = (string) $request->request->get('locale', '');
        if (\in_array($locale, ['fr', 'en'], true)) {
            $this->currentUser()->setPreferredLocale($locale);
        }
        $timezone = (string) $request->request->get('timezone', '');
        if (\array_key_exists($timezone, self::TIMEZONES)) {
            $this->currentProvider()->setTimezone($timezone);
        }
    }
}
