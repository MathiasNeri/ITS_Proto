const { test, expect } = require('@playwright/test');

test.describe('Boutique et fiche produit', () => {
    test('la boutique liste des produits du catalogue de démo', async ({ page }) => {
        await page.goto('/boutique.php');
        const cards = page.locator('.shop-card');
        expect(await cards.count()).toBeGreaterThan(0);
    });

    test('le filtre par catégorie réduit les résultats', async ({ page }) => {
        await page.goto('/boutique.php');
        // Les cartes filtrées restent dans le DOM (display:none) : on compare
        // les cartes effectivement visibles, pas le total dans le DOM.
        const totalAvant = await page.locator('.shop-card:visible').count();
        await page.click('.chip:has-text("Téléphones")');
        await page.waitForTimeout(200);
        const totalApres = await page.locator('.shop-card:visible').count();
        expect(totalApres).toBeLessThan(totalAvant);
        expect(totalApres).toBeGreaterThan(0);
    });

    test('la recherche filtre par nom de produit', async ({ page }) => {
        await page.goto('/boutique.php');
        await page.fill('#searchInput', 'iPhone');
        await page.waitForTimeout(200);
        // Les cartes filtrées restent dans le DOM (display:none) : on ne
        // regarde que celles effectivement visibles.
        const noms = await page.locator('.shop-card:visible .shop-name').allTextContents();
        expect(noms.length).toBeGreaterThan(0);
        for (const nom of noms) {
            expect(nom.toLowerCase()).toContain('iphone');
        }
    });

    test('la fiche produit affiche le prix et permet l\'ajout au panier', async ({ page }) => {
        await page.goto('/produit.php?id=1');
        await expect(page.locator('.product-price')).toBeVisible();
        await expect(page.locator('.product-add-btn')).toBeEnabled();
    });

    test('une donnée structurée Product valide est présente sur la fiche produit', async ({ page }) => {
        await page.goto('/produit.php?id=1');
        const scripts = await page.locator('script[type="application/ld+json"]').allTextContents();
        const produitSchema = scripts.map(s => JSON.parse(s)).find(d => d['@type'] === 'Product');
        expect(produitSchema).toBeTruthy();
        expect(produitSchema.offers.priceCurrency).toBe('EUR');
    });

    test('un produit inexistant affiche un message adapté', async ({ page }) => {
        await page.goto('/produit.php?id=999999');
        await expect(page.locator('.not-found')).toBeVisible();
    });
});
