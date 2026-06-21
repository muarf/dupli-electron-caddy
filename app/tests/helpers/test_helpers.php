<?php

if (!function_exists('execute_routed_endpoint')) {
    /**
     * Exécute un endpoint routé dans un sous-processus PHP (simulé via CLI)
     * Utile pour les tests d'API qui utilisent des variables globales
     */
    function execute_routed_endpoint(string $endpoint, array $config): string
    {
        $payload = [
            'endpoint' => $endpoint,
            'config' => $config
        ];

        // On utilise le helper run_routed_endpoint.php qui initialise l'environnement
        $command = escapeshellarg(PHP_BINARY) . ' ' .
            escapeshellarg(__DIR__ . '/run_routed_endpoint.php') . ' ' .
            escapeshellarg(base64_encode(json_encode($payload)));

        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);

        if ($exitCode !== 0) {
             throw new RuntimeException("Helper run_routed_endpoint a échoué (code $exitCode): " . implode("\n", $output));
        }

        return implode("\n", $output);
    }
}
