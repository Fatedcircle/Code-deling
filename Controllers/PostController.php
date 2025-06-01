<?php 

use Controllers\BaseController;
use RedBeanPHP\R;
use LoadHelpers\Helpers;

class PostController extends BaseController
{
    public function createPost()
    {
        Helpers::startSession();

        if (!Helpers::isLoggedIn()) {
            header('Location: /login');
            exit;
        }

        $languages = R::getAll('SELECT DISTINCT language FROM post');

        $selectedTheme = $_SESSION['theme'] ?? 'light';

        $data = [
            'languages' => $languages,
            'selectedTheme' => $selectedTheme, 
        ];

        echo Helpers::displayTemplate('pages/posts/create.twig', $data);
    }

    public function createPostAction()
    {
        if (!Helpers::isLoggedIn()) {
            header('Location: /login');
            exit;
        }

        $languages = R::findAll('languages');
        $selectedTheme = isset($_POST['selected_theme']) ? $_POST['selected_theme'] : 'light';

        $title = $_POST['title'];
        $content = $_POST['content'];
        $language = $_POST['language'];
        $newLanguage = isset($_POST['new_language']) ? trim($_POST['new_language']) : '';
        $language_content = $_POST['language_content'];
        $caption = isset($_POST['caption']) ? $_POST['caption'] : ''; 

        if ($newLanguage) {
            $existingLanguage = R::findOne('languages', 'name = ?', [$newLanguage]);
            if (!$existingLanguage) {
                $languageRecord = R::dispense('languages');
                $languageRecord->name = $newLanguage;
                R::store($languageRecord);
            }
            $language = $newLanguage;
        }

        $user = R::load('user', $_SESSION['user_id']);

        $post = R::dispense('post');
        $post->title = $title;
        $post->content = $content;
        $post->language = $language;
        $post->language_content = $language_content;
        $post->caption = $caption;
        $post->created_at = date('Y-m-d H:i:s');
        $post->theme = $selectedTheme;

        $post->user = $user;  

        R::store($post);

        header('Location: /');
        exit;
    }
    public function showPost($postId)
    {
        $post = R::load('post', $postId);

        if ($post->id) {
            $post->user = R::load('user', $post->user_id);

            $comments = R::find('comments', 'post_id = ? ORDER BY created_at ASC', [$postId]);

            $post->like_count = R::count('likes', 'post_id = ?', [$postId]);
            $post->liked = in_array($postId, $_SESSION['liked_posts'] ?? []);

            $data = [
            'post' => $post,
            'comments' => $comments,
            ];

            echo Helpers::displayTemplate('pages/posts/show.twig', $data);
        } else {
            http_response_code(404);
            echo "Post niet gevonden.";
        }
    }
    public function deletePost($postId)
    {
        $post = R::load('post', $postId);

        if (!$post->id) {
            http_response_code(404);
            echo "Post niet gevonden.";
            exit;
        }

        $user = Helpers::getLoggedInUser();
        if ($user->id !== $post->user_id) {
            http_response_code(403);
            echo "Je hebt geen toestemming om deze post te verwijderen.";
            exit;
        }

        R::trash($post);

        header("Location: /profile");
        exit;
    }
}
