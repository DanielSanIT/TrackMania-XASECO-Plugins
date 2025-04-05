<?php

/*
 * ---------------------------------------------------------
 * 
 * trackprop plugin for xaseco by DanielSan (www.dartx.net)
 *
 * 2011.07.31 - Initial version 0.1b
 * Небольшие косметические исправления
 * Хелп.
 *
 * 2011.07.20 - Initial version 0.1a
 * 
 * Плагин позволяет добавлять и просматривать информацию о трассах.
 */



Aseco::addChatCommand('iProp', 'Info about track.', true);

function iProp_messages($aseco, $command, $message, $where) {
    switch ($where) {
        case 0:
            $aseco->console_text("[iProp] $message ");
            break;
        case 1:
            $aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors("{#server}> $message"), $command['author']->login);
            break;
    }
    return TRUE;
}

function iProp_help($aseco, $command) {
    iProp_messages($aseco, $command, "Type '/iProp' for track info", 1);
    if(iprop_checkrights($aseco, $command)){
        iProp_messages($aseco, $command, "Type '/iProp class (fs, tech, stech,)'  for set track class", 1);
        iProp_messages($aseco, $command, "Type '/iProp dirt (%)'  for set track dirt count", 1);
        iProp_messages($aseco, $command, "Type '/iProp loop (yes/no)'  for set loop", 1);
        iProp_messages($aseco, $command, "Type '/iProp diff (1,2,3,4,5)'  for set track difficult", 1);
    }
}

function iProp_dbcreate($aseco, $command) {
    // Попытаемся создать базу на случай если её нет
    mysql_query("CREATE TABLE IF NOT EXISTS `challenges_prop` (
  `challengeid` int(11) NOT NULL,
  `class` tinyint(4) DEFAULT NULL,
  `difficult` tinyint(4) DEFAULT NULL,
  `dirt` tinyint(4) DEFAULT NULL,
  `loop` tinyint(1) DEFAULT NULL,
  UNIQUE KEY `challengeid` (`challengeid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;");
}

function chat_iProp($aseco, $command) {
    // Очищаем параметры

    $command['params'] = explode(' ', preg_replace('/ +/', ' ', $command['params']));

    iProp_dbcreate($aseco, $command);

    // Если запрос пустой, вернуть свойства трека
    if ($command['params'][0] == '' || !isset($command['params'][0])) {
        $thistrackid = $aseco->server->challenge->id;
        if (iProp_checkdb($aseco)) {
            $result = mysql_query("SELECT * FROM `challenges_prop` WHERE `challengeid` = '$thistrackid' ");
            $row = mysql_fetch_array($result);
            switch ($row['class']) {
                case 0:
                    $class = "FullSpeed";
                    break;
                case 1:
                    $class = "Techno";
                    break;
                case 2:
                    $class = "SpeedTechno";
                    break;
            }
            if($row['loop'] == 1)
                $loop = 'yes';
            else
                $loop = 'no';
            iProp_messages($aseco, $command, "class: $class, difficult = " . $row['difficult'] . ", dirt = " . $row['dirt'] . "%, loop = $loop.", 1);
        } else
            iProp_messages($aseco, $command, "No properties for this track!", 1);
        return TRUE;
    }
    // Если запрос help то вернуть хелпы
    if ($command['params'][0] == 'help') {
        if (!iprop_checkrights($aseco, $command))
            return false;
        iProp_help($aseco, $command);
        return TRUE;
    }
    // Если запрос на добавление класса трека
    if ($command['params'][0] == 'class') {
        if (!iprop_checkrights($aseco, $command))
            return false;
        $thistrackid = $aseco->server->challenge->id;
        switch ($command['params'][1]) {
            case "fs":
                iProp_messages($aseco, $command, "Set class: FullSpeed", 1);
                if (!iProp_checkdb($aseco))
                    mysql_query("INSERT INTO `challenges_prop` (challengeid, class) VALUES ($thistrackid,0)");
                else
                    mysql_query("UPDATE `challenges_prop` SET class = '0' WHERE challengeid = '$thistrackid'");
                break;
            case "tech":
                iProp_messages($aseco, $command, "Set class: Techno", 1);
                if (!iProp_checkdb($aseco))
                    mysql_query("INSERT INTO `challenges_prop` (challengeid, class) VALUES ($thistrackid,1)");
                else
                    mysql_query("UPDATE `challenges_prop` SET class = '1' WHERE challengeid = '$thistrackid'");
                break;
            case "stech":
                iProp_messages($aseco, $command, "Set class: SpeedTechno", 1);
                if (!iProp_checkdb($aseco))
                    mysql_query("INSERT INTO `challenges_prop` (challengeid, class) VALUES ($thistrackid,2)");
                else
                    mysql_query("UPDATE `challenges_prop` SET class = '2' WHERE challengeid = '$thistrackid'");
                break;
            default :
                iProp_messages($aseco, $command, "Wrong class!!!", 1);
                break;
        }


        return TRUE;
    }

    if ($command['params'][0] == 'diff') {
        if (!iprop_checkrights($aseco, $command))
            return false;

        $thistrackid = $aseco->server->challenge->id;
        iProp_messages($aseco, $command, "Set difficult: " . $command['params'][1], 1);
        if (!iProp_checkdb($aseco))
            mysql_query("INSERT INTO `challenges_prop` (challengeid, difficult) VALUES ($thistrackid," . $command['params'][1] . ")");
        else
            mysql_query("UPDATE `challenges_prop` SET difficult = '" . $command['params'][1] . "' WHERE challengeid = '$thistrackid'");
        return TRUE;
    }

    if ($command['params'][0] == 'dirt') {
        if (!iprop_checkrights($aseco, $command))
            return false;

        $thistrackid = $aseco->server->challenge->id;
        iProp_messages($aseco, $command, "Set dirt: " . $command['params'][1] . "%", 1);
        if (!iProp_checkdb($aseco))
            mysql_query("INSERT INTO `challenges_prop` (challengeid, dirt) VALUES ($thistrackid," . $command['params'][1] . ")");
        else
            mysql_query("UPDATE `challenges_prop` SET dirt = '" . $command['params'][1] . "' WHERE challengeid = '$thistrackid'");
        return TRUE;
    }

    if ($command['params'][0] == 'loop') {
        if (!iprop_checkrights($aseco, $command))
            return false;
        $loop = 0;
        if ($command['params'][1] == "yes")
            $loop = 1;
        $thistrackid = $aseco->server->challenge->id;
        iProp_messages($aseco, $command, "Set loop: $loop", 1);
        if (!iProp_checkdb($aseco))
            mysql_query("INSERT INTO `challenges_prop` (`challengeid`, `loop`) VALUES ('$thistrackid', '$loop')");
        else
            mysql_query("UPDATE `challenges_prop` SET `loop` = '$loop' WHERE `challengeid` = '$thistrackid'");
        return TRUE;
    }
}


function iProp_checkdb($aseco) {
    $thistrackid = $aseco->server->challenge->id;
    $result = mysql_query("SELECT * FROM `challenges_prop` WHERE challengeid = '$thistrackid'");
    if (mysql_numrows($result) == 0)
        return FALSE;
    else
        return true;
}

// Проверка прав пользователя
function iprop_checkrights($aseco, $command) {
    global $rasp;
    $admin = $command['author'];
    $login = $admin->login;
    if ($aseco->isMasterAdmin($admin)) {
        
    } else {
        if ($aseco->isAdmin($admin) && $aseco->allowAdminAbility('dbtools')) {
            
        } else {
            if ($aseco->isOperator($admin) && $aseco->allowOpAbility('dbtools')) {
                
            } else {
                // write warning in console
                $aseco->console($login . ' tried to use dbtools (no permission!) ');
                // show chat message
                $aseco->client->query('ChatSendToLogin', $aseco->formatColors('{#error}You don\'t have the required admin rights to do that!'), $login);
                return false;
            }
        }
    }
    // check for unlocked password (or unlock command)
    if ($aseco->settings['lock_password'] != '' && !$admin->unlocked) {
        // write warning in console
        $aseco->console($login . ' tried to use dbtools (not unlocked!) ');
        // show chat message
        $aseco->client->query('ChatSendToLogin', $aseco->formatColors('{#error}You don\'t have the required admin rights to do that!'), $login);
        return false;
    }
    return true;
}

?>
