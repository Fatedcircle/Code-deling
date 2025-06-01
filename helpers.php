<?php

namespace LoadHelpers;

use Twig\Loader\FilesystemLoader;
use Twig\Environment;
use RedBeanPHP\R;

class Helpers
{
    public static function startSession()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function displayTemplate($template, $data = [])
    {
        self::startSession();

        $data['isLoggedIn'] = self::isLoggedIn();

        if (self::isLoggedIn()) {
            $user = self::getLoggedInUser();
            $data['username'] = $user->username;
            $data['profile_picture'] = $user->profile_picture;
        }

        $data['requestUri'] = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $loader = new FilesystemLoader('../views');
        $twig = new Environment($loader);


        return $twig->render($template, $data);
    }
    public static function error($errorNumber, $errorMessage)
    {
        $loader = new \Twig\Loader\FilesystemLoader('../views');
        $twig = new \Twig\Environment($loader);
        http_response_code($errorNumber);
        echo $twig->render('error.twig', ['errorNumber' => $errorNumber, 'errorMessage' => $errorMessage]);
    }

    public static function getLoggedInUser()
    {
        if (self::isLoggedIn()) {
            $userId = $_SESSION['user_id'];
            return R::load('user', $userId);
        }
        return null;
    }

    public static function isLoggedIn()
    {
        self::startSession();
    
        return isset($_SESSION['user_id']) && isset($_SESSION['username']);
    }

    public static function setUserSession($userId)
    {
        self::startSession();

        session_regenerate_id(true);

        $user = R::load('user', $userId);

        $_SESSION['user_id'] = $userId;
        $_SESSION['username'] = $user->username;  
        $_SESSION['profile_picture'] = $user->profile_picture;
    }

    public static function clearUserSession()
    {
        self::startSession();

        unset($_SESSION['user_id']);
        unset($_SESSION['username']);
        unset($_SESSION['profile_picture']);
        session_destroy();
    }
}
