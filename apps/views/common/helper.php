<?php
/**
 *
 * Common function to render torrent page
 *
 * Created by PhpStorm.
 * User: Rhilip
 * Date: 2019/3/11
 * Time: 16:09
 *
 */

if (!function_exists('get_torrent_uploader')) {
    /**
     * @param \apps\models\Torrent $torrent
     * @return string
     */
    function get_torrent_uploader(\apps\models\Torrent $torrent)
    {
        if ($torrent->getOwnerId() == 0) {
            return '<i>Anonymous</i>';
        } else {
            return "<a class=\"text-default\" href=\"/user/panel?id={$torrent->getOwnerId()}\" data-toggle=\"tooltip\" title=\"User\">{$torrent->getOwner()->getUsername()}</a>";
        }
    }
}
