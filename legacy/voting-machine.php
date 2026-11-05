<?php

namespace ddhf\voting;

require_once 'classes/VotingSystem.php';
use ddhf\voting\classes\VotingSystem;

$voting_system = new VotingSystem();
$voting_system->setTokenAndUser($post['token']);

if (!$voting_system->hasAlreadyVoted() && (!empty($post['poll']) || $post['poll'] === '0')
    && htmlspecialchars($voting_system->getPollQuestion()) == htmlspecialchars($post['poll-question'])) {
    $voting_system->saveResult($post);
}

?>
<header>
    <img alt="DDHF Logo" src="img/ddhf-logo.jpg" class="ddhf-logo">
    <h1>Wahl-O-Mat</h1>
    <p>Willkommen, <strong><?php echo $voting_system->getUsername() ?>!</strong></p>
</header>
<main>
    <aside class="reload">
        <form action="index.php" method="post" id="refresh">
            <input type="hidden" name="token" value="<?php echo $voting_system->getToken() ?>">
            <input type="submit" value="Seite neu laden">
        </form>
    </aside>
    <section class="information">
        <h2>Informationen</h2>
        <p>
            Wir befinden uns im <?php echo $voting_system->getSettings('voting-mode') == 'secret' ?
                'geheimen Wahlmodus. Das bedeutet, dass die Ergebnisse ohne identifizierende Merkmale 
                zwischengespeichert werden' :
                'öffentlichen Wahlmodus. Das bedeutet, dass die Ergebnisse zusammen mit dem Namen der Abstimmenden
                zwischengespeichert werden' ?>.
        </p>
        <p>Du hast/Sie haben <strong><?php echo $voting_system->getUserVotes() . ($voting_system->getUserVotes() == 1 ?
                    ' Stimme' :
                        ' Stimmen') ?></strong>. </p>
    </section>
    <section class="voting-area">
        <h2>Abstimmungen</h2>
        <?php
        $settings = $voting_system->getSettings();

        if ($settings['active'] == 'true' && $voting_system->getPollQuestion()) {

            // display the question
            echo '<p><strong>' . $voting_system->getPollQuestion() . '</strong></p>';

            // handle vote for new presidium
            if ($voting_system->isPresidiumVote()) {
                if (!$voting_system->hasAlreadyVoted()):

                $candidates_groups = $voting_system->getCandidates();
                ?>
                <form action="index.php" method="post" id="poll">
                    <input type="hidden" name="token" value="<?php echo $voting_system->getToken() ?>">
                    <input type="hidden" name="submit-poll" value="true">
                    <input type="hidden" name="poll" value="presidium">
                    <input type="hidden" name="poll-question" value="<?php echo htmlspecialchars($voting_system->getPollQuestion()) ?>">
                    <?php foreach ($candidates_groups as $group => $candidates): ?>
                    <fieldset>
                        <legend><?php echo $group ?></legend>
                        <?php foreach ($candidates as $candidate): ?>
                        <?php $id = str_replace(' ', '', $candidate['name'] . $candidate['club']) ?>
                        <div>
                            <input type="radio" name="<?php echo $candidate['code'] ?>" value="<?php echo $candidate['name'] ?>" id="<?php echo $id ?>">
                            <label for="<?php echo $id ?>"><?php echo $candidate['name'] . ' (' . $candidate['club'] . ')' ?></label>
                        </div>

                        <?php endforeach; ?>
                        <div>
                            <input type="radio" name="<?php echo $candidates[0]['code'] ?>" value="abstention" id="abstention" checked>
                            <label for="abstention">Enthaltung</label>
                        </div>
                    </fieldset>
                    <?php endforeach; ?>

                    <input type="submit" value="Abschicken">
                </form>
                <?php

                else:

                    // results if voted
                    if ($settings['voting-mode'] == 'secret') {
                        echo '<p>Du hast/Sie haben bereits abgestimmt. Sollte dabei ein Fehler unterlaufen sein, bitte melden, dann muss der Wahlvorgang wiederholt werden.</p>';
                    } else {

                        $result = $voting_system->getResultByToken($voting_system->getToken());
                        $result = str_replace([':::', PHP_EOL], [': ', '<br>'], $result);

                        echo '<p>Du hast/Sie haben wie folgt abgestimmt:</p>';
                        echo '<p>' . $result . '</p>';
                    }
                endif;

            }
            else {
                if (!$voting_system->hasAlreadyVoted()):
                    ?>
                    <form action="index.php" method="post" id="poll">
                        <input type="hidden" name="token" value="<?php echo $voting_system->getToken() ?>">
                        <input type="hidden" name="submit-poll" value="true">
                        <input type="hidden" name="poll-question" value="<?php echo htmlspecialchars($voting_system->getPollQuestion()) ?>">
                        <div>
                            <input type="radio" name="poll" value="2" id="pro">
                            <label for="pro">Dafür</label>
                        </div>
                        <div>
                            <input type="radio" name="poll" value="1" id="contra">
                            <label for="contra">Dagegen</label>
                        </div>
                        <div>
                            <input type="radio" name="poll" value="0" id="abstention">
                            <label for="abstention">Enthaltung</label>
                        </div>
                        <input type="submit" value="Abschicken">
                    </form>
                <?php
                else: {
                    if ($settings['voting-mode'] == 'secret') {
                        echo '<p>Du hast/Sie haben bereits abgestimmt. Sollte dabei ein Fehler unterlaufen sein, bitte melden, dann muss der Wahlvorgang wiederholt werden.</p>';
                    } else {
                        switch ($voting_system->getResultByToken($voting_system->getToken())) {
                            case 0:
                                $voted_for = '<i>Enthaltung</i>';
                                break;
                            case 1:
                                $voted_for = '<i>Dagegen</i>';
                                break;
                            case 2:
                                $voted_for = '<i>Dafür</i>';
                                break;
                            default:
                                // should not happen unless switch from private to public without deleting
                                $voted_for = '<i>Unbekannt</i>';
                                break;
                        }
                        echo 'Du hast/Sie haben mit ' . $voted_for . ' gestimmt. ';
                    }
                }
                endif;
            }

        } else {
            echo '<p><strong>Aktuell keine Abstimmung aktiv.</strong></p>';
        }
        ?>
    </section>
</main>