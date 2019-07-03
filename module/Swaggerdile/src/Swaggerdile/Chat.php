<?php
/*
 * Chat.php
 *
 * Class to handle interactions with the chat server.
 *
 * @author sconley
 */

namespace Swaggerdile;

class Chat
{
    /*
     * Refresh a user, or all the users
     *
     * @param int $id user ID to refresh, or 0 to refresh all.
     * @param int $attempts used for retry count recursion -- don't use it
     *
     */
    function refreshUser($id = 0, $attempts = 0) {
        $url = TD_CHATSERVER_HTTP . 'tdadmref?action=user&key=';
        $url .= TD_ADMIN_KEY;

        if($id) {
            $url .= '&id=' . urlencode($id);
        }

        $tmp = file_get_contents($url);

        if($tmp != 'true') {
            if($attempts < 3) {
                $this->refreshUser($id, $attempts+1);
            } else {
                throw new Exception(
                    "Could not refresh your chat user!  Contact support."
                );
            }
        }
    }

    /*
     * Refresh a channel, or all the channel
     *
     * @param int $id channel ID to refresh, or 0 to refresh all.
     * @param int $attempts used for retry count recursion -- don't use it
     *
     */
    function refreshChannel($id = 0, $attempts = 0) {
        // Do nothing if this is not defined
        if(!defined('TD_CHATSERVER_HTTP')) {
            return;
        }

        $url = TD_CHATSERVER_HTTP . 'tdadmref?action=channel&key=';
        $url .= TD_ADMIN_KEY;

        if($id) {
            $url .= '&id=' . urlencode($id);
        }

        $tmp = file_get_contents($url);

        if ($tmp != 'true') {
            if($attempts < 3) {
                $this->refreshChannel($id, $attempts+1);
            } else {
                throw new Exception(
                    "Could not refresh your chat user!  Contact support."
                );
            }
        }
    }
}
