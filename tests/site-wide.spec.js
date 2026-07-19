const { test, expect } = require('@playwright/test');

test.describe('Site — pages publiques et infrastructure', () => {
    test('la page d\'accueil se charge avec le bon titre', async ({ page }) => {
        const response = await page.goto('/accueil.php');
        expect(response.status()).toBe(200);
        await expect(page).toHaveTitle(/ITS/);
    });

    test('la navigation principale mène aux bonnes pages', async ({ page }) => {
        await page.goto('/accueil.php');
        await page.click('.nav-item:has-text("Boutique")');
        await expect(page).toHaveURL(/boutique\.php/);
        await page.click('.nav-item:has-text("Configurateur")');
        await expect(page).toHaveURL(/configurateur\.php/);
    });

    test('sitemap.xml est accessible et bien formé', async ({ request }) => {
        const response = await request.get('/sitemap.xml');
        expect(response.status()).toBe(200);
        const body = await response.text();
        expect(body).toContain('<urlset');
        expect(body).toContain('/boutique.php');
    });

    test('robots.txt référence le sitemap', async ({ request }) => {
        const response = await request.get('/robots.txt');
        expect(response.status()).toBe(200);
        const body = await response.text();
        expect(body).toContain('Sitemap:');
        expect(body).toContain('Disallow: /administration.php');
    });

    test('les pages privées ne sont pas indexables', async ({ page }) => {
        await page.goto('/connexion.php');
        const robots = await page.locator('meta[name="robots"]').getAttribute('content');
        expect(robots).toContain('noindex');
    });

    test('une donnée structurée LocalBusiness valide est présente sur l\'accueil', async ({ page }) => {
        await page.goto('/accueil.php');
        const json = await page.locator('script[type="application/ld+json"]').first().textContent();
        const data = JSON.parse(json);
        expect(data['@type']).toBe('ElectronicsStore');
        expect(data.address.postalCode).toBe('83390');
    });

    test('aucun débordement horizontal sur mobile (accueil, boutique, configurateur)', async ({ browser }) => {
        const context = await browser.newContext({ viewport: { width: 375, height: 800 } });
        const page = await context.newPage();
        for (const url of ['/accueil.php', '/boutique.php', '/configurateur.php']) {
            await page.goto(url);
            const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
            expect(overflow, url + ' ne devrait pas déborder').toBe(0);
        }
        await context.close();
    });

    test('le changement de thème clair/sombre fonctionne', async ({ page }) => {
        await page.goto('/accueil.php');
        const before = await page.evaluate(() => document.documentElement.getAttribute('data-theme'));
        await page.click('button[aria-label="Changer de thème"]');
        const after = await page.evaluate(() => document.documentElement.getAttribute('data-theme'));
        expect(after).not.toBe(before);
    });

    test('une page 404 pour une URL inconnue', async ({ page }) => {
        const response = await page.goto('/cette-page-nexiste-pas.php');
        expect(response.status()).toBe(404);
    });
});
