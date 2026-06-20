<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class HelpController extends AbstractController
{
    #[Route('/aide', name: 'app_help')]
    public function index(): Response
    {
        return $this->render('help/index.html.twig');
    }
}
