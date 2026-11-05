<?php

namespace ddhf\voting\classes;

use FilesystemIterator;

class VotingSystem {

    /**
     * Represents a list of all eligible voters (members of DDHF present while
     * the voting takes place)
     *
     * @var MemberList
     */
    private MemberList $members;

    /**
     * The login-token that is used to identify an eligible voter and
     * grant access to the vote. The tokens need to be provided in
     * data/users.ddhf
     *
     * @var string
     */
    private $token;

    /**
     * The settings used for voting. Stored in data/settings.json
     * The keys are as follows:
     *
     * active: boolean
     * voting-mode: string 'secret'|'public'
     * admin: string admin username
     *
     * @var array
     */
    private array $settings;

    /**
     * The question currently polled on; it is stored in data/question.ddhf
     *
     * @var string
     */
    private $pollQuestion;


    /**
     * Represents a user that is either an admin or a person voting as
     * associative array with the keys 'name' and 'votes' (number of votes
     * the user has)
     *
     * @var array
     */
    private array $user;

    /**
     * The number of participants, that can vote
     *
     * @var int
     */
    private int $numberOfParticipants;

    /**
     * Number of votes, all participants have
     *
     * @var int
     */
    private $maxVotes;

    /**
     * Candidates for the
     *
     * @var array
     */
    private array $candidates;

    /**
     * VotingSystem constructor.
     */
    function __construct() {
        $this->settings = $this->getSettingsFromFile();
        $this->members = new MemberList();
        $this->pollQuestion = $this->getPollQuestionFromFile();
        $this->numberOfParticipants = $this->members->getNumberOfParticipants();
        $this->maxVotes = $this->members->getMaxVotes();
        $this->createFolders();
        $this->candidates = $this->getCandidatesFromFile();
    }

    /**
     * Returns the username (if any)
     *
     * @return string
     */
    public function getUsername() {
        return empty($this->user['name']) ? '' : $this->user['name'];
    }

    /**
     * Returns the number of votes the user has
     *
     * @return int
     */
    public function getUserVotes() {
        return (int) $this->user['votes'];
    }

    /**
     * Returns either a single setting or all of them as array;
     * expects the key of the settings array as parameter
     *
     * @param string $setting
     * @return array|string
     */
    public function getSettings($setting = '') {
        if (!$setting) {
            return $this->settings;
        }
        return $this->settings[$setting];
    }

    /**
     *
     *
     * @return string
     */
    public function getPollQuestion() {
        return $this->pollQuestion;
    }

    /**
     * @return array
     */
    private function getSettingsFromFile() {
        if (file_exists('data/settings.json')) {
            $settings = (array) json_decode(file_get_contents('data/settings.json'));
        }
        else {
            // default settings
            $settings = [
                'active'        => 'false',
                'voting-mode'   => 'secret',
                'admin'         => ''
            ];
            $file = fopen('data/settings.json', 'w') or die('Unable to open file!');
            fwrite($file, json_encode($settings));
            fclose($file);
        }
        return $settings;
    }

    public function setTokenAndUser($token) {
        $this->token = $token;
        $this->user = $this->members->getUserByToken($token, $this->settings['voting-mode']);
    }

    public function hasAlreadyVoted() {
        $filename = 'data/voted/' . $this->getToken();
        return file_exists($filename);
    }


    /**
     * @return mixed
     */
    public function getToken() {
        return $this->token;
    }


    private function getPollQuestionFromFile() {
        if (file_exists('data/question.ddhf')) {
            $question = file_get_contents('data/question.ddhf');
        }
        else {
            // todo create question file with default question
            $question = false;
        }
        return $question;
    }

    public function setPollQuestion($new_question) {
        $file = fopen("data/question.ddhf", "w") or die("Unable to open file!");
        fwrite($file, $new_question);
        fclose($file);

        $this->pollQuestion = $new_question;
    }

    public function saveResult($poll_result) {

        if ($poll_result['poll'] === 'presidium') {

            $results = [
                'president' => !empty($poll_result['president']) ? $poll_result['president'] : 0,
                'vicesport' => !empty($poll_result['vicesport']) ? $poll_result['vicesport'] : 0,
                'viceeducation' => !empty($poll_result['viceeducation']) ? $poll_result['viceeducation'] : 0,
                'vicefinances' => !empty($poll_result['vicefinances']) ? $poll_result['vicefinances'] : 0,
                'active' => !empty($poll_result['active']) ? $poll_result['active'] : 0,
                'international' => !empty($poll_result['international']) ? $poll_result['international'] : 0,
                'youth' => !empty($poll_result['youth']) ? $poll_result['youth'] : 0,
                'equality' => !empty($poll_result['equality']) ? $poll_result['equality'] : 0
            ];

            $result_string = '';
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

            foreach ($results as $function => $result) {
                // create text result
                if ($result !== 0) {
                    $result_string .= $translation[$function] . ':::' . str_replace('abstention', 'Enthaltung', $result) . PHP_EOL;

                    $club_public = $this->settings['voting-mode'] == 'public' ?
                        $this->members->getUserByToken($this->token, $this->settings['voting-mode'])['name'] :
                        '';

                    // write result;
                    $user_votes = $this->members->getUserByToken($this->token, $this->settings['voting-mode'])['votes'];
                    for ($i = 0; $i < $user_votes; $i++) {
                        $file = fopen("data/result/" . $this->generateRandomString(30), "w") or die("Unable to open file!");
                        fwrite($file, $result . PHP_EOL . $function . PHP_EOL . $club_public);
                        fclose($file);
                    }
                }
            }

            // disable further voting on this question for this person
            $file = fopen("data/voted/$this->token", "w") or die("Unable to open file!");
            fwrite($file, ($this->getSettings('voting-mode') == 'public' ? $result_string : 1));
            fclose($file);

        } else {
            $poll_result = $poll_result['poll'];

            $this->getSettingsFromFile();

            $result = '';

            // additional information only when voting publicly
            if ($this->settings['voting-mode'] == 'public') {
                $result = $poll_result;
            } elseif ($this->settings['voting-mode'] == 'secret') {
                $result = 'true';
            }

            // disable further voting
            $file = fopen("data/voted/$this->token", "w") or die("Unable to open file!");
            fwrite($file, $result);
            fclose($file);

            sleep(rand(0,1));

            // write result; 2 = pro; 1 = contra; 0 = abstention
            $user_votes = $this->members->getUserByToken($this->token, $this->settings['voting-mode'])['votes'];
            for ($i = 0; $i < $user_votes; $i++) {
                $file = fopen("data/result/" . $this->generateRandomString(30), "w") or die("Unable to open file!");
                fwrite($file, $poll_result);
                fclose($file);

                sleep(rand(0,1));
            }
        }
    }

    public function deletePollData() {
        $files_result = glob('data/result/*');
        $files_voted = glob('data/voted/*');

        $files = array_merge($files_result, $files_voted);

        foreach($files as $file){
            if(is_file($file)) {
                unlink($file);
            }
        }
    }

    public function setSettings(array $settings) {
        $current_settings = $this->settings;

        foreach ($settings as $i => $setting) {
            $current_settings[$i] = $setting;
        }

        $file = fopen("data/settings.json", "w") or die("Unable to open file!");
        fwrite($file, json_encode($current_settings));
        fclose($file);
        $this->settings = $this->getSettingsFromFile();
    }

    public function getNumberOfVotersFinished() {
        $fi = new FilesystemIterator('data/voted', FilesystemIterator::SKIP_DOTS);
        return iterator_count($fi);
    }

    public function getNumberOfVotesCast() {
        $fi = new FilesystemIterator('data/result', FilesystemIterator::SKIP_DOTS);
        return iterator_count($fi);
    }


    public function getResultsSorted() {
        $result = [
            'pro'           => 0,
            'contra'        => 0,
            'abstention'    => 0
        ];
        $directory = 'data/result/';
        $files = scandir($directory);

        foreach($files as $file) {
            if (!(substr($file, 0, 1) == '.')) {
                $file_content = file_get_contents($directory . $file);
                switch($file_content) {
                    case 2:
                        $result['pro'] = $result['pro'] + 1;
                        break;
                    case 1:
                        $result['contra'] = $result['contra'] + 1;
                        break;
                    case 0:
                        $result['abstention'] = $result['abstention'] + 1;
                        break;
                    default:
                        break;
                }

            }
        }

        arsort($result);
        return $result;
    }

    public function getResultsPresidiumSorted() {
        $directory = 'data/result/';
        $files = scandir($directory);
        $candidates = [];

        // prepare the candidates array for counting
        foreach ($this->candidates as $function => $candidate_list) {
            foreach ($candidate_list as $candidate) {
                $candidates[$candidate['code']][$candidate['name']] = 0;
            }
            $candidates[$candidate['code']]['abstention'] = 0;
        }

        // count the votes
        foreach($files as $file) {
            if (!(substr($file, 0, 1) == '.')) {
                $file_content = file_get_contents($directory . $file);
                $file_content = explode(PHP_EOL, $file_content);
                $candidates[$file_content[1]][$file_content[0]] += 1;
            }
        }
        return $candidates;
    }

    public function getResultByToken(string $getToken) {
        if ($this->settings['voting-mode'] == 'public') {
            return file_get_contents('data/voted/' . $this->token);
        }
        return -1;
    }

    public function getResultDetailsSorted(): array {
        $results = [];
        $directory = 'data/voted/';
        $files = scandir($directory);
        foreach($files as $file) {
            if (!(substr($file, 0, 1) == '.')) {
                $file_content = file_get_contents($directory . $file);
                $results[] = [
                    $this->members->getUserByToken($file, $this->settings['voting-mode'])['name'],
                    $this->members->getUserByToken($file, $this->settings['voting-mode'])['votes'],
                    $file_content
                ];
            }
        }

        return $results;
    }

    /*************************
    **    Helper-Methods    **
    *************************/

    /**
     * Generates a random string of variable length.
     *
     * @param int $length
     * @return string
     */
    private function generateRandomString(int $length = 10): string {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ_-+';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    /**
     * @return int
     */
    public function getNumberOfParticipants(): int {
        return $this->numberOfParticipants;
    }

    /**
     * @return int
     */
    public function getMaxVotes(): int {
        return $this->maxVotes;
    }

    // todo: this belongs into MemberList
    public function setAdmin(string $token, string $username) {
        $this->addUserToList($token, $username, 0);
        $this->setSettings(['admin' => $username]);
        $this->settings = $this->getSettingsFromFile();

        return $this->members->isAdmin($token);
    }

    // todo: this belongs into MemberList
    private function addUserToList(string $token, string $username, int $votes = 1) {
        // todo: add to list not just overwrite it. this is for admin only; return is garbage

        $members = file_get_contents('data/users.ddhf');
        $members = $members == '' ? '' : $members . PHP_EOL;

        $file = fopen("data/users.ddhf", "w") or die("Unable to open file!");
        fwrite($file, $members . $token . ';' . $username . ';' . $votes);
        fclose($file);

        return true;
    }

    public function addUser(string $token, string $username, int $votes = 1) {

        if ($token != '' && $votes != '') {

            // todo check if token already exists

            $members = file_get_contents('data/users.ddhf');
            $members = $members == '' ? '' : $members . PHP_EOL;
            $members .= $token . ';' . $username . ';' . $votes;

            $file = fopen("data/users.ddhf", "w") or die("Unable to open file!");
            fwrite($file, $members);
            fclose($file);

            // todo add check if user exists; if not an error message should appear.
             return true;
        }
        return false;
    }

    public function getUsers() {
        return $this->members->getUsers();
    }

    public function createToken() {
        // todo check if used first
        return $this->generateRandomString(8);
    }

    public function modifyUsers(array $post_data) {
        $users = '';

        foreach ($post_data as $i => $formdata) {
            if ($i != 'token' && $i != 'modify-users' && strpos($i, 'token-reference') === 0) {

                $token = $formdata;
                $new_token = $post_data['user-token-' . $token];
                $name = $post_data['user-name-' . $token];
                $votes = $post_data['user-votes-' . $token];

                // only write user if it should not be deleted
                if (empty($post_data['delete-user-' . $token])) {
                    $users .= ($users == '' ? '' : PHP_EOL) . $new_token . ';' . $name . ';' . $votes;
                }
            }
        }

        if ($users == '') {
            // todo: error
            return false;
        } else {
            // write into file
            $file = fopen("data/users.ddhf", "w") or die("Unable to open file!");
            fwrite($file, $users);
            fclose($file);
        }

        // todo return proper value
        return true;
    }

    public function addUsers($number_of_tokens) {
        $users = file_get_contents('data/users.ddhf');
        for ($i = 0; $i < $number_of_tokens;$i++ ) {
            $users .= PHP_EOL . $this->generateRandomString(8) . ';' . '' . ';' . '1';
        }
        if ($users != '') {
            $file = fopen("data/users.ddhf", "w") or die("Unable to open file!");
            fwrite($file, $users);
            fclose($file);
        }
    }

    public function getUsersWithoutVoteCast() {
        $directory = 'data/voted/';
        $token_voted = scandir($directory);
        $all_token = [];
        $has_voted = [];

        // get tokens from all users (except admin)
        foreach ($this->getUsers() as $user) {
            if ($user[1] != $this->getSettings('admin'))
            $all_token[] = $user[0];
        }
        // get tokens of everyone who has voted
        foreach($token_voted as $token_voted_item) {
            if (!(substr($token_voted_item, 0, 1) == '.')) {
                $has_voted[] = $token_voted_item;
            }
        }
        $result = array_diff($all_token, $has_voted);
        arsort($result);

        return $result;
    }

    public function deleteUsers() {
        $users = file_get_contents('data/users.ddhf');
        $users = explode(PHP_EOL, $users);
        $users = $users[0];
        $file = fopen("data/users.ddhf", "w") or die("Unable to open file!");
        fwrite($file, $users);
        fclose($file);
    }

    /**
     * @return array
     */
    public function getCandidates(): array {
        return $this->candidates;
    }

    public function getResultsPresidium() {
        $directory = 'data/voted/';
        $files = scandir($directory);
        $results = [];

        foreach($files as $file) {

            if (!(substr($file, 0, 1) == '.')) {
                $file_content = trim(file_get_contents($directory . $file));
                $results[$file] = [
                    'name'  => $this->members->getUserByToken($file)['name'],
                    'votes' => $this->members->getUserByToken($file)['votes'],
                    'voted_for' => explode(PHP_EOL, $file_content)
                ];
            }
        }
        return $results;
    }

    private function isTokenUsed($token) {

        return true;
    }

    private function createFolders() {
        if (!file_exists('data/result')) {
            mkdir('data/result', 0755, true);
        }
        if (!file_exists('data/voted')) {
            mkdir('data/voted', 0755, true);
        }
    }

    private function getCandidatesFromFile():array {
        $candidates = [];
        if (file_exists('data/candidates.ddhf')) {
            $candidates_raw = explode(PHP_EOL, file_get_contents('data/candidates.ddhf'));
            foreach ($candidates_raw as $candidate_raw) {
                $candidate = explode(';', $candidate_raw);
                $candidates[$candidate[0]][] = [
                    'name' => $candidate[1],
                    'club' => $candidate[2],
                    'code' => trim($candidate[3]),
                    'name_code' =>
                        str_replace(['ä','ö','ü',' ', 'ß'], ['ae', 'oe', 'ue', '', 'ss'], strtolower($candidate[1]))
                ];
            }
        }

        return $candidates;
    }

    public function isPresidiumVote():bool {
        return strpos($this->pollQuestion, 'Präsidiumswahl') === 0;
    }

    public static function sanitizePostData($post_data) : array {

        foreach ($post_data as $k => $post) {
            $post_data[$k] = htmlspecialchars($post);
        }

        return $post_data;
    }
}
