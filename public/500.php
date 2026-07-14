<?php
// Page volontairement autonome (aucun require vers config.php/header.php) :
// si l'erreur vient de la config ou de la base, cette page doit rester servable.
http_response_code(500);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ITS - Erreur serveur</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, "Segoe UI", Arial, sans-serif;
            background: #202325;
            color: #ffffff;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        .card {
            text-align: center;
            max-width: 480px;
        }
        .code {
            font-size: 5rem;
            font-weight: 800;
            color: #e74c3c;
            line-height: 1;
            margin-bottom: .5rem;
        }
        h1 {
            font-size: 1.3rem;
            margin-bottom: .8rem;
        }
        p {
            color: #bdc3c7;
            line-height: 1.6;
            margin-bottom: 2rem;
        }
        a {
            display: inline-block;
            background: #e74c3c;
            color: #fff;
            text-decoration: none;
            font-weight: bold;
            padding: .9rem 1.8rem;
            border-radius: 6px;
        }
        a:hover {
            background: #c0392b;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="code">500</div>
        <h1>Une erreur inattendue est survenue</h1>
        <p>Le service rencontre un problème technique momentané. Merci de réessayer dans quelques instants.</p>
        <a href="/accueil.php">Retour à l'accueil</a>
    </div>
</body>
</html>
