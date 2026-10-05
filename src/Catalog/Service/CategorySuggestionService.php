<?php

declare(strict_types=1);

namespace App\Catalog\Service;

use App\Catalog\Entity\Category;
use App\Catalog\Entity\CategorySuggestion;
use App\Catalog\Repository\CategoryRepository;
use App\Catalog\Repository\CategorySuggestionRepository;
use App\Notification\Enum\NotificationCategory;
use App\Notification\Service\NotificationService;
use App\User\Entity\User;
use App\User\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Propositions de catégories (05/10) : un membre ou un professionnel qui ne
 * trouve pas sa catégorie la propose depuis l'assistant ; l'équipe la
 * valide ou la refuse dans le back-office (CategorySuggestionCrudController).
 */
final class CategorySuggestionService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CategoryRepository $categories,
        private readonly CategorySuggestionRepository $suggestions,
        private readonly UserRepository $users,
        private readonly NotificationService $notifications,
        private readonly SluggerInterface $slugger,
    ) {
    }

    /**
     * @return array{status: 'existing', category: Category}|array{status: 'created'|'duplicate', suggestion: CategorySuggestion}
     *
     * @throws \InvalidArgumentException nom invalide
     */
    public function suggest(string $name, ?string $description, User $user, string $context): array
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
        if (mb_strlen($name) < 3 || mb_strlen($name) > 60) {
            throw new \InvalidArgumentException('Le nom de la catégorie doit faire entre 3 et 60 caractères.');
        }

        // La catégorie existe déjà (casse et accents mis à part) : on la propose directement.
        $slug = strtolower((string) $this->slugger->slug($name));
        foreach ($this->categories->findAll() as $category) {
            if ($category->getSlug() === $slug || mb_strtolower($category->getName()) === mb_strtolower($name)) {
                return ['status' => 'existing', 'category' => $category];
            }
        }

        $pending = $this->suggestions->findPendingByName($name);
        if (null !== $pending) {
            return ['status' => 'duplicate', 'suggestion' => $pending];
        }

        $suggestion = (new CategorySuggestion())
            ->setName(mb_convert_case(mb_substr($name, 0, 1), \MB_CASE_UPPER).mb_substr($name, 1))
            ->setDescription(null !== $description && '' !== trim($description) ? mb_substr(trim($description), 0, 500) : null)
            ->setRequestedBy($user)
            ->setContext('pro' === $context ? 'pro' : 'private');
        $this->entityManager->persist($suggestion);
        $this->entityManager->flush();

        foreach ($this->users->findAdmins() as $admin) {
            $this->notifications->notify($admin, NotificationCategory::System, 'Nouvelle catégorie proposée', sprintf('« %s », proposée par %s, attend votre validation.', $suggestion->getName(), $user->getFirstName()));
        }

        return ['status' => 'created', 'suggestion' => $suggestion];
    }

    public function approve(CategorySuggestion $suggestion): Category
    {
        if (!$suggestion->isPending()) {
            throw new \InvalidArgumentException('Cette proposition a déjà été traitée.');
        }

        $base = strtolower((string) $this->slugger->slug($suggestion->getName())) ?: 'categorie';
        $slug = $base;
        for ($i = 2; null !== $this->categories->findOneBy(['slug' => $slug]); ++$i) {
            $slug = $base.'-'.$i;
        }
        $position = 1 + max([0, ...array_map(static fn (Category $c): int => $c->getPosition(), $this->categories->findAll())]);

        $category = (new Category())
            ->setName($suggestion->getName())
            ->setSlug($slug)
            ->setParent($suggestion->getParent())
            ->setPosition($position);
        $this->entityManager->persist($category);

        $suggestion->setStatus(CategorySuggestion::STATUS_APPROVED)->setCategory($category)->setDecidedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        if (null !== ($author = $suggestion->getRequestedBy())) {
            $this->notifications->notify($author, NotificationCategory::Activity, 'Catégorie ajoutée', sprintf('Votre catégorie « %s » est validée : elle est maintenant proposée. Reprenez votre brouillon pour la choisir.', $category->getName()));
        }

        return $category;
    }

    public function reject(CategorySuggestion $suggestion, ?string $note): void
    {
        if (!$suggestion->isPending()) {
            throw new \InvalidArgumentException('Cette proposition a déjà été traitée.');
        }
        $suggestion->setStatus(CategorySuggestion::STATUS_REJECTED)->setDecidedAt(new \DateTimeImmutable())
            ->setAdminNote(null !== $note && '' !== trim($note) ? mb_substr(trim($note), 0, 255) : $suggestion->getAdminNote());
        $this->entityManager->flush();

        if (null !== ($author = $suggestion->getRequestedBy())) {
            $this->notifications->notify($author, NotificationCategory::Activity, 'Catégorie non retenue', sprintf('Votre proposition « %s » n’a pas été retenue%s. Choisissez la catégorie la plus proche dans la liste.', $suggestion->getName(), $suggestion->getAdminNote() ? ' : '.$suggestion->getAdminNote() : ''));
        }
    }
}
