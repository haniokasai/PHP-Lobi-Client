<?php
use LobiClientPHP\LobiAPI\LobiAPI;

is_readable("motd")?print(file_get_contents("motd")):null;
require_once("LobiAPI.php");

global $api;
$api = new LobiAPI();

global $exit;
$exit = false;

global $currentgp, $currentgpn, $currentchat, $currentchatxt, $gplist, $pgplist, $notifi;
$currentgp = $currentgpn = $currentchat = $currentchatxt = $gplist = $pgplist = $notifi = null;

logintask();

loader();



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
    if ($ary[0] != "exit") {
      loader();
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



?>