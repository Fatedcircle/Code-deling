<?php

require_once '../vendor/autoload.php';

use LoadHelpers\Helpers;
use RedBeanPHP\R;

$userController = new UserController();
$postController = new PostController();
$commentController = new CommentController();

// $requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

$scriptName = $_SERVER['SCRIPT_NAME']; // bijv. /submap/index.php
$fullUri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH); // bijv. /submap/login
$basePath   = rtrim(str_replace('/' . basename($scriptName), '', $scriptName), '/'); // /submap

// Strip de base path van de URI:
$requestUri = '/' . ltrim(substr($fullUri, strlen($basePath)), '/'); // bijv. /login
switch ($requestUri) {
    case '/':
        Helpers::startSession();

        if (!isset($_SESSION['liked_posts']) || !is_array($_SESSION['liked_posts'])) {
            $_SESSION['liked_posts'] = [];
        }

        $userSearch = $_GET['user_search'] ?? '';
        $postSearch = $_GET['post_search'] ?? '';

        $filteredUsers = $userSearch ? R::find('user', 'username LIKE ?', ['%' . $userSearch . '%']) : [];

        $filteredPosts = $postSearch ? R::find('post', 'title LIKE ? OR content LIKE ?', ['%' . $postSearch . '%', '%' . $postSearch . '%']) : R::findAll('post', 'ORDER BY created_at DESC');

        $userNotFoundMessage = $userSearch && empty($filteredUsers) ? "Geen gebruiker gevonden met de naam '{$userSearch}'." : '';

        $postNotFoundMessage = $postSearch && empty($filteredPosts) ? "Geen posts gevonden met de zoekterm '{$postSearch}'." : '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_id'])) {
            $postId = (int) $_POST['post_id'];
            $post = R::load('post', $postId);

            if ($post->id) {
                if (in_array($postId, $_SESSION['liked_posts'])) {
                    R::exec("DELETE FROM likes WHERE post_id = ?", [$postId]);
                    $_SESSION['liked_posts'] = array_diff($_SESSION['liked_posts'], [$postId]);
                } else {
                    $like = R::dispense('likes');
                    $like->post_id = $postId;
                    R::store($like);
                    $_SESSION['liked_posts'][] = $postId;
                }

                $post->like_count = R::count('likes', 'post_id = ?', [$postId]);
                R::store($post);

                header("Location: /#post-$postId");
                exit;
            }
        }

        foreach ($filteredPosts as $post) {
            $post->like_count = R::count('likes', 'post_id = ?', [$post->id]);
            $post->liked = in_array($post->id, $_SESSION['liked_posts'] ?? []);
            $post->comment_count = R::count('comments', 'post_id = ?', [$post->id]);  // Tel het aantal reacties
            $post->user = R::load('user', $post->user_id);
        }

        $data = [
            'posts' => $filteredPosts,
            'users' => $filteredUsers,
            'user_search' => $userSearch,
            'post_search' => $postSearch,
            'user_not_found_message' => $userNotFoundMessage,
            'post_not_found_message' => $postNotFoundMessage,
        ];

        echo Helpers::displayTemplate('pages/homepage.twig', $data);
        break;

    case '/login':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $userController->loginPost($_POST);
            exit;
        } else {
            $userController->login();
        }
        break;
    case '/logout':
        $userController->logout();
        break;

    case '/register':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $userController->registerPost($_POST);
            exit;
        } else { 
            $userController->register();
        }
        break;
    
    case '/profile':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $userController->profilePost($_POST);
            exit;
        } else {
            $userController->profile();
        }
        break;

    case (preg_match('#^/profile/([^/]+)$#', $requestUri, $matches) ? true : false):
        $userController->profile($matches[1]);
        break;
    
    case '/create':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $postController->createPostAction();
            exit;
        } else {
            $postController->createPost();
        }
        break;

    case (preg_match('#^/post/([^/]+)$#', $requestUri, $matches) ? true : false):
        $postId = (int) $matches[1];
        $postController->showPost($postId);
        break;
        
    case '/comment':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $commentController->addComment($_POST);
        }
        break;
        
        
    case (preg_match('#^/edit/(\d+)$#', $requestUri, $matches) ? true : false):
        $postId = (int) $matches[1];
        $post = R::load('post', $postId);
        
        if (!$post->id) {
            http_response_code(404);
            echo "Post niet gevonden.";
            exit;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $post->title = $_POST['title'];
            $post->content = $_POST['content'];
            $post->language = $_POST['language'];
            $post->language_content = $_POST['language_content'];
            $post->caption = $_POST['caption'];
            $post->selected_theme = $_POST['selected_theme'];
        
            R::store($post);
        
            header("Location: /post/{$post->id}");
            exit;
        }
        $languages = R::getAll('SELECT DISTINCT language FROM post');
        $data = [
            'post' => $post,
            'languages' => $languages,
        ];
        echo Helpers::displayTemplate('pages/posts/edit.twig', $data);
        break;

    case (preg_match('#^/fork/(\d+)$#', $requestUri, $matches) ? true : false):
        $postId = (int) $matches[1];
        $post = R::load('post', $postId);
    
        if ($post->id) {
            $newPost = R::dispense('post');
            $newPost->title = "Forked: " . $post->title;
            $newPost->content = $post->content;
            $newPost->language_content = $post->language_content;
            $newPost->language = $post->language;
            $newPost->theme = $post->theme; 
            $newPost->caption = $post->caption;
            $newPost->user_id = $_SESSION['user_id'];
            $newPost->created_at = date('Y-m-d H:i:s');
            $newPost->updated_at = date('Y-m-d H:i:s');

            $newPostId = R::store($newPost);

            header("Location: /edit/$newPostId");
            exit;
        } else {
            http_response_code(404);
            echo "Post niet gevonden.";
        }
        break;

    case (preg_match('#^/delete/(\d+)$#', $requestUri, $matches) ? true : false):
        $postId = (int) $matches[1];
        $postController->deletePost($postId);
        break;

    default:
        http_response_code(404);
        echo "Pagina niet gevonden.";
        break;
}
