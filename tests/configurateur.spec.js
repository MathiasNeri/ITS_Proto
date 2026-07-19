const { test, expect } = require('@playwright/test');

test.describe('Configurateur PC', () => {
    test('le profil Gaming sélectionne des composants compatibles et calcule un total', async ({ page }) => {
        await page.goto('/configurateur.php');
        await page.click('#presetGaming');
        await page.waitForTimeout(300);

        const cpuChecked = await page.locator('.option-card[data-type="cpu"] input:checked').count();
        const moboChecked = await page.locator('.option-card[data-type="carte_mere"] input:checked').count();
        expect(cpuChecked).toBe(1);
        expect(moboChecked).toBe(1);

        const total = await page.textContent('#summaryTotal');
        expect(total).toMatch(/\d/);
        expect(total).not.toBe('0,00 €');
    });

    test('choisir un CPU masque les cartes mères incompatibles', async ({ page }) => {
        await page.goto('/configurateur.php');
        const cpuCard = page.locator('.option-card[data-type="cpu"]', { hasText: 'Ryzen 5 7600X' });
        await cpuCard.click();
        await page.waitForTimeout(200);

        const visibleMobos = await page.locator('.option-card[data-type="carte_mere"]:visible').count();
        const hiddenMobos = await page.locator('.option-card[data-type="carte_mere"].hidden-incompatible').count();
        expect(visibleMobos).toBeGreaterThan(0);
        expect(hiddenMobos).toBeGreaterThan(0);
    });

    test('ajouter une configuration au panier redirige vers le panier', async ({ page }) => {
        await page.goto('/configurateur.php');
        await page.click('#presetBureautique');
        await page.waitForTimeout(300);
        await page.click('#addToCartBtn');
        await page.waitForLoadState('networkidle');
        await expect(page).toHaveURL(/panier\.php/);
        await expect(page.locator('.cart-row')).toHaveCount(1);
    });

    test('une demande de devis avec périphériques aboutit à un message de succès', async ({ page }) => {
        await page.goto('/configurateur.php');
        await page.click('#presetBureautique');
        await page.waitForTimeout(300);

        await page.fill('#nom', 'Client Devis PC');
        await page.fill('#prenom', 'Test');
        await page.fill('#adresse', '1 rue de Test');
        await page.fill('#code_postal', '83390');
        await page.fill('#ville', 'Pierrefeu-du-Var');
        await page.fill('#email', 'devis.pc@example.com');
        await page.fill('#telephone', '0600000002');
        await page.check('#consentement');
        await page.click('button[value="devis"]');
        await page.waitForLoadState('networkidle');
        await expect(page.locator('.message.success')).toBeVisible();
    });
});
