// Supprime la base SQLite avant la campagne de tests : elle est recréée et
// re-remplie automatiquement (catalogue de démo, compte admin) par
// initDatabase() à la première requête PHP, garantissant un état connu et
// reproductible à chaque exécution (locale ou CI).
const fs = require('fs');
const path = require('path');

module.exports = async () => {
    const dbPath = path.join(__dirname, '..', 'database', 'its.sqlite');
    if (fs.existsSync(dbPath)) {
        fs.unlinkSync(dbPath);
    }
    const backupsDir = path.join(__dirname, '..', 'database', 'backups');
    if (fs.existsSync(backupsDir)) {
        fs.rmSync(backupsDir, { recursive: true, force: true });
    }
};
