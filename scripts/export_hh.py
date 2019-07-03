# This is a simple python script to export the halfway hotel post info
# and images from the WB DB

import datetime
import pymysql
import phpserialize
import shutil


db = pymysql.connect(host='localhost', port=3306, user='root',
                     passwd='', db='tigerdile')


cur = db.cursor()

# Get all our comics
cur.execute("select p.post_title, p.post_content, UNIX_TIMESTAMP(p.post_date), "
            "p.ID "
            "from tigerd_posts p "
            "where p.post_type='comic-page' "
            "order by p.ID asc")

image_cur = db.cursor()

# For each comic, write a file, and copy its image.
for comic in cur.fetchall():
    datestamp = datetime.datetime.fromtimestamp(comic[2]) \
                                 .strftime("%Y-%m-%d")

    # WRite the file
    with open("%s.txt" % datestamp, 'w') as out:
        out.write(comic[0])
        out.write(comic[1])

    # Copy images
    image_cur.execute("select p.guid from tigerd_posts p "
                      "where p.post_type='attachment' and "
                      "p.post_parent=%d" % comic[3])

    images = image_cur.fetchall()

    if len(images):
        shutil.copy("/www/www.tigerdile.com/webroot%s" % images[0][0],
                    "./%s.jpg" % datestamp)

