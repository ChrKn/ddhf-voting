<?php

namespace ddhf\voting;

require_once 'classes/VotingSystem.php';
require_once 'classes/MemberList.php';
use ddhf\voting\classes\MemberList;
use ddhf\voting\classes\VotingSystem;

?>
<!doctype html>
<html lang="de-DE">
<head>
    <title>DDHF Wahl-O-Mat</title>
    <meta charset="utf-8">
    <link rel="stylesheet" href="css/default.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
</head>
<body>
<?php
// set the system up by setting the admin-user, if it does not exist
$voting_system = new VotingSystem();

$post = VotingSystem::sanitizePostData($_POST);

if (!empty($post['admin-setup']) && $voting_system->getSettings('admin') == '') {
    if(empty($voting_system->getSettings('admin'))) {
        $voting_system->setAdmin($post['token'], $post['username']);
    }
}

if (empty($voting_system->getSettings('admin'))) {
    // no admin is set: get the setup
    include 'setup.php';
} elseif (empty($post['token'])) {
    // no token: get login
    include 'login.php';
} elseif ($post['token']) {
    // if a token is given we have to differentiate between admin and normal users
    $userlist = new MemberList();
    if ($userlist->isAdmin($post['token'])) {
        // token is admin: get admin
        include 'admin.php';
    } elseif ($userlist->isUser($post['token'])) {
        // token is user: get voting overlay
        include 'voting-machine.php';
    } else {
        // the token does not exist
        include 'oops.php';
    }
}  else {
    // does this case trigger? like ever? if so:
    // todo: get rid of that double else
    include 'oops.php';
}

?>
<footer>
    <address>
        Deutscher Dachverband für historisches Fechten e.V.<br>
        Fuhlsbüttler Straße 472<br>
        22309 Hamburg<br>
    </address>
    <div id="imprint">
        <a href="https://www.ddhf.de/impressum/" target="_blank">Impressum</a> -
        <a href="https://www.ddhf.de/datenschutz/" target="_blank">Datenschutz</a>
    </div>
</footer>
</body>
</html>
