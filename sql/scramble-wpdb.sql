-- This is a script that obfuscates the Tigerdile WordPress database for
-- development purposes.  This is mostly so that live users don't get emailed
-- by Tigerdile due to development activities, and so that if the DB gets
-- stolen it won't be such a horrid breach.

-- Kill Email address
-- Set all user passwords to 'testing'
-- Clear nicename and logins.
update tigerd_users
    SET user_email = concat(ID,'@noemail.com'),
        user_login = concat('user', ID),
        user_nicename = concat('user', ID),
        display_name = concat('user', ID),
        user_pass = '$P$BniK.BVlMnKtRxxM56kp8/FB2ZA.db.';

-- Kill payment info
delete from sd_child_payment_methods;
delete from sd_user_payment_methods;

update sd_user_payment_methods
    SET metadata = '';

-- Delete payment meta settings
delete from tigerd_usermeta where meta_key in ('paypal_email', 'stripe_recipient', 'wf_recipient');

-- Kill patreon stuff
update sd_profiles
    SET patreon_client_id='',
        patreon_client_secret='',
        patreon_access_token='',
        patreon_refresh_token='';

delete from sd_profile_patreons;
delete from sd_profile_patreon_tiers;
delete from sd_order_items;
delete from sd_payout_requests;
delete from sd_balance_sheet;
delete from sd_subscriptions;
delete from sd_content_tiers_link;
delete from sd_tiers;
delete from sd_comments;
delete from sd_content;
delete from sd_multistream;
delete from sd_orders;
delete from sd_patreon_users;
delete from sd_rebroadcasts;


-- Genericize profiles
update sd_profiles set title=concat('Sample Profile ', id),
                     content='Sample Content',
                         url=concat('profile', id),
               rtmp_password='rtmp',
           above_stream_html='',
           below_stream_html='',
             viewer_password='',
              donation_email='',
                stream_blurb='';




-- Kill first/nast names
update tigerd_usermeta
   SET meta_value = concat('FirstName', user_id)
 WHERE meta_key = 'first_name';

update tigerd_usermeta 
   SET meta_value = concat('LastName', user_id)
 WHERE meta_key = 'last_name';

