<?php

namespace Keboola\AdWordsExtractorBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class DefaultController extends Controller
{
    public function indexAction($name)
    {
        return $this->render('KeboolaAdWordsExtractorBundle:Default:index.html.twig', array('name' => $name));
    }
}
