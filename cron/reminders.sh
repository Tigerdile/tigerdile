#!/bin/bash


mysql --user=`grep DB_USER /www/www.tigerdile.com/webroot/wp-config.php | cut -d "'" -f 4` \
      --password=`grep DB_PASSWORD /www/www.tigerdile.com/webroot/wp-config.php | cut -d "'" -f 4` \
      tigerdile \
      <<EOF
INSERT INTO tigerd_email_queue (subject, headers, send_to, content) 
    SELECT
        'Tigerdile Stream Expiration Notice!' as subject,
        'Reply-To: support@tigerdile.com
Content-Type: text/html' as headers,
        u.user_email as send_to,
        '<!DOCTYPE html>
<body>
<p>Hello!</p>
<p>Your stream over at Tigerdile.com is going to expire in about four
   days.  You can renew now if you don''t want to have a service
   interruption.  Or, if you don''t want to stream for a bit, you can
   always come back later!
</p>
<p>To renew, click here:
  <a href="https://www.tigerdile.com/order" target="_blank">https://www.tigerdile.com/order</a>
</p>
<p>If you do not wish to receive billing reminders anymore, you may turn
   them off here:
   <a href="https://www.tigerdile.com/dashboard/settings">https://www.tigerdile.com/dashboard/settings</a>
</p>
<p>Thanks a lot for giving Tigerdile a try!</p>
<p>&nbsp;</p>
<p>-- The Tigerdile Team</p>
</body>
        ' as content

        FROM sd_profiles p
        JOIN tigerd_users u on u.ID = p.owner_id
        WHERE
            (
                (p.last_paid_on is NULL AND
                 p.created > DATE(DATE_SUB(NOW(), INTERVAL 27 day)) AND
                 p.created < DATE(DATE_SUB(NOW(), INTERVAL 25 day))
                )
                OR
                (
                 p.last_paid_on > DATE(DATE_SUB(NOW(), INTERVAL 27 day)) AND
                 p.last_paid_on < DATE(DATE_SUB(NOW(), INTERVAL 25 day))
                )
            )
            AND
            (p.is_tigerdile_friend = 0 or p.is_tigerdile_friend is null)
            AND
            u.user_email NOT LIKE 'broken!%'
            AND
            u.ID not in (
                SELECT user_id from tigerd_usermeta
                WHERE meta_key = 'billing_optout' AND meta_value='1'
            )
;
EOF
