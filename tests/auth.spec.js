const { test, expect } = require('@playwright/test');
const { registerNewUser, login, uniqueEmail } = require('./helpers');

test.describe('Authentification', () => {
    // Le champ mot de passe porte un pattern/minlength HTML5 : un navigateur
    // bloque déjà la soumission d'un mot de passe faible avant même d'envoyer
    // la requête. On vérifie donc les deux lignes de défense séparément :
    // la contrainte côté client, puis le rejet côté serveur (requête directe,
    // comme le ferait un client qui contournerait le formulaire).
    test('le champ mot de passe refuse un mot de passe trop court côté client', async ({ page }) => {
        await page.goto('/inscription.php');
        await page.fill('#password', 'abc123');
        const valide = await page.locator('#password').evaluate(el => el.checkValidity());
        expect(valide).toBe(false);
    });

    test('le serveur rejette un mot de passe sans chiffre même hors formulaire', async ({ page }) => {
        await page.goto('/inscription.php');
        const token = await page.locator('input[name="csrf_token"]').getAttribute('value');
        // page.request partage les cookies de session de la page (nécessaire
        // pour que la vérification CSRF côté serveur passe), contrairement au
        // fixture "request" indépendant.
        const response = await page.request.post('/inscription.php', {
            form: {
                csrf_token: token,
                nom: 'SansChiffre', prenom: 'Test', email: uniqueEmail('sanschiffre'),
                password: 'abcdefgh', confirm_password: 'abcdefgh',
            },
        });
        const body = await response.text();
        expect(body).toContain('lettre et un chiffre');
    });

    test('inscription avec un mot de passe valide réussit puis permet la connexion', async ({ page }) => {
        const email = uniqueEmail('valide');
        const password = 'MotDePasse8';
        await registerNewUser(page, { nom: 'Valide', prenom: 'Test', email, password });
        await expect(page.locator('.success')).toBeVisible();

        await login(page, email, password);
        await expect(page).toHaveURL(/accueil\.php|\/$/);
        await expect(page.locator('.user-link.active, .user-link:has-text("Profil")')).toBeVisible();
    });

    test('connexion avec un mauvais mot de passe échoue', async ({ page }) => {
        const email = uniqueEmail('mauvais');
        await registerNewUser(page, { nom: 'Mauvais', prenom: 'Test', email, password: 'MotDePasse8' });
        await login(page, email, 'MauvaisMotDePasse9');
        await expect(page.locator('.error')).toBeVisible();
    });

    test('la déconnexion termine la session', async ({ page }) => {
        const email = uniqueEmail('deconnexion');
        const password = 'MotDePasse8';
        await registerNewUser(page, { nom: 'Deco', prenom: 'Test', email, password });
        await login(page, email, password);
        await page.click('a:has-text("Déconnexion")');
        await page.waitForLoadState('networkidle');
        await page.goto('/panier.php');
        await page.goto('/commande.php');
        await expect(page).toHaveURL(/connexion\.php/);
    });
});
