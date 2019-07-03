    
-- Migrate the data - Create a profile for everyone that's not in there.
-- But first merge data in from people who *are* there.
UPDATE sd_profiles sdp JOIN tigerd_posts  tdp
    ON tdp.post_author = sdp.owner_id
   SET title = tdp.post_title,
       is_visible = IF(tdp.post_status = 'publish', 1, 0),
       aspect_ratio = 1
 WHERE tdp.post_type='stream';

-- we have to do this in 2 phases, to avoid a duplicate error
UPDATE sd_profiles sdp JOIN tigerd_posts tdp
    ON tdp.post_author = sdp.owner_id
   SET url = tdp.post_name
 WHERE url <> tdp.post_name AND tdp.post_type='stream';

-- Drox has a collision with a Swaggerdile user.
update tigerd_posts set post_name='jakthedrox' where post_name='jakkal';


-- Migrate everything from posts.  The following comes from meta:
-- above_stream_html, below_stream_html, is_nsfw,
-- is_allow_guest, rtmp_password, stream_blurb,
-- donation_email, last_paid_on, stream_type_id, is_tigerdile_friend

-- Let's hardcode aspect ratio for now

INSERT INTO sd_profiles (
                            title, content, owner_id, created, is_visible,
                            url, aspect_ratio
            )
SELECT post_title, 'This user has not yet configured their profile.',
       post_author, post_date, IF(post_status = 'publish', 1, 0) as is_visible,
       post_name, 1
FROM tigerd_posts
WHERE post_author not in (select owner_id from sd_profiles)
  AND post_type = 'stream';


-- Now migrate meta, one at a time, for ease.

-- RTMP_PASSWORD
UPDATE sd_profiles sdp
  JOIN tigerd_posts p ON sdp.owner_id = p.post_author
  JOIN tigerd_postmeta pm ON p.ID = pm.post_id
   SET rtmp_password = pm.meta_value
 WHERE pm.meta_key = 'stream_token';

-- IS SFW
UPDATE sd_profiles sdp
  JOIN tigerd_posts p ON sdp.owner_id = p.post_author
  JOIN tigerd_postmeta pm ON p.ID = pm.post_id
   SET is_nsfw = IF(pm.meta_value = '1', 0, 1)
 WHERE pm.meta_key = 'sfw';

-- STREAM BLURB
UPDATE sd_profiles sdp
  JOIN tigerd_posts p ON sdp.owner_id = p.post_author
  JOIN tigerd_postmeta pm ON p.ID = pm.post_id
   SET stream_blurb = pm.meta_value
 WHERE pm.meta_key = 'stream_blurb';

-- ABOVE/BELOW HEADER
-- FULL PAGE EDITOR IS EASIER, DO IT FIRST
UPDATE sd_profiles sdp
  JOIN tigerd_posts p ON sdp.owner_id = p.post_author
  JOIN tigerd_postmeta pm ON p.ID = pm.post_id
   SET above_stream_html =
        REPLACE(
            SUBSTRING(p.post_content, 1,
                      LOCATE('[tigerdile-video', p.post_content)-1),
            '[tigerdile-paypal]', ''
        ),
       below_stream_html =
        REPLACE(
            SUBSTRING(p.post_content,
                      LOCATE(']', p.post_content,
                        LOCATE('[tigerdile-video', post_content)) +1),
            '[tigerdile-paypal]', ''
        )
 WHERE pm.meta_key = 'use_full_page_editor' and pm.meta_value = '1';

-- REGULAR EDITOR
UPDATE sd_profiles sdp
  JOIN tigerd_posts p ON sdp.owner_id = p.post_author
  JOIN tigerd_postmeta pm ON p.ID = pm.post_id
   SET above_stream_html =
        CONCAT(
            IFNULL((SELECT CONCAT(
                        CONCAT(
                            '<div class="text-center"><img src="',
                            guid
                        ), '" /></div>'
                )
               FROM tigerd_posts sub_p
               JOIN tigerd_postmeta sub_pm
                 ON sub_p.ID = sub_pm.meta_value
              WHERE sub_pm.meta_key = 'banner'
                AND sub_pm.post_id = p.ID
                AND sub_pm.meta_value is not null
                AND sub_pm.meta_value <> ''), ''),
            CONCAT('<div class="old-top-header">',
                IFNULL((SELECT meta_value
                   FROM tigerd_postmeta
                   WHERE meta_key = 'header_text'
                   AND post_id = p.ID
                ), ''), '</div>')
        )
 WHERE pm.meta_key = 'use_full_page_editor' and pm.meta_value = '0';

-- VIEWER PASSWORD
UPDATE sd_profiles sdp
  JOIN tigerd_posts p ON sdp.owner_id = p.post_author
  JOIN tigerd_postmeta pm ON p.ID = pm.post_id
   SET viewer_password = pm.meta_value
 WHERE pm.meta_key = 'viewer_password';

-- DONATION EMAIL
UPDATE sd_profiles sdp
  JOIN tigerd_posts p ON sdp.owner_id = p.post_author
  JOIN tigerd_postmeta pm ON p.ID = pm.post_id
   SET donation_email = pm.meta_value
 WHERE pm.meta_key = 'donation_url';

-- Somehow, there are a great great great many duplicate subscriptions in
-- the DB.  This query will clean them out.
DELETE n1 FROM tigerd_postmeta n1, tigerd_postmeta n2
 WHERE n1.meta_id > n2.meta_id
   AND n1.meta_key='subscribed_user'
   AND n1.meta_key = n2.meta_key
   AND n1.post_id=n2.post_id
   AND n1.meta_value=n2.meta_value;

-- And we've got some danglers from our user cleanup.
DELETE FROM tigerd_postmeta
 WHERE meta_key='subscribed_user'
   AND meta_value not in (select ID from tigerd_users);

-- This is already populated so clean it up
TRUNCATE sd_profile_users;

-- MIGRATE SUBSCRIBERS
INSERT INTO sd_profile_users (profile_id, user_id, type_id)
SELECT sd_profiles.id, tigerd_postmeta.meta_value, 3
  FROM sd_profiles
  JOIN tigerd_posts
    ON tigerd_posts.post_author = sd_profiles.owner_id
  JOIN tigerd_postmeta
    ON tigerd_posts.ID = tigerd_postmeta.post_id
 WHERE tigerd_postmeta.meta_key = 'subscribed_user';

-- IS ALLOW GUEST
UPDATE sd_profiles sdp
  JOIN tigerd_posts p ON sdp.owner_id = p.post_author
  JOIN tigerd_postmeta pm ON p.ID = pm.post_id
   SET is_allow_guests = IF(pm.meta_value = 'Yes', 1, 0)
 WHERE pm.meta_key = 'allow_guests?';


-- IS TIGERDILE FRIEND
UPDATE sd_profiles sdp
  JOIN tigerd_usermeta um on sdp.owner_id = um.user_id
   SET is_tigerdile_friend = um.meta_value
 WHERE um.meta_key = 'tigerdile_friend';

-- ACCOUNT LEVEL / STREAM TYPE
UPDATE sd_profiles sdp
  JOIN tigerd_usermeta um on sdp.owner_id = um.user_id
   SET stream_type_id = um.meta_value
 WHERE um.meta_key = 'account_level';

-- LAST PAID
UPDATE sd_profiles sdp
  JOIN tigerd_usermeta um on sdp.owner_id = um.user_id
   SET last_paid_on = FROM_UNIXTIME(um.meta_value)
 WHERE um.meta_key = 'last_paid';


-- The moderators (moderators) and the banned list (ban_list) are both
-- PHP serialized arrays stored in the DB cause ya know that's awesome.
-- God I hate WordPress' DB.  It's like a 300 lbs infant with a sledgehammer.
-- Anyway, I'll probably have to script that bit.

