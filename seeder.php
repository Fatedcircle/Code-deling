<?php

use RedBeanPHP\R;

require_once './vendor/autoload.php';

// Use your own database credentials
R::setup('mysql:host=localhost;dbname=test', 'test', 'test');

// Reset database
R::exec('SET FOREIGN_KEY_CHECKS = 0');
R::wipe('user');
R::wipe('post');
R::exec('SET FOREIGN_KEY_CHECKS = 1');

function getLanguageContent($language)
{
    $examples = [
        'PHP' => '<?php echo "Hello, World!"; ?>',
        'JavaScript' => 'console.log("Hello, World!");',
        'Python' => 'print("Hello, World!")',
    ];
    return $examples[$language] ?? 'No example available for this language.';
}

function getRandomTheme()
{
    return ['light', 'dark', 'solarized', 'monokai'][array_rand(['light', 'dark', 'solarized', 'monokai'])];
}

function generateProfilePicture($username)
{
    $uploadDir = __DIR__ . "/public/uploads/avatars/";
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $filePath = $uploadDir . "$username.png";
    $img = imagecreatetruecolor(100, 100);
    $white = imagecolorallocate($img, 255, 255, 255);
    $black = imagecolorallocate($img, 0, 0, 0);

    imagefilledrectangle($img, 0, 0, 100, 100, $white);
    imagestring($img, 5, 40, 40, strtoupper($username[0]), $black);

    imagepng($img, $filePath);
    imagedestroy($img);

    return "uploads/avatars/$username.png";
}

function getRandomBio()
{
    $bios = [
        "Liefhebber van programmeren en technologie.",
        "Altijd op zoek naar nieuwe uitdagingen.",
        "Full-stack developer met een passie voor open source.",
        "Code, koffie en creativiteit!",
        "Ik bouw dingen op het internet."
    ];
    return $bios[array_rand($bios)];
}

function getRandomCaption()
{
    $captions = [
        "Even wat code kloppen! 👨‍💻🔥", "Nieuwe dag, nieuwe commits! ✨",
        "Debugging... oftewel, uitzoeken waarom het niet werkt. 🤯",
        "GIT commit -m 'Oeps, vergeten te pushen'. 😅",
        "Elke bug is een stap dichter bij perfectie. 🚀",
        "Code review time... laat de roast beginnen! 🔥"
    ];
    return $captions[array_rand($captions)];
}

function createLikesForPost($post)
{
    for ($i = 0, $likes = rand(0, 5); $i < $likes; $i++) {
        $like = R::dispense('likes');
        $like->post = $post;
        $like->created_at = date('Y-m-d H:i:s');
        R::store($like);
    }
}

function createCommentsForPost($post)
{
    for ($i = 0, $comments = rand(0, 3); $i < $comments; $i++) {
        $comment = R::dispense('comments');
        $comment->post = $post;
        $comment->user = $post->user; // Kan later random gebruiker worden
        $comment->content = getRandomCaption();
        $comment->created_at = date('Y-m-d H:i:s');
        R::store($comment);
    }
}

function createUserWithPosts($username, $password, $postCount)
{
    $user = R::dispense('user');
    $user->username = $username;
    $user->password = password_hash($password, PASSWORD_DEFAULT);
    $user->profile_picture = generateProfilePicture($username);
    $user->bio = getRandomBio();
    R::store($user);

    $languages = ['PHP', 'JavaScript', 'Python'];

    for ($i = 1; $i <= $postCount; $i++) {
        $post = R::dispense('post');
        $post->title = "Test Post $i van $username";
        $post->content = "Dit is de inhoud van test post $i, aangemaakt door $username.";
        $post->created_at = date('Y-m-d H:i:s');
        $post->language = $languages[array_rand($languages)];
        $post->language_content = getLanguageContent($post->language);
        $post->caption = getRandomCaption();
        $post->theme = getRandomTheme();
        $post->user = $user;

        R::store($post);

        if (rand(0, 1)) {
            createLikesForPost($post);
        }
        createCommentsForPost($post);
    }

    return $user;
}

$users = [
    createUserWithPosts('test1', 'test', 5),
    createUserWithPosts('test2', 'test', 3),
];

echo R::count('user') . " gebruikers toegevoegd.\n";

foreach ($users as $user) {
    $postCount = R::count('post', 'user_id = ?', [$user->id]);
    echo "Gebruiker '{$user->username}' heeft {$postCount} posts.\n";

    foreach (R::find('post', 'user_id = ?', [$user->id]) as $post) {
        echo "Post '{$post->title}' heeft " . R::count('likes', 'post_id = ?', [$post->id]) . " likes en ";
        echo R::count('comments', 'post_id = ?', [$post->id]) . " reacties.\n";
    }
}

R::close();
