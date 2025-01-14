<?php
/**
 * Created by PhpStorm.
 * User: Rhilip
 * Date: 2019/3/17
 * Time: 13:52
 */

namespace apps\task;

use Rid\Base\TaskInterface;
use apps\exceptions\TrackerException;


class TrackerAnnounceTask implements TaskInterface
{

    public function run($data)
    {
        app()->pdo->beginTransaction();
        try {
            $this->processAnnounceRequest($data['queries'], $data['role'], $data['userInfo'], $data['torrentInfo']);
            app()->pdo->commit();
        } catch (TrackerException $e) {
            app()->pdo->rollback();
            // TODO throw $e;
        } catch (\Exception $e) {
            app()->pdo->rollback();
            // TODO throw new TrackerException(998, [":msg" => $e->getMessage()]);
        }
    }

    /** TODO 2018.12.12 Check Muti-Tracker behaviour when a Transaction begin
     * @param $queries
     * @param $seeder
     * @param $userInfo
     * @param $torrentInfo
     * @throws TrackerException
     */
    private function processAnnounceRequest($queries, $seeder, $userInfo, $torrentInfo)
    {
        $timeKey = ($seeder == 'yes') ? 'seed_time' : 'leech_time';
        $torrentUpdateKey = ($seeder == 'yes') ? "complete" : "incomplete";
        $trueUploaded = $trueDownloaded = 0;
        $thisUploaded = $thisDownloaded = 0;

        [$ipField, $ipBindField] = $this->getIpField($queries);

        // Try to fetch session from Table `peers`
        $self = app()->pdo->createCommand("SELECT `uploaded`,`downloaded`,(NOW() - `last_action_at`) as `duration` 
        FROM `peers` WHERE `user_id`=:uid AND `torrent_id`=:tid AND `peer_id`=:pid LIMIT 1;")->bindParams([
            "uid" => $userInfo["id"], "tid" => $torrentInfo["id"], "pid" => $queries["peer_id"]
        ])->queryOne();

        if ($self === false) {
            // If session is not exist and &event!=stopped, a new session should start
            if ($queries['event'] != 'stopped') {
                // Then create new session in database
                // Update `torrents`, if peer's role is a seeder ,so complete +1 , elseif  he is a leecher , so incomplete +1
                app()->pdo->createCommand("UPDATE `torrents` SET `{$torrentUpdateKey}` = `{$torrentUpdateKey}` +1 WHERE id=:tid")->bindParams([
                    "tid" => $torrentInfo["id"]
                ])->execute();

                $trueUploaded = max(0, $queries['uploaded']);
                $trueDownloaded = max(0, $queries['downloaded']);

                app()->pdo->createCommand("INSERT INTO `peers` SET `user_id` =:uid, `torrent_id`= :tid, `peer_id`= :pid, 
                        `agent`=:agent, `seeder` = :seeder, {$ipField} , 
                        `uploaded` = :upload , `downloaded` = :download, `to_go` = :to_go,
                        `corrupt` = :corrupt , `key` = :key ;
                        ")->bindParams([
                        "uid" => $userInfo["id"], "tid" => $torrentInfo["id"], "pid" => $queries["peer_id"],
                        "agent" => $queries['user-agent'],
                        "upload" => $trueUploaded, "download" => $trueDownloaded, "to_go" => $queries["left"],
                        "seeder" => $seeder, "corrupt" => $queries["corrupt"], "key" => $queries["key"],
                    ] + $ipBindField)->execute();

                // Search history record, and create new record if not exist.
                $selfRecordCount = app()->pdo->createCommand("SELECT COUNT(`id`) FROM snatched WHERE user_id=:uid AND torrent_id = :tid")->bindParams([
                    "uid" => $userInfo["id"],
                    "tid" => $torrentInfo["id"]
                ])->queryScalar();

                if ($selfRecordCount == 0) {
                    app()->pdo->createCommand("INSERT INTO snatched (`user_id`,`torrent_id`,`agent`,`port`,`true_downloaded`,`true_uploaded`,`this_download`,`this_uploaded`,`to_go`,`{$timeKey}`,`create_at`,`last_action_at`) 
                        VALUES (:uid,:tid,:agent,:port,:true_dl,:true_up,:this_dl,:this_up,:to_go,:time,NOW(),NOW())")->bindParams([
                        "uid" => $userInfo["id"], "tid" => $torrentInfo["id"],
                        "agent" => $queries['user-agent'], "port" => $queries["port"],
                        "true_up" => 0, "true_dl" => 0,
                        "this_up" => 0, "this_dl" => 0,
                        "to_go" => $queries["left"], "time" => 0
                    ])->execute();
                }
            }
        } else {
            // So that , We can calculate Announce data on a exist session
            $trueUploaded = max(0, $queries['uploaded'] - $self['uploaded']);
            $trueDownloaded = max(0, $queries['downloaded'] - $self['downloaded']);
            $duration = max(0, $self['duration']);

            if (app()->config->get("tracker.enable_upspeed_check")) {
                if ($userInfo["class"] < app()->config->get("authority.pass_tracker_upspeed_check") && $duration > 0)
                    $this->checkUpspeed($userInfo, $torrentInfo, $trueUploaded, $trueDownloaded, $duration);
            }

            $this->getTorrentBuff($userInfo['id'], $torrentInfo["id"], $trueUploaded, $trueDownloaded, $thisUploaded, $thisDownloaded);

            // Update Table `peers`, `snatched` by it's event tag
            // Notice : there MUST have history record in Table `snatched` if session is exist !!!!!!!!
            if ($queries["event"] === "stopped") {
                // Update `torrents`, if peer's role is a seeder ,so complete -1 , elseif  he is a leecher , so incomplete -1
                app()->pdo->createCommand("UPDATE `torrents` SET `{$torrentUpdateKey}` = `{$torrentUpdateKey}` -1 WHERE id=:tid")->bindParams([
                    "tid" => $torrentInfo["id"]
                ])->execute();

                // Peer stop seeding or leeching and should remove this peer from our peer list and update his data.
                app()->pdo->createCommand("DELETE FROM `peers` WHERE `user_id` = :uid AND `torrent_id` = :tid AND `peer_id` = :pid")->bindParams([
                    "uid" => $userInfo["id"],
                    "tid" => $torrentInfo["id"],
                    "pid" => $queries["peer_id"]
                ])->execute();
            } else {
                // if session is exist but event!=stopped , we should continue the old session
                app()->pdo->createCommand("UPDATE `peers` SET `agent`=:agent, {$ipField}," .
                    "`seeder`=:seeder,
                    `uploaded`=`uploaded` + :uploaded, `downloaded`= `downloaded` + :download, `to_go` = :left,
                    `last_action_at`=NOW(), `corrupt`=:corrupt, `key`=:key 
                    WHERE `user_id` = :uid AND `torrent_id` = :tid AND `peer_id`=:pid")->bindParams([
                        "agent" => $queries['user-agent'], "seeder" => $seeder,
                        "uploaded" => $trueUploaded, "download" => $trueDownloaded, "left" => $queries["left"],
                        "corrupt" => $queries["corrupt"], "key" => $queries["key"],
                        "uid" => $userInfo["id"], "tid" => $torrentInfo["id"], "pid" => $queries["peer_id"]
                    ] + $ipBindField)->execute();
            }
            if (app()->pdo->getRowCount() > 0) {   // It means that the delete or update query affected so we can safety update `snatched` table
                app()->pdo->createCommand("UPDATE `snatched` SET `true_uploaded` = `true_uploaded` + :true_up,`true_downloaded` = `true_downloaded` + :true_dl,
                    `this_uploaded` = `this_uploaded` + :this_up, `this_download` = `this_download` + :this_dl, `to_go` = :left, `{$timeKey}`=`{$timeKey}` + :duration,
                    `agent` = :agent WHERE `torrent_id` = :tid AND `user_id` = :uid")->bindParams([
                    "true_up" => $trueUploaded, "true_dl" => $trueDownloaded, "this_up" => $thisUploaded, "this_dl" => $thisDownloaded,
                    "left" => $queries["left"], "duration" => $duration, "agent" => $queries['user-agent'],
                    "tid" => $torrentInfo["id"], "uid" => $userInfo["id"]
                ])->execute();
            }
        }

        // Deal with completed event
        if ($queries["event"] === "completed") {
            app()->pdo->createCommand("UPDATE `snatched` SET `finished` = 'yes' , finish_ip = INET6_ATON(:ip) , finish_at = NOW() WHERE user_id = :uid AND torrent_id = :tid")->bindParams([
                "ip" => $queries['remote_ip'],
                "uid" => $userInfo["id"], "tid" => $torrentInfo["id"],
            ]);
            // Update `torrents`, with complete +1  incomplete -1 downloaded +1
            app()->pdo->createCommand("UPDATE `torrents` SET `complete` = `complete` + 1, `incomplete` = `incomplete` -1 , `downloaded` = `downloaded` + 1 WHERE `id`=:tid")->bindParams([
                "tid" => $torrentInfo["id"]
            ])->execute();
        }

        // Update Table `users` , record his upload and download data and connect time information
        app()->pdo->createCommand("UPDATE `users` SET uploaded = uploaded + :upload, downloaded = downloaded + :download, "
            . ($trueUploaded > 0 ? "last_upload_at=NOW()," : "") . ($trueDownloaded > 0 ? "last_download_at=NOW()," : "") .
            "`last_connect_at`=NOW() , `last_tracker_ip`= INET6_ATON(:ip) WHERE id = :uid")->bindParams([
            "upload" => $thisUploaded, "download" => $thisDownloaded,
            "uid" => $userInfo["id"], "ip" => $queries['remote_ip']
        ])->execute();
    }

    /** Cheater check function from NexusPHP based on user upload speed check
     *
     * See raw code from : https://github.com/ZJUT/NexusPHP/blob/master/include/functions_announce.php#L76
     *
     * @param $userInfo
     * @param $torrentInfo
     * @param $trueUploaded
     * @param $trueDownloaded
     * @param $duration
     * @throws TrackerException
     */
    private function checkUpspeed($userInfo, $torrentInfo, $trueUploaded, $trueDownloaded, $duration)
    {
        $upspeed = (($trueUploaded > 0 && $duration > 0) ? $trueUploaded / $duration : 0);

        $logCheater = function ($commit) use ($userInfo, $torrentInfo, $trueUploaded, $trueDownloaded, $duration) {
            app()->pdo->createCommand("INSERT INTO `cheaters`(`userid`, `torrentid`, `uploaded`, `downloaded`, `anctime`, `seeders`, `leechers`, `hit`, `commit`, `reviewed`, `reviewed_by`) 
            VALUES (:uid, :tid, :uploaded, :downloaded, :anctime, :seeders, :leechers, :hit, :msg, :reviewed, :reviewed_by)  
            ON DUPLICATE KEY UPDATE `hit` = `hit` + 1, `reviewed` = 0,`reviewed_by` = '',`commit` = VALUES(`commit`)")->bindParams([
                "uid" => $userInfo["id"], "tid" => $torrentInfo["id"],
                "uploaded" => $trueUploaded, "downloaded" => $trueDownloaded, "anctime" => $duration,
                "seeders" => $torrentInfo["complete"], "leechers" => $torrentInfo["incomplete"],
                "hit" => 1, "msg" => $commit,
                "reviewed" => 0, "reviewed_by" => ""
            ])->execute();
        };

        // Uploaded more than 1 GB with uploading rate higher than 100 MByte/S (For Consertive level). This is no doubt cheating.
        if ($trueUploaded > 1 * (1024 ** 3) && $upspeed > 100 * (1024 ** 2)) {
            $logCheater("User account was automatically disabled by system");
            // Disable users and Delete user content in cache , so that user cannot get any data when next announce.
            app()->pdo->createCommand("UPDATE users SET status = 'banned' WHERE id = :uid;")->bindParams([
                "uid" => $userInfo["id"],
            ])->execute();

            app()->redis->del("TRACKER:user_passkey_" . $userInfo["passkey"] . "_content");
            throw new TrackerException(170);
        }

        // Uploaded more than 1 GB with uploading rate higher than 25 MByte/S (For Consertive level). This is likely cheating.
        if ($trueUploaded > 1 * (1024 ** 3) && $upspeed > 25 * (1024 ** 2))
            $logCheater("Abnormally high uploading rate");

        // Uploaded more than 1 GB with uploading rate higher than 1 MByte/S when there is less than 8 leechers (For Consertive level). This is likely cheating.
        if ($trueUploaded > 1 * (1024 ** 3) && $upspeed > 1 * (1024 ** 2))
            $logCheater("User is uploading fast when there is few leechers");

        //Uploaded more than 10 MB with uploading speed faster than 100 KByte/S when there is no leecher. This is likely cheating.
        if ($trueUploaded > 10 * (1024 ** 2) && $upspeed > 100 * 1024 && $torrentInfo["incomplete"] == 0)
            $logCheater("User is uploading when there is no leecher");
    }

    private function getTorrentBuff($userid, $torrentid, $trueUploaded, $trueDownloaded, &$thisUploaded, &$thisDownloaded)
    {
        $buff = app()->redis->get("TRACKER:user_" . $userid . "_torrent_" . $torrentid . "_buff");
        if ($buff === false) {
            $buff = app()->pdo->createCommand("SELECT COALESCE(MAX(`upload_ratio`),1) as `up_ratio`, COALESCE(MIN(`download_ratio`),1) as `dl_ratio` FROM `torrents_buff` 
            WHERE start_at < NOW() AND NOW() < expired_at AND (torrent_id = :tid OR torrent_id = 0) AND (beneficiary_id = :bid OR beneficiary_id = 0);")->bindParams([
                "tid" => $torrentid,
                "bid" => $userid
            ])->queryOne();
            app()->redis->setex("TRACKER:user_" . $userid . "_torrent_" . $torrentid . "_buff", 350, $buff);
        }
        $thisUploaded = $trueUploaded * ($buff["up_ratio"] ?: 1);
        $thisDownloaded = $trueDownloaded * ($buff["dl_ratio"] ?: 1);
    }

    private function getIpField($queries)
    {
        $setField = [];
        $bindField = [];
        if ($queries['ip'] && $queries['port']) {
            $setField[] = "`ip` = INET6_ATON(:ip), `port` = :port";
            $bindField['ip'] = $queries['ip'];
            $bindField['port'] = $queries['port'];
        }

        if ($queries['ipv6'] && $queries['ipv6_port']) {
            $setField[] = '`ipv6` = INET6_ATON(:ipv6), `ipv6_port` = :ipv6_port';
            $bindField['ipv6'] = $queries['ipv6'];
            $bindField['ipv6_port'] = $queries['ipv6_port'];
        }
        $setField = join(', ', $setField);

        return [$setField, $bindField];
    }
}
