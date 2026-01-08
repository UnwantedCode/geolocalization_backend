<?php

namespace App\Controller\Admin;

use App\Entity\SafetyAlert;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class SafetyAlertCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return SafetyAlert::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->onlyOnIndex(),
            AssociationField::new('user')->setLabel('User'),
            AssociationField::new('group')->setLabel('Group'),
            TextField::new('type')->setLabel('Alert Type'),
            TextareaField::new('message')->setLabel('Message')->hideOnIndex(),
            AssociationField::new('location')->setLabel('Location'),
            BooleanField::new('resolved')->setLabel('Resolved'),
            DateTimeField::new('resolvedAt')->setLabel('Resolved At')->hideOnIndex(),
            DateTimeField::new('createdAt')->setLabel('Created')->onlyOnIndex(),
        ];
    }
}
