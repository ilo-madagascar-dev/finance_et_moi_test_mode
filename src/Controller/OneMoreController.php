<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class OneMoreController extends AbstractController
{
    /**
     * @Route("/page/two", name="page_two")
     */
    public function pageTwo(): Response
    {
        return $this->render('page_2/index.html.twig', [
            'controller_name' => 'PageTwoController',
        ]);
    }

    /**
     * @Route("/page/three", name="page_three")
     */
    public function pageThree(): Response
    {
        return $this->render('page_3/index.html.twig', [
            'controller_name' => 'PageThreeController',
        ]);
    }

    /**
     * @Route("/page/four", name="page_four")
     */
    public function pageFour(): Response
    {
        return $this->render('page_4/index.html.twig', [
            'controller_name' => 'PageFourController',
        ]);
    }

    /**
     * @Route("/page/five", name="page_five")
     */
    public function pageFive(): Response
    {
        return $this->render('page_5/index.html.twig', [
            'controller_name' => 'PageFiveController',
        ]);
    }

    /**
     * @Route("/page/six", name="page_six")
     */
    public function pageSix(): Response
    {
        return $this->render('page_6/index.html.twig', [
            'controller_name' => 'PageSixController',
        ]);
    }

    /**
     * @Route("/page/seven", name="page_seven")
     */
    public function pageSeven(): Response
    {
        return $this->render('page_7/index.html.twig', [
            'controller_name' => 'PageSevenController',
        ]);
    }
}
