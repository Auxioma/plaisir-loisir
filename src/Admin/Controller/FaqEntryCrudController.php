<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use App\Support\Entity\FaqEntry;
use App\Support\Enum\FaqCategory;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * Questions fréquentes, affichées sur /faq et sur le Centre d'aide.
 *
 * POURQUOI UNE TABLE SÉPARÉE DES TEXTES JURIDIQUES
 * Un texte juridique est versionné et ne se modifie jamais une fois publié :
 * il faut pouvoir prouver ce que l'utilisateur a accepté. Une réponse de FAQ
 * n'engage personne — elle se corrige sur place, et une coquille ne doit pas
 * obliger à publier une « version 2 » de la FAQ entière. D'où deux écrans,
 * avec deux règles opposées et volontairement affichées.
 *
 * CE QUI SE GÈRE ICI ET CE QUI NE S'Y GÈRE PAS
 * Les QUESTIONS se saisissent librement. Les RUBRIQUES, non : chacune possède
 * une icône et une phrase de présentation sur le Centre d'aide, donc elles
 * vivent dans le code (App\Support\Enum\FaqCategory). En ajouter une demande
 * une modification ; en remplir une ne demande rien.
 *
 * @extends AbstractCrudController<FaqEntry>
 */
class FaqEntryCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return FaqEntry::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('question fréquente')
            ->setEntityLabelInPlural('questions fréquentes')
            ->setPageTitle(Crud::PAGE_INDEX, 'FAQ')
            ->setHelp(
                Crud::PAGE_INDEX,
                'Ces questions alimentent /faq et le Centre d\'aide. '
                .'La version française et la version anglaise sont deux lignes distinctes : traduire une question, c\'est en créer une seconde en anglais.',
            )
            // Regroupé comme à l'affichage : on relit une rubrique entière,
            // pas une question isolée.
            ->setDefaultSort(['category' => 'ASC', 'position' => 'ASC'])
            ->setSearchFields(['question', 'answer']);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('category')
            ->add('locale')
            ->add('published')
            ->add('featured');
    }

    public function configureFields(string $pageName): iterable
    {
        yield ChoiceField::new('category', 'Rubrique')
            ->setChoices(array_combine(
                array_map(static fn (FaqCategory $c): string => $c->label(), FaqCategory::ordered()),
                FaqCategory::ordered(),
            ))
            ->setHelp('Détermine sous quel titre la question apparaît, et sur quelle carte du Centre d\'aide elle est comptée.');

        yield ChoiceField::new('locale', 'Langue')
            ->setChoices(['Français' => 'fr', 'Anglais' => 'en']);

        yield TextField::new('question', 'Question')
            ->setHelp('Le libellé replié de l\'accordéon. Formulez-la comme un visiteur la poserait.');

        yield TextEditorField::new('answer', 'Réponse')
            ->setHelp('Les liens vers une autre page du site sont utiles ici : conditions de vente, formulaire de contact.')
            ->hideOnIndex();

        yield IntegerField::new('position', 'Rang')
            ->setHelp('Rang dans la rubrique, du plus petit au plus grand. Les questions les plus posées se mettent en tête — ce que l\'ordre alphabétique ne saurait pas faire.');

        yield BooleanField::new('published', 'Publiée')
            ->setHelp('Décochée, la question reste modifiable ici sans apparaître sur le site : de quoi préparer une réponse avant l\'ouverture d\'une fonctionnalité.');

        yield BooleanField::new('featured', 'Mise en avant')
            ->setHelp('Remonte la question dans « Les questions les plus consultées », sur le Centre d\'aide.');
    }
}
