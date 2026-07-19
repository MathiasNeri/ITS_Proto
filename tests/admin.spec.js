const { test, expect } = require('@playwright/test');
const { loginAsAdmin, fillHiddenDate } = require('./helpers');

test.describe('Panel admin', () => {
    test('administration.php exige d\'être connecté', async ({ page }) => {
        await page.goto('/administration.php');
        await expect(page).toHaveURL(/connexion\.php/);
    });

    test('un admin voit les onglets principaux', async ({ page }) => {
        await loginAsAdmin(page);
        await page.goto('/administration.php');
        for (const onglet of ['rdv', 'devis', 'commandes', 'produits', 'maintenance']) {
            await expect(page.locator(`button[data-tab="${onglet}"]`)).toBeVisible();
        }
    });

    test('l\'onglet Maintenance affiche l\'état des sauvegardes', async ({ page }) => {
        await loginAsAdmin(page);
        await page.goto('/administration.php');
        await page.click('button[data-tab="maintenance"]');
        await expect(page.locator('#tab-maintenance')).toContainText('sauvegarde');
    });

    test('créer une sauvegarde manuelle fonctionne', async ({ page }) => {
        await loginAsAdmin(page);
        await page.goto('/administration.php');
        await page.click('button[data-tab="maintenance"]');
        await page.click('#tab-maintenance button.btn-small');
        await page.waitForLoadState('networkidle');
        await expect(page.locator('.message-box.success')).toContainText('Sauvegarde créée');
    });

    test('une nouvelle demande de RDV déclenche un badge de notification', async ({ page, request }) => {
        // Soumet un RDV via une page dédiée (formulaire réel, jeton CSRF valide).
        const rdvPage = await page.context().newPage();
        await rdvPage.goto('/rdv.php');
        await rdvPage.fill('#nom', 'Notif');
        await rdvPage.fill('#prenom', 'Test');
        await rdvPage.fill('#email', 'notif.badge@example.com');
        await rdvPage.fill('#telephone', '0600000004');
        await rdvPage.selectOption('#service', 'Diagnostic');
        const demain = new Date(Date.now() + 86400000).toISOString().slice(0, 10);
        await fillHiddenDate(rdvPage, '#date', demain);
        await rdvPage.waitForTimeout(3200);
        await rdvPage.click('button.btn-submit');
        await rdvPage.waitForLoadState('networkidle');
        await rdvPage.close();

        await loginAsAdmin(page);
        await page.goto('/administration.php');
        await expect(page.locator('button[data-tab="rdv"] .tab-badge')).toBeVisible();
    });
});
