<?php
/**
 * Google Login - YSWEBAPK
 * 
 * Este archivo maneja la autenticación con Google OAuth 2.0
 * Para usar este sistema necesitas:
 * 1. Crear un proyecto en Google Cloud Console
 * 2. Habilitar la API de Google+ y OAuth 2.0
 * 3. Configurar las credenciales (Client ID y Client Secret)
 * 4. Agregar la URL de redireccionamiento: https://tudominio.com/google-login.php
 */

require_once 'config/database.php';
require_once 'config/funciones.php';

// Configuración de Google OAuth
// OBTÉN ESTOS DATOS DESDE GOOGLE CLOUD CONSOLE
$GOOGLE_CLIENT_ID = 'TU_CLIENT_ID.apps.googleusercontent.com';
$GOOGLE_CLIENT_SECRET = 'TU_CLIENT_SECRET';
$GOOGLE_REDIRECT_URI = 'https://' . $_SERVER['HTTP_HOST'] . '/google-login.php';

// Si se recibe un código, procesar el login
if (isset($_GET['code'])) {
    $code = $_GET['code'];
    
    // Intercambiar código por token
    $token_url = 'https://oauth2.googleapis.com/token';
    $post_data = [
        'code' => $code,
        'client_id' => $GOOGLE_CLIENT_ID,
        'client_secret' => $GOOGLE_CLIENT_SECRET,
        'redirect_uri' => $GOOGLE_REDIRECT_URI,
        'grant_type' => 'authorization_code'
    ];
    
    $ch = curl_init($token_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    $response = curl_exec($ch);
    curl_close($ch);
    
    $token_data = json_decode($response, true);
    
    if (isset($token_data['access_token'])) {
        // Obtener información del usuario
        $user_info_url = 'https://www.googleapis.com/oauth2/v2/userinfo';
        $ch = curl_init($user_info_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $token_data['access_token']]);
        $user_response = curl_exec($ch);
        curl_close($ch);
        
        $user_data = json_decode($user_response, true);
        
        if (isset($user_data['email'])) {
            // Verificar si el usuario ya existe
            $email = $user_data['email'];
            $nombre = $user_data['name'] ?? $user_data['given_name'] ?? 'Usuario';
            $google_id = $user_data['id'];
            $foto = $user_data['picture'] ?? null;
            
            $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ?");
            $stmt->execute([$email]);
            $usuario = $stmt->fetch();
            
            if ($usuario) {
                // Usuario existe, iniciar sesión
                $_SESSION['usuario_id'] = $usuario['id'];
                $_SESSION['nombre'] = $usuario['nombre'];
                $_SESSION['rol'] = $usuario['rol'];
                $_SESSION['email'] = $usuario['email'];
                $_SESSION['foto_perfil'] = $usuario['foto_perfil'] ?? $foto;
                
                header('Location: index.php');
                exit;
            } else {
                // Crear nuevo usuario
                $password_hash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO usuarios (email, nombre, password_hash, foto_perfil) VALUES (?, ?, ?, ?)");
                $stmt->execute([$email, $nombre, $password_hash, $foto]);
                
                $usuario_id = $pdo->lastInsertId();
                
                $_SESSION['usuario_id'] = $usuario_id;
                $_SESSION['nombre'] = $nombre;
                $_SESSION['rol'] = 'usuario';
                $_SESSION['email'] = $email;
                $_SESSION['foto_perfil'] = $foto;
                
                header('Location: index.php');
                exit;
            }
        } else {
            // Error al obtener datos del usuario
            header('Location: login.php?error=google_data_error');
            exit;
        }
    } else {
        // Error al obtener token
        header('Location: login.php?error=google_token_error');
        exit;
    }
} else {
    // No hay código, redirigir a Google
    $auth_url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
        'client_id' => $GOOGLE_CLIENT_ID,
        'redirect_uri' => $GOOGLE_REDIRECT_URI,
        'response_type' => 'code',
        'scope' => 'email profile',
        'access_type' => 'online',
        'prompt' => 'select_account'
    ]);
    
    header('Location: ' . $auth_url);
    exit;
}
?>