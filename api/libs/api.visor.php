<?php

class UbillingVisor {

    /**
     * Contains system alter.ini config as key=>value
     *
     * @var array
     */
    protected $altCfg = array();

    /**
     * Contains all stargazer user data as login=>data
     *
     * @var array
     */
    protected $allUserData = array();

    /**
     * Contains all visor users data as id=>data
     *
     * @var array
     */
    protected $allUsers = array();

    /**
     * Contains all visor cameras data as id=>data
     *
     * @var array
     */
    protected $allCams = array();

    /**
     * Contains all visor dvrs data as id=>data
     *
     * @var array
     */
    protected $allDvrs = array();

    /**
     * Contains all available users payment IDs
     *
     * @var array
     */
    protected $allPaymentIDs = array();

    /**
     * System messages helper object placeholder
     *
     * @var object
     */
    protected $messages = '';

    /**
     * Basic module URLs
     */
    const URL_ME = '?module=visor';
    const URL_USERS = '&users=true';
    const URL_CAMS = '&cams=true';
    const URL_USERCAMS = '&ajaxusercams=';
    const URL_ALLCAMS = '&ajaxallcams=true';
    const URL_DVRS = '&dvrs=true';
    const URL_AJUSERS = '&ajaxusers=true';
    const URL_DELUSER = '&deleteuserid=';
    const URL_USERVIEW = '&showuser=';
    const URL_CAMPROFILE = '?module=userprofile&username=';
    const URL_CAMVIEW = '&showcamera=';

    /**
     * Some default tables names
     */
    const TABLE_USERS = 'visor_users';
    const TABLE_CAMS = 'visor_cams';
    const TABLE_DVRS = 'visor_dvrs';

    public function __construct() {
        $this->loadConfigs();
        $this->initMessages();
        $this->loadUserData();
        $this->loadUsers();
        $this->loadPaymentIds();
        $this->loadCams();
        $this->loadDvrs();
    }

    /**
     * Loads reqired configss
     * 
     * @global object $ubillingConfig
     * 
     * @return void
     */
    protected function loadConfigs() {
        global $ubillingConfig;
        $this->altCfg = $ubillingConfig->getAlter();
    }

    /**
     * Inits system message helper for further usage
     * 
     * @return void
     */
    protected function initMessages() {
        $this->messages = new UbillingMessageHelper();
    }

    /**
     * Loads all existing users data from database
     * 
     * @return void
     */
    protected function loadUserData() {
        $this->allUserData = zb_UserGetAllDataCache();
    }

    /**
     * Loads available payment IDs from database
     * 
     * @return void
     */
    protected function loadPaymentIds() {
        if ($this->altCfg['OPENPAYZ_REALID']) {
            $query = "SELECT `realid`,`virtualid` from `op_customers`";
            $allcustomers = simple_queryall($query);
            if (!empty($allcustomers)) {
                foreach ($allcustomers as $io => $eachcustomer) {
                    $this->allPaymentIDs[$eachcustomer['realid']] = $eachcustomer['virtualid'];
                }
            }
        } else {
            if (!empty($this->allUserData)) {
                foreach ($this->allUserData as $io => $each) {
                    $this->allPaymentIDs[$each['login']] = ip2int($each['ip']);
                }
            }
        }
    }

    /**
     * Loads all visor users data into protected property
     * 
     * @return void
     */
    protected function loadUsers() {
        $query = "SELECT * from `visor_users`";
        $all = simple_queryall($query);
        if (!empty($all)) {
            foreach ($all as $io => $each) {
                $this->allUsers[$each['id']] = $each;
            }
        }
    }

    /**
     * Loads all visor cameras data into protected property
     * 
     * @return void
     */
    protected function loadCams() {
        $query = "SELECT * from `visor_cams` ORDER BY `id` DESC";
        $all = simple_queryall($query);
        if (!empty($all)) {
            foreach ($all as $io => $each) {
                $this->allCams[$each['id']] = $each;
            }
        }
    }

    /**
     * Loads all visor DVR data into protected property
     * 
     * @return void
     */
    protected function loadDvrs() {
        $query = "SELECT * from `visor_dvrs`";
        $all = simple_queryall($query);
        if (!empty($all)) {
            foreach ($all as $io => $each) {
                $this->allDvrs[$each['id']] = $each;
            }
        }
    }

    /**
     * Renders default controls panel
     * 
     * @return string
     */
    public function panel() {
        $result = '';
        $result .= wf_Link(self::URL_ME . self::URL_USERS, wf_img('skins/ukv/users.png') . ' ' . __('Users'), false, 'ubButton') . ' ';
        $result .= wf_modalAuto(wf_img('skins/ukv/add.png') . ' ' . __('Users registration'), __('Users registration'), $this->renderUserCreateForm(), 'ubButton') . ' ';
        $result .= wf_Link(self::URL_ME . self::URL_CAMS, wf_img('skins/photostorage.png') . ' ' . __('Cams'), false, 'ubButton') . ' ';
        $result .= wf_Link(self::URL_ME . self::URL_DVRS, wf_img('skins/icon_restoredb.png') . ' ' . __('DVRs'), false, 'ubButton') . ' ';
        return ($result);
    }

    /**
     * Renders available users list container
     * 
     * @return string
     */
    public function renderUsers() {
        $result = '';
        $opts = '"order": [[ 0, "desc" ]]';
        $columns = array('ID', 'Date', 'Name', 'Phone', 'Charge', 'Cams', 'Actions');
        $result .= wf_JqDtLoader($columns, self::URL_ME . self::URL_AJUSERS, false, 'Users', 50, $opts);
        return ($result);
    }

    /**
     * Renders users datatables data
     * 
     * @return void
     */
    public function ajaxUsersList() {
        $json = new wf_JqDtHelper();
        if (!empty($this->allUsers)) {
            foreach ($this->allUsers as $io => $each) {
                $data[] = $each['id'];
                $data[] = $each['regdate'];
                $visorUserLabel = $this->iconVisorUser() . ' ' . $each['realname'];
                $visorUserLink = wf_Link(self::URL_ME . self::URL_USERVIEW . $each['id'], $visorUserLabel);
                $data[] = $visorUserLink;
                $data[] = $each['phone'];
                $chargeFlag = ($each['chargecams']) ? web_bool_led(true) . ' ' . __('Yes') : web_bool_led(false) . ' ' . __('No');
                $data[] = $chargeFlag;
                $data[] = $this->getUserCamerasCount($each['id']);
                $actLinks = '';
                //$actLinks .= wf_JSAlert(self::URL_ME . self::URL_DELUSER . $each['id'], web_delete_icon(), $this->messages->getDeleteAlert()) . ' ';
                $actLinks .= wf_Link(self::URL_ME . self::URL_USERVIEW . $each['id'], web_edit_icon());
                $data[] = $actLinks;
                $json->addRow($data);
                unset($data);
            }
        }
        $json->getJson();
    }

    /**
     * Renders visor user creation form
     * 
     * @return string
     */
    public function renderUserCreateForm() {
        $result = '';
        $sup = wf_tag('sup') . '*' . wf_tag('sup', true);
        $inputs = wf_HiddenInput('newusercreate', 'true');
        $inputs .= wf_TextInput('newusername', __('Name') . $sup, '', true, 25);
        $inputs .= wf_TextInput('newuserphone', __('Phone'), '', true, 20, 'mobile');
        $inputs .= wf_CheckInput('newuserchargecams', __('Charge money from primary account for linked camera users if required'), true, false);
        $inputs .= wf_delimiter();
        $inputs .= wf_Submit(__('Create'));
        $result .= wf_Form('', 'POST', $inputs, 'glamour');
        return ($result);
    }

    /**
     * Creates new user in database
     * 
     * @return void
     */
    public function createUser() {
        if (wf_CheckPost(array('newusercreate', 'newusername'))) {
            $newRealName = $_POST['newusername'];
            $newRealNameF = mysql_real_escape_string($newRealName);
            $newPhone = mysql_real_escape_string($_POST['newuserphone']);
            $newChargeCams = (wf_CheckPost(array('newuserchargecams'))) ? 1 : 0;
            $date = curdatetime();
            $query = "INSERT INTO `" . self::TABLE_USERS . "` (`id`,`regdate`,`realname`,`phone`,`chargecams`,`primarylogin`) VALUES "
                    . "(NULL,'" . $date . "','" . $newRealNameF . "','" . $newPhone . "','" . $newChargeCams . "','');";
            nr_query($query);
            $newId = simple_get_lastid(self::TABLE_USERS);
            log_register('VISOR USER CREATE [' . $newId . '] NAME `' . $newRealName . '`');
        }
    }

    /**
     * Returns array of cameras associated to some user
     * 
     * @param int $userId
     * 
     * @return array
     */
    protected function getUserCameras($userId) {
        $result = array();
        if (!empty($this->allCams)) {
            foreach ($this->allCams as $io => $each) {
                if ($each['visorid'] == $userId) {
                    $result[$each['id']] = $each;
                }
            }
        }
        return ($result);
    }

    /**
     * Returns camera ID if login have camera associated
     * 
     * @param string $login
     * 
     * @return int/void
     */
    protected function getCameraIdByLogin($login) {
        $result = '';
        if (!empty($this->allCams)) {
            foreach ($this->allCams as $io => $each) {
                if ($each['login'] == $login) {
                    $result = $each['id'];
                    break;
                }
            }
        }
        return($result);
    }

    /**
     * Checks is some account already someones primary or not
     * 
     * @param string $userLogin
     * 
     * @return bool
     */
    protected function isPrimaryAccountFree($userLogin) {
        $result = true;
        if (!empty($userLogin)) {
            if (!empty($this->allUsers)) {
                foreach ($this->allUsers as $io => $each) {
                    if ($each['primarylogin'] == $userLogin) {
                        $result = false;
                        break;
                    }
                }
            }
        }
        return($result);
    }

    /**
     * Returns camera user assigned visor user ID if exists
     * 
     * @param string $userLogin
     * 
     * @return int/void
     */
    public function getCameraUser($userLogin) {
        $result = '';
        if (!empty($this->allCams)) {
            foreach ($this->allCams as $io => $each) {
                if ($each['login'] == $userLogin) {
                    $result = $each['visorid'];
                    break;
                }
            }
        }
        return($result);
    }

    /**
     * Returns userId by its associated primary account
     * 
     * @param string $userLogin
     * 
     * @return int/void
     */
    public function getPrimaryAccountUserId($userLogin) {
        $result = '';
        if (!empty($this->allUsers)) {
            foreach ($this->allUsers as $io => $each) {
                if ($each['primarylogin'] == $userLogin) {
                    $result = $each['id'];
                    break;
                }
            }
        }
        return($result);
    }

    /**
     * Returns count of associated user cameras
     * 
     * @param int $userId
     * 
     * @return int
     */
    protected function getUserCamerasCount($userId) {
        $result = 0;
        $userCameras = $this->getUserCameras($userId);
        if (!empty($userCameras)) {
            $result = sizeof($userCameras);
        }
        return ($result);
    }

    /**
     * Deletes user from database
     * 
     * @param int $userId
     * 
     * @return void/string on error
     */
    public function deleteUser($userId) {
        $result = '';
        $userId = vf($userId, 3);
        if (isset($this->allUsers[$userId])) {
            $camerasCount = $this->getUserCamerasCount($userId);
            if ($camerasCount == 0) {
                $query = "DELETE from `" . self::TABLE_USERS . "` WHERE `id`='" . $userId . "';";
                nr_query($query);
                log_register('VISOR USER DELETE [' . $userId . ']');
            } else {
                $result .= __('User have some cameras associated');
            }
        } else {
            $result .= __('User not exists');
        }
        return ($result);
    }

    /**
     * Returns user primary camera controls if primary available
     * 
     * @param int $userId
     * 
     * @return string
     */
    protected function renderUserPrimaryAccount($userId) {
        $result = '';
        if (isset($this->allUsers[$userId])) {
            $userData = $this->allUsers[$userId];
            $primaryAccount = $userData['primarylogin'];
            if (!empty($primaryAccount)) {
                if (isset($this->allUserData[$primaryAccount])) {
                    $cells = wf_TableCell(__('Primary account'), '30%', 'row2');
                    $linkLabel = (@$this->allUserData[$primaryAccount]['fulladress']) ? $this->allUserData[$primaryAccount]['fulladress'] : $primaryAccount;
                    $primaLink = wf_Link(self::URL_CAMPROFILE . $primaryAccount, web_profile_icon() . ' ' . $linkLabel);
                    $cells .= wf_TableCell($primaLink);
                    $rows = wf_TableRow($cells, 'row3');

                    $cells = wf_TableCell(__('Balance'), '30%', 'row2');
                    $cells .= wf_TableCell($this->allUserData[$primaryAccount]['Cash']);
                    $rows .= wf_TableRow($cells, 'row3');

                    $cells = wf_TableCell(__('Payment ID'), '30%', 'row2');
                    $cells .= wf_TableCell($this->allPaymentIDs[$primaryAccount]);
                    $rows .= wf_TableRow($cells, 'row3');
                    $result .= $rows;
                } else {
                    $cells = wf_TableCell(__('Primary account'), '30%', 'row2');
                    $cells .= wf_TableCell(__('Not exists') . ': ' . $primaryAccount);
                    $rows = wf_TableRow($cells, 'row3');
                    $result .= $rows;
                }
            }
        }

        return($result);
    }

    /**
     * Renders visor users profile with associated cameras and some controls
     * 
     * @param int $userId
     * 
     * @return string
     */
    public function renderUserProfile($userId) {
        $result = '';
        $userId = vf($userId, 3);
        if (isset($this->allUsers[$userId])) {
            $userData = $this->allUsers[$userId];
            if (!empty($userData)) {
                $userCamsCount = $this->getUserCamerasCount($userId);

                $cells = wf_TableCell(__('Name'), '30%', 'row2');
                $cells .= wf_TableCell($userData['realname']);
                $rows = wf_TableRow($cells, 'row3');
                $cells = wf_TableCell(__('Phone'), '', 'row2');
                $cells .= wf_TableCell($userData['phone']);
                $rows .= wf_TableRow($cells, 'row3');
                $cells = wf_TableCell(__('Charge'), '', 'row2');
                $chargeFlag = ($userData['chargecams']) ? wf_img_sized('skins/icon_active.gif', '', '12', '12') . ' ' . __('Yes') : wf_img_sized('skins/icon_inactive.gif', '', '12', '12') . ' ' . __('No');
                $cells .= wf_TableCell($chargeFlag);
                $rows .= wf_TableRow($cells, 'row3');

                //primary user account inline
                $rows .= $this->renderUserPrimaryAccount($userId);

                $result .= wf_TableBody($rows, '100%', 0, '');

                $result .= $this->renderUserControls($userId);

                if ($userCamsCount > 0) {
                    $result .= $this->renderCamerasContainer(self::URL_ME . self::URL_USERCAMS . $userId);
                } else {
                    $result .= $this->messages->getStyledMessage(__('User have no cameras assigned'), 'warning');
                }
            }
        }
        return ($result);
    }

    /**
     * Renders Visor user defaults controls set
     * 
     * @param int $userId
     * 
     * @return string
     */
    protected function renderUserControls($userId) {
        $result = '';
        if (isset($this->allUsers[$userId])) {
            $taskB = wf_tag('div', false, 'dashtask', 'style="height:75px; width:75px;"');
            $taskE = wf_tag('div', true);

            $result .= $taskB . wf_modalAuto(wf_img('skins/ukv/useredit.png', __('Edit user')), __('Edit user'), $this->renderUserEditInterface($userId)) . __('Edit') . $taskE;
            $result .= $taskB . wf_modalAuto(wf_img('skins/icon_king_big.png', __('Primary account')), __('Primary account'), $this->renderUserPrimaryEditForm($userId)) . __('Primary') . $taskE;
            $result .= $taskB . wf_modalAuto(wf_img('skins/annihilation.gif', __('Deleting user')), __('Deleting user'), $this->renderUserDeletionForm($userId), '') . __('Delete') . $taskE;

            $result .= wf_CleanDiv();
        }
        return($result);
    }

    /**
     * Renders user primary account editing interface
     * 
     * @param int $userId
     * 
     * @return string
     */
    protected function renderUserPrimaryEditForm($userId) {
        $result = '';
        if (isset($this->allUsers[$userId])) {
            $currentUserData = $this->allUsers[$userId];
            $currentPrimaryAccount = $currentUserData['primarylogin'];
            $allUserCameras = $this->getUserCameras($userId);
            $camerasTmp = array();
            $selectedCamera = '';
            $camerasTmp[''] = '-';
            if (!empty($allUserCameras)) {

                foreach ($allUserCameras as $io => $each) {
                    if ($each['login'] == $currentPrimaryAccount) {
                        $selectedCamera = $each['login'];
                    }
                    $camerasTmp[$each['login']] = @$this->allUserData[$each['login']]['fulladress'] . ' - ' . @$this->allUserData[$each['login']]['ip'];
                }
            }

            $inputs = '';
            $inputs = wf_Selector('newprimarycameralogin', $camerasTmp, __('Camera'), $selectedCamera, true);
            $inputs .= __('Or') . wf_tag('br');
            $inputs .= wf_TextInput('newprimaryuserlogin', __('Login'), $currentPrimaryAccount, true, 20);
            $inputs .= wf_HiddenInput('editprimarycamerauserid', $userId);
            $inputs .= wf_delimiter();
            $inputs .= wf_Submit(__('Save'));
            $result .= wf_Form('', 'POST', $inputs, 'glamour');
        }
        return($result);
    }

    /**
     * Sets some account as primary for some user
     * 
     * @param int $userId
     * @param string $login
     * 
     * @return void
     */
    protected function setPrimaryAccount($userId, $login = '') {
        $userId = vf($userId, 3);
        $login = trim($login);

        if (isset($this->allUsers[$userId])) {
            $userCameras = $this->getUserCameras($userId);

            $currentPrimary = $this->allUsers[$userId]['primarylogin'];
            if ($currentPrimary != $login) {
                if ($this->isPrimaryAccountFree($login)) {
                    simple_update_field(self::TABLE_USERS, 'primarylogin', $login, "WHERE `id`='" . $userId . "'"); //setting primary account in profile
                    simple_update_field(self::TABLE_CAMS, 'primary', 0, "WHERE `visorid`='" . $userId . "'"); // dropping all camera primary flags
                    log_register('VISOR USER [' . $userId . '] CHANGE PRIMARY `' . $login . '`');
                    $cameraId = $this->getCameraIdByLogin($login);
                    if (!empty($cameraId)) {
                        simple_update_field(self::TABLE_CAMS, 'primary', '1', "WHERE `id`='" . $cameraId . "'"); //setting camera account as primary
                    }
                } else {
                    log_register('VISOR USER [' . $userId . '] FAIL PRIMARY BUSY');
                }
            }
        } else {
            log_register('VISOR USER [' . $userId . '] FAIL PRIMARY NOUSER');
        }
    }

    /**
     * Catches primary editing request and saves changes if required
     * 
     * @return void
     */
    public function savePrimary() {
        if (wf_CheckPost(array('editprimarycamerauserid'))) {
            $userId = vf($_POST['editprimarycamerauserid'], 3);
            $newPrimaryLogin = (wf_CheckPost(array('newprimarycameralogin'))) ? $_POST['newprimarycameralogin'] : '';
            if (wf_CheckPost(array('newprimaryuserlogin')) AND ! wf_CheckPost(array('newprimarycameralogin'))) {
                $newPrimaryLogin = $_POST['newprimaryuserlogin'];
            }
            $this->setPrimaryAccount($userId, $newPrimaryLogin);
        }
    }

    /**
     * user deletion form
     * 
     * @param int $userId existing user ID
     * 
     * @return string
     */
    protected function renderUserDeletionForm($userId) {
        $userId = vf($userId, 3);
        $inputs = __('Be careful, this module permanently deletes user and all data associated with it. Opportunities to raise from the dead no longer.') . ' <br>
               ' . __('To ensure that we have seen the seriousness of your intentions to enter the word сonfirm the field below.');
        $inputs .= wf_HiddenInput('userdeleteprocessing', $userId);
        $inputs .= wf_delimiter();
        $inputs .= wf_tag('input', false, '', 'type="text" name="deleteconfirmation" autocomplete="off"');
        $inputs .= wf_delimiter();
        $inputs .= wf_Submit(__('I really want to stop suffering User'));

        $result = wf_Form('', 'POST', $inputs, 'glamour');
        return ($result);
    }

    /**
     * Renders default cameras view container
     * 
     * @param string $url
     * 
     * @return string
     */
    public function renderCamerasContainer($url) {
        $result = '';
        $opts = '"order": [[ 0, "desc" ]]';
        $columns = array('ID', 'Primary', 'User', 'Address', 'IP', 'Tariff', 'Active', 'Balance', 'Credit', 'Actions');
        if ($this->altCfg['DN_ONLINE_DETECT']) {
            $columns = array('ID', 'Primary', 'User', 'Address', 'IP', 'Tariff', 'Active', 'Online', 'Balance', 'Credit', 'Actions');
        }
        $result .= wf_JqDtLoader($columns, $url, false, __('Cams'), 50, $opts);
        return($result);
    }

    /**
     * Renders ajax json backend for some user assigned cameras
     * 
     * @param int $userId
     * 
     * @return void
     */
    public function ajaxUserCams($userId) {
        $userId = vf($userId, 3);
        $json = new wf_JqDtHelper();
        $dnFlag = ($this->altCfg['DN_ONLINE_DETECT']) ? true : false;

        if (isset($this->allUsers[$userId])) {
            $allUserCams = $this->getUserCameras($userId);
            if (!empty($allUserCams)) {
                foreach ($allUserCams as $io => $each) {
                    $cameraUserData = @$this->allUserData[$each['login']];
                    $data[] = $each['id'];
                    $primaryFlag = ($each['primary']) ? web_bool_led(true) . ' ' . __('Yes') : web_bool_led(false) . ' ' . __('No');
                    $data[] = $primaryFlag;
                    $visorLinkLabel = $this->iconVisorUser() . ' ' . @$this->allUsers[$each['visorid']]['realname'];
                    $visorUserLink = wf_Link(self::URL_ME . self::URL_USERVIEW . $each['visorid'], $visorLinkLabel);
                    $data[] = $visorUserLink;
                    $cameraLinkLabel = web_profile_icon() . ' ' . $cameraUserData['fulladress'];
                    $cameraLink = wf_Link(self::URL_CAMPROFILE . $each['login'], $cameraLinkLabel);
                    $data[] = $cameraLink;
                    $data[] = @$cameraUserData['ip'];
                    $data[] = @$cameraUserData['Tariff'];
                    $cameraCash = @$cameraUserData['Cash'];
                    $cameraCredit = @$cameraUserData['Credit'];
                    $cameraState = '';
                    if ($cameraCash >= '-' . $cameraCredit) {
                        $cameraState = web_bool_led(true) . ' ' . __('Yes');
                    } else {
                        $cameraState = web_bool_led(false) . ' ' . __('No');
                    }
                    $data[] = $cameraState;
                    if ($dnFlag) {
                        $onlineState = web_bool_star(false) . ' ' . __('No');
                        if (file_exists(DATA_PATH . 'dn/' . $each['login'])) {
                            $onlineState = web_bool_star(true) . ' ' . __('Yes');
                        }
                        $data[] = $onlineState;
                    }
                    $data[] = $cameraCash;
                    $data[] = $cameraCredit;
                    $actLinks = wf_Link(self::URL_ME . self::URL_CAMVIEW . $each['id'], web_edit_icon());
                    $data[] = $actLinks;
                    $json->addRow($data);
                    unset($data);
                }
            }
        }
        $json->getJson();
    }

    /**
     * Returns default user icon coode
     * 
     * @return string
     */
    public function iconVisorUser() {
        $result = wf_img('skins/icon_camera_small.png');
        return($result);
    }

    /**
     * Renders ajax json backend for all available cameras
     * 
     * @return void
     */
    public function ajaxAllCams() {
        $json = new wf_JqDtHelper();
        $dnFlag = ($this->altCfg['DN_ONLINE_DETECT']) ? true : false;

        if (!empty($this->allCams)) {
            foreach ($this->allCams as $io => $each) {
                $cameraUserData = @$this->allUserData[$each['login']];
                $data[] = $each['id'];
                $primaryFlag = ($each['primary']) ? web_bool_led(true) . ' ' . __('Yes') : web_bool_led(false) . ' ' . __('No');
                $data[] = $primaryFlag;
                $visorLinkLabel = $this->iconVisorUser() . ' ' . @$this->allUsers[$each['visorid']]['realname'];
                $visorUserLink = wf_Link(self::URL_ME . self::URL_USERVIEW . $each['visorid'], $visorLinkLabel);
                $data[] = $visorUserLink;
                $cameraLinkLabel = web_profile_icon() . ' ' . $cameraUserData['fulladress'];
                $cameraLink = wf_Link(self::URL_CAMPROFILE . $each['login'], $cameraLinkLabel);
                $data[] = $cameraLink;
                $data[] = @$cameraUserData['ip'];
                $data[] = @$cameraUserData['Tariff'];
                $cameraCash = @$cameraUserData['Cash'];
                $cameraCredit = @$cameraUserData['Credit'];
                $cameraState = '';
                if ($cameraCash >= '-' . $cameraCredit) {
                    $cameraState = web_bool_led(true) . ' ' . __('Yes');
                } else {
                    $cameraState = web_bool_led(false) . ' ' . __('No');
                }
                $data[] = $cameraState;
                if ($dnFlag) {
                    $onlineState = web_bool_star(false) . ' ' . __('No');
                    if (file_exists(DATA_PATH . 'dn/' . $each['login'])) {
                        $onlineState = web_bool_star(true) . ' ' . __('Yes');
                    }
                    $data[] = $onlineState;
                }
                $data[] = $cameraCash;
                $data[] = $cameraCredit;
                $actLinks = wf_Link(self::URL_ME . self::URL_CAMVIEW . $each['id'], web_edit_icon());
                $data[] = $actLinks;
                $json->addRow($data);
                unset($data);
            }
        }

        $json->getJson();
    }

    /**
     * Renders initial camera creation interface
     * 
     * @param string $userLogin
     * 
     * @return string
     */
    public function renderCameraCreateInterface($userLogin) {
        $result = '';
        if (!empty($this->allUsers)) {
            $usersTmp = array();
            $usersTmp[''] = '-';
            foreach ($this->allUsers as $io => $each) {
                $usersTmp[$each['id']] = $each['realname'];
            }

            $inputs = wf_Selector('newcameravisorid', $usersTmp, __('The user who will be assigned a new camera'), '', false);
            $inputs .= wf_delimiter();
            $inputs .= wf_HiddenInput('newcameralogin', $userLogin);
            $inputs .= wf_Submit(__('Create'));
            $result .= wf_Form('', 'POST', $inputs, 'glamour');
        } else {
            $result .= $this->messages->getStyledMessage(__('No existing Visor users avaliable, you must create one at least to assign cameras'), 'error');
        }
        return($result);
    }

    /**
     * Creates new camera account and assigns it to existing user
     * 
     * @return void
     */
    public function createCamera() {
        if (wf_CheckPost(array('newcameravisorid', 'newcameralogin'))) {
            $newVisorId = vf($_POST['newcameravisorid'], 3);
            $newCameraLogin = $_POST['newcameralogin'];
            $newCameraLoginF = mysql_real_escape_string($newCameraLogin);
            if (isset($this->allUsers[$newVisorId])) {
                if (!empty($newCameraLoginF)) {
                    $query = "INSERT INTO `" . self::TABLE_CAMS . "` (`id`,`visorid`,`login`,`primary`,`camlogin`,`campassword`,`port`,`dvrid`,`dvrlogin`,`dvrpassword`)"
                            . " VALUES "
                            . " (NULL,'" . $newVisorId . "','" . $newCameraLoginF . "','0','','','','','','');";
                    nr_query($query);
                    $newId = simple_get_lastid(self::TABLE_CAMS);
                    log_register('VISOR CAMERA CREATE [' . $newId . '] ASSIGN [' . $newVisorId . '] LOGIN (' . $newCameraLogin . ')');
                } else {
                    log_register('VISOR CAMERA CREATE FAIL EMPTY_LOGIN');
                }
            } else {
                log_register('VISOR CAMERA CREATE FAIL VISORID_NOT_EXISTS');
            }
        }
    }

    /**
     * Renders users editing interface
     * 
     * @param int $userId
     * 
     * @return string
     */
    protected function renderUserEditInterface($userId) {
        $result = '';
        $userId = vf($userId, 3);
        if (isset($this->allUsers[$userId])) {
            $currentUserData = $this->allUsers[$userId];
            $sup = wf_tag('sup') . '*' . wf_tag('sup', true);
            $inputs = wf_HiddenInput('edituserid', $userId);
            $inputs .= wf_TextInput('editusername', __('Name') . $sup, $currentUserData['realname'], true, 25);
            $inputs .= wf_TextInput('edituserphone', __('Phone'), $currentUserData['phone'], true, 20, 'mobile');
            $inputs .= wf_CheckInput('edituserchargecams', __('Charge money from primary account for linked camera users if required'), true, $currentUserData['chargecams']);
            $inputs .= wf_delimiter();
            $inputs .= wf_Submit(__('Save'));
            $result .= wf_Form('', 'POST', $inputs, 'glamour');
        }
        return($result);
    }

    /**
     * Catches and saves user editing request if required
     * 
     * 
     * @return void
     */
    public function saveUser() {
        if (wf_CheckPost(array('edituserid', 'editusername'))) {
            $editUserId = vf($_POST['edituserid'], 3);
            if (isset($this->allUsers[$editUserId])) {
                $currentUserData = $this->allUsers[$editUserId];
                $where = " WHERE `id`='" . $editUserId . "'";
                $newUserName = $_POST['editusername'];
                $newUserPhone = $_POST['edituserphone'];
                $newCharge = (wf_CheckPost(array('edituserchargecams'))) ? 1 : 0;
                if ($currentUserData['realname'] != $newUserName) {
                    simple_update_field(self::TABLE_USERS, 'realname', $newUserName, $where);
                    log_register('VISOR USER CHANGE NAME `' . $newUserName . '`');
                }

                if ($currentUserData['phone'] != $newUserPhone) {
                    simple_update_field(self::TABLE_USERS, 'phone', $newUserPhone, $where);
                    log_register('VISOR USER CHANGE PHONE `' . $newUserPhone . '`');
                }

                if ($currentUserData['chargecams'] != $newCharge) {
                    simple_update_field(self::TABLE_USERS, 'chargecams', $newCharge, $where);
                    log_register('VISOR USER CHANGE CHARGE `' . $newUserPhone . '`');
                }
            }
        }
    }

    public function renderCameraForm($cameraId) {
        $result = 'TODO';
        debarr($this->allCams[$cameraId]);
        return($result);
    }

}