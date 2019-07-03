# This is a simple python script to port the moderators and
# banned users from WordPress to the new Swaggerdile system.
# It will also copy images to the correct location.
#
# its designed to be run once

import pymysql
import phpserialize
import shutil


db = pymysql.connect(host='localhost', port=3306, user='root',
                     passwd='', db='tigerdile')


cur = db.cursor()

#
# MODERATORS
#

print("MODERATORS")

# Clear old migrations
cur.execute("delete from sd_profile_users where type_id=1")
cur.execute("select p.id, pm.meta_value from tigerd_postmeta pm "
            "join tigerd_posts on tigerd_posts.ID=pm.post_id "
            "join sd_profiles p on tigerd_posts.post_author=p.owner_id "
            "where pm.meta_key='moderators'")

usercheck = db.cursor()

# Migrate them over
for row in cur.fetchall():
    # If its an empty string, skip.
    if len(row[1]) == 0:
        continue

    phpObj = phpserialize.loads(row[1].encode())

    # Sometimes this is a string that's been double-serialized
    if isinstance(phpObj, bytes):
        phpObj = phpserialize.loads(phpObj)

    print(phpObj)

    for x in phpObj.values():
        if isinstance(x, int):
            moderator_id = x
        else:
            moderator_id = int(x.decode('utf8'))

        # Make sure moderator still exists (some don't)
        if usercheck.execute("select ID from tigerd_users where ID=%d" % moderator_id):
            cur.execute("insert into sd_profile_users (profile_id, user_id, type_id) "
                        "values (%d, %d, 1)" % (row[0], moderator_id))

#
# BANLIST
#

print("BANLIST")

# Clear old migrations
cur.execute("delete from sd_profile_users where type_id=2")
cur.execute("select p.id, pm.meta_value from tigerd_postmeta pm "
            "join tigerd_posts on tigerd_posts.ID=pm.post_id "
            "join sd_profiles p on tigerd_posts.post_author=p.owner_id "
            "where pm.meta_key='ban_list'")

# Migrate them over
for row in cur.fetchall():
    # If its an empty string, skip.
    if len(row[1]) == 0:
        continue

    phpObj = phpserialize.loads(row[1].encode())

    # Sometimes this is a string that's been double-serialized
    if isinstance(phpObj, bytes):
        phpObj = phpserialize.loads(phpObj)

    print(phpObj)

    for x in phpObj.values():
        if isinstance(x, int):
            ban_id = x
        else:
            ban_id = int(x.decode('utf8'))

        # Make sure ban_id still exists (some don't)
        if usercheck.execute("select ID from tigerd_users where ID=%d" % ban_id):
            cur.execute("insert into sd_profile_users (profile_id, user_id, type_id) "
                        "values (%d, %d, 2)" % (row[0], ban_id))

#
# VIP IMAGE
#

cur.execute("select p.id, po.guid from tigerd_postmeta pm "
            "join tigerd_posts po on po.ID=pm.meta_value "
            "join sd_profiles p on po.post_author=p.owner_id "
            "where pm.meta_key='vip_image'")

for row in cur.fetchall():
    source = "/www/www.tigerdile.com/webroot%s" % row[1]
    target = "/www/www.swaggerdile.com/swaggerdile/public/uploads/profile-%d-vip" \
             % row[0]
    print("Copy %s -> %s" % (source, target))
    shutil.copy(source, target)

#
# STREAM BACKGROUND
#

cur.execute("select p.id, po.guid from tigerd_postmeta pm "
            "join tigerd_posts po on po.ID=pm.meta_value "
            "join sd_profiles p on po.post_author=p.owner_id "
            "where pm.meta_key='background_image'")

for row in cur.fetchall():
    source = "/www/www.tigerdile.com/webroot%s" % row[1]
    target = "/www/www.swaggerdile.com/swaggerdile/public/uploads/profile-%d-stream-background" \
             % row[0]
    print("Copy %s -> %s" % (source, target))
    shutil.copy(source, target)

#
# DONATE
#

cur.execute("select p.id, po.guid from tigerd_postmeta pm "
            "join tigerd_posts po on po.ID=pm.meta_value "
            "join sd_profiles p on po.post_author=p.owner_id "
            "where pm.meta_key='donation_image'")

for row in cur.fetchall():
    source = "/www/www.tigerdile.com/webroot%s" % row[1]
    target = "/www/www.swaggerdile.com/swaggerdile/public/uploads/profile-%d-donate" \
             % row[0]
    print("Copy %s -> %s" % (source, target))
    shutil.copy(source, target)


#
# OFFLINE IMAGE
#

cur.execute("select p.id, po.guid from tigerd_postmeta pm "
            "join tigerd_posts po on po.ID=pm.meta_value "
            "join sd_profiles p on po.post_author=p.owner_id "
            "where pm.meta_key='placeholder_image'")

for row in cur.fetchall():
    source = "/www/www.tigerdile.com/webroot%s" % row[1]
    target = "/www/www.swaggerdile.com/swaggerdile/public/uploads/profile-%d-stream-offline" \
             % row[0]
    print("Copy %s -> %s" % (source, target))
    shutil.copy(source, target)



# Commit
db.commit()
cur.close()
usercheck.close()

# Close DB
db.close()
