<?php

namespace App\Controller\Admin;

use App\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class UserCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return User::class;
    }


    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->onlyOnIndex(),
            EmailField::new('email'),
            TextField::new('username'),
            TextField::new('avatar'),
            ChoiceField::new('roles', 'Role')
                ->setChoices([
                    'Administrator' => 'ROLE_ADMIN',
                    'Użytkownik' => 'ROLE_USER',
                    'Moderator' => 'ROLE_MODERATOR',
                ])
                ->allowMultipleChoices()
                ->renderExpanded(),
            DateField::new('created_at')->onlyOnIndex(),
            DateField::new('updated_at')->onlyOnIndex(),
        ];
    }

}
