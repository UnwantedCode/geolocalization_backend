<?php

namespace App\Controller\Admin;

use App\Entity\LocationHistory;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class LocationHistoryCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return LocationHistory::class;
    }


    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->onlyOnIndex(),
            NumberField::new('latitude'),
            NumberField::new('longitude'),
            NumberField::new('battery_level'),
            DateField::new('created_at')->onlyOnIndex(),
            DateField::new('updated_at')->onlyOnIndex(),
        ];
    }

}
