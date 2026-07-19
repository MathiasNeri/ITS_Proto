// Petites fonctions réutilisées par plusieurs fichiers de tests, pour
// éviter de dupliquer les mêmes séquences (connexion, ajout au panier...).

const ADMIN_EMAIL = 'admin@its-reparation.fr';
const ADMIN_PASSWORD = 'admin123';

async function loginAsAdmin(page) {
    await page.goto('/connexion.php');
    await page.fill('#email', ADMIN_EMAIL);
    await page.fill('#password', ADMIN_PASSWORD);
    await page.click('button.btn-login');
    await page.waitForLoadState('networkidle');
}

async function registerNewUser(page, { nom, prenom, email, password }) {
    await page.goto('/inscription.php');
    await page.fill('#nom', nom);
    await page.fill('#prenom', prenom);
    await page.fill('#email', email);
    await page.fill('#password', password);
    await page.fill('#confirm_password', password);
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');
}

async function login(page, email, password) {
    await page.goto('/connexion.php');
    await page.fill('#email', email);
    await page.fill('#password', password);
    await page.click('button.btn-login');
    await page.waitForLoadState('networkidle');
}

function uniqueEmail(prefix) {
    return prefix + '.' + Date.now() + '.' + Math.floor(Math.random() * 10000) + '@example.com';
}

// Le champ de date des formulaires RDV est transformé en <input type="hidden">
// par flatpickr (la saisie visible passe par un champ compagnon) : Playwright
// refuse de "remplir" un champ caché, donc on fixe sa valeur directement,
// qui est celle réellement envoyée au serveur au format ISO (AAAA-MM-JJ).
async function fillHiddenDate(page, selector, isoValue) {
    await page.locator(selector).evaluate((el, val) => {
        el.value = val;
        el.dispatchEvent(new Event('input', { bubbles: true }));
        el.dispatchEvent(new Event('change', { bubbles: true }));
    }, isoValue);
}

module.exports = { ADMIN_EMAIL, ADMIN_PASSWORD, loginAsAdmin, registerNewUser, login, uniqueEmail, fillHiddenDate };
