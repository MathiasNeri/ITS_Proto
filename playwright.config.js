const { defineConfig } = require('@playwright/test');

module.exports = defineConfig({
    testDir: './tests',
    // La base SQLite est partagée par toutes les requêtes du serveur PHP
    // intégré (mono-processus) : exécuter les tests en parallèle créerait
    // des interférences entre eux (compteurs de stock, panier, etc.).
    fullyParallel: false,
    workers: 1,
    retries: process.env.CI ? 1 : 0,
    reporter: process.env.CI ? 'github' : 'list',
    globalSetup: require.resolve('./tests/global-setup.js'),
    use: {
        baseURL: 'http://127.0.0.1:8991',
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
    },
    webServer: {
        command: 'php -S 127.0.0.1:8991 -t public public/router.php',
        url: 'http://127.0.0.1:8991/accueil.php',
        reuseExistingServer: !process.env.CI,
        timeout: 30000,
    },
});
