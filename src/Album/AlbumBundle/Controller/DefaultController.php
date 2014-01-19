<?php

namespace Album\AlbumBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class DefaultController extends Controller
{
    public function indexAction($name)
    {
        return $this->render('AlbumBundle:Default:index.html.twig', array('name' => $name));
    }
}
