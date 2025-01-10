<?php

namespace App\Controller\Admin;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserCrudController extends AbstractCrudController
{
    private UserPasswordHasherInterface $passwordHasher;
    private RequestStack $requestStack;

    public function __construct(UserPasswordHasherInterface $passwordHasher, RequestStack $requestStack)
    {
        $this->passwordHasher = $passwordHasher;
        $this->requestStack = $requestStack;
    }
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
            TextField::new('plainPassword', 'Nowe hasło')
                ->onlyOnForms()
                ->setFormType(PasswordType::class)
                ->setFormTypeOptions([
                    'mapped' => false,
                    'required' => false,
                    'attr' => ['autocomplete' => 'new-password'],
                ]),
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

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $request = $this->requestStack->getCurrentRequest();
        $plainPassword = $request->request->all()['User']['plainPassword'] ?? null;
        if ($plainPassword) {
            $entityInstance->setPlainPassword($plainPassword);
        }
        $this->handlePasswordUpdate($entityInstance);
        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $request = $this->requestStack->getCurrentRequest();
        $plainPassword = $request->request->all()['User']['plainPassword'] ?? null;
        if ($plainPassword) {
            $entityInstance->setPlainPassword($plainPassword);
        }
        $this->handlePasswordUpdate($entityInstance);
        parent::updateEntity($entityManager, $entityInstance);
    }

    private function handlePasswordUpdate($user): void
    {
        if ($user instanceof User && $user->getPlainPassword()) {
            $hashedPassword = $this->passwordHasher->hashPassword($user, $user->getPlainPassword());
            $user->setPassword($hashedPassword);
            $user->setPlainPassword(null);
        }
    }

}
