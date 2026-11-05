<?php
namespace ddhf\voting\classes;

class MemberList {

    private $members;
    private $settings;

    public function __construct() {
        $this->members = $this->getUsersFromFile();
        $this->settings = $this->getSettingsFromFile();
    }

    private function getUsersFromFile() {

        if ($this->fileExists('data/users.ddhf')) {
            $users_string = file_get_contents('data/users.ddhf');
            if (!empty($users_string)) {
                $users = explode(PHP_EOL, file_get_contents('data/users.ddhf'));

                foreach ($users as $i => $line) {
                    $users[$i] = explode(';', $users[$i]);
                }
            } else {
                $users = [];
            }

        }
        else {
            // todo: error;
            $users = [];
        }

        return $users;
    }

    /**
     * Checks if a file exists and tries to create it, if it does not exist
     *
     * @param $filename
     * @return bool
     */
    private function fileExists($filename) {
        return file_exists($filename) ?: $this->fileCreation($filename);
    }

    /**
     * Creates a new file
     *
     * @param $filename
     * @return bool
     */
    private function fileCreation($filename) {
        $file = fopen($filename, "w") or die("Unable to open file!");
        fclose($file);

        return file_exists($filename);
    }

    public function isUser($code) {
        $member = false;
        $i = 0;

        while (!$member && $i < count($this->members)) {
            if ($this->members[$i][0] == $code) {
                $member = true;
            }
            $i++;
        }
        return $member;
    }

    public function getUserByToken($token, $mode = NULL) {
        $user = [];

        foreach ($this->members as $member) {
            if ($token == $member[0]) {
                $user = [
                    'name' => $member[1],
                    'votes' => $member[2]
                ];
            }
        }
        return $user;
    }

    public function getNumberOfParticipants() {
        return count($this->members) - 1;
    }

    public function getMaxVotes() {
        $result = 0;
        if (!empty($this->members)) {
            foreach ($this->members as $member) {
                $result += $member[2];
            }
        }


        return $result;
    }

    public function isAdmin($token) {
        // need settings and compare the tokens.
        $username = $this->getUserByToken($token, $this->settings['voting-mode']);
        if (!empty($this->settings['admin']) && !empty($username['name']) && $username['name'] == $this->settings['admin']) {
            return true;
        }
        return false;
    }
    /**
     * @return array
     */
    private function getSettingsFromFile() {
        $settings = [];

        if (file_exists('data/settings.json')) {
            $settings = (array) json_decode(file_get_contents('data/settings.json'));
        }
        return $settings;
    }

    public function getUsers() {
        return $this->members;
    }
}