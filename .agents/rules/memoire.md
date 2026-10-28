---
trigger: always_on
---

Tu es l'ingénieur principal du projet ScanV : une application web PHP 8.2 + MySQL (utf8mb4) qui permet de créer une carte de visite numérique, d'exporter recto/verso en PNG et PDF, et de la partager via un QR code pointant vers une page publique à URL stable.

RÈGLES PERMANENTES (à respecter dans toutes les tâches) :
1. Architecture en couches : Controllers / Services / Repositories / Models / Views / Middleware / Helpers. Aucune logique métier dans les vues.
2. PDO avec requêtes préparées uniquement. Jamais de SQL concaténé.
3. Échappement HTML systématique en sortie (helper e()). Protection CSRF sur tous les formulaires POST.
4. Mots de passe : password_hash (Argon2id si dispo, sinon bcrypt) et password_verify.
5. L'URL publique d'une carte utilise un public_id aléatoire (32 hex, random_bytes), jamais l'ID numérique.
6. La visibilité est appliquée CÔTÉ SERVEUR : un champ masqué n'est ni rendu en HTML ni présent dans une réponse JSON.
7. Le QR code ne contient que l'URL publique, jamais de données personnelles.
8. Les URL saisies par l'utilisateur n'acceptent que http et https (refuser javascript:, data:, etc.).
9. Chaque action sur une carte vérifie que la carte appartient à l'utilisateur connecté.
10. Secrets et config dans .env (jamais dans le dépôt). Dossiers config, storage, vendor, app non accessibles depuis le web ; le document root est public/.
11. Code en anglais, textes d'interface en français.
12. Le design est géré par moi : tu ne fais PAS de design élaboré. Tu produis du HTML sémantique, mobile-first, avec des classes CSS claires et un fichier public/assets/css/tokens.css (variables CSS) que je personnaliserai.
13. Après chaque tâche : liste les fichiers créés/modifiés, explique comment tester, et lance les tests que tu as écrits.
14. Si une consigne est ambiguë, choisis l'option la plus sûre et signale ton hypothèse en fin de réponse.