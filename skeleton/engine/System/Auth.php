<?php defined("ALT_PATH") or die("No direct script access.");

class System_Auth {

    public function login($data){
        // get username and password
        $username = $data['username'] ? $data['username'] : '';
        $password = $data['password'] ? $data['password'] : '';

        // user already login and token is still valid, return previous token
        if(Alt_Auth::islogin()) {
            $userdata = Alt_Auth::get_userdata();

            // check if login using previous username, return token
            if($userdata['username'] == $username)
                return Alt_Auth::get_token();

            Alt_Auth::clear_token();
        }

        // user not logged in but token is exist, try to force logout
        if(!Alt_Auth::islogin() && Alt_Auth::get_token() != ''){
            try{
                $this->logout($data);
            }catch (Exception $e){}
        }

        // validate username and password
        Alt_Validation::instance()
            ->rule(Alt_Validation::required($username), 'Username harus diisi!')
            ->rule(Alt_Validation::required($password), 'Password harus diisi!')
            ->check();

        // check is exist within database
        $user = new System_User();
        $res = $user->get(array(
            'where' => 'username = ' . $user->quote($username),
        ));

        // user not found
        if(count($res) != 1)
            throw new Alt_Exception('User tidak ditemukan!');

        // set userdata
        $userdata = $res[0];

        // checking if user enabled
        if($userdata['isenabled'] != 1)
            throw new Alt_Exception('User tidak aktif! Silahkan hubungi administrator/helpdesk!');

        // checking if password correct
        if(md5($password) != $userdata['password'])
            throw new Alt_Exception('Password tidak cocok!');

        unset($userdata['password']);
        $token = Alt_Auth::generate_token($userdata);
        Alt_Auth::save_token($token);

        return $token;
    }

    public function logout($data){
        Alt_Auth::set_permission(0);

        if(!Alt_Auth::islogin() && Alt_Auth::get_token() == '')
            throw new Alt_Exception('Anda belum login atau sesi anda telah habis');

        Alt_Auth::clear_token();

        return 1;
    }

    public function register($data){
        $data["username"] = $data["username"] ? $data["username"] : $data["email"];
        $data["usergroupid"] = 3;

        Alt_Validation::instance()
            ->rule(Alt_Validation::required($data["name"]), "Nama harus diisi!")
            ->rule(Alt_Validation::required($data["username"]), "Username harus diisi!")
            ->rule(Alt_Validation::required($data["password"]), "Password harus diisi!")
            ->rule(Alt_Validation::required($data["password2"]), "Konfirmasi password harus diisi!")
            ->check();

        if(!Alt_Validation::equals($data["password"], $data["password2"]))
            throw new Alt_Exception("Konfirmasi password tidak sesuai!");

        $data["password"] = md5($data["password"]);

        $dbo_user = new System_User();
        return $dbo_user->insert($data);
    }

}