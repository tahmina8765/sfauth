<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Album\UserBundle\Security\User\Provider;

//use HWI\Bundle\OAuthBundle\Security\Core\User\FOSUBUserProvider;
use HWI\Bundle\OAuthBundle\OAuth\Response\UserResponseInterface;
use Album\AlbumBundle\Entity\Users;
use Symfony\Component\Security\Core\User\UserInterface;
use HWI\Bundle\OAuthBundle\Security\Core\User\OAuthAwareUserProviderInterface;

/**
 * Description of OAuthProvider
 *
 * @author storyteller
 */
class OAuthUserProvider implements OAuthAwareUserProviderInterface //extends FOSUBUserProvider
{

    private $container;
    private $session;
    protected $doctrine;

    public function __construct($doctrine, $session, $container)
    {
        $this->doctrine  = $doctrine;
        $this->container = $container;
        $this->session   = $session;
    }

    //put your code here

    public function loadUserByOAuthUserResponse(UserResponseInterface $response)
    {


        $token = $this->container->get("security.context")->getToken();
        if(empty($token)){
            $userId = 0;
        }else{
            $userId = $token->getUser()->getId();
        }

        $username = $response->getUsername();
        $extra    = $response->getResponse();
        $email    = $response->getEmail();

        echo $username;
        die();
        if (empty($email)) {
            $email = $response->getUsername();
        }

        $resourceOwner = $response->getResourceOwner()->getName();

//        $resourceProperty = $resourceOwner . "Id";
//        $resourceEmail    = $resourceOwner . "Email";
//        if ($resourceProperty == "facebookId") {
//            $resourceProperty = "fbId";
//            $resourceEmail    = "fbEmail";
//        }
        //echo $this->getProperty($response);
        $em   = $this->doctrine->getManager();
        $repo = $em->getRepository('IndieshuffleApiBundle:Users');


        switch ($resourceOwner) {
            case "facebook":
                $data['fb_email']             = $email;
                $data['fb_id']                = $username;
                $data['fb_token']             = $response->getAccessToken();
                break;
            case "twitter":
                $data['twitter_email']        = $email;
                $data['twitter_id']           = $username;
                $data['twitter_token']        = $response->getAccessToken();
                $data['twitter_token_secret'] = $response->getTokenSecret();
                break;
            case "google":
                $data['google_email']         = $email;
                $data['google_id']            = $username;
                $data['google_token']         = $response->getAccessToken();
                break;
        }

        $data['name']    = $extra['name'];
        $data['slug']    = $this->urlSlug($data['name']);
        $data['user_id'] = $userId;

        switch ($resourceOwner) {
            case 'twitter':
                $userId = $repo->saveMobileTwitterUser($data);
                break;
            case 'google':
                $userId = $repo->saveMobileGoogleUser($data);
                break;
            default:
                $userId = $repo->saveMobileFacebookUser($data);
                break;
        }

//        $user = $repo->findOneBy(array($resourceProperty => $response->getUsername()));
        $user = $repo->findOneBy(array('id' => $userId));

        if ($user) {
            //found one
            $this->connect($user, $response);
            return $user;
        } else {

            //create new user
            $token = $this->container->get("security.context")->getToken();
            if ($token) {

                $user = $token->getUser();
//                $user->setRoles(array('ROLE_USER'));
                $this->connect($user, $response);
                return $user;
            } else {
                //create the new user;

                $user  = new Users();
                if (!$email)
                    $email = $username;
                $user->setUsername($email);
                $user->setEmail($email);
                $slug  = $this->urlSlug($extra['name']);
                $user->setSlug($slug);
//                $user->setRoles(array('ROLE_USER'));
                $user->setEnabled(1);
                $user->setPassword('');
                $user->getUserMeta()->setUserId($user->getId());
                if (!empty($extra['name'])) {
                    $user->getUserMeta()->setName($extra['name']);
                }
                $user->getUserMeta()->setPublic(1);
                $this->connect($user, $response);
                return $user;
            }
        }
    }

    public function connect(UserInterface $user, UserResponseInterface $response)
    {
        $resourceOwner    = $response->getResourceOwner()->getName();
        $resourceProperty = $resourceOwner . "Id";
        if ($resourceProperty == "facebookId")
            $resourceProperty = "fbId";
        $username         = $response->getUsername();

        $em   = $this->doctrine->getManager();
        $repo = $em->getRepository('IndieshuffleApiBundle:Users');

        //on connect - get the access token and the user ID
        $service             = $response->getResourceOwner()->getName();
        if ($service == "facebook")
            $service             = "Fb";
        $setter              = 'set' . ucfirst($service);
        $setter_id           = $setter . 'Id';
        $setter_token        = $setter . 'Token';
        $setter_token_secret = $setter . 'TokenSecret';

        //we "disconnect" previously connected users
        if (null !== $previousUser = $repo->findOneBy(array($resourceProperty => $response->getUsername()))) {
            $previousUser->$setter_id(null);
            $previousUser->$setter_token(null);
            $em->flush();
        }

        //we connect current user
        $user->$setter_id($username);
        $user->$setter_token($response->getAccessToken());
        $user->$setter_token_secret($response->getTokenSecret());
        $em->flush();
    }

    /**
     * URL Slug
     * @param str $str
     * @return str
     */
    public function urlSlug($str)
    {
        #convert case to lower
        $str = strtolower($str);
        #remove special characters
        $str = preg_replace('/[^a-zA-Z0-9]/i', ' ', $str);
        #remove white space characters from both side
        $str = trim($str);
        #remove double or more space repeats between words chunk
        $str = preg_replace('/\s+/', ' ', $str);
        #fill spaces with hyphens
        $str = preg_replace('/\s+/', '-', $str);

        //echo $this->getProperty($response);
        $em   = $this->doctrine->getManager();
        $repo = $em->getRepository('IndieshuffleApiBundle:Users');
        $user = $repo->findOneBy(array('slug' => $str));

        if (count($user) > 0) {
            $str = $str . '-' . date('YmdHis');
        }
        return $str;
    }

}
