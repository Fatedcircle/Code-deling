<?php

use Controllers\BaseController;
use RedBeanPHP\R;
use LoadHelpers\Helpers;

class UserController extends BaseController
{
    public function login()
    {
        Helpers::startSession();
        $data = [
            'error_message' => $_SESSION['error_message'] ?? null,
            'login_username' => $_SESSION['login_username'] ?? null
        ];
        unset($_SESSION['error_message'], $_SESSION['login_username']);
        echo Helpers::displayTemplate('pages/users/login.twig', $data);
    }

    public function loginPost()
    {
        Helpers::startSession();
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';

        $user = R::findOne('user', 'username = ?', [$username]);

        if ($user && password_verify($password, $user->password)) {
            Helpers::setUserSession($user->id);
            header('Location: /');
            exit;
        } else {
            $_SESSION['error_message'] = "Ongeldige inloggegevens!";
            $_SESSION['login_username'] = $username;

            header('Location: /login');
            exit;
        }
    }

    public function logout()
    {
        Helpers::clearUserSession();
        header('Location: /login');
        exit;
    }

    public function register()
    {
        Helpers::startSession();
        $data = [
            'error_message' => $_SESSION['error_message'] ?? null,
            'register_username' => $_SESSION['register_username'] ?? null
        ];
        unset($_SESSION['error_message'], $_SESSION['register_username']);
        echo Helpers::displayTemplate('pages/users/register.twig', $data);
    }

    public function registerPost($postData)
    {
        Helpers::startSession();
        $username = trim($postData['username']);
        $password = trim($postData['password']);
        $passwordConfirm = trim($postData['password_confirm']);
        $profilePicture = $_FILES['profile_picture'] ?? null;

        if (empty($username) || empty($password) || empty($passwordConfirm)) {
            $_SESSION['error_message'] = "Alle velden zijn verplicht.";
            header('Location: /register');
            exit;
        }

        if ($password !== $passwordConfirm) {
            $_SESSION['error_message'] = "Wachtwoorden komen niet overeen.";
            header('Location: /register');
            exit;
        }

        $existingUser = R::findOne('user', 'username = ?', [$username]);
        if ($existingUser) {
            $_SESSION['error_message'] = "Deze gebruikersnaam is al in gebruik.";
            header('Location: /register');
            exit;
        }

        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
        $profileImagePath = null;

        if ($profilePicture && $profilePicture['error'] === UPLOAD_ERR_OK) {
            $maxFileSize = 1 * 1024 * 1024;
            if ($profilePicture['size'] > $maxFileSize) {
                $_SESSION['error_message'] = "De profielfoto mag niet groter zijn dan 1MB.";
                header('Location: /register');
                exit;
            }

            $fileExtension = strtolower(pathinfo($profilePicture['name'], PATHINFO_EXTENSION));
            if (!in_array($fileExtension, $allowedExtensions)) {
                $_SESSION['error_message'] = "Alleen JPG, JPEG, PNG en GIF bestanden zijn toegestaan.";
                header('Location: /register');
                exit;
            }

            $newFileName = strtolower($username) . "." . $fileExtension;
            $uploadDir = __DIR__ . "/../public/uploads/avatars/";

            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $fullPath = $uploadDir . $newFileName;
            if (move_uploaded_file($profilePicture['tmp_name'], $fullPath)) {
                $profileImagePath = "uploads/avatars/" . $newFileName;
            } else {
                $_SESSION['error_message'] = "Fout bij het uploaden van de profielfoto.";
                header('Location: /register');
                exit;
            }
        } else {
            $profileImagePath = null;
        }

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $user = R::dispense('user');
        $user->username = $username;
        $user->password = $hashedPassword;
        $user->profile_picture = $profileImagePath;
    
        R::store($user);

        Helpers::setUserSession($user->id);

        header('Location: /');
        exit;
    }

    public function profile($username = null)
    {
        Helpers::startSession();

        if ($username) {
            $user = R::findOne('user', 'username = ?', [$username]);
            if (!$user) {
                header('Location: /');
                exit;
            }
        } else {
            $user = Helpers::getLoggedInUser();
            if (!$user) {
                header('Location: /login');
                exit;
            }
        }

        $isOwnProfile = isset($_SESSION['user_id']) && $_SESSION['user_id'] == $user->id;

        $posts = R::find('post', 'user_id = ?', [$user->id]);

        $data = [
        'user' => $user,
        'profile_picture' => $user->profile_picture,
        'posts' => $posts,
        'isOwnProfile' => $isOwnProfile
        ];

        echo Helpers::displayTemplate('pages/users/profile.twig', $data);
    }

    public function profilePost($postData)
    {
        Helpers::startSession();

        $user = Helpers::getLoggedInUser();
        if (!$user) {
            header('Location: /login');
            exit;
        }

        $username = trim($postData['username']);
        $bio = trim($postData['bio'] ?? '');
        $password = trim($postData['password']);
        $passwordConfirm = trim($postData['password_confirm']);

        $existingUser = R::findOne('user', 'username = ?', [$username]);
        if ($existingUser && $existingUser->id != $user->id) {
            $_SESSION['error_message'] = "Deze gebruikersnaam is al in gebruik.";
            header('Location: /profile');
            exit;
        }

        $user->username = $username;
        $user->bio = $bio;

        if (!empty($password)) {
            if ($password !== $passwordConfirm) {
                $_SESSION['error_message'] = "Wachtwoorden komen niet overeen.";
                header('Location: /profile');
                exit;
            }

            $user->password = password_hash($password, PASSWORD_DEFAULT);
        }

        if (!empty($_FILES['profile_picture']['name'])) {
            $uploadDir = __DIR__ . '/../public/uploads/avatars/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
    
            $fileTmpPath = $_FILES['profile_picture']['tmp_name'];
            $fileExt = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
            $fileName = $username . '.' . $fileExt;
            $filePath = $uploadDir . $fileName;
            $fileSize = $_FILES['profile_picture']['size'];
            $fileType = mime_content_type($fileTmpPath);
    
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            $maxFileSize = 1 * 1024 * 1024; // 1 MB
    
            if (!in_array($fileType, $allowedTypes)) {
                $_SESSION['error_message'] = "Ongeldig bestandstype. Alleen JPG, PNG en GIF zijn toegestaan.";
                header('Location: /profile');
                exit;
            }
    
            if ($fileSize > $maxFileSize) {
                $_SESSION['error_message'] = "De afbeelding mag maximaal 1 MB groot zijn.";
                header('Location: /profile');
                exit;
            }
    
            if (!empty($user->profile_picture) && $user->profile_picture !== 'default.png') {
                $oldFilePath = $uploadDir . $user->profile_picture;
                if (file_exists($oldFilePath)) {
                    unlink($oldFilePath);
                }
            }

            if (move_uploaded_file($fileTmpPath, $filePath)) {
                $user->profile_picture = "uploads/avatars/" . $fileName;
            } else {
                $_SESSION['error_message'] = "Er is een fout opgetreden bij het uploaden van de afbeelding.";
                header('Location: /profile');
                exit;
            }
        }

        R::store($user);

        $_SESSION['success_message'] = "Profiel succesvol bijgewerkt.";
        header('Location: /profile');
        exit;
    }
}
