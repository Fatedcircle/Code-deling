<?php 

use Controllers\BaseController;
use RedBeanPHP\R;
use LoadHelpers\Helpers;

class CommentController extends BaseController
{
    public function addComment($postData)
    {
        if (!Helpers::isLoggedIn()) {
            header('Location: /login');
            exit;
        }

        $postId = (int) $postData['post_id'];
        $content = trim($postData['content']);

        if (empty($content)) {
            $_SESSION['error_message'] = "Reactie mag niet leeg zijn.";
            header("Location: /post/$postId");
            exit;
        }

        $user = R::load('user', $_SESSION['user_id']);

        $comment = R::dispense('comments');
        $comment->content = $content;
        $comment->post_id = $postId;
        $comment->user_id = $user->id;
        $comment->created_at = date('Y-m-d H:i:s');

        R::store($comment);

        header("Location: /post/$postId");
        exit;
    }
}
