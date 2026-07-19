const { test, expect } = require('@playwright/test');
const { loginAsAdmin, fillHiddenDate } = require('./helpers');

test.describe('Formulaires publics et anti-spam', () => {
    test('le formulaire de contact accepte une soumission valide', async ({ page }) => {
        await page.goto('/contact.php');
        await page.fill('#nom', 'Client E2E');
        await page.fill('#email', 'client.e2e@example.com');
        await page.fill('#sujet', 'Question test');
        // Le délai anti-spam minimal (3s) doit être respecté par un humain normal.
        await page.waitForTimeout(3200);
        await page.fill('#message', 'Ceci est un message de test envoyé par la suite automatisée.');
        await page.click('button.btn-submit');
        await page.waitForLoadState('networkidle');
        await expect(page.locator('.message.success, .success')).toBeVisible();
    });

    test('remplir le champ piège (honeypot) ne crée aucun message', async ({ page }) => {
        await page.goto('/contact.php');
        await page.fill('#nom', 'Bot E2E');
        await page.fill('#email', 'bot.e2e@example.com');
        await page.fill('#sujet', 'Spam');
        await page.fill('#message', 'Achetez des montres pas chères');
        // Champ honeypot normalement invisible : on le remplit directement par son id.
        await page.fill('#hp_website', 'http://spam.example.com');
        await page.click('button.btn-submit');
        await page.waitForLoadState('networkidle');
        // Le bot reçoit quand même un message de succès (silencieux), pour ne pas
        // lui révéler qu'il a été bloqué.
        await expect(page.locator('.message.success, .success')).toBeVisible();

        // Vérifie côté admin qu'aucun message "Bot E2E" n'a été enregistré.
        const adminPage = await page.context().newPage();
        await loginAsAdmin(adminPage);
        await adminPage.goto('/administration.php');
        await adminPage.click('button[data-tab="messages"]');
        const tabText = await adminPage.locator('#tab-messages').textContent();
        expect(tabText).not.toContain('Bot E2E');
        await adminPage.close();
    });

    test('le formulaire RDV accepte une soumission valide', async ({ page }) => {
        await page.goto('/rdv.php');
        await page.fill('#nom', 'Client');
        await page.fill('#prenom', 'RDV');
        await page.fill('#email', 'rdv.e2e@example.com');
        await page.fill('#telephone', '0600000003');
        await page.selectOption('#service', 'Diagnostic');
        const demain = new Date(Date.now() + 86400000).toISOString().slice(0, 10);
        await fillHiddenDate(page, '#date', demain);
        await page.waitForTimeout(3200);
        await page.click('button.btn-submit');
        await page.waitForLoadState('networkidle');
        await expect(page.locator('.message.success')).toBeVisible();
    });
});
