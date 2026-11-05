<?php

namespace ddhf\voting;

require_once 'classes/MemberList.php';
require_once 'classes/VotingSystem.php';
use ddhf\voting\classes\VotingSystem;
use ddhf\voting\classes\MemberList;

$voting_system = new VotingSystem();
?>
<!doctype html>
<html lang="de-DE">
    <head>
        <title>Wahl-O-Mat Ergebnisse</title>
        <meta charset="utf-8">
        <link rel="stylesheet" href="css/default.css">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="robots" content="noindex,nofollow">
    </head>
    <body>
    <header>
        <img src="img/ddhf-logo.jpg" class="ddhf-logo">
        <h1>Wahl-O-Mat Ergebnisse</h1>
    </header>
    <main>
        <aside class="reload">
            <form action="results.php" method="post" id="refresh">
                <input type="submit" value="Seite neu laden">
            </form>
        </aside>
        <section class="results">
            <h2>Resultat</h2>
            <?php if ($voting_system->getSettings('active') == 'false'): ?>
            <p>Aktuell ist keine Abstimmung aktiv.</p>
            <?php else:
                echo '<p>Abstimmung: » <i>' . $voting_system->getPollQuestion() . '</i> «</p>';
                $not_voted = $voting_system->getUsersWithoutVoteCast();
                echo empty($not_voted) ? '<p style="font-weight: bold;">Die Abstimmung ist beendet.</p>' : '<p>Noch nicht abgestimmt haben:</p>';
                if (!empty($not_voted)):
                ?>
            <ul>
                <?php
                $users = new MemberList();
                    foreach ($not_voted as $token) {
                        echo '<li>' . $users->getUserByToken($token)['name'] . '</li>';
                    }
                ?>
            </ul>
            <?php endif;
                    if ($voting_system->getSettings('voting-mode') == 'public' || $voting_system->getNumberOfVotersFinished() == $voting_system->getNumberOfParticipants()): ?>
                <p>Es sind <?php echo $voting_system->getNumberOfParticipants() ?> Wahlberechtigte vertreten, mit insgesamt <?php echo $voting_system->getMaxVotes() ?> Stimmen.</p>
                <?php if (!$voting_system->isPresidiumVote()): ?>
                    <ul>
                        <?php
                        $vote_wording = [
                            'pro' => ' Stimme(n) dafür',
                            'contra' => ' Stimme(n) dagegen',
                            'abstention' => ' Enthaltung(en)'
                        ];
                        foreach($voting_system->getResultsSorted() as $i => $result) {
                            echo '<li>' . $result . ($result != 1 ? $i == '' : '') . $vote_wording[$i] . '.</li>';
                        }
                        ?>
                        <li style="margin-top: 10px;">Es wurden <?php echo $voting_system->getNumberOfVotesCast() ?> von <?php echo $voting_system->getMaxVotes() ?> Stimmen abgegeben.</li>
                    </ul>
                <?php else:
                        $translation = [
                            'president' => 'Präsident:in',
                            'vicesport' => 'Vizepräsident:in Sport und Sportentwicklung',
                            'viceeducation' => 'Vizepräsident:in Forschung und Bildung',
                            'vicefinances' => 'Vizepräsident:in Wirtschaft und Finanzen',
                            'active' => 'Aktivenvertreter:in',
                            'international' => 'Beauftragte:r für internat. Kommunikation',
                            'youth' => 'Jugendbeauftragte:r',
                            'equality' => 'Gleichstellungsbeauftragte:r'
                        ];
                        foreach ($voting_system->getResultsPresidiumSorted() as $k => $item): ?>
                            <table>
                                <tr>
                                    <th><?php echo $translation[$k]; ?></th>
                                </tr>
                                <?php
                                foreach ($item as $j => $i) {
                                    $j = $j === 'abstention' ? 'Enthaltung' : $j;
                                    ?>
                                    <tr>
                                        <td><?php echo $j . ': ' . $i . ' Stimme(n)'  ?></td>
                                    </tr>
                                    <?php
                                }
                                ?>
                            </table>
                        <?php endforeach;
                 endif; ?>
            <?php endif; ?>
            <?php

                if ($voting_system->getSettings('voting-mode') == 'public'):
                    $detailed_result = $voting_system->getResultDetailsSorted();
                    if (!$voting_system->isPresidiumVote()):
                    ?>
                    <h2>Detailergebnis</h2>
                    <table>
                        <tr>
                            <th>Name</th>
                            <th>Stimmen</th>
                            <th>Abgestimmt mit</th>
                        </tr>
                        <?php
                        $wording = [
                            0 => 'Enthaltung',
                            1 => 'Dagegen',
                            2 => 'Dafür'
                        ];
                        foreach ($detailed_result as $item):
                            ?>
                            <tr>
                                <td><?php echo $item[0] ?></td>
                                <td><?php echo $item[1] ?></td>
                                <td><?php echo $wording[$item[2]] ?></td>
                            </tr>

                        <?php
                        endforeach;
                        ?>
                    </table>
                <?php
                    else:
                        $detailed_result = $voting_system->getResultsPresidium();
                        ?>
                        <h2>Detailergebnis</h2>
                        <table style="width:100%">
                            <?php

                            foreach ($detailed_result as $item):
                                ?>
                                <tr>
                                    <td style="padding-top:20px;font-weight: bold">
                                        <?php echo $item['name'] . ' (' . $item['votes'] . ' Stimme(n))'  ?><br><br>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="line-height: 1.5; "><?php echo str_replace(':::', ': ', implode('<br>', $item['voted_for'])) ?></td>
                                </tr>

                            <?php
                            endforeach;
                            ?>
                        </table>
            <?php
                    endif;
                endif;
            endif;
            ?>

        </section>
    </main>
    <footer>
        <address>
            Deutscher Dachverband für historisches Fechten e.V.<br>
            Fuhlsbüttler Straße 472<br>
            22309 Hamburg<br>
        </address>
        <div id="imprint">
            <a href="https://www.ddhf.de/impressum/" target="_blank">Impressum</a> - <a href="https://www.ddhf.de/datenschutz/" target="_blank">Datenschutz</a>
        </div>
    </footer>
    </body>
</html>