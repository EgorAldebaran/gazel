<?php  

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class TraderController extends AbstractController
{
    #[Route('/room/entry', name: 'entry_room')]
    public function entry(): Response
    {
        return new Response('trader');
    }
}
