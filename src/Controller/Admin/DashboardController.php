<?php

namespace App\Controller\Admin;

use App\Entity\Group;
use App\Entity\LocationHistory;
use App\Entity\Message;
use App\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DashboardController extends AbstractDashboardController
{
    #[Route('/admin', name: 'admin')]
    public function index(): Response
    {
        //return parent::index();
        return $this->render('admin/dashboard.html.twig', [
            'title' => 'Witaj w CMS!',
            'subtitle' => 'Zarządzaj swoimi danymi w łatwy sposób.',
        ]);
        // Option 1. You can make your dashboard redirect to some common page of your backend
        //
        // $adminUrlGenerator = $this->container->get(AdminUrlGenerator::class);
        // return $this->redirect($adminUrlGenerator->setController(OneOfYourCrudController::class)->generateUrl());

        // Option 2. You can make your dashboard redirect to different pages depending on the user
        //
        // if ('jane' === $this->getUser()->getUsername()) {
        //     return $this->redirect('...');
        // }

        // Option 3. You can render some custom template to display a proper dashboard with widgets, etc.
        // (tip: it's easier if your template extends from @EasyAdmin/page/content.html.twig)
        //
        // return $this->render('some/path/my-dashboard.html.twig');
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('CMS - Panel administracyjny');
    }

    public function configureMenuItems(): iterable
    {
        return [
            MenuItem::linkToDashboard('Dashboard', 'fa fa-home'),
            MenuItem::linkToCrud('Group', 'fa fa-tags', Group::class),
            MenuItem::linkToCrud('User', 'fa fa-tags', User::class),
            MenuItem::linkToCrud('Location History', 'fa fa-file-text', LocationHistory::class),
            MenuItem::linkToCrud('Messages', 'fa fa-comment', Message::class),
            MenuItem::linkToRoute('Send Notification', 'fa fa-bell', 'admin_firebase_send'),
            MenuItem::linkToRoute('Send Notification For Seleced', 'fa fa-bell', 'admin_firebase_send_selected'),
            //MenuItem::linkToLogout('Logout', 'fa fa-exit'),
         ];
    }
}
