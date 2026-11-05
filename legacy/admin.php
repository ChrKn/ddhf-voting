<?php

namespace ddhf\voting\admin;

require_once 'classes/MemberList.php';
require_once 'classes/VotingSystem.php';
use ddhf\voting\classes\VotingSystem;

// set new question
if (!empty($post['new-question'])) {
    $voting_system = new VotingSystem();
    $voting_system->setPollQuestion($post['new-question']);
}

// delete results of last question
if (!empty($post['delete-data'])) {
    $voting_system = new VotingSystem();
    $voting_system->deletePollData();
}

// set new settings
if (!empty($post['settings'])) {
    $voting_system = new VotingSystem();
    $voting_system->setSettings([
        'active' => $post['active'],
        'voting-mode' => $post['voting-mode']
    ]);
}

// modify users
if (!empty($post['modify-users'])) {
    $voting_system = new VotingSystem();
    $voting_system->modifyUsers($post);
}

// add users
if (!empty($post['add-new-user'])) {
    $voting_system = new VotingSystem();
    $voting_system->addUser($post['user-token'], $post['user-name'], $post['user-votes']);
}

// add multiple new tokens
if (!empty($post['add-new-users'])) {
    $voting_system = new VotingSystem();
    $voting_system->addUsers($post['number-of-users']);
}

// delete all users
if (!empty($post['delete-all-users'])) {
    $voting_system = new VotingSystem();
    $voting_system->deleteUsers();
}
// set up the voting system, register the admin-user and get the settings
$voting_system = new VotingSystem();
$voting_system->setTokenAndUser($post['token']);
$voting_settings = $voting_system->getSettings();


?>
<header>
    <img alt="DDHF Logo" src="img/ddhf-logo.jpg" class="ddhf-logo">
    <h1>Wahl-O-Mat Administration</h1>
</header>
<main>
    <aside class="reload">
        <form action="index.php" method="post" id="refresh">
            <input type="hidden" name="token" value="<?php echo $voting_system->getToken() ?>">
            <input type="submit" value="Seite neu laden">
        </form>
    </aside>
    <section class="admin-area">
        <h2>Abstimmung</h2>
        <p>Aktuelle Abstimmung: <i><?php echo $voting_system->getPollQuestion() ?></i></p>
        <form action="index.php" method="post" id="new-question" class="admin">
            <input type="hidden" name="token" value="<?php echo $voting_system->getToken() ?>">
            <div>
                <label for="new-question">Neue Abstimmungsfrage eintragen</label>
                <input type="text" name="new-question" id="new-question">
            </div>
            <input type="submit" value="Abschicken">
        </form>

        <h2 class="admin">Daten der aktuellen Umfrage löschen</h2>
        <p>Das setzt die aktuelle Abstimmung zurück, sodass jeder neu antworten kann oder startet eine neu eingestellte Frage.</p>
        <p>
            Abgestimmt haben: <?php echo $voting_system->getNumberOfVotersFinished() . '/' . $voting_system->getNumberOfParticipants(); ?>
            <br>
            Abgegebene Stimmen: <?php echo $voting_system->getNumberOfVotesCast() . '/' . $voting_system->getMaxVotes() ?>
        </p>

        <form action="index.php" method="post" id="delete-data" class="admin">
            <input type="hidden" name="token" value="<?php echo $voting_system->getToken() ?>">
            <input type="hidden" name="delete-data" value="true">
            <input type="submit" value="Jetzt löschen">
        </form>

        <h2 class="admin">Abstimmungseinstellungen</h2>

        <form action="index.php" method="post" id="settings" class="admin">
            <input type="hidden" name="token" value="<?php echo $voting_system->getToken() ?>">
            <input type="hidden" name="settings" value="true">
            <p>Umfrage aktiv?</p>
            <div>
                <input type="radio" name="active" id="active" value="true" <?php echo $voting_settings['active'] == 'true' ? 'checked' : '' ?>>
                <label for="active">ja</label>
            </div>
            <div>
                <input type="radio" name="active" id="inactive" value="false" <?php echo $voting_settings['active'] == 'false' ? 'checked' : '' ?>>
                <label for="inactive">nein</label>
            </div>
            <p>Umfragemodus setzen.</p>
            <div>
                <input type="radio" name="voting-mode" id="public" value="public" <?php echo $voting_settings['voting-mode'] == 'public' ? 'checked' : '' ?>>
                <label for="public">öffentlich</label>
            </div>
            <div>
                <input type="radio" name="voting-mode" id="secret" value="secret" <?php echo $voting_settings['voting-mode'] == 'secret' ? 'checked' : '' ?>>
                <label for="secret">geheim</label>
            </div>
            <input type="submit" value="Einstellungen speichern">
        </form>

        <h2 class="admin">Einen neuen Benutzer hinzufügen</h2>
        <form action="index.php" method="post" id="add-new-user" class="admin">
            <input type="hidden" name="token" value="<?php echo $voting_system->getToken() ?>">
            <input type="hidden" name="add-new-user" value="true">
            <div>
                <label for="user-token">Token</label>
                <input type="text" name="user-token" id="user-token" value="<?php echo $voting_system->createToken() ?>">
            </div>
            <div>
                <label for="user-name"> Name</label>
                <input type="text" name="user-name" id="user-name">
            </div>
            <div>
                <label for="user-votes">Anzahl Stimmen</label>
                <input type="number" name="user-votes" id="user-votes" value="1">
            </div>
            <input type="submit" value="Nutzer hinzufügen">
        </form>

        <h2 class="admin">Mehrere Token für Benutzer generieren</h2>
        <form action="index.php" method="post" id="add-new-users" class="admin">
            <input type="hidden" name="token" value="<?php echo $voting_system->getToken() ?>">
            <input type="hidden" name="add-new-users" value="true">
            <div>
                <label for="user-token">Anzahl</label>
                <input type="number" name="number-of-users" id="number-of-users" value="0">
            </div>
            <input type="submit" value="Benutzer anlegen">
        </form>

        <h2 class="admin">Vorhandene Benutzer bearbeiten</h2>
        <form action="index.php" method="post" id="modify-users" class="admin">
            <input type="hidden" name="token" value="<?php echo $voting_system->getToken() ?>">
            <input type="hidden" name="modify-users" value="true">
            <?php
            $users = $voting_system->getUsers();
            foreach ($users as $i => $user):
                if (!empty($user[0])):
            ?>
            <div class="user">
                <div class="modify-user">
                    <?php echo $user[1] != $voting_system->getSettings('admin') ? '<label for="user-token-' . $user[0] . '">Token</label>' : ''; ?>
                    <input type="<?php echo $user[1] != $voting_system->getSettings('admin') ? 'text' : 'hidden' ?>" name="user-token-<?php echo $user[0] ?>" id="user-token-<?php echo $user[0] ?>" value="<?php echo $user[0] ?>">
                    <input type="hidden" name="token-reference-<?php echo $user[0] ?>" value="<?php echo $user[0] ?>">
                </div>
                <div class="modify-user">
                    <?php echo $user[1] != $voting_system->getSettings('admin') ? '<label for="user-name-' . $user[0] . '"> Name</label>' : ''; ?>
                    <input type="<?php echo $user[1] != $voting_system->getSettings('admin') ? 'text' : 'hidden' ?>" name="user-name-<?php echo $user[0] ?>" id="user-name-<?php echo $user[0] ?>" value="<?php echo $user[1] ?>">
                </div>
                <div class="modify-user">
                    <?php echo $user[1] != $voting_system->getSettings('admin') ? '<label for="user-votes-' . $user[0] . '">Anzahl Stimmen</label>' : ''; ?>
                    <input type="<?php echo $user[1] != $voting_system->getSettings('admin') ? 'number' : 'hidden' ?>" name="user-votes-<?php echo $user[0] ?>" id="user-votes-<?php echo $user[0] ?>" value="<?php echo trim($user[2]) ?>">
                </div>
            </div>
            <?php
                if ($user[1] != $voting_system->getSettings('admin')):
            ?>
            <div style="border-bottom: 1px solid black; padding-bottom: 5px;">
                <input type="checkbox" name="delete-user-<?php echo $user[0] ?>" id="delete-user-<?php echo $user[0] ?>">
                <label for="delete-user-<?php echo $user[0] ?>">löschen</label>
            </div>
            <?php
                endif;
            endif;
            endforeach;
            ?>
            <input type="submit" value="Datensätze aktualisieren">
        </form>

<!--        <h2 class="admin">Alle Benutzer löschen</h2>-->
<!--        <p>Ja, wirklich! Es gibt keine Sicherheitsabfrage. Ist zum Aufräumen gedacht oder falls viel zu viele angelegt wurden. Der Adminbenutzer ist davon nicht betroffen.</p>-->
<!--        <form action="index.php" method="post" id="delete-all" class="admin">-->
<!--            <input type="hidden" name="token" value="--><?php //echo $voting_system->getToken() ?><!--">-->
<!--            <input type="hidden" name="delete-all-users" value="true">-->
<!--            <input type="submit" value="Alle Benutzer löschen">-->
<!--        </form>-->
    </section>
</main>