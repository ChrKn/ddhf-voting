<?php namespace ddhf\voting; ?>
<header>
    <img alt="Logo DDHF" src="img/ddhf-logo.jpg" class="ddhf-logo">
    <h1>Wahl-O-Mat Setup</h1>
</header>
<main>
    <p style="text-align: center">Hier bitte den Adminbenutzer inklusive Einwahlcode festlegen.</p>
    <form action="index.php" method="post">
        <input type="hidden" name="admin-setup" value="true">
        <div>
            <label for="username">Nutzername (nur für interne Zwecke)</label>
            <input type="text" id="username" name="username" required>
        </div>
        <div>
            <label for="token">Einwahlcode (Bitte merken, wird nicht wieder angezeigt!)</label>
            <input type="text" id="token" name="token" required>
        </div>
        <div>
            <input type="submit" value="Weiter zum Adminbereich">
        </div>
    </form>
</main>
