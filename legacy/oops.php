<?php namespace ddhf\voting; ?>
<header>
    <img alt="Logo DDHF" src="img/ddhf-logo.jpg" class="ddhf-logo">
    <h1>Fehler beim Login</h1>
</header>
<main>
    <p>
        Entweder gab es hier ein <b>technisches Problem</b>, oder der von euch angegebene <b>Einwahlcode ist ungültig</b>.
        Versucht es einfach noch einmal oder meldet euch zu Wort, falls es partout nicht klappen will.
    </p>
    <form action="index.php" method="post">
        <div>
            <label for="token">Einwahlcode</label>
            <input type="password" id="token" name="token">
        </div>
        <div>
            <input type="submit" value="Zur Abstimmung">
        </div>
    </form>
</main>
