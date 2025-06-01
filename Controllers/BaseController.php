<?php

namespace Controllers;

use LoadHelpers\Helpers;
use RedBeanPHP\R;

class BaseController
{
    public function __construct()
    {
        $this->initializeDatabaseConnection();
        Helpers::startSession();
        $this->checkAccess();
    }

    private function initializeDatabaseConnection()
    {
        // Use your own database credentials
        if (!R::testConnection()) {
            R::setup('mysql:host=localhost;dbname=test', 'test', 'test');
        }
    }

    private function checkAccess()
    {
        $currentRoute = $this->getCurrentRoute();

        $publicRoutes = $this->getPublicRoutes();

        $isPublicProfile = $this->isPublicProfile($currentRoute);
        $isPublicPost = $this->isPublicPost($currentRoute);

        if (!$this->isLoggedIn() && !$this->isPublicPage($currentRoute, $publicRoutes, $isPublicProfile, $isPublicPost)) {
            header('Location: /login'); 
            exit;
        }
    }

    private function getCurrentRoute()
    {
        return parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    }

    private function getPublicRoutes()
    {
        return ['/', '/login', '/register'];
    }

    private function isPublicProfile($route)
    {
        return preg_match('#^/profile/[^/]+$#', $route);
    }

    private function isPublicPost($route)
    {
        return preg_match('#^/post/\d+$#', $route);
    }

    private function isLoggedIn()
    {
        return Helpers::isLoggedIn();
    }

    private function isPublicPage($route, $publicRoutes, $isPublicProfile, $isPublicPost)
    {
        return in_array($route, $publicRoutes) || $isPublicProfile || $isPublicPost;
    }
}
