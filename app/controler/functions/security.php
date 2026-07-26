<?php
/**
 * Security utilities: CSRF protection, Path Traversal validation, Session checks.
 */

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

/**
 * Génère ou récupère le jeton CSRF de la session courante.
 * 
 * @return string Jeton hexadécimal de 64 caractères (256 bits)
 */
function generate_csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Valide un jeton CSRF par rapport à celui de la session.
 * 
 * @param string|null $token Jeton à vérifier
 * @return bool True si valide, False sinon
 */
function verify_csrf_token(?string $token): bool
{
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Vérifie qu'un chemin de fichier est sécurisé et ne fait pas de Path Traversal (../../).
 * Le chemin résolu par realpath() doit impérativement se trouver dans l'un des dossiers autorisés.
 * 
 * @param string $path Chemin à valider
 * @param array $allowed_base_dirs Liste des dossiers racines autorisés (par défaut: BibliothequeDir, TempDir)
 * @return bool True si le chemin est valide et autorisé
 */
function validate_safe_path(string $path, array $allowed_base_dirs = []): bool
{
    if (empty($path)) {
        return false;
    }

    $real_path = realpath($path);
    if (!$real_path) {
        // Si le fichier n'existe pas encore, on résout son dossier parent
        $dir = dirname($path);
        $real_dir = realpath($dir);
        if (!$real_dir) {
            return false;
        }
        $real_path = $real_dir;
    }

    if (empty($allowed_base_dirs)) {
        require_once __DIR__ . '/paths.php';
        require_once __DIR__ . '/bibliotheque.php';
        $allowed_base_dirs = [
            getBibliothequeDir(),
            resolveTempDir()
        ];
    }

    $real_path_normalized = str_replace('\\', '/', strtolower($real_path));

    foreach ($allowed_base_dirs as $allowed_dir) {
        $real_allowed = realpath($allowed_dir);
        if ($real_allowed) {
            $allowed_normalized = str_replace('\\', '/', strtolower($real_allowed));
            if (strpos($real_path_normalized, $allowed_normalized) === 0) {
                return true;
            }
        }
    }

    return false;
}
