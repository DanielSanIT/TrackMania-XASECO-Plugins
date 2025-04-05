<?php

/*
 * *****************************************
 * VERSION 0.1a FOR MANIA PLANET SERVER ONLY
 * *****************************************
 * 
 * Удалена поддержка плагина iProp
 * 
 * 
 * Версия 0.4a - 2011.08.29
 * Работает c Xaseco v1.13  от 2011,07,14
 * Отредактирована by DanielSan (www.dartx.net)
 * 
 * Добавлена поддержка плагина iProp
 * 
 * 
 * Версия 0.3b - 2011.07.19
 * Работает c Xaseco v1.13  от 2011,07,14
 * Отредактирована by DanielSan (www.dartx.net)
 * 
 * Доработана функция резервного копирования.
 * 
 * * Добавте седующие прова в adminops.xml , значения по выбору:
 * * <dbtools>true</dbtools>
 * 
 * * Функция восстановления данных ->
 * * Добавлен подсчёт затраченного времени.
 * 
 * * Функция удаляющая игрока ->
 * * Сделано резервное копирование.
 * * Добавлен подсчёт времени.
 * * Дабавлен подсчёт удалённых строк.
 * 
 * ---------------------------------------------------------
 * Версия 0.2b - 2011.07.14
 * Работает c Xaseco v1.13  от 2011,07,14
 * Отредактирована by DanielSan (www.dartx.net)
 * 
 * Добавлена новая команда backuprecs которая позволяет восстановить данные
 * с последнего срабатывания команды по очистке базы. (на случай если удалилось чтото не то)
 * 
 * * Функция удаления неиспользуемых карт и сопутствующих им материалов ->
 * * Исправлен баг при котором не удалялись треки на которых небыло рекордов.
 * * Добавлена функция логирования/бекапа удалённых из базы строк,
 * * для этого требуется 4 дополнительных таблици в базе:
 * * xback_maps, xback_records, xback_rs_karma, xback_rs_times
 * * идентичные исходным таблицам. (в функции таблици создаются автоматически).
 * * Делать Бекап или нет настраивается переменной createbackup.
 */
define(createbackup, true);
/*
 * * Добавлена демонстрация количества удалённых строк.
 * * Добавлен подсчёт времени затраченного на обработку.
 * * Добавлено удаление рекордов, времён и голосов от несуществующих треков.
 * 
 * -----------------------------------------------------------
 * 
 * Database tools plugin for xaseco by Strobe (www.easy-day.net)
  2010.03.09 - Initial version 0.1
 */
Aseco::addChatCommand('cleanuprecs', 'Cleans up database - removes tracks which are not in tracklist', true);
Aseco::addChatCommand('delplayer', 'Removes all info about player from database. Usage: delplayer <login>', true);
Aseco::addChatCommand('restorebackup', 'Restores the latest data deleted command cleanuprecs', true);

function chat_cleanuprecs($aseco, $command) {
    $time = microtime(true);
    global $rasp;

    $event[0] = "fuckoff";
    clearMapsCache($aseco, $event); //rasp.funcs.php

    if (!dbtools_checkrights($aseco, $command))
        return false;

    $aseco->console_text('[RASP] Pruning records/rs_times for deleted tracks');
    $rasp->getMaps(); // from rasp.php
    $tracks = $rasp->maps;

    $res = mysql_query('SELECT ID FROM maps');
    $trackscount = 0;
    $recordscount = 0;
    $karmacount = 0;
    $timescount = 0;

    if (createbackup) // Создание или очистка таблиц для резервной копии
        create_clear_backup();

    // Перебор треков и удаление тех которых нет в листе сервера
    while ($row = mysql_fetch_row($res)) {
        $track = $row[0];
        // delete records & rs_times if it's not in server's challenge list
        if (!in_array($track, $tracks)) {
            $aseco->console_text('[RASP] ...MapId: ' . $track);

            if (createbackup) {
                $query = 'INSERT INTO xback_records(SELECT * FROM records WHERE MapId = ' . $track . ' )';
                mysql_query($query);
                $query = 'INSERT INTO xback_rs_times(SELECT * FROM rs_times WHERE MapId = ' . $track . ' )';
                mysql_query($query);
                $query = 'INSERT INTO xback_rs_karma(SELECT * FROM rs_karma WHERE MapId = ' . $track . ' )';
                mysql_query($query);
                $query = 'INSERT INTO xback_maps(SELECT * FROM maps WHERE ID = ' . $track . ' )';
                mysql_query($query);
            }

            $query = 'DELETE FROM records WHERE MapId=' . $track;
            mysql_query($query);
            $recordscount = $recordscount + mysql_affected_rows();

            $query = 'DELETE FROM rs_times WHERE MapId=' . $track;
            mysql_query($query);
            $timescount = $timescount + mysql_affected_rows();

            $query = 'DELETE FROM rs_karma WHERE MapId=' . $track;
            mysql_query($query);
            $karmacount = $karmacount + mysql_affected_rows();

            $query = 'DELETE FROM maps WHERE Id=' . $track;
            mysql_query($query);
            $trackscount++;

        }
    }

    // Удаление всякого шлака от треков которых нет в базе данных
    $query = 'INSERT INTO xback_records(SELECT * FROM records rst WHERE NOT EXISTS (SELECT id FROM maps cha WHERE cha.Id = rst.MapId))';
    mysql_query($query);
    $query = 'DELETE FROM records WHERE NOT EXISTS (SELECT id FROM maps WHERE Id = MapId)';
    mysql_query($query);
    $recordscount = $recordscount + mysql_affected_rows();

    $query = 'INSERT INTO xback_rs_times(SELECT * FROM rs_times tim WHERE NOT EXISTS (SELECT id FROM maps cha WHERE cha.Id = tim.MapId))';
    mysql_query($query);
    $query = 'DELETE FROM rs_times WHERE NOT EXISTS (SELECT id FROM maps WHERE Id = MapId)';
    mysql_query($query);
    $timescount = $timescount + mysql_affected_rows();

    $query = 'INSERT INTO xback_rs_karma (SELECT * FROM rs_karma kar WHERE NOT EXISTS (SELECT id FROM maps cha WHERE cha.Id = kar.MapId))';
    mysql_query($query);
    $query = 'DELETE FROM rs_karma WHERE NOT EXISTS (SELECT id FROM maps WHERE Id = MapId)';
    mysql_query($query);
    $karmacount = $karmacount + mysql_affected_rows();



    mysql_free_result($res);

    $time = microtime(true) - $time;

    $message = "{#server}> $trackscount tracks, $recordscount records, $timescount times, $karmacount karma,";
    $message .= " deleted in " . round($time, 3) . " seconds";
    $aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $command['author']->login);
    return true;
}

//chat_cleanuprecs

function chat_restorebackup($aseco, $command) {
//Восстанавливает бекап обратно в главную базу
    $time = microtime(true);
    if (!dbtools_checkrights($aseco, $command))
        return false;

    $trackscount = 0;
    $recordscount = 0;
    $karmacount = 0;
    $timescount = 0;
    $plextra = 0;
    $rank = 0;
    $players = 0;

    $query = 'INSERT INTO rs_karma(SELECT * FROM xback_rs_karma WHERE 1)';
    mysql_query($query);
    $karmacount = mysql_affected_rows();

    $query = 'INSERT INTO records(SELECT * FROM xback_records WHERE 1)';
    mysql_query($query);
    $recordscount = mysql_affected_rows();

    $query = 'INSERT INTO maps(SELECT * FROM xback_maps WHERE 1)';
    mysql_query($query);
    $trackscount = mysql_affected_rows();

    $query = 'INSERT INTO rs_times(SELECT * FROM xback_rs_times WHERE 1)';
    mysql_query($query);
    $timescount = mysql_affected_rows();
///    
    $query = 'INSERT INTO players_extra(SELECT * FROM xback_players_extra WHERE 1)';
    mysql_query($query);
    $plextra = mysql_affected_rows();

    $query = 'INSERT INTO rs_rank(SELECT * FROM xback_rs_rank WHERE 1)';
    mysql_query($query);
    $rank = mysql_affected_rows();

    $query = 'INSERT INTO players(SELECT * FROM xback_players WHERE 1)';
    mysql_query($query);
    $players = mysql_affected_rows();



    $time = microtime(true) - $time;

    $message = "{#server}> $trackscount tracks, $recordscount records, $timescount times, $karmacount karma, $plextra plextra, $rank rank, $players players,";
    $message .= " restored in " . round($time, 3) . " seconds";

    $aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $command['author']->login);
    return true;
}

//chat_backuprecs

function chat_delplayer($aseco, $command) {
    $time = microtime(true);
    $recordscount = 0;
    $karmacount = 0;
    $timescount = 0;
    $plextra = 0;
    $rank = 0;
    global $rasp, $createbackup;

    $command['params'] = explode(' ', preg_replace('/ +/', ' ', $command['params']));

    if (!dbtools_checkrights($aseco, $command))
        return false;

    if ($command['params'][0] == '' || !isset($command['params'][0])) {
        $aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors("{#error}> Usage: /delplayer <login>"), $command['author']->login);
        return false;
    }

    $login = mysql_real_escape_string($command['params'][0]);

    $query = "SELECT ID FROM players WHERE Login='$login'";
    $res = mysql_query($query);

    if (!($row = mysql_fetch_row($res)))
        $aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors("{#error}> There is no player with login $login in database."), $command['author']->login);
    else {
        if (createbackup) {
            create_clear_backup();
            mysql_query("INSERT INTO xback_rs_times(SELECT * FROM rs_times WHERE PlayerID = " . $row[0] . ")");
            mysql_query("INSERT INTO xback_rs_rank(SELECT * FROM rs_rank WHERE PlayerID = " . $row[0] . ")");
            mysql_query("INSERT INTO xback_rs_karma(SELECT * FROM rs_karma WHERE PlayerID = " . $row[0] . ")");
            mysql_query("INSERT INTO xback_records(SELECT * FROM records WHERE PlayerID = " . $row[0] . ")");
            mysql_query("INSERT INTO xback_players_extra(SELECT * FROM players_extra WHERE PlayerID = " . $row[0] . ")");
            mysql_query("INSERT INTO xback_players(SELECT * FROM players WHERE ID = " . $row[0] . ")");
        }
        $aseco->console('Deleting player ' . $login . ' ...');


        $query = 'DELETE FROM rs_times WHERE PlayerID=' . $row[0];
        mysql_query($query);
        $rank = mysql_affected_rows();

        $query = 'DELETE FROM rs_rank WHERE PlayerID=' . $row[0];
        mysql_query($query);
        $timescount = mysql_affected_rows();

        $query = 'DELETE FROM rs_karma WHERE PlayerID=' . $row[0];
        mysql_query($query);
        $karmacount = mysql_affected_rows();

        $query = 'DELETE FROM records WHERE PlayerID=' . $row[0];
        mysql_query($query);
        $recordscount = mysql_affected_rows();

        $query = 'DELETE FROM players_extra WHERE PlayerID=' . $row[0];
        mysql_query($query);
        $plextra = mysql_affected_rows();

        $query = 'DELETE FROM players WHERE ID=' . $row[0];
        mysql_query($query);

        $aseco->console('Done!');
        $time = microtime(true) - $time;
        $aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors("{#server}> Player $login has been deleted with: $recordscount records, $timescount times, $karmacount karma, $plextra plextra, $rank rank in " . round($time, 3) . " seconds"), $command['author']->login);
    }

    return true;
}

//chat_delplayer

function create_clear_backup() {
    // ПРоверка на наличие таблиц, если нет - то создаём, если есть то очищаем

    $result = mysql_query("SHOW TABLES LIKE 'xback_maps'");
    if (!mysql_fetch_row($result))
        mysql_query("create table `xback_maps` as select * from `maps` where 0");
    else
        mysql_query('DELETE FROM xback_maps');

    $result = mysql_query("SHOW TABLES LIKE 'xback_records'");
    if (!mysql_fetch_row($result))
        mysql_query("create table `xback_records` as select * from `records` where 0");
    else
        mysql_query('DELETE FROM xback_records');

    $result = mysql_query("SHOW TABLES LIKE 'xback_rs_karma'");
    if (!mysql_fetch_row($result))
        mysql_query("create table `xback_rs_karma` as select * from `rs_karma` where 0");
    else
        mysql_query('DELETE FROM xback_rs_karma');

    $result = mysql_query("SHOW TABLES LIKE 'xback_rs_times'");
    if (!mysql_fetch_row($result))
        mysql_query("create table `xback_rs_times` as select * from `rs_times` where 0");
    else
        mysql_query('DELETE FROM xback_rs_times');
    /// + for players 
    $result = mysql_query("SHOW TABLES LIKE 'xback_rs_rank'");
    if (!mysql_fetch_row($result))
        mysql_query("create table `xback_rs_rank` as select * from `rs_rank` where 0");
    else
        mysql_query('DELETE FROM xback_rs_rank');
    $result = mysql_query("SHOW TABLES LIKE 'xback_players_extra'");
    if (!mysql_fetch_row($result))
        mysql_query("create table `xback_players_extra` as select * from `players_extra` where 0");
    else
        mysql_query('DELETE FROM xback_players_extra');
    $result = mysql_query("SHOW TABLES LIKE 'xback_players'");
    if (!mysql_fetch_row($result))
        mysql_query("create table `xback_players` as select * from `players` where 0");
    else
        mysql_query('DELETE FROM xback_players');

}

function dbtools_checkrights($aseco, $command) {
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
