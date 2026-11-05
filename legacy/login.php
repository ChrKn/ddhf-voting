<?php namespace ddhf\voting; ?>
<header>
    <img alt="Logo DDHF" src="img/ddhf-logo.jpg" class="ddhf-logo">
    <h1>Wahl-O-Mat Login</h1>
</header>
<main>
    <p>Bitten den Einwahlcode eingeben. Es geht dann direkt weiter.</p>
    <form action="index.php" method="post">
        <input type="hidden" name="setup-mode" value="true">
        <div>
            <label for="token">Einwahlcode</label>
            <input type="password" id="token" name="token">
        </div>
        <div>
            <input type="submit" value="Zur Abstimmung">
        </div>
    </form>
</main>
