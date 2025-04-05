<?php

/*
 * ---------------------------------------------------------
 * 
 * iJuke plugin for xaseco by DanielSan (www.dartx.net)
 * 
 * 

 * 
 * 2011.09.26 - Initial version 0.4a
 * 
 * Добавлена поддержка ManiaPlanet серверов (Изменены запросы к базе данных, скоректированы запросы к серверу)
 *  
 * 2011.08.10 - Initial version 0.3a
 *  
 * 
 *
 * Изменён способ конфигурации плагина.
 * Исправлен способ отладки. Больше отладки.
 * Исправлен баг с добавление нулевого трека.
 * Исправлена невидиние плагином треков без рекордов.
 * Исправлен баг - при старте плагина в iProp аггоритме по нулям за каждый класс.
 *
 * Добавлена интеграция с плагином iProp
 * Возможно ограничивать повторяемость некоторых классов треков.
 * 
 * Добавлено: при отборе треков с плохими рекордами можно отбрасывать треки за которые поставлено --
 * 
 * 2011.07.25 - Initial version 0.2b
 * 
 * Исправлена ошибка с подбором плохих треков.
 * Исправлен массовый вывод сообщений в лог файл.
 * Исправлен подбор игроков с учётом спектатов. 
 * 
 * 2011.07.25 - Initial version 0.2a
 * 
 * Улучшена выдача карт для случаев когда есть игроки у которых много рекордов,
 * для них будут предлагаться карты с их худшими рекордами.
 * 
 * Функция удаления игрока:
 *  Теперь игрок откладывается в отдельную переменную для отдельного поиска.
 * 
 * Функция поиска игроков:
 *  Спектаторы не учитываются в списке игроков.
 * 
 * 2011.07.20 - Initial version 0.1b
 * 
 * Плагин для поиска подходящих треков для текущих игроков на сервере.
 * Ищутся треки на которых нет рекордов для текущих игроков на сервере.
 * Если для текущих игроков нет треков, то из списка удаляется игрок с самым большим количеством рекордов.
 * Из списка доступных треков выбирается трек с наименьшим количеством рекордов.
 * 
 * 
 * Имеется функция для создания лога работы плагина в базе данных.
 * Если лог включён, то автоматически создастся таблица в базе данных.
 */

// Options:
// autostart Будет ли запускаться автоматически
// timeleft Время после старта карты когда запуститься поиск карты.
// usechatcommand Запускать ли чат командой
// algorythm2 использоваь ли алгоритм с поиском плохих рекордов на треках
// worstlimit количество плохих рекордов в поиске
// dblog делать ли лог в базу данных
// debuglevel уровень отладки: 0 - мало информации, 1 - информация в лог xaseco, 2 - в лог xaseco и чат игры.
// use_iProp использовать плагин i_prop для рандомизации карт
// tm_version - не трогать - записывается версия игры



global $iJukeOptions;
$iJukeOptions = array('botname' => "[iJuke][bot v.0.3.a]", 'autostart' => false, 'timeleft' => 30, 'usechatcommand' => true,
    'algorythm2' => false, 'worstlimit1' => 2, 'worstlimit2' => 3, 'nobadkarma' => true,
    'dblog' => true, 'debuglevel' => 2,
    'use_iProp_trackclass_limiter' => true, 'iProp_FS_limit' => 1, 'iProp_tech_limit' => 0, 'iProp_Stech_limit' => 0,
    'tm_version');

Aseco::registerEvent('onSync', 'iJuke_getserverversion');

if ($iJukeOptions['use_iProp_trackclass_limiter']) { // Интеграция с плагином plugin.iProp.php
    Aseco::registerEvent('onNewChallenge', 'iJuke_iProp_start');
    global $iJukeTrackClassCounter;
    $iJukeTrackClassCounter = array('fs' => 0, 'tech' => 0, 'stech' => 0);
}

if ($iJukeOptions['usechatcommand']) {
    Aseco::addChatCommand('iJuke', 'Find and put in jukebox relevant track', true);
}

if ($iJukeOptions['autostart']) {
    Aseco::registerEvent('onEverySecond', 'iJuke_autosecond');
    Aseco::registerEvent('onNewChallenge', 'iJuke_autostart');
}

$playeridlist;
$deletedplayers;
$challengeidlist;
$worstchallenges;
$errorcode;
$finalchallenge;
$worstchlist;
$iJuke_time = null;

function iJuke_getserverversion($aseco, $data) {
    global $iJukeOptions;
    $aseco->client->query('GetVersion');
    $temp = $aseco->client->getResponse();
    $iJukeOptions['tm_version'] = $temp['Name'];
}

function chat_iJuke($aseco, $command) {
    iJuke_go($aseco);
}

function iJuke_iProp_start($aseco, $challenge_item) {
    global $iJukeTrackClassCounter;

    // Если только стартовал плагин
    if ($iJukeTrackClassCounter['tech'] == 0 && $iJukeTrackClassCounter['stech'] == 0 && $iJukeTrackClassCounter['fs'] == 0) {
        $iJukeTrackClassCounter['tech'] = 100;
        $iJukeTrackClassCounter['stech'] = 100;
        $iJukeTrackClassCounter['fs'] = 100;
    }


    $result = mysql_query("SELECT * FROM `challenges_prop` WHERE challengeid = '" . $challenge_item->id . "'");
    if (mysql_num_rows($result) < 1) {
        iJuke_messages($aseco, $command, "No info about this track.", 3);
        return false;
    }
    $row = mysql_fetch_array($result);
    // Сколько карт небыло такого вида трека
    switch ($row['class']) {
        case 0: // FS
            iJuke_messages($aseco, $command, "This is Full Speed", 3);
            $iJukeTrackClassCounter['tech']++;
            $iJukeTrackClassCounter['stech']++;
            $iJukeTrackClassCounter['fs'] = 0;
            return true;
        case 1: // Techno
            iJuke_messages($aseco, $command, "This is Techno", 3);
            $iJukeTrackClassCounter['tech'] = 0;
            $iJukeTrackClassCounter['fs']++;
            $iJukeTrackClassCounter['stech']++;
            return true;
        case 2: // Speed Techno
            iJuke_messages($aseco, $command, "This is Speed Techno", 3);
            $iJukeTrackClassCounter['stech'] = 0;
            $iJukeTrackClassCounter['tech']++;
            $iJukeTrackClassCounter['fs']++;
            return true;
        case 3: // Null
            iJuke_messages($aseco, $command, "This is Null", 3);
            $iJukeTrackClassCounter['stech']++;
            $iJukeTrackClassCounter['tech']++;
            $iJukeTrackClassCounter['fs']++;
            return false;
        default:
            iJuke_messages($aseco, $command, "This is Unknow", 3);
            return false;
    }
}

function iJuke_autostart($aseco, $challenge_item) {
    global $iJuke_time;
    $iJuke_time = time();
    iJuke_messages($aseco, $command, "Prepare to start " . $iJukeOptions['botname'], 3);
}

function iJuke_autosecond($aseco, $command) {
    global $iJuke_time, $test_time, $iJukeOptions;
    $test_time = time() - $iJuke_time;
    if ($iJuke_time != null && time() == $iJuke_time + $iJukeOptions['timeleft']) {
        iJuke_go($aseco);
        $iJuke_time = null;
    }
}

function iJuke_messages($aseco, $command, $message, $where) {
    global $iJukeOptions;
    switch ($where) {
        case 0:
            $aseco->console_text($iJukeOptions['botname'] . " $message");
            if ($iJukeOptions['debuglevel'] == 2)
                $aseco->client->query('ChatSendServerMessage', $aseco->formatColors("{#server}> " . $iJukeOptions['botname'] . " " . $message));
            break;
        case 1:
            $aseco->client->query('ChatSendServerMessage', $aseco->formatColors("{#server}> " . $iJukeOptions['botname'] . " " . $message));
            if ($iJukeOptions['debuglevel'] == 1)
                $aseco->console_text($iJukeOptions['botname'] . " $message");
            break;
        case 2:
            $aseco->client->query('ChatSendServerMessage', $aseco->formatColors("{#server}> " . $iJukeOptions['botname'] . " " . $message));
            $aseco->console_text($iJukeOptions['botname'] . " $message");
            break;
        case 3:
            if ($iJukeOptions['debuglevel'] == 2) {
                $aseco->client->query('ChatSendServerMessage', $aseco->formatColors("{#server}> " . $iJukeOptions['botname'] . " " . $message));
                $aseco->console_text($iJukeOptions['botname'] . " $message");
            } elseif ($iJukeOptions['debuglevel'] == 1)
                $aseco->console_text($iJukeOptions['botname'] . " $message");
            break;
    }
    return TRUE;
}

function iJuke_errors($aseco, $command, $errorcode) {
    switch ($errorcode) {
        case 0:
            iJuke_messages($aseco, $command, "No players!", 0);
            return TRUE;
        case 1:
            iJuke_messages($aseco, $command, "No tracks for current players! [db]", 0);
            return TRUE;
        case 2:
            iJuke_messages($aseco, $command, "No tracks for current players! [db]+[TM]+[history]", 0);
            return TRUE;
        case 3:
            iJuke_messages($aseco, $command, "Jukebox not empty!", 0);
            return TRUE;
    }
}

function iJuke_dblog($aseco, $call, $algorytm, $plcount, $restcnt, $finalchallenge, $time) {
    mysql_query("CREATE TABLE IF NOT EXISTS `iJuke_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `datetime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `call` tinyint(4) NOT NULL,
  `algorythm` tinyint(4) NOT NULL,
  `playercnt` smallint(6) NOT NULL,
  `restcnt` smallint(6) NOT NULL,
  `mapid` int(11) NOT NULL,
  `time` float NOT NULL,
  KEY `id` (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=15 ;");

    mysql_query("INSERT INTO `iJuke_log` (`call`, `algorythm`, `playercnt`, `restcnt`, `mapid`, `time`) VALUES ('$call','$algorytm','" . $plcount . "', '" . $restcnt . "', '" . $finalchallenge . "', '" . $time . "')");
}

function iJuke_go($aseco) {
    $time = microtime(true);

    iJuke_messages($aseco, $command, 'Serch for new track!', 0);

    $trueorfalse = true;

    $restcnt = 0; // счётчик рестартов поиска мапы

    global $rasp, $playeridlist, $challengeidlist, $errorcode, $finalchallenge, $worstchlist;
    global $jukebox, $jukebox_adminnoskip, $deletedplayers, $worstchallenges, $iJukeOptions;




    $playeridlist = array();
    $deletedplayers = array();
    $challengeidlist = array();
    $worstchallenges = array();
    $errorcode = null;
    $finalchallenge = null;

// Проверка на занятость джукбокса
    if (!empty($jukebox)) {
        iJuke_errors($aseco, $command, 3);
        $trueorfalse = false;
    }

// Получаем список ID игроков
    if ($trueorfalse)
        if (!iJuke_searchplayers($aseco, $command)) {
            iJuke_errors($aseco, $command, $errorcode);
            $trueorfalse = false;
        }
// Получаем список ID треков
    if ($trueorfalse) {
        $delplayer = false;
        $trysearch = true;
        $limiter = 20;
        while ($trysearch && $limiter > 0) {
            $limiter--;
            if (!iJuke_searchchallenges($aseco, $command, $delplayer)) {
                iJuke_errors($aseco, $command, $errorcode);
                //if (count($playeridlist) > 1) {
                $delplayer = true;
                iJuke_messages($aseco, $command, "Try Search Again -1 player", 0);
                $restcnt++;
                //} else {
                //    iJuke_messages($aseco, $command, "Epic Fail", 3);
                //    $trysearch = FALSE;
                //   $trueorfalse = false;
                //}
            } else
                $trysearch = FALSE;
        }
    }
    if ($limiter == 0) {
        iJuke_messages($aseco, $command, "Epic Fail", 3);
        $trueorfalse = false;
    }

    if ($trueorfalse) {
        if (count($challengeidlist) > 1) {
            $finalchallenge = iJuke_findonechallege($challengeidlist);
            $finalchallenge = iJuke_findonechallege($challengeidlist);
        } else {
            foreach ($challengeidlist as $value) {
                $finalchallenge = $value;
            }
        }

        iJuke_messages($aseco, $command, "Final Challenge ID $finalchallenge", 0);
    }
    if ($trueorfalse) {

// Добавление трека в jukebox
        $challengesCache;
        if ($iJukeOptions['tm_version'] == 'ManiaPlanet') {
            $challengesCache = getMapsCache($aseco);
        } else {
            $challengesCache = getChallengesCache($aseco);
        }

        $result = mysql_query("SELECT Uid FROM `challenges` WHERE Id = '$finalchallenge'");
        $row = mysql_fetch_row($result);
        $challenge = $challengesCache[$row[0]];
        $jukebox[$challenge['UId']]['FileName'] = $challenge['FileName'];
        $jukebox[$challenge['UId']]['Name'] = $challenge['Name'];
        $jukebox[$challenge['UId']]['Env'] = $challenge['Environnement'];
        $jukebox[$challenge['UId']]['Login'] = 'danielsan_it';
        $jukebox[$challenge['UId']]['Nick'] = $iJukeOptions['botname'];
        $jukebox[$challenge['UId']]['source'] = $iJukeOptions['botname'];
        $jukebox[$challenge['UId']]['tmx'] = FALSE;
        $jukebox[$challenge['UId']]['uid'] = $challenge['UId'];
        $jukebox_adminnoskip = TRUE;

        $message = formatText($rasp->messages['JUKEBOX'][0], stripColors($jukebox[$challenge['UId']]['Name']), stripColors($jukebox[$challenge['UId']]['source']));
        if ($jukebox_in_window && function_exists('send_window_message'))
            send_window_message($aseco, $message, false);
        else
            $aseco->client->query('ChatSendServerMessage', $aseco->formatColors($message));

        $time = microtime(true) - $time;
        iJuke_messages($aseco, $command, "Cmpleted in " . round($time, 3) . " seconds", 0);

        if ($iJukeOptions['dblog'])
            if (count($worstchallenges) > 0)
                iJuke_dblog($aseco, 0, 2, count($deletedplayers) + count($playeridlist), $restcnt, $finalchallenge, round($time, 3));
            else
                iJuke_dblog($aseco, 0, 1, count($deletedplayers) + count($playeridlist), $restcnt, $finalchallenge, round($time, 3));

        mysql_free_result($result);
        return true;
    }
    else {
        mysql_free_result($result);
        return FALSE;
    }
}

// Создание списка ID игроков которые присутствуют на сервере
function iJuke_searchplayers($aseco, $command) {
    global $playeridlist;
    global $errormessage;
    global $errorcode;
//Получаем список игроков от сервера
    $aseco->client->query('GetPlayerList', 300, 0);
    $players = $aseco->client->getResponse();
    foreach ($players as $key => &$player)
        if ($player['IsSpectator'])
            unset($players[$key]);

    if (count($players) == 0) {
        $errorcode = 0;
        return FALSE;
    } else {
        //Запрос на получение списка ID игроков
        $query = iJuke_requestform("SELECT `Id` FROM `players` WHERE `Login` in (", ")", $players, "Login");
        $result = mysql_query($query);
        while ($row = mysql_fetch_row($result)) {
            $playeridlist[] = $row[0]; // Добавляем ID в глобальную переменную
            iJuke_messages($aseco, $command, "PID $row[0]", 3);
        }
        mysql_free_result($result);
        iJuke_messages($aseco, $command, "Finded " . count($playeridlist) . " players!", 0);
        return TRUE;
    }
}

function iJuke_searchchallenges($aseco, $command, $delplayer) {
    global $rasp, $challengeidlist, $errorcode, $playeridlist, $jb_buffer;
    global $worstchallenges, $deletedplayers, $iJukeOptions;

    if ($delplayer) { // Если получена команда о удалении одного игрока
        iJuke_messages($aseco, $command, 'Request delete one player!', 0);
        $deletedplayer = iJuke_delplayer($aseco); // Удаляем игрока из глобального списка, и вносим в два других
        // Поиск плохих рекордов для данного игрока если включена опция
        if ($iJukeOptions['algorythm2']) {
            $query;
            if ($iJukeOptions['nobadkarma']) {

                if ($iJukeOptions['tm_version'] == 'ManiaPlanet') {

                    $query = "SELECT req.MapId
FROM ( SELECT rec.playerid, rec.MapId, (
		SELECT count(playerid) +1
		FROM records
		WHERE MapId = rec.MapId
		AND score < rec.score
	    )pos
	FROM records rec	
	GROUP BY playerid, MapId
	HAVING playerid = '$deletedplayer'
	ORDER BY `pos` DESC
LIMIT " . $iJukeOptions['worstlimit2'] . "
)req
LEFT OUTER JOIN rs_karma kar ON req.playerid = kar.playerid
AND req.MapId = kar.MapId
where ifnull(kar.Score, 0 ) <> -1
LIMIT " . $iJukeOptions['worstlimit1'];
                } else {

                    $query = "SELECT req.challengeid
FROM ( SELECT rec.playerid, rec.challengeid, (
		SELECT count(playerid) +1
		FROM records
		WHERE challengeid = rec.challengeid
		AND score < rec.score
	    )pos
	FROM records rec	
	GROUP BY playerid, challengeid
	HAVING playerid = '$deletedplayer'
	ORDER BY `pos` DESC
LIMIT " . $iJukeOptions['worstlimit2'] . "
)req
LEFT OUTER JOIN rs_karma kar ON req.playerid = kar.playerid
AND req.challengeid = kar.challengeid
where ifnull(kar.Score, 0 ) <> -1
LIMIT " . $iJukeOptions['worstlimit1'];
                }
            } else {
                // Иначе просто ище мтреки на удаление - наверное
                if ($iJukeOptions['tm_version'] == 'ManiaPlanet') {
                    $query = "select MapId from (select playerid
                    , MapId , (select count(playerid) + 1 from records where MapId = rec.MapId 
                    and score < rec.score) pos from records rec group by playerid, MapId 
                    HAVING playerid ='$deletedplayer' ORDER BY `pos` DESC LIMIT " . $iJukeOptions['worstlimit'] . ") req";
                } else {
                    $query = "select challengeid from (select playerid
                    , challengeid , (select count(playerid) + 1 from records where challengeid = rec.challengeid 
                    and score < rec.score) pos from records rec group by playerid, challengeid 
                    HAVING playerid ='$deletedplayer' ORDER BY `pos` DESC LIMIT " . $iJukeOptions['worstlimit'] . ") req";
                }
            }
            $result = mysql_query($query);

            while ($row = mysql_fetch_row($result))
                if (!in_array($row[0], $worstchallenges)) {
                    $worstchallenges[] = $row[0];
                    iJuke_messages($aseco, $command, "DELW_TID " . $row[0], 3);
                }
            // Поиск трасс без рекордов для этого игрока

            if ($iJukeOptions['tm_version'] == 'ManiaPlanet') {
                $query = "select ch.id from maps ch where not exists (select playerid from records where playerid in ('$deletedplayer') and MapId = ch.id)";
            } else {
                $query = "select ch.id from challenges ch where not exists (select playerid from records where playerid in ('$deletedplayer') and challengeid = ch.id)";
            }
            $result = mysql_query($query);
            while ($row = mysql_fetch_row($result))
                if (!in_array($row[0], $worstchallenges)) {
                    $worstchallenges[] = $row[0];
                    iJuke_messages($aseco, $command, "DELN_TID " . $row[0], 3);
                }
        }
    }

    if (count($playeridlist) == 0) // Если не осталось игроков в списке
        $tracksarray = $worstchallenges;
    else {
// Составляем список треков // Запрос на поиск треков где ни у кого нет рекордов
        if ($iJukeOptions['tm_version'] == 'ManiaPlanet') {
            $query = iJuke_requestform("select ch.id from maps ch where not exists (select playerid from records where playerid in (", ") and MapId = ch.id)", $playeridlist, NULL);
        } else {
            $query = iJuke_requestform("select ch.id from challenges ch where not exists (select playerid from records where playerid in (", ") and challengeid = ch.id)", $playeridlist, NULL);
        }
        $result = mysql_query($query);

        $tracksarray;
        while ($row = mysql_fetch_row($result)) // Перемещаем треки в массив
            $tracksarray[] = $row[0];
    }

    if (count($tracksarray) > 0) { // Если есть треки в базе то ищем доступные на сервере
// Ищем доступные треки
        $tracks;
        if ($iJukeOptions['tm_version'] == 'ManiaPlanet') {
            $rasp->getMaps(); // from rasp.php
            $tracks = $rasp->maps;
        } else {
            $rasp->getChallenges(); // from rasp.php
            $tracks = $rasp->challenges;
        }
        foreach ($tracksarray as $key => $value) {
            $track = $value;
            if (in_array($track, $tracks)) {// Если трек имеется на сервере, то проверим на нахождение в истории
                if ($iJukeOptions['tm_version'] == 'ManiaPlanet') {
                    $query = "SELECT Uid FROM `maps` WHERE Id = '$track'";
                } else {
                    $query = "SELECT Uid FROM `challenges` WHERE Id = '$track'";
                }
                $result2 = mysql_query($query);
                $trackuid = mysql_fetch_row($result2);
                if (!in_array($trackuid[0], $jb_buffer)) {
                    $challengeids[] = $value;
                    iJuke_messages($aseco, $command, "ALL_TID $value", 3);
                }
            }
        }
        // Если до этого удаляли игрока и работает алгоритм2, то провести поиск совпадений его плохих треков с найденными
        if ($iJukeOptions['algorythm2'])
            if ($delplayer) {
                foreach ($challengeids as $key => &$date) {
                    if (!in_array($date, $worstchallenges)) {
                        iJuke_messages($aseco, $command, "Killed TID " . $challengeids[$key], 3);
                        unset($challengeids[$key]); // Убираем несовпавшие треки
                    }
                }
            }

        // Если используем рандомизатор с базой iProp
        if ($iJukeOptions['use_iProp_trackclass_limiter']) {
            global $iJukeTrackClassCounter;
            $iPropNeedClasses = array(0 => 0, 1 => 1, 2 => 2);

// Если фс лимитировани
            if ($iJukeOptions['iProp_FS_limit'] > $iJukeTrackClassCounter['fs'])
                unset($iPropNeedClasses[0]);

// Если теч лимитирован
            if ($iJukeOptions['iProp_tech_limit'] > $iJukeTrackClassCounter['tech'])
                unset($iPropNeedClasses[1]);

// Если спидтеч лимитирован
            if ($iJukeOptions['iProp_Stech_limit'] > $iJukeTrackClassCounter['stech'])
                unset($iPropNeedClasses[2]);

            $query = iJuke_requestform("select challengeid from challenges_prop where challengeid in (", ") and not (class in (", $challengeids, NULL);
            $query .= iJuke_requestform("", "))", $iPropNeedClasses, NULL);
            $result = mysql_query($query);
// Убираем треки которые содержат ненужные классы
            while ($row = mysql_fetch_row($result))
                foreach ($challengeids as $key => &$date) {
                    if ($date == $row[0]) {
                        iJuke_messages($aseco, $command, "Killed like wrong class TID " . $challengeids[$key], 3);
                        unset($challengeids[$key]);
                    }
                }
        }

        if (count($challengeids) != 0) { // Если найдены треки
            $challengeidlist = $challengeids; // Заносим треки в глобальную переменную
            iJuke_messages($aseco, $command, "Finded " . count($challengeids) . " tracks!", 0);
            foreach ($challengeids as $value)
                iJuke_messages($aseco, $command, "END_TID $value", 3);
        } else {
            $errorcode = 2;
            mysql_free_result($result);
            return FALSE;
        }
    } else { // Если нет треков в базе то досвидос, у когото много рекордов...
        $errorcode = 1;
        mysql_free_result($result);
        return FALSE;
    }
    mysql_free_result($result);
    mysql_free_result($result2);

    return TRUE;
}

function iJuke_delplayer($aseco) {
    global $deletedplayers, $playeridlist;
    $deletedplayer;
    $command;
    iJuke_messages($aseco, $command, "DelPlayer started", 3);
// Поиск игрока у которого больше всего рекордов и удаение его из списка
    $query = iJuke_requestform("SELECT playerid FROM (SELECT playerid, COUNT(*) cnt FROM records GROUP BY playerid HAVING playerid in (", ")) reccount ORDER BY cnt DESC LIMIT 1", $playeridlist, NULL);

    $result = mysql_query($query);
    $row = mysql_fetch_row($result);
    iJuke_messages($aseco, $command, "Search player for kill", 3);
    // Поиск и удаление задрота из списка игроков
    foreach ($playeridlist as $key => &$PLID) {
        if ($PLID == $row[0]) {
            $deletedplayers[] = $playeridlist[$key]; // Сохраняем
            $deletedplayer = $playeridlist[$key]; // Записываем для отправки
            unset($playeridlist[$key]); // Удаляем
            iJuke_messages($aseco, $command, "DELETED_PID $deletedplayer", 3);
        }
    }
    mysql_free_result($result);
    return $deletedplayer;
}

function iJuke_findonechallege($challengeidlist) {
    $challengeidandrecscount;

    if ($iJukeOptions['tm_version'] == 'ManiaPlanet') {
        $query = iJuke_requestform("SELECT MapID, cnt FROM (SELECT MapID, COUNT(*) cnt FROM records  GROUP BY MapID HAVING MapID in (", ")) reccount", $challengeidlist, NULL);
    } else {
        $query = iJuke_requestform("SELECT challengeid, cnt FROM (SELECT challengeid, COUNT(*) cnt FROM records  GROUP BY challengeid HAVING challengeid in (", ")) reccount", $challengeidlist, NULL);
    }
    $result = mysql_query($query);
    // Создаём массив айди с количеством рекордов
    while ($row = mysql_fetch_row($result)) {
        $challengeidandrecscount[$row['challengeid']] = $row['cnt'];
    }
// Добавляем в массив треки без рекордов
    foreach ($challengeidlist as $value) {
        if (!isset($challengeidandrecscount[$value])) {
            $challengeidandrecscountid[$value] = 0;
        }
    }
    // Сортируем и берём cамое меньшее
    $minkey;
    $mincnt = 10000;
    foreach ($challengeidandrecscountid as $key => $value) {
        if ($mincnt > $value) {
            $minkey = $key;
        }
    }

    return $minkey;
}

function iJuke_requestform($begin, $end, $array, $param) {
    // Собирает запросы воедино // изучи функцию implode
    $query = $begin;

    //$coma = false;
    foreach ($array as $data) {
        if ($param == NULL)
            $insertion = $data;
        else
            $insertion = $data[$param];
        if ($coma)
            $query .= ",'" . $insertion . "'";
        else {
            $query .= "'" . $insertion . "'";
            $coma = true;
        }
    }
    $query .= $end;
    return $query;
}

// Проверка прав пользователя
//function iJuke_checkrights($aseco, $command) {
//    global $rasp;
//    $admin = $command['author'];
//    $login = $admin->login;
//    if ($aseco->isMasterAdmin($admin)) {
//        
//    } else {
//        if ($aseco->isAdmin($admin) && $aseco->allowAdminAbility('iJuke')) {
//            
//        } else {
//            if ($aseco->isOperator($admin) && $aseco->allowOpAbility('iJuke')) {
//                
//            } else {
//// write warning in console
//                $aseco->console($login . ' tried to use dbtools (no permission!) ');
//// show chat message
//                $aseco->client->query('ChatSendToLogin', $aseco->formatColors('{#error}You don\'t have the required admin rights to do that!'), $login);
//                return false;
//            }
//        }
//    }
//// check for unlocked password (or unlock command)
//    if ($aseco->settings['lock_password'] != '' && !$admin->unlocked) {
//// write warning in console
//        $aseco->console($login . ' tried to use dbtools (not unlocked!) ');
//// show chat message
//        $aseco->client->query('ChatSendToLogin', $aseco->formatColors('{#error}You don\'t have the required admin rights to do that!'), $login);
//        return false;
//    }
//    return true;
//}
?>
