const { test, expect } = require('@playwright/test');
const { loginAsAdmin } = require('./helpers');

test.describe('Panier et commande', () => {
    test('le panier vide invite à aller à la boutique', async ({ page }) => {
        await page.goto('/panier.php');
        await expect(page.locator('.cart-empty')).toBeVisible();
    });

    test('ajouter un produit au panier met à jour le badge et la liste', async ({ page }) => {
        await loginAsAdmin(page);
        await page.goto('/produit.php?id=1');
        await page.click('button.product-add-btn');
        await page.waitForLoadState('networkidle');

        await page.goto('/panier.php');
        await expect(page.locator('.cart-row')).toHaveCount(1);
        await expect(page.locator('.cart-badge')).toContainText('1');
    });

    test('supprimer un article vide le panier', async ({ page }) => {
        await loginAsAdmin(page);
        await page.goto('/produit.php?id=1');
        await page.click('button.product-add-btn');
        await page.waitForLoadState('networkidle');

        await page.goto('/panier.php');
        await page.click('button.cart-remove');
        await page.waitForLoadState('networkidle');
        await expect(page.locator('.cart-empty')).toBeVisible();
    });

    test('la commande exige d\'être connecté', async ({ page }) => {
        await page.goto('/commande.php');
        await expect(page).toHaveURL(/connexion\.php/);
    });

    test('parcours complet : retrait en boutique jusqu\'à la confirmation', async ({ page }) => {
        await loginAsAdmin(page);
        await page.goto('/produit.php?id=1');
        await page.click('button.product-add-btn');
        await page.waitForLoadState('networkidle');

        await page.goto('/commande.php');
        await expect(page.locator('select#boutique option')).toHaveText(['Pierrefeu']);

        await page.fill('#nom', 'Test E2E Boutique');
        await page.fill('#telephone', '0600000000');
        await page.click('button.btn-submit');
        await page.waitForLoadState('networkidle');
        // Mode simulation (pas de Stripe configuré dans les tests)
        await expect(page).toHaveURL(/paiement-simulation\.php/);
        await page.click('button.btn-pay');
        await page.waitForLoadState('networkidle');
        await expect(page.locator('body')).toContainText('Commande confirmée');
    });

    test('parcours complet : livraison Colissimo avec adresse structurée', async ({ page }) => {
        await loginAsAdmin(page);
        await page.goto('/produit.php?id=1');
        await page.click('button.product-add-btn');
        await page.waitForLoadState('networkidle');

        await page.goto('/commande.php');
        await page.click('button[value="colissimo"]');
        await page.waitForLoadState('networkidle');

        await page.fill('#nom', 'Test E2E Livraison');
        await page.fill('#telephone', '0600000001');
        await page.fill('#adresse_ligne1', '5 avenue des Tests');
        await page.fill('#code_postal', '83390');
        await page.fill('#ville', 'Pierrefeu-du-Var');
        await page.click('button.btn-submit');
        await page.waitForLoadState('networkidle');
        await expect(page).toHaveURL(/paiement-simulation\.php/);
    });
});
