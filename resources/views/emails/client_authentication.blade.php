<!DOCTYPE html>
<html>
<head>
    <title>Vos informations d'authentification</title>
</head>
<body>
    <h1>Bienvenue {{ $client->nom }} {{ $client->prenom }}</h1>

    <p>Votre compte a été créé avec succès. Voici vos informations d'authentification :</p>

    <ul>
        <li><strong>Email :</strong> {{ $client->email }}</li>
        <li><strong>Mot de passe :</strong> {{ $password }}</li>
        <li><strong>Code d'authentification :</strong> {{ $client->nci }}</li>
    </ul>

    <p>Veuillez conserver ces informations en sécurité.</p>

    <p>Cordialement,<br>
    L'équipe de gestion bancaire</p>
</body>
</html>