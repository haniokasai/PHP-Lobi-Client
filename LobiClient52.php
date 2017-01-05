<?php
is_readable("motd")?print(file_get_contents("motd")):null;
date_default_timezone_set('Asia/Tokyo');

global $api;
$api = new LobiAPI();

global $exit;
$exit = false;

global $currentgp, $currentgpn, $currentchat, $currentchatxt, $gplist, $pgplist, $notifi;
$currentgp = $currentgpn = $currentchat = $currentchatxt = $gplist = $pgplist = $notifi = null;

logintask();

while(1==1) {
    loader();
}



function logintask(){
    if (is_readable("conf.ini")) {
        echo PHP_EOL,'設定ファイルが見つかりました',PHP_EOL;

        global $api;
        $ini = parse_ini_file("conf.ini");
        if($api->Login($ini['email'],$ini['password'])){
            if(($me = $api->GetMe()) ==null){
                echo "ログイン失敗.ユーザー名か、パスワードが違います。",PHP_EOL;
                exittunes();
            }
            //var_dump($me);
            echo "ログイン成功",PHP_EOL;
            echo "ユーザー名 : ". $me["name"],PHP_EOL;
        }else{
            echo "ログイン失敗.サーバーにアクセスできませんでした。",PHP_EOL;
            exittunes();
        }

    } else {
        echo '設定ファイルがありませんでした、記述しておいてください、conf.iniです',PHP_EOL;
        $ini['email'] = "email";
        $ini['password'] = "password";
        $ini['check-noftication-interval-second'] = 0;
        $ini['check-reply-interval-second'] = 0;
        $fp = fopen("conf.ini", 'w');
        foreach ($ini as $k => $i) fputs($fp, "$k=$i\n");
        fclose($fp);
        exittunes();
    }
}
function loader(){
    global $currentchat;
    global $currentgp;
    echo PHP_EOL,"LobiC ",substr($currentgp,0,5),"/",substr($currentchat,0,5)," > ";
    $stdin = trim(fgets(STDIN));
    switcher($stdin);
}

function switcher($args)
{
    //echo $args;
    $ary = explode(" ", $args); //[]内は全角スペース+半角スペース
    echo $ary[0],PHP_EOL;
    global $api, $gplist,$pgplist,$currentgp,$currentchat,$currentgpn,$currentchatxt;
    switch ($ary[0]) {
        case "exit":
            exittunes();
            break;
        case "gpls": {
            reloadgpl();
            $i = 0;
            echo "/*公開グループ一覧*/", PHP_EOL, PHP_EOL, PHP_EOL;

            //逆順システム
            while (isset($gplist[0][0]["items"][$i])){++$i;}--$i;


            while (isset($gplist[0][0]["items"][$i]["name"])) {
                echo "/*/*/*/*/*/*/*/", PHP_EOL;;
                echo "|グループ名 | ", $gplist[0][0]["items"][$i]["name"], " | ", PHP_EOL;
                echo "|UID | ", $gplist[0][0]["items"][$i]["uid"], " | ", PHP_EOL;
                echo "|人数 | ", $gplist[0][0]["items"][$i]["total_users"], " | ", PHP_EOL;
                echo "|最終つぶやき日 | ", date("Y/m/d H:i:s", $gplist[0][0]["items"][$i]["last_chat_at"]), " ", " | ", PHP_EOL;
                echo "/*/*/*/*/*/*/*/", PHP_EOL, PHP_EOL;
                --$i;
            }
            break;
        }
        case "pgpls": {
            reloadgpl();

            $i = 0;

            //逆順システム
            while (isset($pgplist[0][0]["items"][$i])){++$i;}--$i;


            echo "/*非公開グループ一覧*/", PHP_EOL, PHP_EOL, PHP_EOL;
            while (isset($pgplist[0][0]["items"][$i]["name"])) {
                echo "/*/*/*/*/*/*/*/", PHP_EOL;;
                echo "|グループ名 | ", $pgplist[0][0]["items"][$i]["name"], " | ", PHP_EOL;
                echo "|UID | ", $pgplist[0][0]["items"][$i]["uid"], " | ", PHP_EOL;
                echo "|人数 | ", $pgplist[0][0]["items"][$i]["total_users"], " | ", PHP_EOL;
                echo "|最終つぶやき日 | ", date("Y/m/d H:i:s", $pgplist[0][0]["items"][$i]["last_chat_at"]), " ", " | ", PHP_EOL;
                echo "/*/*/*/*/*/*/*/", PHP_EOL, PHP_EOL;
                --$i;
            }
            break;
        }

        case "mvgp": {
            if (!isset($ary[1])) {
                $currentgp = null;
                $currentgpn = null;
                $currentchat = null;
                $currentchatxt = null;
                echo "ルートディレクトリに戻りました", PHP_EOL;
                break;
            }

            reloadgpl();

            $i = 0;
            $continue = true;

            while (isset($gplist[0][0]["items"][$i]["uid"]) & $continue == true) {
                if (strpos($gplist[0][0]["items"][$i]["uid"], $ary[1]) !== false) {
                    $result = $gplist[0][0]["items"][$i]["uid"];
                    $resultname = $gplist[0][0]["items"][$i]["name"];
                    $continue = false;
                }
                ++$i;
            }
            $i = 0;
            while (isset($pgplist[0][0]["items"][$i]["uid"]) & $continue == true) {
                if (strpos($pgplist[0][0]["items"][$i]["uid"], $ary[1]) !== false) {
                    $result = $pgplist[0][0]["items"][$i]["uid"];
                    $resultname = $pgplist[0][0]["items"][$i]["name"];
                    $continue = false;
                }
                ++$i;
            }
            if (!isset($result)) {
                echo "そのようなグループはありません", PHP_EOL;
                break;
            } else {
                echo "移動先 : ";
                echo $result, PHP_EOL;
                echo $resultname, PHP_EOL;
                $currentgp = $result;
                $currentgpn = $resultname;
                break;
            }
        }
        case "pwd":{
            echo "現在地",PHP_EOL;
            echo "グループ番号 | ",$currentgp,PHP_EOL;
            echo "グループUID | ",$currentgpn,PHP_EOL;
            echo "チャットUID　| ",$currentchat,PHP_EOL;
            echo "チャット内容 | ",$currentchatxt,PHP_EOL;
        }

        case "chatls":{ //arg1 で表示個数を調節できる
            if($currentgp == null){
                echo "あなたはどこのグルにもいません。",PHP_EOL;
                break;
            }
            if($currentchat == null){
                $tls = $api->GetThreads($currentgp/*,(isset($ary[1])?(is_numeric($ary[1])?$ary[1]:null):null)*/);
                $i=0;
                while (isset($tls[$i])){
                    ++$i;
                }
                --$i;
                while (isset($tls[$i])){
                    echo "/*/*/*/*/",PHP_EOL;
                    echo "話者　: ",$tls[$i]["user"]["name"],PHP_EOL;
                    echo "時間  : ",date("Y/m/d H:i:s",$tls[$i]["created_date"]),PHP_EOL;
                    echo "ID : ",$tls[$i]["id"],PHP_EOL;
                    echo "内容 : ",$tls[$i]["message"],PHP_EOL;
                    echo "/*/*/*/*/",PHP_EOL,PHP_EOL,PHP_EOL;
                    --$i;
                }
                break;
            }else{
                $tls = $api->GetReplies($currentgp,$currentchat/*,(isset($ary[1])?(is_numeric($ary[1])?$ary[1]:null):null)*/);
                //var_dump($tls);

                $i=0;
                while (isset($tls["chats"][$i])){
                    ++$i;
                }
                --$i;
                while (isset($tls["chats"][$i])){
                    echo "/*/*/*/*/",PHP_EOL;
                    echo "話者　: ",$tls["chats"][$i]["user"]["name"],PHP_EOL;
                    echo "時間  : ",date("Y/m/d H:i:s",$tls["chats"][$i]["created_date"]),PHP_EOL;
                    echo "ID : ",$tls["chats"][$i]["id"],PHP_EOL;
                    echo "内容 : ",$tls["chats"][$i]["message"],PHP_EOL;
                    echo "/*/*/*/*/",PHP_EOL,PHP_EOL,PHP_EOL;
                    --$i;
                }
                break;
            }
        }

        case "mvchat":{ //$ary[1] で latest　で最後の指定
            if($currentgp == null){
                echo "あなたはどこのグルにもいません。",PHP_EOL;
                break;
            }
            $tls = $api->GetThreads($currentgp);
            if($ary[1] == "latest"){
                if(isset($tls[0])){
                    $result = $tls[0]["id"];
                    $resultname = $tls[0]["message"];
                }
            }else {
                $i = 0;
                $continue = true;

                while (isset($tls[$i]) & $continue == true) {
                    if (strpos($tls[$i]["id"], $ary[1]) !== false) {
                        $result = $tls[$i]["id"];
                        $resultname = $tls[$i]["message"];
                        $continue = false;
                    }
                    ++$i;
                }
            }
            if (!isset($result)) {
                echo "そのようなチャットはありません", PHP_EOL;
                break;
            } else {
                echo "移動先 : ";
                echo "現在のグループ : ",$currentgpn,PHP_EOL;
                echo $result, PHP_EOL;
                echo $resultname, PHP_EOL;
                $currentchat = $result;
                $currentchatxt = $resultname;
                break;
            }

        }

        case "say":{
            if($currentgp == null){
                echo "グループに入ってください";
                break;
            }
            if(!isset($ary[1])|| (!isset($ary[2])&$ary[1]=="shout")){
                echo "入力内容がありません";
                break;
            }

            $ary2 = $ary;
            if($ary[1]=="shout") {
                unset($ary2[1]);
            }
            unset($ary2[0]);

            if($currentchat ==null){
                $api->MakeThread($currentgp,implode($ary2),($ary[1]=="shout")?true:false);
                echo "スレ立てしました";
            }else{
                $api->Reply($currentgp,$currentchat,implode($ary2));
                echo "リプライしました";
            }
            break;
        }


        case "notifi":{
            global $notifi;
            if($notifi ==null){
                $notifi = $api->GetNotifications();
                //var_dump($notifi);


                $i=0;
                while (isset($notifi["notifications"][$i])){
                    ++$i;
                }
                --$i;
                while (isset($notifi["notifications"][$i])){
                    echo "/*/*/*/*/",PHP_EOL;
                    echo "話者　: ",$notifi["notifications"][$i]["user"]["name"],PHP_EOL;
                    echo "時間  : ",date("Y/m/d H:i:s",$notifi["notifications"][$i]["created_date"]),PHP_EOL;

                    ///ぐるかどうか
                    if(strpos($notifi["notifications"][$i]["link"],"group") != null){
                        $link =$notifi["notifications"][$i]["link"];
                        $link2 = ltrim($link,"nakamap://group/");
                        $linary = explode("/",$link2);
                        // 0 group num ,1 "chat", 2 chat num ,3 "reply" ,4 reply num
                        ////グループからとんなきゃいけなそう....
                        ///echo "グループ名 : ",$linary[0],PHP_EOL;
                        echo "グループID : ",$linary[0],PHP_EOL;
                        echo "レス元のチャットID : ",$linary[2],PHP_EOL;
                    }
                    echo "ID : ",$notifi["notifications"][$i]["id"],PHP_EOL;
                    echo "内容 : ",$notifi["notifications"][$i]["message"],PHP_EOL;
                    echo "/*/*/*/*/",PHP_EOL,PHP_EOL,PHP_EOL;
                    --$i;
                }



            }else{
                $notifi2 = $api->GetNotifications();
                $diff=array_diff_key($notifi,$notifi2);
                //var_dump($diff);
                $notifi = $notifi2;
            }
            break;
        }

        case "reload":{
            $gplist = $api->GetPublicGroupList();
            $pgplist = $api->GetPrivateGroupList();
            echo "リロードしました";
            break;
        }


    }
}

function reloadgpl(){
    global $gplist,$pgplist,$api;
    if ($gplist == null) {
        $gplist = $api->GetPublicGroupList();
    }
    if ($pgplist == null) {
        $pgplist = $api->GetPrivateGroupList();
    }
}
function exittunes(){
    global $exit;
    $exit = true;
    echo 'どれかキーを押すと終了します',PHP_EOL;
    fgets(STDIN);
    global $exit;
    $exit = true;
    exit();
}

function getexit(){
    global $exit;
    return $exit;
}


#lobapi
class LobiAPI{
    private $NetworkAPI = null;

    /**
     * LobiAPI constructor.
     */
    public function __construct(){
        $this->NetworkAPI = new Http();
    }

    /**
     * @param $mail
     * @param $password
     * @return bool
     */
    public function Login($mail, $password){

        $source = $this->NetworkAPI->get('https://lobi.co/signin');
        $csrf_token = Pattern::get_string($source, Pattern::$csrf_token, '"');

        $post_data = sprintf('csrf_token=%s&email=%s&password=%s', $csrf_token, $mail, $password);

        return strpos($this->NetworkAPI->post('https://lobi.co/signin', $post_data), 'ログインに失敗しました') === false;
    }

    public function TwitterLogin($mail, $password){

        $source = $this->NetworkAPI->get('https://lobi.co/signup/twitter');
        $authenticity_token = Pattern::get_string($source, Pattern::$authenticity_token, '"');
        $redirect_after_login = Pattern::get_string($source, Pattern::$redirect_after_login, '"');
        $oauth_token = Pattern::get_string($source, Pattern::$oauth_token, '"');

        $post_data = 'authenticity_token=' . $authenticity_token . '&redirect_after_login=' . $redirect_after_login . '&oauth_token=' . $oauth_token . '&session%5Busername_or_email%5D=' . $mail . '&session%5Bpassword%5D=' . $password;

        $source2 = $this->NetworkAPI->post('https://api.twitter.com/oauth/authorize', $post_data);
        if(strpos($source2, 'Twitterにログイン') !== false)
            return false;

        return strpos($this->NetworkAPI->get(Pattern::get_string($source2, Pattern::$twitter_redirect_to_lobi, '"')), 'ログインに失敗しました') === false;
    }

    public function GetMe(){

        return json_decode($this->NetworkAPI->get('https://web.lobi.co/api/me?fields=premium'),true);
    }

    public function GetPublicGroupList(){

        $result = array();

        $index = 1;
        while(true){
            $pg = json_decode($this->NetworkAPI->get("https://web.lobi.co/api/public_groups?count=1000&page=$index&with_archived=1"),true);
            $index++;
            if(count($pg[0]["items"]) == 0)
                break;
            foreach($pg as $pgbf)
                $result[] = $pg;
        }

        return $result;
    }

    public function GetPrivateGroupList(){

        $result = array();

        $index = 1;
        while(true){
            $pg = json_decode($this->NetworkAPI->get("https://web.lobi.co/api/groups?count=1000&page=$index"),true);
            $index++;
            if(count($pg[0]["items"]) == 0)
                break;
            foreach($pg as $pgbf)
                $result[] = $pg;
        }

        return $result;
    }

    public function GetNotifications(){

        return json_decode($this->NetworkAPI->get('https://web.lobi.co/api/info/notifications?platform=any'),true);
    }

    public function GetContacts($uid){

        return json_decode($this->NetworkAPI->get("https://web.lobi.co/api/user/$uid/contacts"),true);
    }

    public function GetFollowers($uid){

        return json_decode($this->NetworkAPI->get("https://web.lobi.co/api/user/$uid/followers"),true);
    }

    public function GetGroup($uid){

        return json_decode($this->NetworkAPI->get("https://web.lobi.co/api/group/$uid?error_flavor=json2&fields=group_bookmark_info%2Capp_events_info"),true);
    }

    public function GetGroupMembersCount($uid){;

        $result = json_decode($this->NetworkAPI->get("https://web.lobi.co/api/group/$uid?error_flavor=json2&fields=group_bookmark_info%2Capp_events_info"),true);
        if(!isset($result->members_count))
            return 0;
        if($result->members_count == null)
            return 0;
        return $result->members_count;
    }

    public function GetGroupMembers($uid){

        $result = array();
        $next = '0';
        $limit = 10000;
        while($limit-- > 0){
            $g = json_decode($this->NetworkAPI->get("https://web.lobi.co/api/group/$uid?members_cursor=$next"),true);
            foreach($g->members as $m)
                $result[] = $m;
            if($g->members_next_cursor == 0)
                break;
            $next = $g->members_next_cursor;
        }

        return $result;
    }

    public function GetThreads($uid, $count = 20){

        return json_decode($this->NetworkAPI->get("https://web.lobi.co/api/group/$uid/chats?count=$count"),true);
    }

    public function GetReplies($uid,$chatid){


        return json_decode($this->NetworkAPI->get("https://web.lobi.co/api/group/$uid/chats/replies?to=$chatid"),true);
    }

    public function Goo($group_id, $chat_id){

        $data = array('test'=>'test_content');

        $this->NetworkAPI->post('https://web.lobi.co/api/group/$group_id/chats/like', $data);
    }

    public function UnGoo($group_id, $chat_id){

        $data = array('id' => $chat_id);

        $this->NetworkAPI->post("https://web.lobi.co/api/group/$group_id/chats/unlike", $data);
    }

    public function Boo($group_id, $chat_id){


        $data = array('id' => $chat_id);

        $this->NetworkAPI->post("https://web.lobi.co/api/group/$group_id/chats/boo", $data);
    }

    public function UnBoo($group_id, $chat_id){

        $data = array('id' => $chat_id);

        $this->NetworkAPI->post("https://web.lobi.co/api/group/$group_id/chats/unboo", $data);
    }

    public function Follow($user_id){

        $data = array('users' => $user_id);

        $this->NetworkAPI->post("https://web.lobi.co/api/me/contacts", $data);
    }

    public function UnFollow($user_id){

        $data = array('users' => $user_id);

        $this->NetworkAPI->post("https://web.lobi.co/api/me/contacts/remove", $data);
    }

    public function MakeThread($group_id, $message, $shout = false){

        $data = array(
            'type' => $shout ? 'shout' : 'normal',
            'lang' => 'ja',
            'message' => $message
        );

        $this->NetworkAPI->post("https://web.lobi.co/api/group/$group_id/chats", $data);
    }

    public function Reply($group_id, $thread_id, $message){

        $data = array(
            'type' => 'normal',
            'lang' => 'ja',
            'message' => $message,
            'reply_to' => $thread_id
        );

        $this->NetworkAPI->post("https://web.lobi.co/api/group/$group_id/chats", $data);
    }

    public function MakePrivateGroup($user_id){

        $data = array('user' => $user_id);

        $this->NetworkAPI->post('https://web.lobi.co/api/groups/1on1s', $data);
    }

    public function ChangeProfile($name, $description){
        $data = array(
            'name' => $name,
            'description' => $description
        );

        $this->NetworkAPI->post("https://web.lobi.co/api/me/profile", $data);
    }
}

class Pattern{
    public static $csrf_token = '<input type="hidden" name="csrf_token" value="';
    public static $authenticity_token = '<input name="authenticity_token" type="hidden" value="';
    public static $redirect_after_login = '<input name="redirect_after_login" type="hidden" value="';
    public static $oauth_token = '<input id="oauth_token" name="oauth_token" type="hidden" value="';
    public static $twitter_redirect_to_lobi = '<a class="maintain-context" href="';
    public static function get_string($source, $pattern, $end_pattern){
        $start = strpos($source, $pattern) + strlen($pattern);
        $end = strpos($source, $end_pattern, $start + 1);
        return substr($source, $start, $end - $start);
    }
}

class Http{
    public $cookie_path;

    public function __construct($cookie_file_path = ''){
        $path = ($cookie_file_path == '' ? 'cookie.txt' : $cookie_file_path);
        if(file_exists($path))
            unlink($path);
        touch($path);
        $this->cookie_path = $path;
    }

    public function get($url){
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        $req_header = array();

        $req_header[] = 'Connection: keep-alive';

        $req_header[] = 'Accept: ' . 'application/json, text/plain, */*';

        $req_header[] = 'User-Agent: ' . 'Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/49.0.2623.110 Safari/537.36';

        $req_header[] = 'Accept-Language: ' . 'ja,en-US;q=0.8,en;q=0.6';
        if(count($req_header) > 0)
            curl_setopt($ch, CURLOPT_HTTPHEADER, $req_header);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookie_path);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookie_path);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }

    public function post($url, $data){
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        $req_header = array();

        $req_header[] = 'Connection: keep-alive';

        $req_header[] = 'Accept: ' . 'application/json, text/plain, */*';

        $req_header[] = 'User-Agent: ' . 'Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/49.0.2623.110 Safari/537.36';

        $req_header[] = 'Accept-Language: ' . 'ja,en-US;q=0.8,en;q=0.6';
        curl_setopt($ch, CURLOPT_HTTPHEADER, $req_header);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookie_path);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookie_path);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }
}

?>
